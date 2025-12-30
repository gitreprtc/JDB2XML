<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Filter\OutputFilter;

class Jdb2xmlTagConversionHelper
{
    public static function convertCsvToTagsXml(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            throw new RuntimeException('CSV file not found.');
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        $delimiter = ',';
        $tags = [];
        $order = [];

        $firstRow = fgetcsv($handle, 0, $delimiter);
        if ($firstRow !== false && count($firstRow) === 1 && strpos((string) $firstRow[0], ';') !== false) {
            $delimiter = ';';
            $firstRow = str_getcsv((string) $firstRow[0], $delimiter);
        }

        $idIndex = null;
        if ($firstRow !== false) {
            $normalizedFirst = array_map('mb_strtolower', array_map('trim', $firstRow));
            if (!empty($normalizedFirst)) {
                $normalizedFirst[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $normalizedFirst[0]);
            }

            if (self::isHeaderRow($normalizedFirst, ['title', 'tag', 'tags', 'metadata', 'metakey', 'meta', 'id'])) {
                $idIndex = array_search('id', $normalizedFirst, true);
            } else {
                self::consumeRow($firstRow, $tags, $order);
            }
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($idIndex !== null && array_key_exists($idIndex, $row)) {
                unset($row[$idIndex]);
                $row = array_values($row);
            }
            self::consumeRow($row, $tags, $order);
        }

        fclose($handle);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('tags');
        $dom->appendChild($root);

        $id = 1;
        foreach ($order as $title) {
            $entry = $tags[$title] ?? null;
            if (!$entry) {
                continue;
            }

            $tagNode = $dom->createElement('tag');

            $tagNode->appendChild($dom->createElement('id', (string) $id));
            $tagNode->appendChild($dom->createElement('title', $entry['title']));
            $tagNode->appendChild($dom->createElement('alias', $entry['alias']));

            $description = $dom->createElement('description');
            $description->appendChild($dom->createCDATASection(''));
            $tagNode->appendChild($description);

            $params = $dom->createElement('params');
            $params->appendChild($dom->createCDATASection('{"tag_layout":"","tag_link_class":""}'));
            $tagNode->appendChild($params);

            $metadata = $dom->createElement('metadata');
            $metadata->appendChild($dom->createCDATASection($entry['metadata']));
            $tagNode->appendChild($metadata);

            $tagNode->appendChild($dom->createElement('published', '1'));
            $tagNode->appendChild($dom->createElement('access', '1'));
            $tagNode->appendChild($dom->createElement('language', '*'));

            $root->appendChild($tagNode);
            $id++;
        }

        return [
            'xml' => $dom->saveXML(),
            'count' => $id - 1,
        ];
    }

    public static function convertCsvToPhocaGalleryTagsXml(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            throw new RuntimeException('CSV file not found.');
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        $delimiter = ',';
        $firstRow = fgetcsv($handle, 0, $delimiter);
        if ($firstRow !== false && count($firstRow) === 1 && strpos((string) $firstRow[0], ';') !== false) {
            $delimiter = ';';
            $firstRow = str_getcsv((string) $firstRow[0], $delimiter);
        }

        if ($firstRow === false) {
            fclose($handle);
            return ['xml' => '', 'count' => 0];
        }

        $headers = array_map('trim', $firstRow);
        if (!empty($headers)) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        }
        $headers = array_map('mb_strtolower', $headers);

        $idIndex = array_search('id', $headers, true);
        $hasHeader = self::isHeaderRow($headers, ['title', 'alias', 'description', 'published', 'access', 'language', 'id']);
        if (!$hasHeader) {
            $rows = [$firstRow];
            $headers = ['title', 'alias', 'description'];
            $idIndex = null;
        } else {
            $rows = [];
        }

