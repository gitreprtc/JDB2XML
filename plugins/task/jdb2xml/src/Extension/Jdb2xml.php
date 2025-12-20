<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

namespace Joomla\Plugin\Task\Jdb2xml\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Scheduler\Task\TaskInterface;
use Joomla\Component\Scheduler\Task\TaskPlugin;
use Joomla\CMS\Factory;

class Jdb2xml extends TaskPlugin
{
    protected const TASKS = [
        'import' => [
            'langConstPrefix' => 'PLG_TASK_JDB2XML_IMPORT',
            'method' => 'runImport',
        ],
        'export' => [
            'langConstPrefix' => 'PLG_TASK_JDB2XML_EXPORT',
            'method' => 'runExport',
        ],
    ];

    protected function runImport(TaskInterface $task): int
    {
        if (!$this->shouldRunSchedule('import_schedule')) {
            return TaskInterface::SUCCESS;
        }

        require_once JPATH_ADMINISTRATOR . '/components/com_jdb2xml/helpers/automatic_import.php';

        $result = \Jdb2xmlAutomaticImportHelper::run(JPATH_ROOT . '/media/com_jdb2xml/import');
        \Jdb2xmlAutomaticImportHelper::logBatch($result);

        return $result['failed'] > 0 ? TaskInterface::FAILURE : TaskInterface::SUCCESS;
    }

    protected function runExport(TaskInterface $task): int
    {
        if (!$this->shouldRunSchedule('export_schedule')) {
            return TaskInterface::SUCCESS;
        }

        require_once JPATH_ADMINISTRATOR . '/components/com_jdb2xml/helpers/export.php';

        try {
            \Jdb2xmlExportHelper::runType(JPATH_ROOT . '/media/com_jdb2xml/export', 'all');
            \Jdb2xmlExportHelper::logBatch('all', true);
            return TaskInterface::SUCCESS;
        } catch (\Throwable $e) {
            \Jdb2xmlExportHelper::logBatch('all', false, $e->getMessage());
            return TaskInterface::FAILURE;
        }
    }

    private function shouldRunSchedule(string $paramKey): bool
    {
        $params = ComponentHelper::getParams('com_jdb2xml');
        $rawSchedule = $params->get($paramKey, []);
        $schedule = is_object($rawSchedule) ? (array) $rawSchedule : (array) $rawSchedule;

        $weekdays = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        $now = Factory::getDate();
        $dayKey = array_search((int) $now->format('N'), $weekdays, true);
        if (!$dayKey) {
            return false;
        }

        $dayRaw = $schedule[$dayKey] ?? [];
        $day = is_object($dayRaw) ? (array) $dayRaw : (array) $dayRaw;

        $intervalMinutes = max(1, (int) ($day['interval_minutes'] ?? 0));
        $timeFrom = $day['time_from'] ?? '00:00';
        $timeTo = $day['time_to'] ?? '23:59';

        $currentMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        [$fromHour, $fromMinute] = array_map('intval', explode(':', $timeFrom));
        [$toHour, $toMinute] = array_map('intval', explode(':', $timeTo));
        $fromMinutes = $fromHour * 60 + $fromMinute;
        $toMinutes = $toHour * 60 + $toMinute;

        if ($currentMinutes < $fromMinutes || $currentMinutes > $toMinutes) {
            return false;
        }

        return (($currentMinutes - $fromMinutes) % $intervalMinutes) === 0;
    }
}
