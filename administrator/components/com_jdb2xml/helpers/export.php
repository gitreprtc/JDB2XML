<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class Jdb2xmlExportHelper
{
    public static function run(string $dir): string
    {
        $db = Factory::getDbo();
        $ts = date('Ymd_His');

        // =====================
        // CATEGORIES
        // =====================
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
            throw new RuntimeException('XML generatie mislukt (categories)');
        }
        if (file_put_contents($catFile, $xmlString) === false) {
            throw new RuntimeException('Kan categorie XML niet schrijven');
        }

        // =====================
        // TAGS
        // =====================
        $query = $db->getQuery(true)
            ->select('*')->from('#__tags')
            ->order('title ASC');

        $tags = $db->setQuery($query)->loadObjectList();

        $tagFile = $dir . '/tags_' . $ts . '.xml';
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tags/>');

        foreach ($tags as $t) {
            $n = $xml->addChild('tag');
            $n->addChild('id', (int) $t->id);
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
            throw new RuntimeException('XML generatie mislukt (tags)');
        }
        if (file_put_contents($tagFile, $xmlString) === false) {
            throw new RuntimeException('Kan tag XML niet schrijven');
        }

        return 'Export voltooid (volledig): '
            . basename($catFile) . ' & ' . basename($tagFile);
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
