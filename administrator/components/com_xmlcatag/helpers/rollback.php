<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class XmlcatagRollbackHelper
{
    public static function listLogsByType(string $type): array
    {
        $logDir = JPATH_ADMINISTRATOR . '/logs';
        $files = glob($logDir . '/xmlcatag_rollback_*.json') ?: [];
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
            $list[] = basename($file);
        }

        return $list;
    }

    public static function apply(string $type, string $untilFile): string
    {
        $type = $type === 'tags' ? 'tags' : 'categories';
        if ($untilFile === '') {
            return 'Geen rollback-log geselecteerd.';
        }

        $logDir = JPATH_ADMINISTRATOR . '/logs';
        $files = glob($logDir . '/xmlcatag_rollback_*.json') ?: [];
        rsort($files);

        $selectedPath = $logDir . '/' . $untilFile;
        if (!file_exists($selectedPath)) {
            return 'Rollback-log ontbreekt: ' . $untilFile;
        }

        $applyFiles = [];
        foreach ($files as $file) {
            $applyFiles[] = $file;
            if (basename($file) === $untilFile) {
                break;
            }
        }

        $db = Factory::getDbo();
        $deleted = 0;
        $reverted = 0;

        foreach ($applyFiles as $file) {
            $json = json_decode(file_get_contents($file), true);
            if (!is_array($json)) {
                continue;
            }
            $created = array_reverse($json['created'][$type] ?? []);
            foreach ($created as $id) {
                $table = $type === 'tags' ? '#__tags' : '#__categories';
                $db->setQuery($db->getQuery(true)->delete($table)->where('id=' . (int)$id))->execute();
                $deleted++;
            }

            foreach ($json['updated'][$type] ?? [] as $u) {
                $obj = (object) ['id' => (int)($u['id'] ?? 0)];
                foreach (($u['fields'] ?? []) as $field => $prev) {
                    $obj->$field = $prev;
                }
                $table = $type === 'tags' ? '#__tags' : '#__categories';
                $db->updateObject($table, $obj, 'id');
                $reverted++;
            }
        }

        return 'Rollback uitgevoerd voor ' . $type . ': verwijderd=' . $deleted . ', teruggedraaid=' . $reverted . '.';
    }
}