        if ($idIndex !== null) {
            unset($headers[$idIndex]);
            $headers = array_values($headers);
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && $delimiter === ';' && strpos((string) $row[0], ';') !== false) {
                $row = str_getcsv((string) $row[0], $delimiter);
            }
            if ($idIndex !== null && array_key_exists($idIndex, $row)) {
                unset($row[$idIndex]);
                $row = array_values($row);
            }
            $rows[] = $row;
        }
        fclose($handle);

        $headerMap = [];
        foreach ($headers as $index => $header) {
            $key = trim((string) $header);
            if ($key !== '') {
                $headerMap[$key] = $index;
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('phocagallerytags');
        $dom->appendChild($root);

        $id = 1;
        foreach ($rows as $row) {
            $title = trim((string) ($row[$headerMap['title'] ?? 0] ?? ''));
            $alias = trim((string) ($row[$headerMap['alias'] ?? 1] ?? ''));
            $description = trim((string) ($row[$headerMap['description'] ?? 2] ?? ''));
            $published = isset($headerMap['published']) ? trim((string) ($row[$headerMap['published']] ?? '')) : '';
            $access = isset($headerMap['access']) ? trim((string) ($row[$headerMap['access']] ?? '')) : '';
            $language = isset($headerMap['language']) ? trim((string) ($row[$headerMap['language']] ?? '')) : '';

            if ($title === '' && $alias === '') {
                continue;
            }

            if ($alias === '') {
                $alias = self::slugify($title);
            }

            $tagNode = $dom->createElement('tag');
            $tagNode->appendChild($dom->createElement('id', (string) $id));
            $tagNode->appendChild($dom->createElement('title', $title !== '' ? $title : $alias));
            $tagNode->appendChild($dom->createElement('alias', $alias));

            $descriptionNode = $dom->createElement('description');
            $descriptionNode->appendChild($dom->createCDATASection($description));
            $tagNode->appendChild($descriptionNode);

            if ($published !== '') {
                $tagNode->appendChild($dom->createElement('published', $published));
            }
            if ($access !== '') {
                $tagNode->appendChild($dom->createElement('access', $access));
            }
            if ($language !== '') {
                $tagNode->appendChild($dom->createElement('language', $language));
            }

            $root->appendChild($tagNode);
            $id++;
        }

        return [
            'xml' => $dom->saveXML(),
            'count' => $id - 1,
        ];
    }

    public static function convertCsvToCategoriesXml(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            throw new RuntimeException('CSV file not found.');
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        $delimiter = ',';
        $categories = [];
        $order = [];

        $firstRow = fgetcsv($handle, 0, $delimiter);
        if ($firstRow !== false && count($firstRow) === 1 && strpos((string) $firstRow[0], ';') !== false) {
            $delimiter = ';';
            $firstRow = str_getcsv((string) $firstRow[0], $delimiter);
        }

        $skipIdIndex = null;
        if ($firstRow !== false) {
            $normalizedFirst = array_map('mb_strtolower', array_map('trim', $firstRow));
            if (!empty($normalizedFirst)) {
                $normalizedFirst[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $normalizedFirst[0]);
            }

            if (!self::isHeaderRow($normalizedFirst, ['categorie', 'categories', 'category', 'level', 'niveau', 'path', 'title', 'alias', 'id'])) {
                self::consumeCategoryRow($firstRow, $categories, $order);
            } else {
                $skipIdIndex = array_search('id', $normalizedFirst, true);
            }
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($skipIdIndex !== null && array_key_exists($skipIdIndex, $row)) {
                unset($row[$skipIdIndex]);
                $row = array_values($row);
            }
            self::consumeCategoryRow($row, $categories, $order);
        }

        fclose($handle);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('categories');
        $root->setAttribute('extension', 'com_content');
        $dom->appendChild($root);

        $id = 1;
        foreach ($order as $path) {
            $entry = $categories[$path] ?? null;
            if (!$entry) {
                continue;
            }

            $node = $dom->createElement('category');
            $node->appendChild($dom->createElement('id', (string) $id));
            $node->appendChild($dom->createElement('path', $entry['path']));
            $node->appendChild($dom->createElement('title', $entry['title']));
            $node->appendChild($dom->createElement('alias', $entry['alias']));

            $description = $dom->createElement('description');
            $description->appendChild($dom->createCDATASection(''));
            $node->appendChild($description);

            $params = $dom->createElement('params');
            $params->appendChild($dom->createCDATASection(''));
            $node->appendChild($params);

            $metadata = $dom->createElement('metadata');
            $metadata->appendChild($dom->createCDATASection(''));
            $node->appendChild($metadata);

            $metadesc = $dom->createElement('metadesc');
            $metadesc->appendChild($dom->createCDATASection(''));
            $node->appendChild($metadesc);

            $metakey = $dom->createElement('metakey');
            $metakey->appendChild($dom->createCDATASection(''));
            $node->appendChild($metakey);

            $note = $dom->createElement('note');
            $note->appendChild($dom->createCDATASection(''));
            $node->appendChild($note);

            $node->appendChild($dom->createElement('published', '1'));
            $node->appendChild($dom->createElement('access', '1'));
            $node->appendChild($dom->createElement('language', '*'));

            $root->appendChild($node);
            $id++;
        }

        return [
            'xml' => $dom->saveXML(),
            'count' => $id - 1,
        ];
    }

    public static function convertCsvToMenusXml(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            throw new RuntimeException('CSV file not found.');
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        $delimiter = ',';
        $menus = [];
        $order = [];

        $firstRow = fgetcsv($handle, 0, $delimiter);
        if ($firstRow !== false && count($firstRow) === 1 && strpos((string) $firstRow[0], ';') !== false) {
            $delimiter = ';';
            $firstRow = str_getcsv((string) $firstRow[0], $delimiter);
        }

        if ($firstRow === false) {
            fclose($handle);
            return ['xml' => '', 'count' => 0];
        }

        $headers = array_map('trim', $firstRow);
        if (!empty($headers)) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        }
        $normalizedHeaders = array_map('mb_strtolower', $headers);

        $idIndex = array_search('id', $normalizedHeaders, true);
        $hasHeader = self::isHeaderRow($normalizedHeaders, ['menutype', 'path', 'title', 'alias', 'link', 'type', 'published', 'access', 'language', 'note', 'params', 'niveau', 'level', 'parent', 'child', 'id']);
        if ($hasHeader) {
            $rows = [];
        } else {
            $rows = [$firstRow];
            $headers = ['menutype', 'niveau_1', 'niveau_2', 'niveau_3', 'niveau_4', 'title', 'alias', 'link', 'type', 'published', 'access', 'language', 'note', 'params'];
            $normalizedHeaders = array_map('mb_strtolower', $headers);
            $idIndex = null;
        }

        if ($idIndex !== null) {
            unset($headers[$idIndex]);
            unset($normalizedHeaders[$idIndex]);
            $headers = array_values($headers);
            $normalizedHeaders = array_values($normalizedHeaders);
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && $delimiter === ';' && strpos((string) $row[0], ';') !== false) {
                $row = str_getcsv((string) $row[0], $delimiter);
            }
            if ($idIndex !== null && array_key_exists($idIndex, $row)) {
                unset($row[$idIndex]);
                $row = array_values($row);
            }
            $rows[] = $row;
        }

        fclose($handle);

        $headerMap = [];
        foreach ($normalizedHeaders as $index => $header) {
            if ($header === '') {
                continue;
            }

            $headerMap[$header] = $index;

            $canonical = preg_replace('/[^a-z0-9]+/', '', $header);
            if ($canonical !== '' && !isset($headerMap[$canonical])) {
                $headerMap[$canonical] = $index;
            }
        }

        foreach ($rows as $row) {
            self::consumeMenuHierarchyRow($row, $headers, $headerMap, $menus, $order);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('menus');
        $dom->appendChild($root);

        $id = 1;
        $parentIds = [];
        foreach ($order as $key) {
            $entry = $menus[$key] ?? null;
            if (!$entry) {
                continue;
            }

            $level = substr_count($entry['path'], '/') + 1;
            $parentPath = '';
            if (strpos($entry['path'], '/') !== false) {
                $parentPath = substr($entry['path'], 0, strrpos($entry['path'], '/'));
            }
            $parentKey = $entry['menutype'] . '|' . $parentPath;
            $parentId = $parentIds[$parentKey] ?? 1;

            $node = $dom->createElement('menuitem');
            $node->appendChild($dom->createElement('id', (string) $id));
            $node->appendChild($dom->createElement('menutype', $entry['menutype']));
            $node->appendChild($dom->createElement('parent_id', (string) $parentId));
            $node->appendChild($dom->createElement('level', (string) $level));
            $node->appendChild($dom->createElement('path', $entry['path']));

            $node->appendChild($dom->createElement('title', $entry['title']));
            $node->appendChild($dom->createElement('alias', $entry['alias']));
            $node->appendChild($dom->createElement('link', $entry['link']));
            $node->appendChild($dom->createElement('type', $entry['type']));

            $node->appendChild($dom->createElement('published', (string) $entry['published']));
            $node->appendChild($dom->createElement('access', (string) $entry['access']));
            $node->appendChild($dom->createElement('language', $entry['language']));

            $params = $dom->createElement('params');
            $params->appendChild($dom->createCDATASection($entry['params']));
            $node->appendChild($params);

            $node->appendChild($dom->createElement('note', $entry['note']));

            $root->appendChild($node);

            $parentIds[$entry['menutype'] . '|' . $entry['path']] = $id;
            $id++;
        }

        return [
            'xml' => $dom->saveXML(),
            'count' => $id - 1,
        ];
    }

    public static function convertCsvToArticlesXml(string $csvPath): array
    {
        if (!is_file($csvPath)) {
            throw new RuntimeException('CSV file not found.');
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read CSV file.');
        }

        $delimiter = ',';
        $firstRow = fgetcsv($handle, 0, $delimiter);
        if ($firstRow !== false && count($firstRow) === 1 && strpos((string) $firstRow[0], ';') !== false) {
            $delimiter = ';';
            $firstRow = str_getcsv((string) $firstRow[0], $delimiter);
        }

        if ($firstRow === false) {
            fclose($handle);
            return ['xml' => '', 'count' => 0];
        }

        $headers = array_map('trim', $firstRow);
        if (!empty($headers)) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        }
        $headers = array_map('mb_strtolower', $headers);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && $delimiter === ';' && strpos((string) $row[0], ';') !== false) {
                $row = str_getcsv((string) $row[0], $delimiter);
            }
            $rows[] = $row;
        }
        fclose($handle);

        $fields = [
            'catid',
            'title',
            'alias',
            'introtext',
            'fulltext',
            'state',
            'access',
            'language',
            'created_by',
            'created_by_alias',
            'modified_by',
            'ordering',
            'featured',
            'hits',
            'images',
            'urls',
            'attribs',
            'metadata',
            'metadesc',
            'metakey',
            'note',
        ];

        $headerMap = [];
        foreach ($headers as $index => $header) {
            $key = trim((string) $header);
            if ($key !== '' && in_array($key, $fields, true)) {
                $headerMap[$key] = $index;
            }
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement('articles');
        $dom->appendChild($root);

        $count = 0;
        foreach ($rows as $row) {
            $rowValues = [];
            foreach ($fields as $field) {
                if (isset($headerMap[$field])) {
                    $value = $row[$headerMap[$field]] ?? '';
                    $rowValues[$field] = trim((string) $value);
                } else {
                    $rowValues[$field] = '';
                }
            }

            $nonEmpty = array_filter($rowValues, static function ($value) {
                return $value !== '';
            });
            if (empty($nonEmpty)) {
                continue;
            }

            $articleNode = $dom->createElement('article');
            foreach ($fields as $field) {
                $value = $rowValues[$field];
                if (in_array($field, ['introtext', 'fulltext', 'images', 'urls', 'attribs', 'metadata', 'metadesc', 'metakey', 'note'], true)) {
                    $node = $dom->createElement($field);
                    $node->appendChild($dom->createCDATASection($value));
                    $articleNode->appendChild($node);
                } else {
                    $articleNode->appendChild($dom->createElement($field, $value));
                }
            }

            $root->appendChild($articleNode);
            $count++;
        }

        return [
            'xml' => $dom->saveXML(),
            'count' => $count,
        ];
    }

    private static function consumeRow(array $row, array &$tags, array &$order): void
    {
        $values = array_map('trim', $row);
        if (!empty($values)) {
            $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $values[0]);
        }
        $values = array_values(array_filter($values, static function ($value) {
            return $value !== '';
        }));
        $values = self::filterValues($values);

        if (!$values) {
            return;
        }

        $fullMetadata = $values;
        $count = count($values);

        foreach ($values as $index => $value) {
            if (!isset($tags[$value])) {
                $alias = OutputFilter::stringURLSafe($value);
                if ($alias === '') {
                    $alias = strtolower(preg_replace('/\s+/', '-', trim($value)));
                }

                $tags[$value] = [
                    'title' => $value,
                    'alias' => $alias,
                    'metadata_tokens' => [],
                    'metadata_set' => [],
                ];
                $order[] = $value;
            }

            $metadataTokens = $index === $count - 1 ? $fullMetadata : array_slice($values, $index);
            foreach ($metadataTokens as $token) {
                $token = trim($token);
                if ($token === '') {
                    continue;
                }
                if (!isset($tags[$value]['metadata_set'][$token])) {
                    $tags[$value]['metadata_set'][$token] = true;
                    $tags[$value]['metadata_tokens'][] = $token;
                }
            }
        }

        foreach ($values as $value) {
            $tags[$value]['metadata'] = implode(', ', $tags[$value]['metadata_tokens']);
        }
    }

    private static function consumeCategoryRow(array $row, array &$categories, array &$order): void
    {
        $values = array_map('trim', $row);
        if (!empty($values)) {
            $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $values[0]);
        }
        $values = self::filterValues($values);

        if (!$values) {
            return;
        }

        $segments = [];
        foreach ($values as $value) {
            $slug = self::slugify($value);
            if ($slug === '') {
                continue;
            }
            $segments[] = [
                'title' => $value,
                'slug' => $slug,
            ];
        }

        if (!$segments) {
            return;
        }

        $pathParts = [];
        foreach ($segments as $segment) {
            $pathParts[] = $segment['slug'];
            $path = implode('/', $pathParts);
            if (!isset($categories[$path])) {
                $categories[$path] = [
                    'path' => $path,
                    'title' => $segment['title'],
                    'alias' => $segment['slug'],
                ];
                $order[] = $path;
            }
        }
    }

    private static function consumeMenuHierarchyRow(array $row, array $headers, array $headerMap, array &$menus, array &$order): void
    {
        $menutype = '';
        if (isset($headerMap['menutype']) && array_key_exists($headerMap['menutype'], $row)) {
            $menutype = trim((string) $row[$headerMap['menutype']]);
        }
        if ($menutype === '' && array_key_exists(0, $row)) {
            $menutype = trim((string) $row[0]);
        }
        if ($menutype === '') {
            $menutype = 'mainmenu';
        }

        $levels = [];
        foreach ($headers as $index => $header) {
            $key = mb_strtolower(trim((string) $header));
            if (!preg_match('/^(niveau|level|parent|child)/', $key)) {
                continue;
            }
            $value = isset($row[$index]) ? trim((string) $row[$index]) : '';
            if ($value === '') {
                continue;
            }
            $slug = self::slugify($value);
            if ($slug === '') {
                continue;
            }
            $levels[] = ['title' => $value, 'slug' => $slug];
        }

        $explicitPath = trim((string) ($row[$headerMap['path'] ?? null] ?? ''));
        $path = $explicitPath;
        if ($path === '' && $levels) {
            $path = implode('/', array_column($levels, 'slug'));
        }

        if ($path === '') {
            $fallback = isset($row[1]) ? trim((string) $row[1]) : '';
            if ($fallback !== '') {
                $segments = array_map('trim', explode('/', $fallback));
                $slugSegments = [];
                foreach ($segments as $seg) {
                    $slug = self::slugify($seg);
                    if ($slug !== '') {
                        $slugSegments[] = $slug;
                    }
                }
                $path = implode('/', $slugSegments);
            }
        }

        if ($path === '') {
            return;
        }

        $title = trim((string) ($row[$headerMap['title'] ?? null] ?? ''));
        if ($title === '' && $levels) {
            $title = end($levels)['title'];
        }
        if ($title === '') {
            $segments = explode('/', $path);
            $title = ucwords(str_replace('-', ' ', end($segments)));
        }

        $alias = trim((string) ($row[$headerMap['alias'] ?? null] ?? ''));
        if ($alias === '') {
            $alias = self::slugify($title !== '' ? $title : $path);
        }

        $link = trim((string) ($row[$headerMap['link'] ?? null] ?? ''));
        if ($link === '') {
            $link = 'index.php';
        }

        $type = trim((string) ($row[$headerMap['type'] ?? null] ?? ''));
        if ($type === '') {
            $type = 'component';
        }

        $published = trim((string) ($row[$headerMap['published'] ?? null] ?? ''));
        if ($published === '') {
            $published = '1';
        }

        $access = trim((string) ($row[$headerMap['access'] ?? null] ?? ''));
        if ($access === '') {
            $access = '1';
        }

        $language = trim((string) ($row[$headerMap['language'] ?? null] ?? ''));
        if ($language === '') {
            $language = '*';
        }

        $note = trim((string) ($row[$headerMap['note'] ?? null] ?? ''));
        $params = trim((string) ($row[$headerMap['params'] ?? null] ?? ''));

        $key = $menutype . '|' . $path;
        if (!isset($menus[$key])) {
            $order[] = $key;
        }

        $menus[$key] = [
            'menutype' => $menutype,
            'path' => $path,
            'title' => $title,
            'alias' => $alias,
            'link' => $link,
            'type' => $type,
            'published' => $published,
            'access' => $access,
            'language' => $language,
            'params' => $params,
            'note' => $note,
        ];
    }

    private static function filterValues(array $values): array
    {
        $values = array_values(array_filter($values, static function ($value) {
            return $value !== '';
        }));
        $values = array_values(array_unique($values));
        $values = array_values(array_filter($values, static function ($value) {
            $lower = mb_strtolower($value);
            return !in_array($lower, ['land', 'provincie', 'naam', 'plaats'], true);
        }));
        return $values;
    }

    private static function isHeaderRow(array $row, array $needles): bool
    {
        $normalized = array_map(static function ($value) {
            return mb_strtolower(trim((string) $value));
        }, $row);

        $canonicalNeedles = array_map(static function ($needle) {
            return preg_replace('/[^a-z0-9]+/', '', $needle);
        }, $needles);

        foreach ($normalized as $value) {
            if ($value === '') {
                continue;
            }

            $canonicalValue = preg_replace('/[^a-z0-9]+/', '', $value);

            foreach ($needles as $index => $needle) {
                $canonicalNeedle = $canonicalNeedles[$index];

                if ($value === $needle || ($canonicalNeedle !== '' && $canonicalValue === $canonicalNeedle)) {
                    return true;
                }

                if ($needle !== '' && (str_starts_with($value, $needle . '_') || str_starts_with($value, $needle . '-'))) {
                    return true;
                }

                if ($canonicalNeedle !== '' && (str_starts_with($canonicalValue, $canonicalNeedle . '_') || str_starts_with($canonicalValue, $canonicalNeedle . '-'))) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function slugify(string $value): string
    {
        $slug = trim($value);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = OutputFilter::stringURLSafe($slug);
        if ($slug === '') {
            $slug = strtolower(preg_replace('/\s+/', '-', trim($value)));
        }
        return $slug;
    }
}
