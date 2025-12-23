<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
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
            'created' => ['categories' => [], 'phoca_tags' => [], 'tags' => [], 'articles' => []],
            'updated' => ['categories' => [], 'phoca_tags' => [], 'tags' => [], 'articles' => []],
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
        $createdPhocaTags = $changedPhocaTags = $skippedPhocaTags = 0;
        $createdTags = $changedTags = $skippedTags = 0;
        $createdArticles = $changedArticles = $skippedArticles = 0;
        $warnings = [];
        $summaries = [
            'categories' => false,
            'phoca_tags' => false,
            'tags' => false,
            'articles' => false,
        ];

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
        $allowedPhocaTagSet = null;
        $allowedArticleSet = null;
        $allowTags = true;
        $allowArticles = true;
        $allowPhocaTags = true;
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

                $allowPhocaTags = !empty($previewData['phocaTags']) && is_array($previewData['phocaTags']);
                if ($allowPhocaTags) {
                    $allowedPhocaTagSet = [];
                    $rows = $previewData['phocaTags'] ?? [];
                    foreach ($rows as $row) {
                        $key = (string)($row['path'] ?? $row['id'] ?? '');
                        if ($key === '' || isset($excludeSet[$key])) {
                            continue;
                        }
                        $allowedPhocaTagSet[$key] = true;
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
                        $summaries['categories'] = true;
                    } elseif ($xml->getName() === 'categories') {
                        self::importCategories($db, $xml, $dryRun, $excludeSet, $createdCats, $changedCats, $skippedCats, $warnings, $rollback, $allowedCategorySet);
                        $summaries['categories'] = true;
                    }

                    if ($allowPhocaTags) {
                        if (isset($xml->phocagallerytags)) {
                            self::importPhocaGalleryTags($db, $xml->phocagallerytags, $dryRun, $excludeSet, $createdPhocaTags, $changedPhocaTags, $skippedPhocaTags, $warnings, $rollback, $allowedPhocaTagSet);
                            $summaries['phoca_tags'] = true;
                        } elseif (isset($xml->phocagallerycategories)) {
                            self::importPhocaGalleryTags($db, $xml->phocagallerycategories, $dryRun, $excludeSet, $createdPhocaTags, $changedPhocaTags, $skippedPhocaTags, $warnings, $rollback, $allowedPhocaTagSet);
                            $summaries['phoca_tags'] = true;
                        } elseif (in_array($xml->getName(), ['phocagallerytags', 'phocagallerycategories'], true)) {
                            self::importPhocaGalleryTags($db, $xml, $dryRun, $excludeSet, $createdPhocaTags, $changedPhocaTags, $skippedPhocaTags, $warnings, $rollback, $allowedPhocaTagSet);
                            $summaries['phoca_tags'] = true;
                        }
                    }

                    if ($allowTags) {
                        if (isset($xml->tags)) {
                            self::importTags($db, $xml->tags, $dryRun, $excludeSet, $createdTags, $changedTags, $skippedTags, $warnings, $rollback);
                            $summaries['tags'] = true;
                        } elseif ($xml->getName() === 'tags') {
                            self::importTags($db, $xml, $dryRun, $excludeSet, $createdTags, $changedTags, $skippedTags, $warnings, $rollback);
                            $summaries['tags'] = true;
                        }
                    }

                    if ($allowArticles) {
                        if (isset($xml->articles)) {
                            self::importArticles($db, $xml->articles, $dryRun, $excludeSet, $createdArticles, $changedArticles, $skippedArticles, $warnings, $rollback, $allowedArticleSet);
                            $summaries['articles'] = true;
                        } elseif ($xml->getName() === 'articles') {
                            self::importArticles($db, $xml, $dryRun, $excludeSet, $createdArticles, $changedArticles, $skippedArticles, $warnings, $rollback, $allowedArticleSet);
                            $summaries['articles'] = true;
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
            if ($summaries['categories']) {
                $msg[] = 'Categories: new=' . $createdCats . ', changed=' . $changedCats . ', skipped=' . $skippedCats;
            }
            if ($summaries['phoca_tags']) {
                $msg[] = 'Phoca Gallery tags: new=' . $createdPhocaTags . ', changed=' . $changedPhocaTags . ', skipped=' . $skippedPhocaTags;
            }
            if ($summaries['tags']) {
                $msg[] = 'Tags: new=' . $createdTags . ', changed=' . $changedTags . ', skipped=' . $skippedTags;
            }
            if ($summaries['articles']) {
                $msg[] = 'Articles: new=' . $createdArticles . ', changed=' . $changedArticles . ', skipped=' . $skippedArticles;
            }
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

    private static function importPhocaGalleryTags(
        $db,
        $tags,
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
            $warnings[] = 'Phoca Gallery tags table missing.';
            return;
        }

        $entries = [];
        if (isset($tags->tag)) {
            $entries = $tags->tag;
        } elseif (isset($tags->category)) {
            $entries = $tags->category;
        }

        $existingIndex = self::getPhocaCategoryIndex($db);
        $createdIndex = [];

        foreach ($entries as $node) {
            $id = (int) ($node->id ?? 0);
            $alias = trim((string) ($node->alias ?? ''));
            $key = $alias !== '' ? $alias : (string) $id;

            if ($key === '') {
                $skipped++;
                $warnings[] = 'Phoca Gallery tag without alias or id skipped.';
                continue;
            }
            if (isset($exclude[$key])) { $skipped++; continue; }
            if ($allowed !== null && !isset($allowed[$key])) { $skipped++; continue; }

            $existing = $existingIndex['alias'][$alias] ?? $existingIndex['id'][$id] ?? null;

            $title = (string) ($node->title ?? $alias);
            $resolvedParent = self::resolvePhocaParent($node, $existingIndex, $createdIndex);
            $parentId = $resolvedParent['id'];
            $parentEntry = $resolvedParent['entry'];

            $map = [
                'title' => $title,
                'alias' => $alias,
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'approved' => (string) ($node->approved ?? '1'),
                'published' => (string) ($node->published ?? ''),
                'access' => (string) ($node->access ?? ''),
                'language' => (string) ($node->language ?? ''),
                'ordering' => (string) ($node->ordering ?? ''),
                'parent_id' => $parentId,
            ];

            if (isset($columns['userfolder'])) {
                $map['userfolder'] = self::buildPhocaUserfolder($node, $title, $alias, $parentEntry);
            }

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
                    if (!isset($columns[$field])) {
                        continue;
                    }
                    if (!property_exists($existing, $field)) {
                        continue;
                    }
                    $dbVal = (string) ($existing->$field ?? '');
                    if ($field === 'parent_id') {
                        if ($value !== '' && (int)$dbVal !== (int)$value) {
                            $before['fields'][$field] = $dbVal;
                            $existing->$field = (int) $value;
                            $changedLocal = true;
                        }
                        continue;
                    }
                    if ($field === 'userfolder') {
                        if ($dbVal === '' && $value !== '') {
                            $before['fields'][$field] = $dbVal;
                            $existing->$field = $value;
                            $changedLocal = true;
                        }
                        continue;
                    }
                    if ($field === 'approved') {
                        if ($value !== '' && (int) $dbVal !== (int) $value) {
                            $before['fields'][$field] = $dbVal;
                            $existing->$field = (int) $value;
                            $changedLocal = true;
                        }
                        continue;
                    }
                    if ($value === '') {
                        continue;
                    }
                    if ($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') {
                        $before['fields'][$field] = $dbVal;
                        $existing->$field = $value;
                        $changedLocal = true;
                    }
                }

                if ($changedLocal) {
                    if (!$dryRun) {
                        $db->updateObject('#__phocagallery_categories', $existing, 'id');
                        $rollback['updated']['phoca_tags'][] = $before;

                        if (isset($columns['userfolder']) && !empty($existing->userfolder)) {
                            self::ensurePhocaUserFolder($existing->userfolder);
                        }
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
                'approved' => isset($node->approved) ? (int) $node->approved : 1,
                'published' => (int) ($node->published ?? 1),
                'access' => (int) ($node->access ?? 1),
                'language' => (string) ($node->language ?? '*'),
                'ordering' => (int) ($node->ordering ?? 0),
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'parent_id' => $parentId,
            ];
            if (isset($columns['date'])) {
                $defaults['date'] = (string) ($node->date ?? date('Y-m-d H:i:s'));
            }
            if (isset($columns['userfolder'])) {
                $defaults['userfolder'] = self::buildPhocaUserfolder($node, $title, $aliasValue, $parentEntry);
            }

            foreach ($defaults as $field => $value) {
                if (isset($columns[$field])) {
                    $data[$field] = $value;
                }
            }

            if ($data === []) {
                $warnings[] = 'Phoca Gallery tag skipped (no valid columns).';
                $skipped++;
                continue;
            }

            $obj = (object) $data;
            if (!$db->insertObject('#__phocagallery_categories', $obj)) {
                throw new RuntimeException('Phoca Gallery tag save failed');
            }

            if (isset($columns['userfolder']) && !empty($obj->userfolder)) {
                self::ensurePhocaUserFolder($obj->userfolder);
            }

            $rollback['created']['phoca_tags'][] = (int) ($obj->id ?? 0);
            $created++;

            $newId = (int) ($obj->id ?? 0);
            if ($aliasValue !== '') {
                $createdIndex[$aliasValue] = (object) ['id' => $newId, 'alias' => $aliasValue, 'parent_id' => $parentId, 'userfolder' => ($data['userfolder'] ?? '')];
            }
            if ($newId > 0) {
                $createdIndex[$newId] = (object) ['id' => $newId, 'alias' => $aliasValue, 'parent_id' => $parentId, 'userfolder' => ($data['userfolder'] ?? '')];
            }
        }
    }

    private static function getPhocaCategoryIndex($db): array
    {
        $index = ['alias' => [], 'id' => []];
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'alias', 'parent_id', 'userfolder']))
                ->from('#__phocagallery_categories');
            $rows = (array) $db->setQuery($query)->loadObjectList();
            foreach ($rows as $row) {
                $index['id'][(int) $row->id] = $row;
                if (!empty($row->alias)) {
                    $index['alias'][(string) $row->alias] = $row;
                }
            }
        } catch (Throwable $e) {
        }

        return $index;
    }

    private static function resolvePhocaParent($node, array $existingIndex, array $createdIndex): array
    {
        $parentAlias = trim((string) ($node->parent_alias ?? ''));
        $parentId = (int) ($node->parent_id ?? 0);

        if ($parentAlias !== '') {
            if (isset($createdIndex[$parentAlias])) {
                $parent = $createdIndex[$parentAlias];
                return ['id' => (int) ($parent->id ?? 0), 'entry' => $parent];
            }
            if (isset($existingIndex['alias'][$parentAlias])) {
                $parent = $existingIndex['alias'][$parentAlias];
                return ['id' => (int) ($parent->id ?? 0), 'entry' => $parent];
            }
        }

        if ($parentId > 0) {
            $parent = $createdIndex[$parentId] ?? ($existingIndex['id'][$parentId] ?? null);
            return ['id' => $parent ? (int) $parent->id : $parentId, 'entry' => $parent];
        }

        return ['id' => 0, 'entry' => null];
    }

    private static function buildPhocaUserfolder($node, string $title, string $alias, ?object $parent = null): string
    {
        $candidate = trim((string) ($node->userfolder ?? ''));
        if ($candidate === '') {
            $candidate = $alias !== '' ? $alias : OutputFilter::stringURLSafe($title);
        }
        $candidate = trim($candidate, '/');

        if ($parent && !empty($parent->userfolder)) {
            $parentPath = trim((string) $parent->userfolder, '/');
            if ($parentPath !== '') {
                $candidate = $parentPath . '/' . $candidate;
            }
        }

        return $candidate;
    }

    private static function ensurePhocaUserFolder(string $userfolder): void
    {
        $base = JPATH_ROOT . '/images/phocagallery';
        $target = $base . '/' . trim($userfolder, '/');
        if (!is_dir($target)) {
            Folder::create($target);
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
            $publishUp = Factory::getDate()->setTime(0, 0, 0)->toSql();
            $noteText = 'Uploaded by JDB2XML';
            $nullPublishDown = null;
            $userId = (int) Factory::getUser()->id;
            $defaultAccess = 1;
            $defaultLanguage = '*';
            $defaultState = 1;

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
                'state' => (string) $defaultState,
                'access' => (string) $defaultAccess,
                'language' => (string) $defaultLanguage,
                'created' => $now,
                'created_by' => (string) $userId,
                'created_by_alias' => '',
                'modified' => $now,
                'modified_by' => (string) $userId,
                'publish_up' => $publishUp,
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
                            'state' => $defaultState,
                            'access' => $defaultAccess,
                            'language' => $defaultLanguage,
                            'created' => $now,
                            'created_by' => $userId,
                            'modified' => $now,
                            'modified_by' => $userId,
                            'publish_up' => $publishUp,
                            'publish_down' => $nullPublishDown,
                            'note' => $noteText,
                        ];
                        $db->updateObject('#__content', $override, 'id');
                        self::ensureArticleAsset($db, (int) $existing->id, $catid, $userId, (string) ($node->title ?? $alias));
                        self::ensureArticleUcmContent($db, (int) $existing->id);
                        self::ensureArticleWorkflowAssociation($db, (int) $existing->id);
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
                'state' => $defaultState,
                'access' => $defaultAccess,
                'language' => $defaultLanguage,
                'created' => $now,
                'created_by' => $userId,
                'created_by_alias' => '',
                'modified' => $now,
                'modified_by' => $userId,
                'publish_up' => $publishUp,
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
                'state' => $defaultState,
                'access' => $defaultAccess,
                'language' => $defaultLanguage,
                'created' => $now,
                'created_by' => $userId,
                'modified' => $now,
                'modified_by' => $userId,
                'publish_up' => $publishUp,
                'publish_down' => $nullPublishDown,
                'note' => $noteText,
            ];
            $db->updateObject('#__content', $override, 'id');
            self::ensureArticleAsset($db, (int) $table->id, $catid, $userId, (string) ($node->title ?? $alias));
            self::ensureArticleUcmContent($db, (int) $table->id);
            self::ensureArticleWorkflowAssociation($db, (int) $table->id);

            $rollback['created']['articles'][] = (int) $table->id;
            $created++;
        }
    }

    private static function buildArticleKey(string $alias, int $catid): string
    {
        return 'article:' . $catid . ':' . $alias;
    }

    private static function ensureArticleAsset($db, int $articleId, int $catid, int $userId, string $title): void
    {
        if ($articleId <= 0) {
            return;
        }

        $currentAssetId = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('asset_id')
                ->from('#__content')
                ->where('id=' . (int) $articleId)
        )->loadResult();

        $assetName = 'com_content.article.' . $articleId;
        if ($currentAssetId > 0) {
            $assetRow = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name']))
                    ->from('#__assets')
                    ->where('id=' . (int) $currentAssetId)
            )->loadAssoc();
            if ($assetRow && (string) ($assetRow['name'] ?? '') === $assetName) {
                return;
            }
        }

        $assetId = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('id')
                ->from('#__assets')
                ->where('name=' . $db->quote($assetName))
        )->loadResult();

        if ($assetId > 0) {
            $assetUpdate = (object) ['id' => $articleId, 'asset_id' => $assetId];
            $db->updateObject('#__content', $assetUpdate, 'id');
            return;
        }

        $parentName = $catid > 0 ? 'com_content.category.' . $catid : 'com_content';
        $parentId = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('id')
                ->from('#__assets')
                ->where('name=' . $db->quote($parentName))
        )->loadResult();

        if ($parentId <= 0) {
            $parentId = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('id')
                    ->from('#__assets')
                    ->where('name=' . $db->quote('com_content'))
            )->loadResult();
        }
        if ($parentId <= 0) {
            $parentId = 1;
        }

        $assetTable = Table::getInstance('Asset');
        if ($assetTable === false) {
            return;
        }

        $assetTable->parent_id = $parentId;
        $assetTable->name = $assetName;
        $assetTable->title = $title !== '' ? $title : ('Article ' . $articleId);
        $assetTable->rules = '{}';
        $assetTable->created_user_id = $userId;
        if ($assetTable->check() && $assetTable->store()) {
            $assetUpdate = (object) ['id' => $articleId, 'asset_id' => (int) $assetTable->id];
            $db->updateObject('#__content', $assetUpdate, 'id');
        }
    }

    private static function ensureArticleUcmContent($db, int $articleId): void
    {
        if ($articleId <= 0) {
            return;
        }

        $columns = self::getTableColumns($db, '#__ucm_content');
        if (empty($columns)) {
            return;
        }

        $content = $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from('#__content')
                ->where('id=' . (int) $articleId)
        )->loadObject();
        if (!$content) {
            return;
        }

        $typeId = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('type_id')
                ->from('#__content_types')
                ->where('type_alias=' . $db->quote('com_content.article'))
        )->loadResult();

        $exists = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('core_content_id')
                ->from('#__ucm_content')
                ->where('core_content_id=' . (int) $articleId)
                ->where('core_type_alias=' . $db->quote('com_content.article'))
        )->loadResult();

        $coreBody = (string) ($content->introtext ?? '') . (string) ($content->fulltext ?? '');
        $data = [
            'core_content_id' => (int) $articleId,
            'core_type_alias' => 'com_content.article',
            'core_type_id' => $typeId,
            'core_title' => (string) ($content->title ?? ''),
            'core_alias' => (string) ($content->alias ?? ''),
            'core_body' => $coreBody,
            'core_state' => (int) ($content->state ?? 1),
            'core_access' => (int) ($content->access ?? 1),
            'core_params' => (string) ($content->attribs ?? ''),
            'core_featured' => (int) ($content->featured ?? 0),
            'core_metadata' => (string) ($content->metadata ?? ''),
            'core_created_time' => (string) ($content->created ?? ''),
            'core_modified_time' => (string) ($content->modified ?? ''),
            'core_publish_up' => (string) ($content->publish_up ?? ''),
            'core_publish_down' => (string) ($content->publish_down ?? ''),
            'core_language' => (string) ($content->language ?? '*'),
            'core_author_id' => (int) ($content->created_by ?? 0),
            'core_images' => (string) ($content->images ?? ''),
            'core_urls' => (string) ($content->urls ?? ''),
            'core_version' => (int) ($content->version ?? 1),
            'core_ordering' => (int) ($content->ordering ?? 0),
            'core_hits' => (int) ($content->hits ?? 0),
            'core_catid' => (int) ($content->catid ?? 0),
            'core_asset_id' => (int) ($content->asset_id ?? 0),
            'core_checked_out_time' => (string) ($content->checked_out_time ?? ''),
            'core_checked_out_user_id' => (int) ($content->checked_out ?? 0),
        ];

        $payload = [];
        foreach ($data as $field => $value) {
            if (isset($columns[$field])) {
                $payload[$field] = $value;
            }
        }
        if (empty($payload)) {
            return;
        }

        $object = (object) $payload;
        if ($exists) {
            $db->updateObject('#__ucm_content', $object, 'core_content_id');
        } else {
            $db->insertObject('#__ucm_content', $object);
        }
    }

    private static function ensureArticleWorkflowAssociation($db, int $articleId): void
    {
        if ($articleId <= 0) {
            return;
        }

        $columns = self::getTableColumns($db, '#__workflow_associations');
        if (empty($columns)) {
            return;
        }

        $exists = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('item_id')
                ->from('#__workflow_associations')
                ->where('item_id=' . (int) $articleId)
                ->where('extension=' . $db->quote('com_content.article'))
        )->loadResult();

        if ($exists) {
            return;
        }

        $stageColumns = self::getTableColumns($db, '#__workflow_stages');
        $stageQuery = $db->getQuery(true)
            ->select('id')
            ->from('#__workflow_stages')
            ->where('published=1');
        if (isset($stageColumns['extension'])) {
            $stageQuery->where('extension=' . $db->quote('com_content'));
        } elseif (isset($stageColumns['scope'])) {
            $stageQuery->where('scope=' . $db->quote('com_content'));
        }
        $stageQuery->order('ordering ASC');
        $stageId = (int) $db->setQuery($stageQuery)->loadResult();
        if ($stageId <= 0) {
            $stageId = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('id')
                    ->from('#__workflow_stages')
                    ->order('ordering ASC')
            )->loadResult();
        }

        if ($stageId <= 0) {
            return;
        }

        $payload = (object) [
            'item_id' => $articleId,
            'stage_id' => $stageId,
            'extension' => 'com_content.article',
        ];
        $db->insertObject('#__workflow_associations', $payload);
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
