<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class Jdb2xmlController extends BaseController
{
    protected $default_view = 'controlpanel';

    public function preview()
    {
        $app = $this->getApplicationWithTokenCheck();
        $dir = JPATH_ROOT . '/media/com_jdb2xml/import';

        $selected = basename((string) $app->input->getString('selected_file', ''));
        $app->setUserState('com_jdb2xml.selected_file', $selected);

        if (!$selected) {
            $app->enqueueMessage('Preview geweigerd: selecteer eerst een bestand.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml&selected_file=' . urlencode($selected) . '&show_preview=1');
            return;
        }

        require_once __DIR__ . '/helpers/import_preview.php';

        try {
            $data = Jdb2xmlImportPreviewHelper::run($dir, $selected);
            $app->setUserState('com_jdb2xml.preview.' . $selected, $data);
        } catch (Throwable $e) {
            $app->enqueueMessage('Preview fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&selected_file=' . urlencode($selected) . '&show_preview=1');
    }

    public function import()
    {
        $app = $this->getApplicationWithTokenCheck();

        $selected = basename((string) $app->input->getString('selected_file', ''));
        $app->setUserState('com_jdb2xml.selected_file', $selected);

        if (!$selected) {
            $app->enqueueMessage('Import geweigerd: selecteer eerst een bestand en voer een preview uit.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml');
            return;
        }

        // Enforce mandatory preview per file
        $preview = $app->getUserState('com_jdb2xml.preview.' . $selected);
        if (!$preview) {
            $app->enqueueMessage('Import geweigerd: voer eerst een preview uit voor dit bestand.', 'warning');
            $this->setRedirect('index.php?option=com_jdb2xml');
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

        // Blocking rule: any row marked as 'overgeslagen' (skipped) must be excluded explicitly
        foreach ($rows as $row) {
            $key = (string)($row['id'] ?? '');
            if (!empty($row['path'])) {
                $key = (string)$row['path'];
            }
            if ($key === '') {
                continue;
            }
            if (($row['action'] ?? '') === 'overgeslagen' && empty($exclude[$key])) {
                $app->enqueueMessage('Import geblokkeerd: niet alle foutieve records zijn uitgesloten.', 'warning');
                $this->setRedirect('index.php?option=com_jdb2xml');
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
            $app->enqueueMessage('Import fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml');
    }

    public function deletefile()
    {
        $app = $this->getApplicationWithTokenCheck();
        $file = basename($app->input->getCmd('file'));

        $path = JPATH_ROOT . '/media/com_jdb2xml/import/' . $file;

        if ($file && is_file($path)) {
            unlink($path);
            $app->enqueueMessage('Bestand verwijderd: ' . $file, 'message');
        }

        // Remove preview so UI refreshes
        $app->setUserState('com_jdb2xml.preview', null);
        $this->setRedirect('index.php?option=com_jdb2xml');
    }

    public function rollback()
    {
        $app = $this->getApplicationWithTokenCheck();
        $app->setUserState('com_jdb2xml.show_rollback', 1);
        $this->setRedirect('index.php?option=com_jdb2xml&show_rollback=1');
    }

    public function refreshsftp()
    {
        $app = $this->getApplicationWithTokenCheck();
        $app->setUserState('com_jdb2xml.selected_file', '');
        $this->setRedirect('index.php?option=com_jdb2xml');
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

        try {
            $result = Jdb2xmlRollbackHelper::apply($type, $file);
            $app->enqueueMessage($result, 'message');
        } catch (Throwable $e) {
            $app->enqueueMessage('Rollback fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml&show_rollback=1');
    }

    public function resetpreview()
    {
        $app = $this->getApplicationWithTokenCheck();
        $selected = basename((string) $app->input->getString('selected_file', ''));
        if ($selected) {
            $app->setUserState('com_jdb2xml.preview.' . $selected, null);
        }
        $app->setUserState('com_jdb2xml.selected_file', '');
        $this->setRedirect('index.php?option=com_jdb2xml');
    }

    public function export()
    {
        require_once __DIR__ . '/helpers/export.php';
        $app = $this->getApplicationWithTokenCheck();

        try {
            $result = Jdb2xmlExportHelper::run(JPATH_ROOT . '/media/com_jdb2xml/export');
            $app->enqueueMessage($result, 'message');
        } catch (Throwable $e) {
            $app->enqueueMessage('Export fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_jdb2xml');
    }

    protected function getApplicationWithTokenCheck(): CMSApplicationInterface
    {
        if (!Session::checkToken()) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return Factory::getApplication();
    }
}
