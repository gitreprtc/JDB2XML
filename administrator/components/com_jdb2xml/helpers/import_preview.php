<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/ImportPlan.php';

class Jdb2xmlImportPreviewHelper
{
    public static function run(string $dir, ?string $selectedFile = null): array
    {
        if (!is_dir($dir)) {
            throw new RuntimeException('Import folder does not exist: ' . $dir);
        }

        $db = Factory::getDbo();
        $files = glob($dir . '/*.xml') ?: [];
        if ($selectedFile) {
            $path = $dir . '/' . basename($selectedFile);
            $files = is_file($path) ? [$path] : [];
        }
        $result = [];

        foreach ($files as $file) {
            $fileKey = basename($file);
            $result[$fileKey] = [
                'categories' => [],
                'categoryTree' => [],
                'tags' => [],
                'tagTree' => [],
                'articles' => [],
                'articleTree' => [],
                'warnings' => [],
                'hasWarnings' => false
            ];

            $xmlString = file_get_contents($file);
            if (!$xmlString) {
                $result[$fileKey]['warnings'][] = 'File is empty or not readable';
                $result[$fileKey]['hasWarnings'] = true;
                continue;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlString);
            if (!$xml) {
                $result[$fileKey]['warnings'][] = 'Invalid XML';
                $result[$fileKey]['hasWarnings'] = true;
                continue;
            }

            if (isset($xml->categories)) {
                self::previewCategories($db, $xml->categories, $result[$fileKey]);
            } elseif ($xml->getName() === 'categories') {
                self::previewCategories($db, $xml, $result[$fileKey]);
            }

            if (isset($xml->tags)) {
                self::previewTags($db, $xml->tags, $result[$fileKey]);
            } elseif ($xml->getName() === 'tags') {
                self::previewTags($db, $xml, $result[$fileKey]);
            }

            if (isset($xml->articles)) {
                self::previewArticles($db, $xml->articles, $result[$fileKey]);
            } elseif ($xml->getName() === 'articles') {
                self::previewArticles($db, $xml, $result[$fileKey]);
            }

            // Build a tree from category paths
            $result[$fileKey]['categoryTree'] = self::buildTree($result[$fileKey]['categories']);
            $result[$fileKey]['tagTree'] = self::buildTreeFromParents($result[$fileKey]['tags'], 'id', 'parent_id');
            $result[$fileKey]['articleTree'] = self::buildArticleTree($db, $result[$fileKey]['articles']);

            if ($result[$fileKey]['warnings']) {
                $result[$fileKey]['hasWarnings'] = true;
            }
        }

        return $result;
    }

    private static function buildTree(array $rows): array
    {
        // rows contain id=path, action, title
        $nodes = [];
        $root = [];

        foreach ($rows as $r) {
            $path = $r['id'];
            if ($path === '') continue;
            $nodes[$path] = $r + ['children' => []];
        }

        foreach ($nodes as $path => $node) {
            $parent = '';
            if (strpos($path, '/') !== false) {
                $parent = substr($path, 0, strrpos($path, '/'));
            }
            if ($parent !== '' && isset($nodes[$parent])) {
                $nodes[$parent]['children'][] = $path;
            } else {
                $root[] = $path;
            }
        }

        // Convert to nested arrays for rendering
        $walk = function($path) use (&$walk, &$nodes) {
            $n = $nodes[$path];
            $children = [];
            foreach ($n['children'] as $cp) {
                $children[] = $walk($cp);
            }
            $n['children'] = $children;
            return $n;
        };

        $tree = [];
        foreach ($root as $rp) {
            $tree[] = $walk($rp);
        }

        // Sort tree alphabetically by title for consistency
        $sortFn = function(&$arr) use (&$sortFn) {
            usort($arr, function($a,$b){ return strcasecmp($a['title'],$b['title']); });
            foreach ($arr as &$n) {
                if (!empty($n['children'])) $sortFn($n['children']);
            }
        };
        $sortFn($tree);

        return $tree;
    }

    private static function previewCategories($db, $categories, array &$out): void
    {
        $extension = (string) ($categories['extension'] ?? 'com_content');

        foreach ($categories->category as $node) {
            $path = trim((string) $node->path);

            if ($path === '') {
                $out['warnings'][] = 'Category without path skipped';
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => '',
                    'title' => (string) $node->title,
                    'action' => 'skipped',
                    'reason' => 'Missing path',
                    'exclude' => true
                ];
                continue;
            }

            $query = $db->getQuery(true)
                ->select('id, title, description, params, metadata, metadesc, metakey, note')
                ->from('#__categories')
                ->where('path=' . $db->quote($path))
                ->where('extension=' . $db->quote($extension));

            $existing = $db->setQuery($query)->loadObject();

            if (!$existing) {
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => $path,
                    'title' => (string) $node->title,
                    'action' => 'new',
                    'reason' => '',
                    'exclude' => false
                ];
                continue;
            }

