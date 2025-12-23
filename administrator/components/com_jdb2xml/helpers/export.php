<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Throwable;

class Jdb2xmlExportHelper
{
    public static function run(string $dir): string
    {
        return self::runType($dir, 'all');
    }

    public static function runType(string $dir, string $type): string
    {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $db = Factory::getDbo();
        $ts = date('Ymd_His');
        $messages = [];
        $phocaAvailable = self::isPhocaAvailable($db);

        // =====================
        // CATEGORIES
        // =====================
        if ($type === 'all' || $type === 'categories') {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__categories')
                ->order('lft ASC');

            $cats = $db->setQuery($query)->loadObjectList();

            $catFile = $dir . '/categories_' . $ts . '.xml';

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><categories/>');
            $xml->addAttribute('extension', 'com_content');

            foreach ($cats as $c) {
                $n = $xml->addChild('category');

                // structure
                $n->addChild('id', (int) $c->id);
                $n->addChild('parent_id', (int) $c->parent_id);
                $n->addChild('level', (int) $c->level);
                $n->addChild('path', $c->path);

                // content
                $n->addChild('title', htmlspecialchars($c->title));
                $n->addChild('alias', htmlspecialchars($c->alias));

                self::addCdata($n, 'description', $c->description);

                // state
                $n->addChild('published', (int) $c->published);
                $n->addChild('access', (int) $c->access);
                $n->addChild('language', $c->language);

                // params & metadata
                foreach (['params','metadata','metadesc','metakey','note'] as $field) {
                    self::addCdata($n, $field, $c->$field ?? '');
                }

                // audit
                $n->addChild('created_user_id', (int) $c->created_user_id);
                $n->addChild('created_time', $c->created_time);
                $n->addChild('modified_user_id', (int) $c->modified_user_id);
                $n->addChild('modified_time', $c->modified_time);
            }

            $xmlString = self::formatXml($xml);
            if ($xmlString === false) {
                throw new RuntimeException('XML generation failed (categories)');
            }
            if (file_put_contents($catFile, $xmlString) === false) {
                throw new RuntimeException('Cannot write categories XML');
            }
            $messages[] = basename($catFile);
        }

        // =====================
        // PHOCA GALLERY CATEGORIES
        // =====================
        if ($type === 'all' || $type === 'phocagallerycategories') {
            if (!$phocaAvailable) {
                if ($type === 'phocagallerycategories') {
                    return 'Phoca Gallery not available; no export generated.';
                }
            } else {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__phocagallery_categories')
                ->order('parent_id ASC, ordering ASC, title ASC');

            $cats = $db->setQuery($query)->loadObjectList();

            $catFile = $dir . '/phocagallery_categories_' . $ts . '.xml';
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><phocagallerycategories/>');

            foreach ($cats as $c) {
                $n = $xml->addChild('category');

                $title = $c->title ?? $c->name ?? '';
                $alias = $c->alias ?? $c->name ?? '';

                $n->addChild('id', (int) ($c->id ?? 0));
                $n->addChild('parent_id', (int) ($c->parent_id ?? 0));
                $n->addChild('title', htmlspecialchars((string) $title));
                $n->addChild('alias', htmlspecialchars((string) $alias));
                $n->addChild('ordering', (int) ($c->ordering ?? 0));
                $n->addChild('published', (int) ($c->published ?? 1));
                $n->addChild('access', (int) ($c->access ?? 1));
                $n->addChild('language', (string) ($c->language ?? '*'));

                self::addCdata($n, 'description', (string) ($c->description ?? ''));
                self::addCdata($n, 'params', (string) ($c->params ?? ''));
                self::addCdata($n, 'metadata', (string) ($c->metadata ?? ''));
            }

            $xmlString = self::formatXml($xml);
            if ($xmlString === false) {
                throw new RuntimeException('XML generation failed (phoca gallery categories)');
            }
            if (file_put_contents($catFile, $xmlString) === false) {
                throw new RuntimeException('Cannot write phoca gallery categories XML');
            }
            $messages[] = basename($catFile);
            }
        }

        // =====================
        // TAGS
        // =====================
        if ($type === 'all' || $type === 'tags') {
            $query = $db->getQuery(true)
                ->select('*')->from('#__tags')
                ->order('title ASC');

            $tags = $db->setQuery($query)->loadObjectList();

            $tagFile = $dir . '/tags_' . $ts . '.xml';
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tags/>');

            foreach ($tags as $t) {
                $n = $xml->addChild('tag');
                $n->addChild('id', (int) $t->id);
                $n->addChild('parent_id', (int) ($t->parent_id ?? 1));
                $n->addChild('title', htmlspecialchars($t->title));
                $n->addChild('alias', htmlspecialchars($t->alias));
                self::addCdata($n, 'description', $t->description);
                foreach (['params','metadata'] as $field) {
                    self::addCdata($n, $field, $t->$field ?? '');
                }
                $n->addChild('published', (int) $t->published);
                $n->addChild('access', (int) $t->access);
                $n->addChild('language', $t->language);
            }

            $xmlString = self::formatXml($xml);
            if ($xmlString === false) {
                throw new RuntimeException('XML generation failed (tags)');
            }
            if (file_put_contents($tagFile, $xmlString) === false) {
                throw new RuntimeException('Cannot write tags XML');
            }
            $messages[] = basename($tagFile);
        }

        // =====================
        // ARTICLES
        // =====================
        if ($type === 'all' || $type === 'articles') {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__content')
                ->order('id ASC');

            $articles = $db->setQuery($query)->loadObjectList();

            $articleFile = $dir . '/articles_' . $ts . '.xml';
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><articles/>');

            foreach ($articles as $a) {
                $n = $xml->addChild('article');
                $n->addChild('id', (int) $a->id);
                $n->addChild('catid', (int) $a->catid);
                $n->addChild('title', htmlspecialchars($a->title));
                $n->addChild('alias', htmlspecialchars($a->alias));
                self::addCdata($n, 'introtext', $a->introtext ?? '');
                self::addCdata($n, 'fulltext', $a->fulltext ?? '');
                $n->addChild('state', (int) $a->state);
                $n->addChild('access', (int) $a->access);
                $n->addChild('language', $a->language);
                $n->addChild('created', $a->created);
                $n->addChild('created_by', (int) $a->created_by);
                $n->addChild('created_by_alias', htmlspecialchars($a->created_by_alias ?? ''));
                $n->addChild('modified', $a->modified);
                $n->addChild('modified_by', (int) $a->modified_by);
                $n->addChild('publish_up', $a->publish_up);
                $n->addChild('publish_down', $a->publish_down);
                $n->addChild('ordering', (int) $a->ordering);
                $n->addChild('featured', (int) $a->featured);
                $n->addChild('hits', (int) $a->hits);
                foreach (['images','urls','attribs','metadata','metadesc','metakey','note'] as $field) {
                    self::addCdata($n, $field, $a->$field ?? '');
                }
            }

            $xmlString = self::formatXml($xml);
            if ($xmlString === false) {
                throw new RuntimeException('XML generation failed (articles)');
            }
            if (file_put_contents($articleFile, $xmlString) === false) {
                throw new RuntimeException('Cannot write articles XML');
            }
            $messages[] = basename($articleFile);
        }

        $suffix = $type === 'all' ? 'full' : $type;
        return 'Export completed (' . $suffix . '): ' . implode(' & ', $messages);
    }

    private static function isPhocaAvailable($db): bool
    {
        try {
            $columns = $db->getTableColumns('#__phocagallery_categories', false);
        } catch (Throwable $e) {
            return false;
        }

        return !empty($columns);
    }

    public static function logBatch(string $type, bool $success, ?string $error = null): void
    {
        $logFile = JPATH_ADMINISTRATOR . '/logs/jdb2xml_export_batches.json';
        $entries = [];
        if (is_file($logFile)) {
            $data = json_decode(file_get_contents($logFile), true);
            if (is_array($data)) {
                $entries = $data;
            }
        }

        $entries[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'status' => $success ? 'success' : 'failed',
            'error' => $error,
        ];

        $entries = array_slice($entries, -10);
        file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function addCdata(SimpleXMLElement $node, string $name, string $value): void
    {
        $child = $node->addChild($name);
        $dom = dom_import_simplexml($child);
        $owner = $dom->ownerDocument;
        $dom->appendChild($owner->createCDATASection($value));
    }

    private static function formatXml(SimpleXMLElement $xml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }
}
