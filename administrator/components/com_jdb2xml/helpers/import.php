<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/ImportPlan.php';

class Jdb2xmlImportHelper
{
    public static function runSingle(string $dir, string $selectedFile, bool $dryRun = false, array $exclude = []): string
    {
        return self::run($dir, $dryRun, $exclude, $selectedFile);
    }

    public static function run(string $dir, bool $dryRun = false, array $exclude = [], ?string $selectedFile = null): string
    {
        $lock = JPATH_ADMINISTRATOR . '/cache/jdb2xml_import.lock';
        if (!is_dir(dirname($lock))) @mkdir(dirname($lock), 0755, true);
        if (file_exists($lock)) return 'Import aborted: lock active.';
        @file_put_contents($lock, (string) time());

        // Rollback log (only when not dry-run)
        $rollbackFile = null;
        $rollback = [
            'created' => ['categories' => [], 'tags' => [], 'articles' => []],
            'updated' => ['categories' => [], 'tags' => [], 'articles' => []],
            'timestamp' => date('c'),
        ];
        if (!$dryRun) {
            $logDir = JPATH_ADMINISTRATOR . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $rollbackFile = $logDir . '/jdb2xml_rollback_' . date('Ymd_His') . '.json';
        }

        $processedDir = $dir . '/processed';
        $failedDir    = $dir . '/failed';
        if (!is_dir($processedDir)) @mkdir($processedDir, 0755, true);
        if (!is_dir($failedDir)) @mkdir($failedDir, 0755, true);

        $createdCats = $changedCats = $skippedCats = 0;
        $createdTags = $changedTags = $skippedTags = 0;
        $createdArticles = $changedArticles = $skippedArticles = 0;
        $warnings = [];

        // If a preview plan is available, execute exactly that plan (no re-analysis)
        $app = Factory::getApplication();
        $preview = $app->getUserState('com_jdb2xml.preview');
        $planMap = [];
        if (is_array($preview)) {
            foreach ($preview as $fileKey => $data) {
                if (isset($data['_plan']) && is_array($data['_plan'])) {
                    $planMap[$fileKey] = Jdb2xmlImportPlan::fromArray($data['_plan']);
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
        $allowedArticleSet = null;
        $allowTags = true;
        $allowArticles = true;
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
                $allowArticles = !empty($previewData['articles']) && is_array($previewData['articles']);
                $allowedArticleSet = [];
                $rows = $previewData['articles'] ?? [];
                foreach ($rows as $row) {
                    $key = (string)($row['id'] ?? '');
                    if ($key === '' || isset($excludeSet[$key])) {
                        continue;
                    }
                    $allowedArticleSet[$key] = true;
                }
            }
        }

        $db = Factory::getDbo();

        try {
            $files = glob($dir . '/*.xml') ?: [];
            if ($selectedFile) {
                $path = $dir . '/' . basename($selectedFile);
                if (!is_file($path)) {
                    throw new RuntimeException('File not found in import folder: ' . $path);
                }
                $files = [$path];
            }
            if (!empty($planMap)) {
                $files = array_values(array_filter($files, function($f) use ($planMap){
                    return isset($planMap[basename($f)]);
                }));
            }
            if (!$files) return 'No XML files found.';

            foreach ($files as $file) {
                try {
                    $xmlString = file_get_contents($file);
                    if (!$xmlString) throw new RuntimeException('Empty file');
                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($xmlString);
                    if (!$xml) throw new RuntimeException('Invalid XML');

                    if (isset($xml->categories)) {
                        self::importCategories($db, $xml->categories, $dryRun, $excludeSet, $createdCats, $changedCats, $skippedCats, $warnings, $rollback, $allowedCategorySet);
                    } elseif ($xml->getName() === 'categories') {
                        self::importCategories($db, $xml, $dryRun, $excludeSet, $createdCats, $changedCats, $skippedCats, $warnings, $rollback, $allowedCategorySet);
                    }

                    if ($allowTags) {
                        if (isset($xml->tags)) {
                            self::importTags($db, $xml->tags, $dryRun, $excludeSet, $createdTags, $changedTags, $skippedTags, $warnings, $rollback);
                        } elseif ($xml->getName() === 'tags') {
                            self::importTags($db, $xml, $dryRun, $excludeSet, $createdTags, $changedTags, $skippedTags, $warnings, $rollback);
                        }
                    }

                    if ($allowArticles) {
                        if (isset($xml->articles)) {
                            self::importArticles($db, $xml->articles, $dryRun, $excludeSet, $createdArticles, $changedArticles, $skippedArticles, $warnings, $rollback, $allowedArticleSet);
                        } elseif ($xml->getName() === 'articles') {
                            self::importArticles($db, $xml, $dryRun, $excludeSet, $createdArticles, $changedArticles, $skippedArticles, $warnings, $rollback, $allowedArticleSet);
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
                file_put_contents(JPATH_ADMINISTRATOR . '/cache/jdb2xml_last_rollback.txt', basename($rollbackFile));
            }

            $msg = [];
            $msg[] = 'Import completed' . ($dryRun ? ' (dry-run)' : '') . '.';
            $msg[] = 'Categories: new=' . $createdCats . ', changed=' . $changedCats . ', skipped=' . $skippedCats;
            $msg[] = 'Tags: new=' . $createdTags . ', changed=' . $changedTags . ', skipped=' . $skippedTags;
            $msg[] = 'Articles: new=' . $createdArticles . ', changed=' . $changedArticles . ', skipped=' . $skippedArticles;
            if ($warnings) $msg[] = 'Warnings: ' . implode(' | ', array_unique($warnings));
            if (!$dryRun && $rollbackFile) $msg[] = 'Rollback log: ' . basename($rollbackFile);
            return implode(' ', $msg);
        } finally {
            @unlink($lock);
        }
    }

    private static function importCategories($db, $categories, bool $dryRun, array $exclude, int &$created, int &$changed, int &$skipped, array &$warnings, array &$rollback, ?array $allowed = null): void
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

                $xmlTitle = (string) ($node->title ?? '');
                if ($xmlTitle !== '' && (string) ($existing->title ?? '') !== $xmlTitle) {
                    $before['fields']['title'] = (string) ($existing->title ?? '');
                    $existing->title = $xmlTitle;
                    $changed = true;
                }

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
                    $changed++;
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
            if (!$table->check()) {
                throw new RuntimeException('Category check failed: ' . $table->getError());
            }
            if (!$table->store()) {
                throw new RuntimeException('Category save failed: ' . $table->getError());
            }
            if (method_exists($table, 'rebuildPath')) {
                $table->rebuildPath($table->id);
            }

            $rollback['created']['categories'][] = (int) $table->id;
            $created++;
        }
    }

    private static function importTags($db, $tags, bool $dryRun, array $exclude, int &$created, int &$changed, int &$skipped, array &$warnings, array &$rollback): void
    {
        foreach ($tags->tag as $node) {
            $alias = trim((string) ($node->alias ?? ''));
            if ($alias === '' || isset($exclude[$alias])) { $skipped++; continue; }

            $query = $db->getQuery(true)
                ->select('*')->from('#__tags')
                ->where('alias=' . $db->quote($alias));
            $existing = $db->setQuery($query)->loadObject();

            $map = [
                'title'       => (string) ($node->title ?? ''),
                'description' => (string) ($node->description ?? ''),
                'language'    => (string) ($node->language ?? ''),
                'params'      => (string) ($node->params ?? ''),
                'metadata'    => (string) ($node->metadata ?? ''),
                'access'      => (string) ($node->access ?? ''),
                'published'   => (string) ($node->published ?? ''),
                'parent_id'   => (string) ($node->parent_id ?? ''),
            ];

            if ($existing) {
                $changedLocal = false;
                $before = ['id' => (int)$existing->id, 'fields' => []];

                foreach ($map as $field => $value) {
                    if ($value === '') continue;
                    if (!property_exists($existing, $field)) continue;
                    $dbVal = (string) ($existing->$field ?? '');
                    if ($dbVal !== $value) {
                        $before['fields'][$field] = $dbVal;
                        $existing->$field = $value;
                        $changedLocal = true;
                    }
                }

                if ($changedLocal) {
                    if (!$dryRun) {
                        $db->updateObject('#__tags', $existing, 'id');
                        $rollback['updated']['tags'][] = $before;
                    }
                    $changed++;
                } else {
                    $skipped++;
                }
                continue;
            }

            if ($dryRun) { $created++; continue; }

            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tags/tables');
            $table = Table::getInstance('Tag', 'TagsTable');
            if ($table === false) {
                throw new RuntimeException('Tag-table kan niet worden geladen');
            }
            $data = [
                'title' => (string) ($node->title ?? $alias),
                'alias' => $alias,
                'path' => (string) ($node->path ?? $alias),
                'published' => (int) ($node->published ?? 1),
                'access' => (int) ($node->access ?? 1),
                'language' => (string) ($node->language ?? '*'),
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'parent_id' => (int) ($node->parent_id ?? 1),
            ];
            $table->bind($data);
            $table->setLocation(1, 'last-child');
            if (!$table->check()) {
                throw new RuntimeException('Tag check failed: ' . $table->getError());
            }
            if (!$table->store()) {
                throw new RuntimeException('Tag save failed: ' . $table->getError());
            }

            $rollback['created']['tags'][] = (int) $table->id;
            $created++;
        }
    }

    private static function importArticles(
        $db,
        $articles,
        bool $dryRun,
        array $exclude,
        int &$created,
        int &$changed,
        int &$skipped,
        array &$warnings,
        array &$rollback,
        ?array $allowed = null
    ): void {
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_content/tables');

        foreach ($articles->article as $node) {
            $alias = trim((string) ($node->alias ?? ''));
            $catid = (int) ($node->catid ?? 0);
            $key = self::buildArticleKey($alias, $catid);

            if ($alias === '' || $catid === 0) {
                $skipped++;
                $warnings[] = 'Article without alias or category skipped.';
                continue;
            }
            if (isset($exclude[$key])) { $skipped++; continue; }
            if ($allowed !== null && !isset($allowed[$key])) { $skipped++; continue; }

            $query = $db->getQuery(true)
                ->select('*')->from('#__content')
                ->where('alias=' . $db->quote($alias))
                ->where('catid=' . (int) $catid);
            $existing = $db->setQuery($query)->loadObject();

            $fields = [
                'title' => (string) ($node->title ?? ''),
                'introtext' => (string) ($node->introtext ?? ''),
                'fulltext' => (string) ($node->fulltext ?? ''),
                'state' => (string) ($node->state ?? ''),
                'access' => (string) ($node->access ?? ''),
                'language' => (string) ($node->language ?? ''),
                'created' => (string) ($node->created ?? ''),
                'created_by' => (string) ($node->created_by ?? ''),
                'created_by_alias' => (string) ($node->created_by_alias ?? ''),
                'modified' => (string) ($node->modified ?? ''),
                'modified_by' => (string) ($node->modified_by ?? ''),
                'publish_up' => (string) ($node->publish_up ?? ''),
                'publish_down' => (string) ($node->publish_down ?? ''),
                'ordering' => (string) ($node->ordering ?? ''),
                'featured' => (string) ($node->featured ?? ''),
                'hits' => (string) ($node->hits ?? ''),
                'images' => (string) ($node->images ?? ''),
                'urls' => (string) ($node->urls ?? ''),
                'attribs' => (string) ($node->attribs ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'metadesc' => (string) ($node->metadesc ?? ''),
                'metakey' => (string) ($node->metakey ?? ''),
                'note' => (string) ($node->note ?? ''),
            ];

            if ($existing) {
                $changedLocal = false;
                $before = ['id' => (int)$existing->id, 'fields' => []];

                foreach ($fields as $field => $value) {
                    if (!property_exists($existing, $field)) {
                        continue;
                    }
                    $dbVal = (string) ($existing->$field ?? '');
                    if ($dbVal !== $value) {
                        $before['fields'][$field] = $dbVal;
                        $existing->$field = $value;
                        $changedLocal = true;
                    }
                }

                if ($changedLocal) {
                    if (!$dryRun) {
                        $db->updateObject('#__content', $existing, 'id');
                        $rollback['updated']['articles'][] = $before;
                    }
                    $changed++;
                } else {
                    $skipped++;
                }
                continue;
            }

            if ($dryRun) { $created++; continue; }

            $table = Table::getInstance('Content');
            if ($table === false) {
                throw new RuntimeException('Article-table can not be loaded');
            }
            $data = [
                'title' => (string) ($node->title ?? $alias),
                'alias' => $alias,
                'catid' => $catid,
                'introtext' => (string) ($node->introtext ?? ''),
                'fulltext' => (string) ($node->fulltext ?? ''),
                'state' => (int) ($node->state ?? 1),
                'access' => (int) ($node->access ?? 1),
                'language' => (string) ($node->language ?? '*'),
                'created' => (string) ($node->created ?? Factory::getDate()->toSql()),
                'created_by' => (int) ($node->created_by ?? 0),
                'created_by_alias' => (string) ($node->created_by_alias ?? ''),
                'modified' => (string) ($node->modified ?? $db->getNullDate()),
                'modified_by' => (int) ($node->modified_by ?? 0),
                'publish_up' => (string) ($node->publish_up ?? $db->getNullDate()),
                'publish_down' => (string) ($node->publish_down ?? $db->getNullDate()),
                'ordering' => (int) ($node->ordering ?? 0),
                'featured' => (int) ($node->featured ?? 0),
                'hits' => (int) ($node->hits ?? 0),
                'images' => (string) ($node->images ?? ''),
                'urls' => (string) ($node->urls ?? ''),
                'attribs' => (string) ($node->attribs ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'metadesc' => (string) ($node->metadesc ?? ''),
                'metakey' => (string) ($node->metakey ?? ''),
                'note' => (string) ($node->note ?? ''),
            ];
            $table->bind($data);
            if (!$table->check()) {
                throw new RuntimeException('Article check failed: ' . $table->getError());
            }
            if (!$table->store()) {
                throw new RuntimeException('Article save failed: ' . $table->getError());
            }

            $rollback['created']['articles'][] = (int) $table->id;
            $created++;
        }
    }

    private static function buildArticleKey(string $alias, int $catid): string
    {
        return 'article:' . $catid . ':' . $alias;
    }
}
