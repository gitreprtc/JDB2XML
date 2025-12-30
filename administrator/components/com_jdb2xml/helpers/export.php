<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class Jdb2xmlExportHelper
{
    public static function run(string $dir): string
    {
        return self::runType($dir, 'all');
    }

    public static function runCsv(string $dir): string
    {
        return self::runCsvType($dir, 'all');
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
        // MENUS
        // =====================
        if ($type === 'all' || $type === 'menus') {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__menu')
                ->where('client_id = 0')
                ->order('lft ASC');

            $menuItems = $db->setQuery($query)->loadObjectList();

            $menuFile = $dir . '/menus_' . $ts . '.xml';

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><menus/>');

            foreach ($menuItems as $item) {
                $n = $xml->addChild('menuitem');

                $n->addChild('id', (int) $item->id);
                $n->addChild('menutype', htmlspecialchars((string) ($item->menutype ?? '')));
                $n->addChild('parent_id', (int) $item->parent_id);
                $n->addChild('level', (int) $item->level);
                $n->addChild('path', $item->path);

                $n->addChild('title', htmlspecialchars((string) ($item->title ?? '')));
                $n->addChild('alias', htmlspecialchars((string) ($item->alias ?? '')));
                $n->addChild('link', htmlspecialchars((string) ($item->link ?? '')));
                $n->addChild('type', htmlspecialchars((string) ($item->type ?? '')));

                $n->addChild('published', (int) ($item->published ?? 1));
                $n->addChild('access', (int) ($item->access ?? 1));
                $n->addChild('language', (string) ($item->language ?? '*'));

                self::addCdata($n, 'params', (string) ($item->params ?? ''));
                self::addCdata($n, 'note', (string) ($item->note ?? ''));
            }

            $xmlString = self::formatXml($xml);
            if ($xmlString === false) {
                throw new RuntimeException('XML generation failed (menus)');
            }
            if (file_put_contents($menuFile, $xmlString) === false) {
                throw new RuntimeException('Cannot write menus XML');
            }
            $messages[] = basename($menuFile);
        }

        // =====================
        // PHOCA GALLERY TAGS
        // =====================
        if ($type === 'all' || $type === 'phocagallerytags') {
            if (!$phocaAvailable) {
                if ($type === 'phocagallerytags') {
                    return 'Phoca Gallery tags not available; no export generated.';
                }
            } else {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__phocagallery_categories')
                ->order('ordering ASC, title ASC');

            $tags = $db->setQuery($query)->loadObjectList();

            $tagFile = $dir . '/phocagallery_tags_' . $ts . '.xml';
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><phocagallerytags/>');

            foreach ($tags as $t) {
                $n = $xml->addChild('tag');

                $title = $t->title ?? $t->name ?? '';
                $alias = $t->alias ?? $t->name ?? '';

                $n->addChild('id', (int) ($t->id ?? 0));
                $n->addChild('title', htmlspecialchars((string) $title));
                $n->addChild('alias', htmlspecialchars((string) $alias));
                $n->addChild('ordering', (int) ($t->ordering ?? 0));
                $n->addChild('published', (int) ($t->published ?? 1));
                $n->addChild('access', (int) ($t->access ?? 1));
                $n->addChild('accessuserid', (int) ($t->accessuserid ?? 0));
                $n->addChild('uploaduserid', (int) ($t->uploaduserid ?? 0));
                $n->addChild('deleteuserid', (int) ($t->deleteuserid ?? 0));
                $n->addChild('language', (string) ($t->language ?? '*'));

                self::addCdata($n, 'description', (string) ($t->description ?? ''));
                self::addCdata($n, 'params', (string) ($t->params ?? ''));
                self::addCdata($n, 'metadata', (string) ($t->metadata ?? ''));
            }

            $xmlString = self::formatXml($xml);
            if ($xmlString === false) {
                throw new RuntimeException('XML generation failed (phoca gallery tags)');
            }
            if (file_put_contents($tagFile, $xmlString) === false) {
                throw new RuntimeException('Cannot write phoca gallery tags XML');
            }
            $messages[] = basename($tagFile);
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

    public static function runCsvType(string $dir, string $type): string
    {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $db = Factory::getDbo();
        $ts = date('Ymd_His');
        $messages = [];
        $phocaAvailable = self::isPhocaAvailable($db);

        // =====================
        // CATEGORIES (CSV)
        // =====================
        if ($type === 'all' || $type === 'categories') {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__categories')
                ->order('lft ASC');

            $cats = $db->setQuery($query)->loadObjectList();

            $rows = [];
            $maxDepth = 0;
            $titleMap = [];
            foreach ($cats as $c) {
                if ((int) ($c->level ?? 0) < 1) {
                    continue;
                }
                $titleMap[$c->path] = $c->title ?? $c->alias ?? $c->path;
            }

            foreach ($cats as $c) {
                if ((int) ($c->level ?? 0) < 1) {
                    continue;
                }
                $segments = array_filter(explode('/', (string) $c->path), static function ($seg) {
                    return $seg !== '';
                });
                $maxDepth = max($maxDepth, count($segments));
                $pathParts = [];
                $row = [(string) ($c->id ?? '')];
                foreach ($segments as $seg) {
                    $pathParts[] = $seg;
                    $path = implode('/', $pathParts);
                    $row[] = $titleMap[$path] ?? str_replace('-', ' ', $seg);
                }
                $rows[] = $row;
            }

            $headers = ['id'];
            for ($i = 1; $i <= $maxDepth; $i++) {
                $headers[] = 'niveau_' . $i;
            }

            $catFile = $dir . '/categories_' . $ts . '.csv';
            $fh = fopen($catFile, 'wb');
            if ($fh === false) {
                throw new RuntimeException('Cannot create categories CSV file');
            }

            if ($headers) {
                fputcsv($fh, $headers);
            }

            foreach ($rows as $row) {
                $row = array_pad($row, count($headers), '');
                fputcsv($fh, $row);
            }

            fclose($fh);
            $messages[] = basename($catFile);
        }

        // =====================
        // MENUS (CSV)
        // =====================
        if ($type === 'all' || $type === 'menus') {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__menu')
                ->where('client_id = 0')
                ->order('lft ASC');

            $menuItems = $db->setQuery($query)->loadObjectList();

            $titleMap = [];
            foreach ($menuItems as $item) {
                if ((int) ($item->level ?? 0) < 1) {
                    continue;
                }
                $key = $item->menutype . '|' . $item->path;
                $titleMap[$key] = $item->title ?? $item->alias ?? $item->path;
            }

            $rows = [];
            $maxDepth = 0;
            foreach ($menuItems as $item) {
                if ((int) ($item->level ?? 0) < 1) {
                    continue;
                }
                $segments = array_filter(explode('/', (string) $item->path), static function ($seg) {
                    return $seg !== '';
                });
                $maxDepth = max($maxDepth, count($segments));
                $pathParts = [];
                $levels = [];
                foreach ($segments as $seg) {
                    $pathParts[] = $seg;
                    $path = implode('/', $pathParts);
                    $key = $item->menutype . '|' . $path;
                    $levels[] = $titleMap[$key] ?? str_replace('-', ' ', $seg);
                }

                $rows[] = array_merge([
                    (string) ($item->id ?? ''),
                    $item->menutype ?? 'mainmenu',
                ], $levels, [
                    $item->link ?? '',
                    $item->type ?? '',
                    (string) ($item->published ?? 1),
                    (string) ($item->access ?? 1),
                    (string) ($item->language ?? '*'),
                    $item->note ?? '',
                    (string) ($item->params ?? ''),
                ]);
            }

            $headers = ['id', 'menutype'];
            for ($i = 1; $i <= $maxDepth; $i++) {
                $headers[] = 'niveau_' . $i;
            }
            $headers = array_merge($headers, ['link', 'type', 'published', 'access', 'language', 'note', 'params']);

            $menuFile = $dir . '/menus_' . $ts . '.csv';
            $fh = fopen($menuFile, 'wb');
            if ($fh === false) {
                throw new RuntimeException('Cannot create menus CSV file');
            }
            fputcsv($fh, $headers);
            foreach ($rows as $row) {
                $row = array_pad($row, count($headers), '');
                fputcsv($fh, $row);
            }
            fclose($fh);
            $messages[] = basename($menuFile);
        }

        // =====================
        // PHOCA GALLERY TAGS (CSV)
        // =====================
        if ($type === 'all' || $type === 'phocagallerytags') {
            if (!$phocaAvailable) {
                if ($type === 'phocagallerytags') {
                    return 'Phoca Gallery tags not available; no export generated.';
                }
            } else {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from('#__phocagallery_categories')
                    ->order('ordering ASC, title ASC');

                $tags = $db->setQuery($query)->loadObjectList();
                $tagFile = $dir . '/phocagallery_tags_' . $ts . '.csv';
                $fh = fopen($tagFile, 'wb');
                if ($fh === false) {
                    throw new RuntimeException('Cannot create Phoca Gallery tags CSV file');
                }
                $headers = ['id', 'title', 'alias', 'description', 'published', 'access', 'language'];
                fputcsv($fh, $headers);
                foreach ($tags as $t) {
                    fputcsv($fh, [
                        (string) ($t->id ?? ''),
                        $t->title ?? $t->name ?? '',
                        $t->alias ?? $t->name ?? '',
                        $t->description ?? '',
                        (string) ($t->published ?? 1),
                        (string) ($t->access ?? 1),
                        (string) ($t->language ?? '*'),
                    ]);
                }
                fclose($fh);
                $messages[] = basename($tagFile);
            }
        }

        // =====================
        // TAGS (CSV)
        // =====================
        if ($type === 'all' || $type === 'tags') {
            $query = $db->getQuery(true)
                ->select('*')->from('#__tags')
                ->order('title ASC');

            $tags = $db->setQuery($query)->loadObjectList();

            $tagFile = $dir . '/tags_' . $ts . '.csv';
            $fh = fopen($tagFile, 'wb');
            if ($fh === false) {
                throw new RuntimeException('Cannot create tags CSV file');
            }
            fputcsv($fh, ['id', 'title', 'metadata']);
            foreach ($tags as $t) {
                fputcsv($fh, [
                    (string) ($t->id ?? ''),
                    $t->title ?? '',
                    $t->metadata ?? '',
                ]);
            }
            fclose($fh);
            $messages[] = basename($tagFile);
        }

        // =====================
        // ARTICLES (CSV)
        // =====================
        if ($type === 'all' || $type === 'articles') {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__content')
                ->order('id ASC');

            $articles = $db->setQuery($query)->loadObjectList();

            $articleFile = $dir . '/articles_' . $ts . '.csv';
            $fh = fopen($articleFile, 'wb');
            if ($fh === false) {
                throw new RuntimeException('Cannot create articles CSV file');
            }
            $headers = [
                'id', 'catid', 'title', 'alias', 'introtext', 'fulltext', 'state', 'access', 'language',
                'created_by', 'created_by_alias', 'modified_by', 'ordering', 'featured', 'hits', 'images',
                'urls', 'attribs', 'metadata', 'metadesc', 'metakey', 'note',
            ];
            fputcsv($fh, $headers);
            foreach ($articles as $a) {
                fputcsv($fh, [
                    (string) ($a->id ?? ''),
                    (string) ($a->catid ?? ''),
                    $a->title ?? '',
                    $a->alias ?? '',
                    $a->introtext ?? '',
                    $a->fulltext ?? '',
                    (string) ($a->state ?? 0),
                    (string) ($a->access ?? 1),
                    (string) ($a->language ?? '*'),
                    (string) ($a->created_by ?? ''),
                    $a->created_by_alias ?? '',
                    (string) ($a->modified_by ?? ''),
                    (string) ($a->ordering ?? 0),
                    (string) ($a->featured ?? 0),
                    (string) ($a->hits ?? 0),
                    $a->images ?? '',
                    $a->urls ?? '',
                    $a->attribs ?? '',
                    $a->metadata ?? '',
                    $a->metadesc ?? '',
                    $a->metakey ?? '',
                    $a->note ?? '',
                ]);
            }
            fclose($fh);
            $messages[] = basename($articleFile);
        }

        $suffix = $type === 'all' ? 'full-csv' : $type . '-csv';
        return 'CSV export completed (' . $suffix . '): ' . implode(' & ', $messages);
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
