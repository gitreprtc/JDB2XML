<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use DateTime;

class Jdb2xmlRollbackHelper
{
    public static function listLogsByType(string $type): array
    {
        $logDir = JPATH_ADMINISTRATOR . '/logs';
        $files = glob($logDir . '/jdb2xml_rollback_*.json') ?: [];
        rsort($files);

        $list = [];
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $created = $data['created'][$type] ?? [];
            $updated = $data['updated'][$type] ?? [];
            if (empty($created) && empty($updated)) {
                continue;
            }
            $rolledCreated = $data['rolled_back']['created'][$type] ?? [];
            $rolledUpdated = $data['rolled_back']['updated'][$type] ?? [];

            $list[] = [
                'file' => basename($file),
                'label' => self::formatTimestamp($file),
                'created' => self::buildCreatedItems($created, $rolledCreated),
                'updated' => self::buildUpdatedItems($updated, $rolledUpdated),
            ];
        }

        return $list;
    }

    public static function apply(string $type, string $targetFile, array $createdIds, array $updatedIds): array
    {
        $type = in_array($type, ['categories', 'tags', 'articles'], true) ? $type : 'categories';
        if ($targetFile === '') {
            return ['message' => 'No rollback log selected.', 'warning' => null];
        }

        $logDir = JPATH_ADMINISTRATOR . '/logs';
        $files = glob($logDir . '/jdb2xml_rollback_*.json') ?: [];
        rsort($files);

        $selectedPath = $logDir . '/' . $targetFile;
        if (!file_exists($selectedPath)) {
            return ['message' => 'Rollback log missing: ' . $targetFile, 'warning' => null];
        }

        $createdIds = array_values(array_unique(array_map('intval', $createdIds)));
        $updatedIds = array_values(array_unique(array_map('intval', $updatedIds)));
        $selectedIds = array_values(array_unique(array_merge($createdIds, $updatedIds)));
        if (empty($selectedIds)) {
            return ['message' => 'No changes selected for rollback.', 'warning' => null];
        }

        $db = Factory::getDbo();
        $deleted = 0;
        $reverted = 0;
        $warning = null;

        $laterUpdates = self::collectLaterUpdates($files, $targetFile, $type, $selectedIds);
        if (!empty($laterUpdates)) {
            $warning = self::buildWarningMessage($type, $laterUpdates);
        }

        if ($type === 'tags') {
            $table = '#__tags';
        } elseif ($type === 'articles') {
            $table = '#__content';
        } else {
            $table = '#__categories';
        }

        foreach ($laterUpdates as $entry) {
            $obj = (object) ['id' => (int)$entry['id']];
            foreach (($entry['fields'] ?? []) as $field => $prev) {
                $obj->$field = $prev;
            }
            $db->updateObject($table, $obj, 'id');
            $reverted++;
        }

        $selectedData = json_decode(file_get_contents($selectedPath), true);
        $selectedData = is_array($selectedData) ? $selectedData : [];
        $selectedUpdates = $selectedData['updated'][$type] ?? [];

        foreach ($selectedUpdates as $u) {
            $id = (int)($u['id'] ?? 0);
            if (!in_array($id, $updatedIds, true)) {
                continue;
            }
            $obj = (object) ['id' => $id];
            foreach (($u['fields'] ?? []) as $field => $prev) {
                $obj->$field = $prev;
            }
            $db->updateObject($table, $obj, 'id');
            $reverted++;
        }

        foreach ($createdIds as $id) {
            $db->setQuery($db->getQuery(true)->delete($table)->where('id=' . (int)$id))->execute();
            $deleted++;
        }

        self::markRolledBack($selectedPath, $type, $createdIds, $updatedIds);
        foreach ($laterUpdates as $entry) {
            self::markRolledBack($entry['file'], $type, [], [(int)$entry['id']]);
        }

        self::cleanupLogIfEmpty($selectedPath, $type);
        foreach ($laterUpdates as $entry) {
            self::cleanupLogIfEmpty($entry['file'], $type);
        }

        return [
            'message' => 'Rollback completed for ' . $type . ': deleted=' . $deleted . ', reverted=' . $reverted . '.',
            'warning' => $warning,
        ];
    }

    public static function fetchEntities(string $type, array $ids): array
    {
        $type = in_array($type, ['categories', 'tags', 'articles'], true) ? $type : 'categories';
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $db = Factory::getDbo();
        if ($type === 'tags') {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias']))
                ->from($db->quoteName('#__tags'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
        } elseif ($type === 'articles') {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias']))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
        } else {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'path']))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadAssocList() ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = [
                'title' => $row['title'] ?? '',
                'path' => $type === 'tags' || $type === 'articles' ? ($row['alias'] ?? '') : ($row['path'] ?? ''),
            ];
        }

        return $map;
    }

    private static function formatTimestamp(string $file): string
    {
        if (preg_match('/jdb2xml_rollback_(\d{8}_\d{6})\\.json$/', $file, $matches)) {
            $dt = DateTime::createFromFormat('Ymd_His', $matches[1]);
            if ($dt) {
                return $dt->format('d-m-Y H:i:s');
            }
        }
        return basename($file);
    }

    private static function buildCreatedItems(array $created, array $rolledBack): array
    {
        $items = [];
        foreach ($created as $id) {
            $id = (int)$id;
            $items[] = [
                'id' => $id,
                'rolled_back' => in_array($id, $rolledBack, true),
            ];
        }
        return $items;
    }

    private static function buildUpdatedItems(array $updated, array $rolledBack): array
    {
        $items = [];
        foreach ($updated as $entry) {
            $id = (int)($entry['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'fields' => $entry['fields'] ?? [],
                'rolled_back' => in_array($id, $rolledBack, true),
            ];
        }
        return $items;
    }

    private static function collectLaterUpdates(array $files, string $targetFile, string $type, array $ids): array
    {
        $laterUpdates = [];
        $targetIndex = array_search(JPATH_ADMINISTRATOR . '/logs/' . $targetFile, $files, true);
        if ($targetIndex === false) {
            return [];
        }

        for ($i = 0; $i < $targetIndex; $i++) {
            $file = $files[$i];
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $rolledUpdated = $data['rolled_back']['updated'][$type] ?? [];
            foreach ($data['updated'][$type] ?? [] as $entry) {
                $id = (int)($entry['id'] ?? 0);
                if ($id === 0 || !in_array($id, $ids, true)) {
                    continue;
                }
                if (in_array($id, $rolledUpdated, true)) {
                    continue;
                }
                $laterUpdates[] = [
                    'file' => $file,
                    'id' => $id,
                    'fields' => $entry['fields'] ?? [],
                ];
            }
        }

        return $laterUpdates;
    }

    private static function buildWarningMessage(string $type, array $entries): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            $fields = [];
            foreach (($entry['fields'] ?? []) as $field => $value) {
                $fields[] = $field . '=' . (is_scalar($value) ? (string)$value : json_encode($value));
            }
            $lines[] = 'ID ' . (int)$entry['id'] . ' (' . self::formatTimestamp($entry['file']) . ')' . (!empty($fields) ? ' [' . implode(', ', $fields) . ']' : '');
        }
        return 'Warning: later changes were found and will also be reverted: ' . implode('; ', $lines);
    }

    private static function markRolledBack(string $filePath, string $type, array $createdIds, array $updatedIds): void
    {
        if (!file_exists($filePath)) {
            return;
        }
        $data = json_decode(file_get_contents($filePath), true);
        if (!is_array($data)) {
            return;
        }
        if (!isset($data['rolled_back'])) {
            $data['rolled_back'] = ['created' => [], 'updated' => []];
        }

        if (!isset($data['rolled_back']['created'][$type])) {
            $data['rolled_back']['created'][$type] = [];
        }
        if (!isset($data['rolled_back']['updated'][$type])) {
            $data['rolled_back']['updated'][$type] = [];
        }

        $data['rolled_back']['created'][$type] = array_values(array_unique(array_merge(
            $data['rolled_back']['created'][$type],
            array_map('intval', $createdIds)
        )));
        $data['rolled_back']['updated'][$type] = array_values(array_unique(array_merge(
            $data['rolled_back']['updated'][$type],
            array_map('intval', $updatedIds)
        )));

        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function cleanupLogIfEmpty(string $filePath, string $type): void
    {
        if (!file_exists($filePath)) {
            return;
        }
        $data = json_decode(file_get_contents($filePath), true);
        if (!is_array($data)) {
            return;
        }
        $created = $data['created'][$type] ?? [];
        $updated = $data['updated'][$type] ?? [];
        $rolledCreated = $data['rolled_back']['created'][$type] ?? [];
        $rolledUpdated = $data['rolled_back']['updated'][$type] ?? [];

        $remainingCreated = array_diff(array_map('intval', $created), array_map('intval', $rolledCreated));
        $remainingUpdated = [];
        foreach ($updated as $entry) {
            $id = (int)($entry['id'] ?? 0);
            if ($id !== 0 && !in_array($id, $rolledUpdated, true)) {
                $remainingUpdated[] = $id;
            }
        }

        if (empty($remainingCreated) && empty($remainingUpdated)) {
            unlink($filePath);
        }
    }
}