            $titleChanged = trim((string) $node->title) !== (string) ($existing->title ?? '');
            $canSupplement = false;
            $supplementFields = [];
            foreach (['description','params','metadata','metadesc','metakey','note'] as $field) {
                $dbVal = (string) ($existing->$field ?? '');
                $xmlVal = (string) ($node->$field ?? '');
                if (($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') && $xmlVal !== '') {
                    $canSupplement = true;
                    $supplementFields[] = $field;
                }
            }

            if ($titleChanged || $canSupplement) {
                $reasons = [];
                if ($titleChanged) {
                    $reasons[] = 'Title changed';
                }
                if (!empty($supplementFields)) {
                    $reasons[] = 'Fill empty: ' . implode(', ', $supplementFields);
                }
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => $path,
                    'title' => (string) $node->title,
                    'action' => 'changed',
                    'reason' => implode(' | ', $reasons),
                    'exclude' => false
                ];
            } else {
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => $path,
                    'title' => (string) $node->title,
                    'action' => 'skipped',
                    'reason' => 'No empty fields to fill',
                    'exclude' => true
                ];
            }
        }
    }

    private static function previewTags($db, $tags, array &$out): void
    {
        foreach ($tags->tag as $node) {
            $alias = trim((string) $node->alias);
            $title = (string) $node->title;

            if ($alias === '') {
                $out['warnings'][] = 'Tag without alias skipped';
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => 0,
                    'path' => '',
                    'parent_id' => 0,
                    'title' => $title,
                    'action' => 'skipped',
                    'reason' => 'Missing alias',
                    'exclude' => true
                ];
                continue;
            }

            $query = $db->getQuery(true)
                ->select('id, title, description, params, metadata')
                ->from('#__tags')
                ->where('alias=' . $db->quote($alias));

            $existing = $db->setQuery($query)->loadObject();

            if (!$existing) {
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => (int) ($node->id ?? 0),
                    'path' => $alias,
                    'path_display' => $alias,
                    'exclude_key' => $alias,
                    'parent_id' => (int) ($node->parent_id ?? 1),
                    'title' => $title,
                    'action' => 'new',
                    'reason' => '',
                    'exclude' => false
                ];
                continue;
            }

            $titleChanged = trim((string) $node->title) !== (string) ($existing->title ?? '');
            $canSupplement = false;
            $supplementFields = [];
            foreach (['description','params','metadata'] as $field) {
                $dbVal = (string) ($existing->$field ?? '');
                $xmlVal = (string) ($node->$field ?? '');
                if (($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') && $xmlVal !== '') {
                    $canSupplement = true;
                    $supplementFields[] = $field;
                }
            }

            if ($titleChanged || $canSupplement) {
                $reasons = [];
                if ($titleChanged) {
                    $reasons[] = 'Title changed';
                }
                if (!empty($supplementFields)) {
                    $reasons[] = 'Fill empty: ' . implode(', ', $supplementFields);
                }
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => (int) ($node->id ?? $existing->id ?? 0),
                    'path' => $alias,
                    'path_display' => $alias,
                    'exclude_key' => $alias,
                    'parent_id' => (int) ($node->parent_id ?? $existing->parent_id ?? 1),
                    'title' => $title,
                    'action' => 'changed',
                    'reason' => implode(' | ', $reasons),
                    'exclude' => false
                ];
            } else {
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => (int) ($node->id ?? $existing->id ?? 0),
                    'path' => $alias,
                    'path_display' => $alias,
                    'exclude_key' => $alias,
                    'parent_id' => (int) ($node->parent_id ?? $existing->parent_id ?? 1),
                    'title' => $title,
                    'action' => 'skipped',
                    'reason' => 'No empty fields to fill',
                    'exclude' => true
                ];
            }
        }
    }

    private static function previewArticles($db, $articles, array &$out): void
    {
        foreach ($articles->article as $node) {
            $alias = trim((string) $node->alias);
            $title = (string) $node->title;
            $catid = (int) ($node->catid ?? 0);
            $key = self::buildArticleKey($alias, $catid);

            if ($alias === '' || $catid === 0) {
                $out['warnings'][] = 'Article without alias or category skipped';
                $out['articles'][] = [
                    'type' => 'article',
                    'id' => $key,
                    'path' => $key,
                    'path_display' => $alias,
                    'exclude_key' => $key,
                    'title' => $title,
                    'alias' => $alias,
                    'catid' => $catid,
                    'action' => 'skipped',
                    'reason' => 'Missing alias or category',
                    'exclude' => true
                ];
                continue;
            }

            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__content')
                ->where('alias=' . $db->quote($alias))
                ->where('catid=' . (int) $catid);

            $existing = $db->setQuery($query)->loadObject();

            if (!$existing) {
                $out['articles'][] = [
                    'type' => 'article',
                    'id' => $key,
                    'path' => $key,
                    'path_display' => $alias,
                    'exclude_key' => $key,
                    'title' => $title,
                    'alias' => $alias,
                    'catid' => $catid,
                    'action' => 'new',
                    'reason' => '',
                    'exclude' => false
                ];
                continue;
            }

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

            $hasChanges = false;
            $changeFields = [];
            foreach ($fields as $field => $value) {
                if (!property_exists($existing, $field)) {
                    continue;
                }
                $dbVal = (string) ($existing->$field ?? '');
                if ($dbVal !== $value) {
                    $hasChanges = true;
                    $changeFields[] = $field;
                }
            }

            if ($hasChanges) {
                $out['articles'][] = [
                    'type' => 'article',
                    'id' => $key,
                    'path' => $key,
                    'path_display' => $alias,
                    'exclude_key' => $key,
                    'title' => $title,
                    'alias' => $alias,
                    'catid' => $catid,
                    'action' => 'changed',
                    'reason' => !empty($changeFields) ? 'Changed: ' . implode(', ', $changeFields) : 'Changed',
                    'exclude' => false
                ];
            } else {
                $out['articles'][] = [
                    'type' => 'article',
                    'id' => $key,
                    'path' => $key,
                    'path_display' => $alias,
                    'exclude_key' => $key,
                    'title' => $title,
                    'alias' => $alias,
                    'catid' => $catid,
                    'action' => 'skipped',
                    'reason' => 'No changes detected',
                    'exclude' => true
                ];
            }
        }
    }

    private static function buildArticleKey(string $alias, int $catid): string
    {
        return 'article:' . $catid . ':' . $alias;
    }

    private static function buildTreeFromParents(array $rows, string $idKey, string $parentKey): array
    {
        $nodes = [];
        $root = [];

        foreach ($rows as $r) {
            $nodeId = (int) ($r[$idKey] ?? 0);
            if ($nodeId === 0) {
                continue;
            }
            $nodes[$nodeId] = $r + ['children' => []];
        }

        foreach ($nodes as $nodeId => $node) {
            $parentId = (int) ($node[$parentKey] ?? 0);
            if ($parentId !== 0 && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = $nodeId;
            } else {
                $root[] = $nodeId;
            }
        }

        $walk = function ($nodeId) use (&$walk, &$nodes) {
            $n = $nodes[$nodeId];
            $children = [];
            foreach ($n['children'] as $childId) {
                $children[] = $walk($childId);
            }
            $n['children'] = $children;
            return $n;
        };

        $tree = [];
        foreach ($root as $rootId) {
            $tree[] = $walk($rootId);
        }

        $sortFn = function (&$arr) use (&$sortFn) {
            usort($arr, function ($a, $b) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });
            foreach ($arr as &$n) {
                if (!empty($n['children'])) {
                    $sortFn($n['children']);
                }
            }
        };
        $sortFn($tree);

        return $tree;
    }

    private static function buildArticleTree($db, array $articles): array
    {
        if (empty($articles)) {
            return [];
        }

        $catIds = [];
        foreach ($articles as $article) {
            $catId = (int) ($article['catid'] ?? 0);
            if ($catId > 0) {
                $catIds[$catId] = $catId;
            }
        }
        if (empty($catIds)) {
            return [];
        }

        $categoryMap = [];
        $pending = $catIds;
        while (!empty($pending)) {
            $query = $db->getQuery(true)
                ->select('id, title, path, parent_id')
                ->from('#__categories')
                ->where('id IN (' . implode(',', $pending) . ')');
            $categories = $db->setQuery($query)->loadAssocList() ?: [];
            $pending = [];
            foreach ($categories as $cat) {
                $catId = (int) $cat['id'];
                if (!isset($categoryMap[$catId])) {
                    $categoryMap[$catId] = $cat;
                    $parentId = (int) ($cat['parent_id'] ?? 0);
                    if ($parentId > 0 && !isset($categoryMap[$parentId])) {
                        $pending[$parentId] = $parentId;
                    }
                }
            }
        }

        $categoryNodes = [];
        foreach ($categoryMap as $catId => $cat) {
            $categoryNodes[$catId] = [
                'type' => 'article-category',
                'id' => 'category:' . $catId,
                'path' => (string) ($cat['path'] ?? ''),
                'path_display' => (string) ($cat['path'] ?? ''),
                'title' => (string) ($cat['title'] ?? ''),
                'action' => '',
                'reason' => '',
                'exclude' => false,
                'parent_id' => (int) ($cat['parent_id'] ?? 0),
                'children' => [],
            ];
        }

        foreach ($articles as $article) {
            $catId = (int) ($article['catid'] ?? 0);
            if ($catId === 0) {
                continue;
            }
            $articleNode = $article;
            $articleNode['children'] = [];
            $categoryNodes[$catId]['children'][] = $articleNode;
        }

        $root = [];
        foreach ($categoryNodes as $catId => $node) {
            $parentId = (int) ($node['parent_id'] ?? 0);
            if ($parentId !== 0 && isset($categoryNodes[$parentId])) {
                $categoryNodes[$parentId]['children'][] = $node;
            } else {
                $root[] = $node;
            }
        }

        $sortFn = function (&$arr) use (&$sortFn) {
            usort($arr, function ($a, $b) {
                return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            });
            foreach ($arr as &$n) {
                if (!empty($n['children']) && is_array($n['children'])) {
                    $sortFn($n['children']);
                }
            }
        };
        $sortFn($root);

        return $root;
    }
}
