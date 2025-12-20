<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/import.php';
require_once __DIR__ . '/import_preview.php';

class Jdb2xmlAutomaticImportHelper
{
    public static function run(string $dir): array
    {
        $automaticDir = $dir . '/automatic';
        $errorDir = $dir . '/error';
        $processedDir = $dir . '/processed';

        if (!is_dir($automaticDir)) {
            return ['processed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => ['Automatic import folder missing.']];
        }

        if (!is_dir($errorDir)) {
            @mkdir($errorDir, 0755, true);
        }
        if (!is_dir($processedDir)) {
            @mkdir($processedDir, 0755, true);
        }

        $files = glob($automaticDir . '/*.xml', GLOB_NOSORT) ?: [];
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($files as $file) {
            $basename = basename($file);
            try {
                $preview = Jdb2xmlImportPreviewHelper::run($automaticDir, $basename);
                $data = $preview[$basename] ?? null;
                if (!$data || !is_array($data)) {
                    $errors[] = $basename . ': preview data missing.';
                    @rename($file, $errorDir . '/' . $basename);
                    $failed++;
                    continue;
                }

                $hasAction = false;
                foreach (['categories', 'tags', 'articles'] as $type) {
                    foreach (($data[$type] ?? []) as $row) {
                        $action = (string)($row['action'] ?? '');
                        if ($action !== '' && $action !== 'skipped') {
                            $hasAction = true;
                            break 2;
                        }
                    }
                }

                if (!$hasAction) {
                    $errors[] = $basename . ': no changes to import.';
                    @rename($file, $errorDir . '/' . $basename);
                    $skipped++;
                    continue;
                }

                Jdb2xmlImportHelper::runSingle($automaticDir, $basename, false, []);
                @rename($file, $processedDir . '/' . $basename);
                $processed++;
            } catch (Throwable $e) {
                $errors[] = $basename . ': ' . $e->getMessage();
                @rename($file, $errorDir . '/' . $basename);
                $failed++;
            }
        }

        if (!empty($errors)) {
            self::writeErrorLog($errors);
        }

        return ['processed' => $processed, 'skipped' => $skipped, 'failed' => $failed, 'errors' => $errors];
    }

    public static function logBatch(array $result): void
    {
        $logFile = JPATH_ADMINISTRATOR . '/logs/jdb2xml_import_batches.json';
        $entries = [];
        if (is_file($logFile)) {
            $data = json_decode(file_get_contents($logFile), true);
            if (is_array($data)) {
                $entries = $data;
            }
        }

        $status = $result['failed'] > 0 ? 'failed' : 'success';
        $entries[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'processed' => (int) $result['processed'],
            'skipped' => (int) $result['skipped'],
            'failed' => (int) $result['failed'],
            'status' => $status,
        ];

        $entries = array_slice($entries, -10);
        file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function writeErrorLog(array $errors): void
    {
        $logFile = JPATH_ADMINISTRATOR . '/logs/jdb2xml_import_errors.log';
        $lines = [];
        foreach ($errors as $error) {
            $lines[] = '[' . date('Y-m-d H:i:s') . '] ' . $error;
        }
        file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND);
    }
}
