<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/ImportPlan.php';

class XmlcatagImportPreviewHelper
{
    public static function run(string $dir, ?string $selectedFile = null): array
    {
        if (!is_dir($dir)) {
            throw new RuntimeException('Import map bestaat niet: ' . $dir);
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
                'warnings' => [],
                'hasWarnings' => false
            ];

            $xmlString = file_get_contents($file);
            if (!$xmlString) {
                $result[$fileKey]['warnings'][] = 'Bestand is leeg of niet leesbaar';
                $result[$fileKey]['hasWarnings'] = true;
                continue;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlString);
            if (!$xml) {
                $result[$fileKey]['warnings'][] = 'Ongeldige XML';
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

            // Build a tree from category paths
            $result[$fileKey]['categoryTree'] = self::buildTree($result[$fileKey]['categories']);

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
                $out['warnings'][] = 'Categorie zonder path overgeslagen';
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => '',
                    'title' => (string) $node->title,
                    'action' => 'overgeslagen',
                    'reason' => 'Ontbrekende path',
                    'exclude' => true
                ];
                continue;
            }

            $query = $db->getQuery(true)
                ->select('id, description, params, metadata, metadesc, metakey, note')
                ->from('#__categories')
                ->where('path=' . $db->quote($path))
                ->where('extension=' . $db->quote($extension));

            $existing = $db->setQuery($query)->loadObject();

            if (!$existing) {
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => $path,
                    'title' => (string) $node->title,
                    'action' => 'nieuw',
                    'reason' => '',
                    'exclude' => false
                ];
                continue;
            }

            $canSupplement = false;
            foreach (['description','params','metadata','metadesc','metakey','note'] as $field) {
                $dbVal = (string) ($existing->$field ?? '');
                $xmlVal = (string) ($node->$field ?? '');
                if (($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') && $xmlVal !== '') {
                    $canSupplement = true;
                    break;
                }
            }

            if ($canSupplement) {
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => $path,
                    'title' => (string) $node->title,
                    'action' => 'aanvullen',
                    'reason' => '',
                    'exclude' => false
                ];
            } else {
                $out['categories'][] = [
                    'type' => 'category',
                    'id' => $path,
                    'title' => (string) $node->title,
                    'action' => 'overgeslagen',
                    'reason' => 'Geen lege velden om aan te vullen',
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
                $out['warnings'][] = 'Tag zonder alias overgeslagen';
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => '',
                    'title' => $title,
                    'action' => 'overgeslagen',
                    'reason' => 'Ontbrekende alias',
                    'exclude' => true
                ];
                continue;
            }

            $query = $db->getQuery(true)
                ->select('id, description, params, metadata')
                ->from('#__tags')
                ->where('alias=' . $db->quote($alias));

            $existing = $db->setQuery($query)->loadObject();

            if (!$existing) {
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => $alias,
                    'title' => $title,
                    'action' => 'nieuw',
                    'reason' => '',
                    'exclude' => false
                ];
                continue;
            }

            $canSupplement = false;
            foreach (['description','params','metadata'] as $field) {
                $dbVal = (string) ($existing->$field ?? '');
                $xmlVal = (string) ($node->$field ?? '');
                if (($dbVal === '' || $dbVal === '{}' || $dbVal === '[]') && $xmlVal !== '') {
                    $canSupplement = true;
                    break;
                }
            }

            if ($canSupplement) {
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => $alias,
                    'title' => $title,
                    'action' => 'aanvullen',
                    'reason' => '',
                    'exclude' => false
                ];
            } else {
                $out['tags'][] = [
                    'type' => 'tag',
                    'id' => $alias,
                    'title' => $title,
                    'action' => 'overgeslagen',
                    'reason' => 'Geen lege velden om aan te vullen',
                    'exclude' => true
                ];
            }
        }
    }
}
