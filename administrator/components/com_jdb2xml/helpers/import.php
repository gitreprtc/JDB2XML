<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Filesystem\File;
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
            'created' => ['categories' => [], 'phoca_categories' => [], 'tags' => [], 'articles' => []],
            'updated' => ['categories' => [], 'phoca_categories' => [], 'tags' => [], 'articles' => []],
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
        $createdPhocaCats = $changedPhocaCats = $skippedPhocaCats = 0;
        $createdTags = $changedTags = $skippedTags = 0;
        $createdArticles = $changedArticles = $skippedArticles = 0;
        $warnings = [];

        // If a preview plan is available, execute exactly that plan (no re-analysis)
        $app = Factory::getApplication();
        $preview = $app->getUserState('com_jdb2xml.preview');
        if ((!is_array($preview) || $preview === []) && $selectedFile) {
            $preview = $app->getUserState('com_jdb2xml.preview.' . $selectedFile);
        }
        if ($selectedFile && is_array($preview) && !isset($preview[$selectedFile]) && array_key_exists('categories', $preview)) {
            $preview = [$selectedFile => $preview];
        }
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
        $allowedPhocaCategorySet = null;
        $allowedArticleSet = null;
        $allowTags = true;
        $allowArticles = true;
        $allowPhocaCategories = true;
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

                $allowPhocaCategories = !empty($previewData['phocaCategories']) && is_array($previewData['phocaCategories']);
                if ($allowPhocaCategories) {
                    $allowedPhocaCategorySet = [];
                    $rows = $previewData['phocaCategories'] ?? [];
                    foreach ($rows as $row) {
                        $key = (string)($row['path'] ?? $row['id'] ?? '');
                        if ($key === '' || isset($excludeSet[$key])) {
                            continue;
                        }
                        $allowedPhocaCategorySet[$key] = true;
                    }
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

                    if ($allowPhocaCategories) {
                        if (isset($xml->phocagallerycategories)) {
                            self::importPhocaGalleryCategories($db, $xml->phocagallerycategories, $dryRun, $excludeSet, $createdPhocaCats, $changedPhocaCats, $skippedPhocaCats, $warnings, $rollback, $allowedPhocaCategorySet);
                        } elseif ($xml->getName() === 'phocagallerycategories') {
                            self::importPhocaGalleryCategories($db, $xml, $dryRun, $excludeSet, $createdPhocaCats, $changedPhocaCats, $skippedPhocaCats, $warnings, $rollback, $allowedPhocaCategorySet);
                        }
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

                    if (!$dryRun) {
                        self::moveFile($file, $processedDir);
                    }
                } catch (Throwable $e) {
                    $warnings[] = basename($file) . ': ' . $e->getMessage();
                    if (!$dryRun) {
                        self::moveFile($file, $failedDir);
                    }
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
            $msg[] = 'Phoca Gallery categories: new=' . $createdPhocaCats . ', changed=' . $changedPhocaCats . ', skipped=' . $skippedPhocaCats;
            $msg[] = 'Tags: new=' . $createdTags . ', changed=' . $changedTags . ', skipped=' . $skippedTags;
            $msg[] = 'Articles: new=' . $createdArticles . ', changed=' . $changedArticles . ', skipped=' . $skippedArticles;
            if ($warnings) $msg[] = 'Warnings: ' . implode(' | ', array_unique($warnings));
            if (!$dryRun && $rollbackFile) $msg[] = 'Rollback log: ' . basename($rollbackFile);
            return implode(' ', $msg);
        } finally {
            @unlink($lock);
        }
    }

    private static function moveFile(string $source, string $targetDir): void
    {
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $base = basename($source);
        $target = $targetDir . '/' . $base;

        if (is_file($target)) {
            $name = pathinfo($base, PATHINFO_FILENAME);
            $ext = pathinfo($base, PATHINFO_EXTENSION);
            $counter = 1;
            do {
                $suffix = '_' . $counter;
                $candidate = $name . $suffix . ($ext !== '' ? '.' . $ext : '');
                $target = $targetDir . '/' . $candidate;
                $counter++;
            } while (is_file($target));
        }

        if (!File::move($source, $target)) {
            @rename($source, $target);
        }
    }

    private static function importCategories($db, $categories, bool $dryRun, array $exclude, int &$created, int &$changed, int &$skipped, array &$warnings, array &$rollback, ?array $allowed = null): void
    {
        $extension = (string) ($categories['extension'] ?? 'com_content');
        $nodeMap = [];
        foreach ($categories->category as $node) {
            $nodePath = trim((string) $node->path);
            if ($nodePath === '') {
                continue;
            }
            $nodeMap[$nodePath] = $node;
        }

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

            self::ensureCategoryExists($db, $extension, $path, $nodeMap, $dryRun, $created, $warnings, $rollback);
        }
    }

    private static function ensureCategoryExists($db, string $extension, string $path, array $nodeMap, bool $dryRun, int &$created, array &$warnings, array &$rollback): int
    {
        if ($path === '') {
            return 1;
        }

        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('path=' . $db->quote($path))
            ->where('extension=' . $db->quote($extension));
        $existingId = (int) $db->setQuery($query)->loadResult();
        if ($existingId > 0) {
            return $existingId;
        }

        if ($dryRun) {
            $created++;
            return 1;
        }

        $node = $nodeMap[$path] ?? null;

        $parentId = 1;
        if (strpos($path, '/') !== false) {
            $parentPath = substr($path, 0, strrpos($path, '/'));
            if ($parentPath !== '') {
                $parentId = self::ensureCategoryExists($db, $extension, $parentPath, $nodeMap, $dryRun, $created, $warnings, $rollback);
            }
        }

        $segment = $path;
        if (strpos($path, '/') !== false) {
            $segment = substr($path, strrpos($path, '/') + 1);
        }

        $titleFallback = ucwords(str_replace('-', ' ', $segment));
        $aliasFallback = $segment;

        $table = Table::getInstance('Category');
        $nodeTitle = $node ? (string) ($node->title ?? '') : '';
        $nodeAlias = $node ? (string) ($node->alias ?? '') : '';
        $nodePublished = $node ? (int) ($node->published ?? 1) : 1;
        $nodeAccess = $node ? (int) ($node->access ?? 1) : 1;
        $nodeLanguage = $node ? (string) ($node->language ?? '*') : '*';
        $nodeDescription = $node ? (string) ($node->description ?? '') : '';
        $nodeParams = $node ? (string) ($node->params ?? '') : '';
        $nodeMetadata = $node ? (string) ($node->metadata ?? '') : '';
        $nodeMetadesc = $node ? (string) ($node->metadesc ?? '') : '';
        $nodeMetakey = $node ? (string) ($node->metakey ?? '') : '';
        $nodeNote = $node ? (string) ($node->note ?? '') : '';

        $data = [
            'title' => $nodeTitle !== '' ? $nodeTitle : $titleFallback,
            'alias' => $nodeAlias !== '' ? $nodeAlias : $aliasFallback,
            'path'  => $path,
            'extension' => $extension,
            'published' => $nodePublished,
            'access' => $nodeAccess,
            'language' => $nodeLanguage,
            'description' => $nodeDescription,
            'params' => $nodeParams,
            'metadata' => $nodeMetadata,
            'metadesc' => $nodeMetadesc,
            'metakey' => $nodeMetakey,
            'note' => $nodeNote,
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

        return (int) $table->id;
    }

    private static function importPhocaGalleryCategories(
        $db,
        $categories,
        bool $dryRun,
        array $exclude,
        int &$created,
        int &$changed,
        int &$skipped,
        array &$warnings,
        array &$rollback,
        ?array $allowed = null
    ): void {
        $columns = self::getTableColumns($db, '#__phocagallery_categories');
        if (empty($columns)) {
            $warnings[] = 'Phoca Gallery categories table missing.';
            return;
        }

        foreach ($categories->category as $node) {
            $id = (int) ($node->id ?? 0);
            $alias = trim((string) ($node->alias ?? ''));
            $key = $alias !== '' ? $alias : (string) $id;

            if ($key === '') {
                $skipped++;
                $warnings[] = 'Phoca Gallery category without alias or id skipped.';
                continue;
            }
            if (isset($exclude[$key])) { $skipped++; continue; }
            if ($allowed !== null && !isset($allowed[$key])) { $skipped++; continue; }

            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__phocagallery_categories');
            if ($alias !== '') {
                $query->where('alias=' . $db->quote($alias));
            } else {
                $query->where('id=' . (int) $id);
            }
            $existing = $db->setQuery($query)->loadObject();

            $title = (string) ($node->title ?? $alias);
            $map = [
                'title' => $title,
                'alias' => $alias,
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'published' => (string) ($node->published ?? ''),
                'access' => (string) ($node->access ?? ''),
                'language' => (string) ($node->language ?? ''),
                'parent_id' => (string) ($node->parent_id ?? ''),
                'ordering' => (string) ($node->ordering ?? ''),
            ];

            if ($existing) {
                $changedLocal = false;
                $before = ['id' => (int)$existing->id, 'fields' => []];

                if ($title !== '' && (string) ($existing->title ?? '') !== $title) {
                    if (isset($columns['title'])) {
                        $before['fields']['title'] = (string) ($existing->title ?? '');
                        $existing->title = $title;
                        $changedLocal = true;
                    }
                }

                foreach ($map as $field => $value) {
                    if ($value === '' || !isset($columns[$field])) {
                        continue;
                    }
                    if (!property_exists($existing, $field)) {
                        continue;
                    }
                    $dbVal = (string) ($existing->$field ?? '');
                    if ($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') {
                        $before['fields'][$field] = $dbVal;
                        $existing->$field = $value;
                        $changedLocal = true;
                    }
                }

                if ($changedLocal) {
                    if (!$dryRun) {
                        $db->updateObject('#__phocagallery_categories', $existing, 'id');
                        $rollback['updated']['phoca_categories'][] = $before;
                    }
                    $changed++;
                } else {
                    $skipped++;
                }
                continue;
            }

            if ($dryRun) { $created++; continue; }

            $data = [];
            $aliasValue = $alias !== '' ? $alias : OutputFilter::stringURLSafe($title);
            $defaults = [
                'title' => $title,
                'alias' => $aliasValue,
                'parent_id' => (int) ($node->parent_id ?? 0),
                'published' => (int) ($node->published ?? 1),
                'access' => (int) ($node->access ?? 1),
                'language' => (string) ($node->language ?? '*'),
                'ordering' => (int) ($node->ordering ?? 0),
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
            ];

            foreach ($defaults as $field => $value) {
                if (isset($columns[$field])) {
                    $data[$field] = $value;
                }
            }

            if ($data === []) {
                $warnings[] = 'Phoca Gallery category skipped (no valid columns).';
                $skipped++;
                continue;
            }

            $obj = (object) $data;
            if (!$db->insertObject('#__phocagallery_categories', $obj)) {
                throw new RuntimeException('Phoca Gallery category save failed');
            }

            $rollback['created']['phoca_categories'][] = (int) ($obj->id ?? 0);
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
                $parentChange = false;
                $before = ['id' => (int)$existing->id, 'fields' => []];

                foreach ($map as $field => $value) {
                    if ($value === '') continue;
                    if (!property_exists($existing, $field)) continue;
                    $dbVal = (string) ($existing->$field ?? '');
                    if ($dbVal !== $value) {
                        $before['fields'][$field] = $dbVal;
                        $existing->$field = $value;
                        $changedLocal = true;
                        if ($field === 'parent_id' && $value !== '') {
                            $parentChange = true;
                        }
                    }
                }

                if ($changedLocal) {
                    if (!$dryRun) {
                        if ($parentChange) {
                            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_tags/tables');
                            $table = Table::getInstance('Tag', 'TagsTable');
                            if ($table === false || !$table->load((int) $existing->id)) {
                                throw new RuntimeException('Tag-table kan niet worden geladen');
                            }
                            foreach ($map as $field => $value) {
                                if ($value === '') {
                                    continue;
                                }
                                $table->$field = $existing->$field;
                            }
                            $table->setLocation((int) $existing->parent_id, 'last-child');
                            if (!$table->check()) {
                                throw new RuntimeException('Tag check failed: ' . $table->getError());
                            }
                            if (!$table->store()) {
                                throw new RuntimeException('Tag save failed: ' . $table->getError());
                            }
                        } else {
                            $db->updateObject('#__tags', $existing, 'id');
                        }
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
            $now = Factory::getDate()->toSql();
            $noteText = 'Uploaded by JDB2XML';
            $nullPublishDown = null;

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
                'created' => $now,
                'created_by' => (string) ($node->created_by ?? ''),
                'created_by_alias' => (string) ($node->created_by_alias ?? ''),
                'modified' => $now,
                'modified_by' => (string) ($node->modified_by ?? ''),
                'publish_up' => $now,
                'publish_down' => $nullPublishDown,
                'ordering' => (string) ($node->ordering ?? ''),
                'featured' => (string) ($node->featured ?? ''),
                'hits' => (string) ($node->hits ?? ''),
                'images' => (string) ($node->images ?? ''),
                'urls' => (string) ($node->urls ?? ''),
                'attribs' => (string) ($node->attribs ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'metadesc' => (string) ($node->metadesc ?? ''),
                'metakey' => (string) ($node->metakey ?? ''),
                'note' => $noteText,
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
                        $override = (object) [
                            'id' => (int) $existing->id,
                            'created' => $now,
                            'modified' => $now,
                            'publish_up' => $now,
                            'publish_down' => $nullPublishDown,
                            'note' => $noteText,
                        ];
                        $db->updateObject('#__content', $override, 'id');
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
                'created' => $now,
                'created_by' => (int) ($node->created_by ?? 0),
                'created_by_alias' => (string) ($node->created_by_alias ?? ''),
                'modified' => $now,
                'modified_by' => (int) ($node->modified_by ?? 0),
                'publish_up' => $now,
                'publish_down' => $nullPublishDown,
                'ordering' => (int) ($node->ordering ?? 0),
                'featured' => (int) ($node->featured ?? 0),
                'hits' => (int) ($node->hits ?? 0),
                'images' => (string) ($node->images ?? ''),
                'urls' => (string) ($node->urls ?? ''),
                'attribs' => (string) ($node->attribs ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'metadesc' => (string) ($node->metadesc ?? ''),
                'metakey' => (string) ($node->metakey ?? ''),
                'note' => $noteText,
            ];
            $table->bind($data);
            if (!$table->check()) {
                throw new RuntimeException('Article check failed: ' . $table->getError());
            }
            if (!$table->store()) {
                throw new RuntimeException('Article save failed: ' . $table->getError());
            }
            $override = (object) [
                'id' => (int) $table->id,
                'created' => $now,
                'modified' => $now,
                'publish_up' => $now,
                'publish_down' => $nullPublishDown,
                'note' => $noteText,
            ];
            $db->updateObject('#__content', $override, 'id');

            $rollback['created']['articles'][] = (int) $table->id;
            $created++;
        }
    }

    private static function buildArticleKey(string $alias, int $catid): string
    {
        return 'article:' . $catid . ':' . $alias;
    }

    private static function getTableColumns($db, string $table): array
    {
        try {
            $columns = $db->getTableColumns($table, false);
        } catch (Throwable $e) {
            return [];
        }

        return is_array($columns) ? $columns : [];
    }
}
