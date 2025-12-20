<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
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

        $rows = $data['categories'] ?? [];

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
        } catch (Throwable $e) {
            $app->enqueueMessage('Export error: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=export');
    }

    public function exportmanual()
    {
        require_once __DIR__ . '/helpers/export.php';
        $app = $this->getApplicationWithTokenCheck();
        $type = $app->input->getCmd('export_type', 'all');
        if (!in_array($type, ['categories', 'tags'], true)) {
            $type = 'all';
        }

        try {
            $result = Jdb2xmlExportHelper::runType(JPATH_ROOT . '/media/com_jdb2xml/export', $type);
            $app->enqueueMessage($result, 'message');
        } catch (Throwable $e) {
            $app->enqueueMessage('Export error: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=export');
    }

    public function saveexportschedule()
    {
        $app = $this->getApplicationWithTokenCheck();
        $weekday = $app->input->getCmd('export_weekday', 'monday');
        $interval = max(1, (int) $app->input->getInt('export_interval_hours', 24));
        $timeFrom = $app->input->getString('export_time_from', '00:00');
        $timeTo = $app->input->getString('export_time_to', '23:59');

        $validWeekdays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        if (!in_array($weekday, $validWeekdays, true)) {
            $weekday = 'monday';
        }

        $component = ComponentHelper::getComponent('com_jdb2xml');
        $table = Table::getInstance('extension');
        $table->load($component->id);
        $params = new Registry($table->params);
        $params->set('export_weekday', $weekday);
        $params->set('export_interval_hours', $interval);
        $params->set('export_time_from', $timeFrom);
        $params->set('export_time_to', $timeTo);
        $table->params = $params->toString();

        if (!$table->check() || !$table->store()) {
            $app->enqueueMessage('Failed to save export schedule settings.', 'error');
        } else {
            $app->enqueueMessage('Export schedule settings saved.', 'message');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&view=export');
    }

    protected function getApplicationWithTokenCheck(): CMSApplicationInterface
    {
        if (!Session::checkToken()) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return Factory::getApplication();
    }
}
