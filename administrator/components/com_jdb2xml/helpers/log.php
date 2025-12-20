<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

class Jdb2xmlLogHelper
{
    private const CHANGE_LOG = JPATH_ADMINISTRATOR . '/logs/jdb2xml_change_log.json';
    private const ROLLBACK_LOG = JPATH_ADMINISTRATOR . '/logs/jdb2xml_rollback_log.json';
    private const RETENTION_DAYS = 30;

    public static function logChange(string $type, string $action, string $identifier, string $title, array $details = []): void
    {
        self::write(self::CHANGE_LOG, [
            'timestamp' => date('c'),
            'type' => $type,
            'action' => $action,
            'identifier' => $identifier,
            'title' => $title,
            'details' => $details,
        ]);
    }

    public static function logRollback(string $type, string $action, string $identifier, string $title, array $details = []): void
    {
        self::write(self::ROLLBACK_LOG, [
            'timestamp' => date('c'),
            'type' => $type,
            'action' => $action,
            'identifier' => $identifier,
            'title' => $title,
            'details' => $details,
        ]);
    }

    public static function getChangesByType(string $type): array
    {
        return self::readByType(self::CHANGE_LOG, $type);
    }

    public static function getRollbacksByType(string $type): array
    {
        return self::readByType(self::ROLLBACK_LOG, $type);
    }

    private static function readByType(string $file, string $type): array
    {
        $entries = self::read($file);
        $filtered = array_values(array_filter($entries, function ($entry) use ($type) {
            return ($entry['type'] ?? '') === $type;
        }));
        usort($filtered, function ($a, $b) {
            return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
        });
        return $filtered;
    }

    private static function write(string $file, array $entry): void
    {
        $entries = self::read($file);
        $entries[] = $entry;
        $entries = self::prune($entries);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            return [];
        }
        $pruned = self::prune($data);
        if (count($pruned) !== count($data)) {
            file_put_contents($file, json_encode($pruned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return $pruned;
    }

    private static function prune(array $entries): array
    {
        $cutoff = strtotime('-' . self::RETENTION_DAYS . ' days');
        $kept = array_filter($entries, function ($entry) use ($cutoff) {
            $ts = strtotime((string) ($entry['timestamp'] ?? ''));
            return $ts !== false && $ts >= $cutoff;
        });
        return array_values($kept);
    }
}
