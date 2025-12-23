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
                'phocaTags' => [],
                'tags' => [],
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

            if (isset($xml->phocagallerytags)) {
                self::previewPhocaGalleryTags($db, $xml->phocagallerytags, $result[$fileKey]);
            } elseif (isset($xml->phocagallerycategories)) {
                self::previewPhocaGalleryTags($db, $xml->phocagallerycategories, $result[$fileKey]);
            } elseif (in_array($xml->getName(), ['phocagallerytags', 'phocagallerycategories'], true)) {
                self::previewPhocaGalleryTags($db, $xml, $result[$fileKey]);
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
            $result[$fileKey]['articleTree'] = self::buildArticleTree($result[$fileKey]['articles']);

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

    private static function buildArticleTree(array $rows): array
    {
        $parents = [];

        foreach ($rows as $row) {
            $catid = (int) ($row['catid'] ?? 0);
            $parentKey = 'cat:' . $catid;
            if (!isset($parents[$parentKey])) {
                $parents[$parentKey] = [
                    'id' => $parentKey,
                    'title' => $catid ? ('Category ' . $catid) : 'Unassigned',
                    'path' => '',
                    'action' => '',
                    'reason' => '',
                    'children' => []
                ];
            }

            $childKey = (string) ($row['id'] ?? '');
            if ($childKey === '') {
                continue;
            }

            $child = $row;
            $child['path'] = $childKey;
            $child['displayPath'] = (string) ($row['alias'] ?? '');
            $child['children'] = [];
            $parents[$parentKey]['children'][] = $child;
        }

        $tree = array_values($parents);

        usort($tree, function ($a, $b) {
            return strcasecmp((string) $a['title'], (string) $b['title']);
        });

        foreach ($tree as &$parent) {
            if (!empty($parent['children'])) {
                usort($parent['children'], function ($a, $b) {
                    return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
                });
            }
        }
        unset($parent);

        return $tree;
    }

    private static function buildParentTree(array $rows): array
    {
        $nodes = [];
        $root = [];

        foreach ($rows as $row) {
            $nodeId = (string) ($row['node_id'] ?? $row['id'] ?? '');
            if ($nodeId === '') {
                continue;
            }
            $nodes[$nodeId] = $row + ['children' => []];
        }

        foreach ($nodes as $nodeId => $node) {
            $parentId = (string) ($node['parent_id'] ?? '');
            if ($parentId !== '' && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = $nodeId;
            } else {
                $root[] = $nodeId;
            }
        }

        $walk = function ($nodeId) use (&$walk, &$nodes) {
            $node = $nodes[$nodeId];
            $children = [];
            foreach ($node['children'] as $childId) {
                $children[] = $walk($childId);
            }
            $node['children'] = $children;
            return $node;
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
            foreach (['description','params','metadata','metadesc','metakey','note'] as $field) {
                $dbVal = (string) ($existing->$field ?? '');
                $xmlVal = (string) ($node->$field ?? '');
                if (($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') && $xmlVal !== '') {
                    $canSupplement = true;
                    break;
                }
            }

            if ($titleChanged || $canSupplement) {
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => $path,
                    'title' => (string) $node->title,
                    'action' => 'changed',
                    'reason' => '',
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

    private static function previewPhocaGalleryTags($db, $tags, array &$out): void
    {
        try {
            $columns = $db->getTableColumns('#__phocagallery_categories', false);
        } catch (Throwable $e) {
            $columns = [];
        }

        if (empty($columns)) {
            $out['warnings'][] = 'Phoca Gallery tags table not available.';
            $out['hasWarnings'] = true;
            return;
        }

        $entries = [];
        if (isset($tags->tag)) {
            $entries = $tags->tag;
        } elseif (isset($tags->category)) {
            $entries = $tags->category;
        }

        foreach ($entries as $node) {
            $id = (int) ($node->id ?? 0);
            $alias = trim((string) ($node->alias ?? ''));
            $title = (string) ($node->title ?? $alias);
            $key = $alias !== '' ? $alias : (string) $id;

            if ($key === '') {
                $out['warnings'][] = 'Phoca Gallery tag without alias or id skipped';
                $out['phocaTags'][] = [
                    'type' => 'phoca_tag',
                    'id' => '',
                    'title' => $title,
                    'action' => 'skipped',
                    'reason' => 'Missing alias or id',
                    'exclude' => true
                ];
                continue;
            }

            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__phocagallery_categories');
            if ($alias !== '') {
                $query->where('alias=' . $db->quote($alias));
            } else {
                $query->where('id=' . (int) $id);
            }

            $existing = $db->setQuery($query)->loadObject();

            if (!$existing) {
                $out['phocaTags'][] = [
                    'type' => 'phoca_tag',
                    'id' => $key,
                    'title' => $title,
                    'action' => 'new',
                    'reason' => '',
                    'exclude' => false,
                    'path' => $key,
                    'displayPath' => $alias !== '' ? $alias : (string) $id
                ];
                continue;
            }

            $fields = [
                'title' => (string) ($node->title ?? ''),
                'alias' => $alias,
                'description' => (string) ($node->description ?? ''),
                'params' => (string) ($node->params ?? ''),
                'metadata' => (string) ($node->metadata ?? ''),
                'published' => (string) ($node->published ?? ''),
                'access' => (string) ($node->access ?? ''),
                'language' => (string) ($node->language ?? ''),
                'ordering' => (string) ($node->ordering ?? ''),
            ];

            $hasChanges = false;
            foreach ($fields as $field => $value) {
                if (!isset($columns[$field]) || !property_exists($existing, $field)) {
                    continue;
                }
                $dbVal = (string) ($existing->$field ?? '');
                if ($dbVal !== $value && $value !== '') {
                    $hasChanges = true;
                    break;
                }
            }

            if ($hasChanges) {
                $out['phocaTags'][] = [
                    'type' => 'phoca_tag',
                    'id' => $key,
                    'title' => $title,
                    'action' => 'changed',
                    'reason' => '',
                    'exclude' => false,
                    'path' => $key,
                    'displayPath' => $alias !== '' ? $alias : (string) $id
                ];
            } else {
                $out['phocaTags'][] = [
                    'type' => 'phoca_tag',
                    'id' => $key,
                    'title' => $title,
                    'action' => 'skipped',
                    'reason' => 'No changes detected',
                    'exclude' => true,
                    'path' => $key,
                    'displayPath' => $alias !== '' ? $alias : (string) $id
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
                    'id' => '',
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
                    'id' => $alias,
                    'title' => $title,
                    'action' => 'new',
                    'reason' => '',
                    'exclude' => false
                ];
                continue;
            }

            $titleChanged = trim((string) $node->title) !== (string) ($existing->title ?? '');
            $canSupplement = false;
            foreach (['description','params','metadata'] as $field) {
                $dbVal = (string) ($existing->$field ?? '');
                $xmlVal = (string) ($node->$field ?? '');
                if (($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') && $xmlVal !== '') {
                    $canSupplement = true;
                    break;
                }
            }

            if ($titleChanged || $canSupplement) {
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => $alias,
                    'title' => $title,
                    'action' => 'changed',
                    'reason' => '',
                    'exclude' => false
                ];
            } else {
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => $alias,
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
            foreach ($fields as $field => $value) {
                if (!property_exists($existing, $field)) {
                    continue;
                }
                $dbVal = (string) ($existing->$field ?? '');
                if ($dbVal !== $value) {
                    $hasChanges = true;
                    break;
                }
            }

            if ($hasChanges) {
                $out['articles'][] = [
                    'type' => 'article',
                    'id' => $key,
                    'title' => $title,
                    'alias' => $alias,
                    'catid' => $catid,
                    'action' => 'changed',
                    'reason' => '',
                    'exclude' => false
                ];
            } else {
                $out['articles'][] = [
                    'type' => 'article',
                    'id' => $key,
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
}
