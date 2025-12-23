<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

class Jdb2xmlController extends BaseController
{
    protected $default_view = 'landing';

    public function preview()
    {
        $app = $this->getApplicationWithTokenCheck();
        $dir = JPATH_ROOT . '/media/com_jdb2xml/import';

        $selected = basename((string) $app->input->getString('selected_file', ''));
        $app->setUserState('com_jdb2xml.selected_file', $selected);

        if (!$selected) {
            $app->enqueueMessage('Preview denied: select a file first.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel&selected_file=' . urlencode($selected) . '&show_preview=1');
            return;
        }

        require_once __DIR__ . '/helpers/import_preview.php';

        try {
            $data = Jdb2xmlImportPreviewHelper::run($dir, $selected);
            $app->setUserState('com_jdb2xml.preview.' . $selected, $data);
        } catch (Throwable $e) {
            $app->enqueueMessage('Preview error: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel&selected_file=' . urlencode($selected) . '&show_preview=1');
    }

    public function import()
    {
        $app = $this->getApplicationWithTokenCheck();

        $selected = basename((string) $app->input->getString('selected_file', ''));
        $app->setUserState('com_jdb2xml.selected_file', $selected);

        if (!$selected) {
            $app->enqueueMessage('Import denied: select a file and run a preview first.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
            return;
        }

        // Enforce mandatory preview per file
        $preview = $app->getUserState('com_jdb2xml.preview.' . $selected);
        if (!$preview) {
            $app->enqueueMessage('Import denied: run a preview for this file first.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
            return;
        }

        // Exclusions are submitted as exclude[<filename>][<key>]=1
        $excludeAll = $app->input->get('exclude', [], 'array');
        $exclude = (isset($excludeAll[$selected]) && is_array($excludeAll[$selected])) ? $excludeAll[$selected] : [];

        // Determine rows from preview (helper may return either the file-data or wrapper)
        $data = $preview;
        if (is_array($preview) && isset($preview[$selected]) && is_array($preview[$selected])) {
            $data = $preview[$selected];
        }

        $rows = array_merge(
            $data['categories'] ?? [],
            $data['phocaCategories'] ?? [],
            $data['tags'] ?? [],
            $data['articles'] ?? []
        );

        // Blocking rule: any row marked as 'skipped' must be excluded explicitly
        foreach ($rows as $row) {
            $key = (string)($row['id'] ?? '');
            if (!empty($row['path'])) {
                $key = (string)$row['path'];
            }
            if ($key === '') {
                continue;
            }
            if (($row['action'] ?? '') === 'skipped' && empty($exclude[$key])) {
                $app->enqueueMessage('Import blocked: not all invalid records are excluded.', 'warning');
                $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
                return;
            }
        }

        require_once __DIR__ . '/helpers/import.php';

        $dryRun = (bool) $app->input->getInt('dry_run', 0);

        try {
            $result = Jdb2xmlImportHelper::runSingle(JPATH_ROOT . '/media/com_jdb2xml/import', $selected, $dryRun, $exclude);
            $app->enqueueMessage($result, 'message');
            // Clear preview for this file after successful import
            $app->setUserState('com_jdb2xml.preview.' . $selected, null);
        } catch (Throwable $e) {
            $app->enqueueMessage('Import error: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
    }

    public function deletefile()
    {
        $app = $this->getApplicationWithTokenCheck();
        $file = basename($app->input->getCmd('file'));

        $path = JPATH_ROOT . '/media/com_jdb2xml/import/' . $file;

        if ($file && is_file($path)) {
            unlink($path);
            $app->enqueueMessage('File deleted: ' . $file, 'message');
        }

        // Remove preview so UI refreshes
        $app->setUserState('com_jdb2xml.preview', null);
        $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
    }

    public function rollback()
    {
        $app = $this->getApplicationWithTokenCheck();
        $this->setRedirect('index.php?option=com_jdb2xml&view=rollback');
    }

    public function refreshsftp()
    {
        $app = $this->getApplicationWithTokenCheck();
        $app->setUserState('com_jdb2xml.selected_file', '');
        $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
    }

    public function rejectimport()
    {
        $app = $this->getApplicationWithTokenCheck();
        $dir = JPATH_ROOT . '/media/com_jdb2xml/import';
        $rejectedDir = $dir . '/rejected';

        $selected = basename((string) $app->input->getString('selected_file', (string) $app->getUserState('com_jdb2xml.selected_file', '')));
        if (!$selected) {
            $app->enqueueMessage('Reject failed: select a file first.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
            return;
        }

        if (!is_dir($rejectedDir)) {
            @mkdir($rejectedDir, 0755, true);
        }

        $source = $dir . '/' . $selected;
        $dest = $rejectedDir . '/' . $selected;

        if (!is_file($source)) {
            $app->enqueueMessage('Reject failed: file not found.', 'error');
            $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
            return;
        }

        if (!@rename($source, $dest)) {
            $app->enqueueMessage('Reject failed: unable to move file to rejected.', 'error');
            $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
            return;
        }

        $app->setUserState('com_jdb2xml.preview.' . $selected, null);
        $app->setUserState('com_jdb2xml.selected_file', '');
        $app->enqueueMessage('File rejected by user.', 'message');
        $app->enqueueMessage('File moved to rejected: ' . $selected, 'message');
        $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
    }

    public function listfiles()
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('get')) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $dir = JPATH_ROOT . '/media/com_jdb2xml/import';
        if (is_dir($dir)) {
            clearstatcache(true, $dir);
        }

        $files = is_dir($dir) ? (glob($dir . '/*.xml', GLOB_NOSORT) ?: []) : [];
        $names = array_map('basename', $files);

        $app->setHeader('Content-Type', 'application/json', true);
        echo json_encode(['files' => $names], JSON_UNESCAPED_SLASHES);
        $app->close();
    }

    public function rollbackapply()
    {
        require_once __DIR__ . '/helpers/rollback.php';
        $app = $this->getApplicationWithTokenCheck();

        $type = $app->input->getCmd('rollback_type', 'categories');
        $file = basename((string) $app->input->getString('rollback_file', ''));
        $items = $app->input->get('rollback_items', [], 'array');
        $createdIds = isset($items['created']) && is_array($items['created']) ? $items['created'] : [];
        $updatedIds = isset($items['updated']) && is_array($items['updated']) ? $items['updated'] : [];

        try {
            $result = Jdb2xmlRollbackHelper::apply($type, $file, $createdIds, $updatedIds);
            $app->enqueueMessage($result['message'], 'message');
            if (!empty($result['warning'])) {
                $app->enqueueMessage($result['warning'], 'warning');
            }
        } catch (Throwable $e) {
            $app->enqueueMessage('Rollback error: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=rollback');
    }

    public function resetpreview()
    {
        $app = $this->getApplicationWithTokenCheck();
        $selected = basename((string) $app->input->getString('selected_file', ''));
        if ($selected) {
            $app->setUserState('com_jdb2xml.preview.' . $selected, null);
        }
        $app->setUserState('com_jdb2xml.selected_file', '');
        $this->setRedirect('index.php?option=com_jdb2xml&view=controlpanel');
    }

    public function export()
    {
        require_once __DIR__ . '/helpers/export.php';
        $app = $this->getApplicationWithTokenCheck();

        try {
            $result = Jdb2xmlExportHelper::runType(JPATH_ROOT . '/media/com_jdb2xml/export', 'all');
            $app->enqueueMessage($result, 'message');
            Jdb2xmlExportHelper::logBatch('all', true);
        } catch (Throwable $e) {
            $app->enqueueMessage('Export error: ' . $e->getMessage(), 'error');
            Jdb2xmlExportHelper::logBatch('all', false, $e->getMessage());
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=export');
    }

    public function exportmanual()
    {
        require_once __DIR__ . '/helpers/export.php';
        $app = $this->getApplicationWithTokenCheck();
        $type = $app->input->getCmd('export_type', 'all');
        if (!in_array($type, ['categories', 'tags', 'articles'], true)) {
            $type = 'all';
        }

        try {
            $result = Jdb2xmlExportHelper::runType(JPATH_ROOT . '/media/com_jdb2xml/export', $type);
            $app->enqueueMessage($result, 'message');
            Jdb2xmlExportHelper::logBatch($type, true);
        } catch (Throwable $e) {
            $app->enqueueMessage('Export error: ' . $e->getMessage(), 'error');
            Jdb2xmlExportHelper::logBatch($type, false, $e->getMessage());
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=export');
    }

    public function csvconversionupload()
    {
        $app = $this->getApplicationWithTokenCheck();
        $file = $app->input->files->get('csv_file');
        $type = $app->input->getCmd('conversion_type', 'tags');

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $app->enqueueMessage('Upload failed: please select a CSV file.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml&view=csvconversion');
            return;
        }

        $extension = strtolower((string) File::getExt($file['name']));
        if ($extension !== 'csv') {
            $app->enqueueMessage('Upload failed: only CSV files are supported.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml&view=csvconversion');
            return;
        }

        $importDir = JPATH_ROOT . '/media/com_jdb2xml/import';
        if (!Folder::exists($importDir)) {
            Folder::create($importDir);
        }

        require_once __DIR__ . '/helpers/tag_conversion.php';

        try {
            if ($type === 'categories') {
                $result = Jdb2xmlTagConversionHelper::convertCsvToCategoriesXml($file['tmp_name']);
            } else {
                $result = Jdb2xmlTagConversionHelper::convertCsvToTagsXml($file['tmp_name']);
            }
            $safeBase = File::makeSafe(pathinfo((string) $file['name'], PATHINFO_FILENAME));
            $timestamp = date('Ymd_His');
            $prefix = $type === 'categories' ? 'categories' : 'tags';
            $filename = ($safeBase !== '' ? $safeBase . '_' : '') . $prefix . '_conversie_' . $timestamp . '.xml';

            $targetPath = $importDir . '/' . $filename;
            File::write($targetPath, $result['xml']);

            $label = $type === 'categories' ? 'categories' : 'tags';
            $app->enqueueMessage('CSV conversion created ' . (int) $result['count'] . ' ' . $label . ' and saved ' . $filename . ' to the import folder.', 'message');
        } catch (Throwable $e) {
            $app->enqueueMessage('CSV conversion error: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=csvconversion');
    }

    public function saveexportschedule()
    {
        $app = $this->getApplicationWithTokenCheck();
        $scheduleInput = $app->input->get('export_schedule', [], 'array');
        $validWeekdays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $schedule = [];
        foreach ($validWeekdays as $day) {
            $dayInput = $scheduleInput[$day] ?? [];
            $interval = max(10, (int) ($dayInput['interval_minutes'] ?? 60));
            $timeFrom = (string) ($dayInput['time_from'] ?? '00:00');
            $timeTo = (string) ($dayInput['time_to'] ?? '23:59');
            $schedule[$day] = [
                'interval_minutes' => $interval,
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
            ];
        }

        $component = ComponentHelper::getComponent('com_jdb2xml');
        $table = Table::getInstance('extension');
        $table->load($component->id);
        $params = new Registry($table->params);
        $params->set('export_schedule', $schedule);
        $table->params = $params->toString();

        if (!$table->check() || !$table->store()) {
            $app->enqueueMessage('Failed to save export schedule settings.', 'error');
        } else {
            $app->enqueueMessage('Export schedule settings saved.', 'message');
            $this->upsertSchedulerTask(
                'export',
                'JDB2XML Export',
                ['schedule' => $schedule],
                ['rule' => 'interval', 'interval' => 1, 'unit' => 'minute']
            );
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=export');
    }

    public function saveimportschedule()
    {
        $app = $this->getApplicationWithTokenCheck();
        $scheduleInput = $app->input->get('import_schedule', [], 'array');
        $validWeekdays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $schedule = [];
        foreach ($validWeekdays as $day) {
            $dayInput = $scheduleInput[$day] ?? [];
            $interval = max(10, (int) ($dayInput['interval_minutes'] ?? 60));
            $timeFrom = (string) ($dayInput['time_from'] ?? '00:00');
            $timeTo = (string) ($dayInput['time_to'] ?? '23:59');
            $schedule[$day] = [
                'interval_minutes' => $interval,
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
            ];
        }

        $component = ComponentHelper::getComponent('com_jdb2xml');
        $table = Table::getInstance('extension');
        $table->load($component->id);
        $params = new Registry($table->params);
        $params->set('import_schedule', $schedule);
        $table->params = $params->toString();

        if (!$table->check() || !$table->store()) {
            $app->enqueueMessage('Failed to save import schedule settings.', 'error');
        } else {
            $app->enqueueMessage('Import schedule settings saved.', 'message');
            $this->upsertSchedulerTask(
                'import',
                'JDB2XML Automatic Import',
                ['schedule' => $schedule],
                ['rule' => 'interval', 'interval' => 1, 'unit' => 'minute']
            );
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=importautomatic');
    }

    public function runautomaticimport()
    {
        require_once __DIR__ . '/helpers/automatic_import.php';
        $app = $this->getApplicationWithTokenCheck();

        $result = Jdb2xmlAutomaticImportHelper::run(JPATH_ROOT . '/media/com_jdb2xml/import');
        Jdb2xmlAutomaticImportHelper::logBatch($result);

        if (!empty($result['errors'])) {
            $app->enqueueMessage('Automatic import finished with errors.', 'warning');
        } else {
            $app->enqueueMessage('Automatic import completed.', 'message');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=importautomatic');
    }

    private function upsertSchedulerTask(string $type, string $title, array $params, array $executionRules): void
    {
        $db = Factory::getDbo();
        $columns = $db->getTableColumns('#__scheduler_tasks');
        $now = Factory::getDate();
        $nowSql = $now->toSql();
        $nullDate = $db->getNullDate();
        $userId = Factory::getApplication()->getIdentity()->id ?? 0;

        $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
        $rulesJson = json_encode($executionRules, JSON_UNESCAPED_UNICODE);

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($type));
        $taskId = (int) $db->setQuery($query)->loadResult();

        $fields = [];
        if (isset($columns['asset_id'])) {
            $fields['asset_id'] = 0;
        }
        if (isset($columns['title'])) {
            $fields['title'] = $title;
        }
        if (isset($columns['type'])) {
            $fields['type'] = $type;
        }
        if (isset($columns['execution_rules'])) {
            $fields['execution_rules'] = $rulesJson;
        }
        if (isset($columns['params'])) {
            $fields['params'] = $paramsJson;
        }
        if (isset($columns['state'])) {
            $fields['state'] = 1;
        }
        if (isset($columns['enabled'])) {
            $fields['enabled'] = 1;
        }
        if (isset($columns['created'])) {
            $fields['created'] = $nowSql;
        }
        if (isset($columns['created_by'])) {
            $fields['created_by'] = $userId;
        }
        if (isset($columns['checked_out'])) {
            $fields['checked_out'] = 0;
        }
        if (isset($columns['checked_out_time'])) {
            $fields['checked_out_time'] = $nullDate;
        }
        if (isset($columns['modified'])) {
            $fields['modified'] = $nowSql;
        }
        if (isset($columns['modified_by'])) {
            $fields['modified_by'] = $userId;
        }
        if (isset($columns['locked'])) {
            $fields['locked'] = 0;
        }
        if (isset($columns['last_execution'])) {
            $fields['last_execution'] = $nullDate;
        }
        if (isset($columns['next_execution'])) {
            $intervalMinutes = 1;
            if (($executionRules['rule'] ?? '') === 'interval') {
                $intervalMinutes = (int) ($executionRules['interval'] ?? 1);
            }
            $next = (clone $now)->modify('+' . max(1, $intervalMinutes) . ' minutes');
            $fields['next_execution'] = $next->toSql();
        }

        if ($taskId > 0) {
            $set = [];
            foreach ($fields as $column => $value) {
                $set[] = $db->quoteName($column) . ' = ' . $db->quote($value);
            }
            if ($set) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__scheduler_tasks'))
                    ->set($set)
                    ->where($db->quoteName('id') . ' = ' . (int) $taskId);
                $db->setQuery($query)->execute();
            }
            return;
        }

        if (!$fields) {
            return;
        }

        $columnsList = [];
        $valuesList = [];
        foreach ($fields as $column => $value) {
            $columnsList[] = $db->quoteName($column);
            $valuesList[] = $db->quote($value);
        }
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__scheduler_tasks'))
            ->columns($columnsList)
            ->values(implode(',', $valuesList));
        $db->setQuery($query)->execute();
    }

    protected function getApplicationWithTokenCheck(): CMSApplicationInterface
    {
        if (!Session::checkToken()) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return Factory::getApplication();
    }
}
