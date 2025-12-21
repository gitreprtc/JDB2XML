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

        if ($firstRow !== false) {
            self::consumeRow($firstRow, $tags, $order);
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
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

        if ($firstRow !== false) {
            self::consumeCategoryRow($firstRow, $categories, $order);
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
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
