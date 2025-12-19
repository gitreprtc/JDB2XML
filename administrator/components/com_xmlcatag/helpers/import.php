<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/ImportPlan.php';

class XmlcatagImportHelper
{
    public static function runSingle(string $dir, string $selectedFile, bool $dryRun = false, array $exclude = []): string
    {
        return self::run($dir, $dryRun, $exclude, $selectedFile);
    }

    public static function run(string $dir, bool $dryRun = false, array $exclude = [], ?string $selectedFile = null): string
    {
        $lock = JPATH_ADMINISTRATOR . '/cache/xmlcatag_import.lock';
        if (!is_dir(dirname($lock))) @mkdir(dirname($lock), 0755, true);
        if (file_exists($lock)) return 'Import afgebroken: lock actief.';
        @file_put_contents($lock, (string) time());

        // Rollback log (only when not dry-run)
        $rollbackFile = null;
        $rollback = [
            'created' => ['categories' => [], 'tags' => []],
            'updated' => ['categories' => [], 'tags' => []],
            'timestamp' => date('c'),
        ];
        if (!$dryRun) {
            $logDir = JPATH_ADMINISTRATOR . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $rollbackFile = $logDir . '/xmlcatag_rollback_' . date('Ymd_His') . '.json';
        }

        $processedDir = $dir . '/processed';
        $failedDir    = $dir . '/failed';
        if (!is_dir($processedDir)) @mkdir($processedDir, 0755, true);
        if (!is_dir($failedDir)) @mkdir($failedDir, 0755, true);

        $createdCats = $updatedCats = $skippedCats = 0;
        $createdTags = $updatedTags = $skippedTags = 0;
        $warnings = [];

        // If a preview plan is available, execute exactly that plan (no re-analysis)
        $app = Factory::getApplication();
        $preview = $app->getUserState('com_xmlcatag.preview');
        $planMap = [];
        if (is_array($preview)) {
            foreach ($preview as $fileKey => $data) {
                if (isset($data['_plan']) && is_array($data['_plan'])) {
                    $planMap[$fileKey] = XmlcatagImportPlan::fromArray($data['_plan']);
                }
            }
        }


        // Normalize exclude keys into set for quick lookup
        $excludeSet = [];
        foreach ($exclude as $k => $v) {
            if (!$v) continue;
            $excludeSet[$k] = true;
            // also add tail id to support earlier styles
            if (strpos($k, '|') !== false) {
                $parts = explode('|', $k);
                $tail = end($parts);
                if ($tail !== '') $excludeSet[$tail] = true;
            }
        }

        // If preview exists for selected file, only import categories included in preview
        $allowedCategorySet = null;
        $allowTags = true;
        if ($selectedFile && is_array($preview)) {
            $previewData = $preview[$selectedFile] ?? null;
            if (is_array($previewData)) {
                $allowedCategorySet = [];
                $rows = $previewData['categories'] ?? [];
                foreach ($rows as $row) {
                    $key = (string)($row['path'] ?? $row['id'] ?? '');
                    if ($key === '' || isset($excludeSet[$key])) {
                        continue;
                    }
                    $allowedCategorySet[$key] = true;
                }
                $allowTags = !empty($previewData['tags']) && is_array($previewData['tags']);
            }
        }

        $db = Factory::getDbo();

        try {
            $files = glob($dir . '/*.xml') ?: [];
            if ($selectedFile) {
                $path = $dir . '/' . basename($selectedFile);
                if (!is_file($path)) {
                    throw new RuntimeException('Bestand niet gevonden in import map: ' . $path);
                }
                $files = [$path];
            }
            if (!empty($planMap)) {
                $files = array_values(array_filter($files, function($f) use ($planMap){
                    return isset($planMap[basename($f)]);
                }));
            }
            if (!$files) return 'Geen XML-bestanden gevonden.';

            foreach ($files as $file) {
                try {
                    $xmlString = file_get_contents($file);
                    if (!$xmlString) throw new RuntimeException('Leeg bestand');
                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($xmlString);
                    if (!$xml) throw new RuntimeException('Ongeldige XML');

                    if (isset($xml->categories)) {
                        self::importCategories($db, $xml->categories, $dryRun, $excludeSet, $createdCats, $updatedCats, $skippedCats, $warnings, $rollback, $allowedCategorySet);
                    } elseif ($xml->getName() === 'categories') {
                        self::importCategories($db, $xml, $dryRun, $excludeSet, $createdCats, $updatedCats, $skippedCats, $warnings, $rollback, $allowedCategorySet);
                    }

                    if ($allowTags) {
                        if (isset($xml->tags)) {
                            self::importTags($db, $xml->tags, $dryRun, $excludeSet, $createdTags, $updatedTags, $skippedTags, $warnings, $rollback);
                        } elseif ($xml->getName() === 'tags') {
                            self::importTags($db, $xml, $dryRun, $excludeSet, $createdTags, $updatedTags, $skippedTags, $warnings, $rollback);
                        }
                    }

                    if (!$dryRun) @rename($file, $processedDir . '/' . basename($file));
                } catch (Throwable $e) {
                    $warnings[] = basename($file) . ': ' . $e->getMessage();
                    if (!$dryRun) @rename($file, $failedDir . '/' . basename($file));
                }
            }

            if (!$dryRun && $rollbackFile) {
                file_put_contents($rollbackFile, json_encode($rollback, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                // store pointer to latest rollback file
                file_put_contents(JPATH_ADMINISTRATOR . '/cache/xmlcatag_last_rollback.txt', basename($rollbackFile));
            }

            $msg = [];
            $msg[] = 'Import afgerond' . ($dryRun ? ' (dry-run)' : '') . '.';
            $msg[] = 'Categorieën: nieuw=' . $createdCats . ', aangevuld=' . $updatedCats . ', overgeslagen=' . $skippedCats;
            $msg[] = 'Tags: nieuw=' . $createdTags . ', aangevuld=' . $updatedTags . ', overgeslagen=' . $skippedTags;
            if ($warnings) $msg[] = 'Waarschuwingen: ' . implode(' | ', array_unique($warnings));
            if (!$dryRun && $rollbackFile) $msg[] = 'Rollback-log: ' . basename($rollbackFile);
            return implode(' ', $msg);
        } finally {
            @unlink($lock);
        }
    }

    private static function importCategories($db, $categories, bool $dryRun, array $exclude, int &$created, int &$updated, int &$skipped, array &$warnings, array &$rollback, ?array $allowed = null): void
    {
        $extension = (string) ($categories['extension'] ?? 'com_content');

        foreach ($categories->category as $node) {
            $path = trim((string) $node->path);
            if ($path === '' || isset($exclude[$path])) { $skipped++; continue; }
            if ($allowed !== null && !isset($allowed[$path])) { $skipped++; continue; }

            $query = $db->getQuery(true)
                ->select('*')->from('#__categories')
                ->where('path=' . $db->quote($path))
                ->where('extension=' . $db->quote($extension));

            $existing = $db->setQuery($query)->loadObject();

            $map = [
                'description' => (string) ($node->description ?? ''),
                'language'    => (string) ($node->language ?? ''),
                'params'      => (string) ($node->params ?? ''),
                'metadata'    => (string) ($node->metadata ?? ''),
                'metadesc'    => (string) ($node->metadesc ?? ''),
                'metakey'     => (string) ($node->metakey ?? ''),
                'note'        => (string) ($node->note ?? ''),
                'access'      => (string) ($node->access ?? ''),
                'published'   => (string) ($node->published ?? ''),
            ];

            if ($existing) {
                $changed = false;
                $before = ['id' => (int)$existing->id, 'fields' => []];

                foreach ($map as $field => $value) {
                    if ($value === '') continue;
                    if (!property_exists($existing, $field)) continue;
                    $dbVal = (string) ($existing->$field ?? '');
                    if ($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') {
                        $before['fields'][$field] = $dbVal; // for rollback
                        $existing->$field = $value;
                        $changed = true;
                    }
                }

                if ($changed) {
                    if (!$dryRun) {
                        $db->updateObject('#__categories', $existing, 'id');
                        $rollback['updated']['categories'][] = $before;
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            if ($dryRun) { $created++; continue; }

            $parentId = 1;
            if (strpos($path, '/') !== false) {
                $parentPath = substr($path, 0, strrpos($path, '/'));
                if ($parentPath !== '') {
                    $parentQuery = $db->getQuery(true)
                        ->select('id')
                        ->from('#__categories')
                        ->where('path=' . $db->quote($parentPath))
                        ->where('extension=' . $db->quote($extension));
                    $parentId = (int) $db->setQuery($parentQuery)->loadResult() ?: 1;
                }
            }

            $table = Table::getInstance('Category');
            $data = [
                'title' => (string) ($node->title ?? $path),
                'alias' => (string) ($node->alias ?? ''),
                'path'  => $path,
                'extension' => $extension,
                'published' => (int) ($node->published ?? 1),
                'access' => (int) ($node->access ?? 1),
                'language' => (string) ($node->language ?? '*'),
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'metadesc' => (string) ($node->metadesc ?? ''),
                'metakey' => (string) ($node->metakey ?? ''),
                'note' => (string) ($node->note ?? ''),
                'parent_id' => $parentId
            ];
            $table->bind($data);
            $table->setLocation($parentId, 'last-child');
            $table->store();

            $rollback['created']['categories'][] = (int) $table->id;
            $created++;
        }
    }

    private static function importTags($db, $tags, bool $dryRun, array $exclude, int &$created, int &$updated, int &$skipped, array &$warnings, array &$rollback): void
    {
        foreach ($tags->tag as $node) {
            $alias = trim((string) ($node->alias ?? ''));
            if ($alias === '' || isset($exclude[$alias])) { $skipped++; continue; }

            $query = $db->getQuery(true)
                ->select('*')->from('#__tags')
                ->where('alias=' . $db->quote($alias));
            $existing = $db->setQuery($query)->loadObject();

            $map = [
                'description' => (string) ($node->description ?? ''),
                'language'    => (string) ($node->language ?? ''),
                'params'      => (string) ($node->params ?? ''),
                'metadata'    => (string) ($node->metadata ?? ''),
                'access'      => (string) ($node->access ?? ''),
                'published'   => (string) ($node->published ?? ''),
            ];

            if ($existing) {
                $changed = false;
                $before = ['id' => (int)$existing->id, 'fields' => []];

                foreach ($map as $field => $value) {
                    if ($value === '') continue;
                    if (!property_exists($existing, $field)) continue;
                    $dbVal = (string) ($existing->$field ?? '');
                    if ($dbVal !== $value) {
                        $before['fields'][$field] = $dbVal;
                        $existing->$field = $value;
                        $changed = true;
                    }
                }

                if ($changed) {
                    if (!$dryRun) {
                        $db->updateObject('#__tags', $existing, 'id');
                        $rollback['updated']['tags'][] = $before;
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            if ($dryRun) { $created++; continue; }

            $table = Table::getInstance('Tag');
            $data = [
                'title' => (string) ($node->title ?? $alias),
                'alias' => $alias,
                'published' => (int) ($node->published ?? 1),
                'access' => (int) ($node->access ?? 1),
                'language' => (string) ($node->language ?? '*'),
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'parent_id' => 1,
            ];
            $table->bind($data);
            $table->setLocation(1, 'last-child');
            $table->store();

            $rollback['created']['tags'][] = (int) $table->id;
            $created++;
        }
    }
}
