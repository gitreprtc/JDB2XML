<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class XmlcatagController extends BaseController
{
    protected $default_view = 'controlpanel';

    public function preview()
    {
        $app = $this->getApplicationWithTokenCheck();
        $dir = JPATH_ROOT . '/import/xmlcatag';

        $selected = basename((string) $app->input->getString('selected_file', ''));
        $app->setUserState('com_xmlcatag.selected_file', $selected);

        if (!$selected) {
            $app->enqueueMessage('Preview geweigerd: selecteer eerst een bestand.', 'warning');
            $this->setRedirect('index.php?option=com_xmlcatag&selected_file=' . urlencode($selected) . '&show_preview=1');
            return;
        }

        require_once __DIR__ . '/helpers/import_preview.php';

        try {
            $data = XmlcatagImportPreviewHelper::run($dir, $selected);
            $app->setUserState('com_xmlcatag.preview.' . $selected, $data);
        } catch (Throwable $e) {
            $app->enqueueMessage('Preview fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_xmlcatag&selected_file=' . urlencode($selected) . '&show_preview=1');
    }

    public function import()
    {
        $app = $this->getApplicationWithTokenCheck();

        $selected = basename((string) $app->input->getString('selected_file', ''));
        $app->setUserState('com_xmlcatag.selected_file', $selected);

        if (!$selected) {
            $app->enqueueMessage('Import geweigerd: selecteer eerst een bestand en voer een preview uit.', 'warning');
            $this->setRedirect('index.php?option=com_xmlcatag');
            return;
        }

        // Enforce mandatory preview per file
        $preview = $app->getUserState('com_xmlcatag.preview.' . $selected);
        if (!$preview) {
            $app->enqueueMessage('Import geweigerd: voer eerst een preview uit voor dit bestand.', 'warning');
            $this->setRedirect('index.php?option=com_xmlcatag');
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
                $this->setRedirect('index.php?option=com_xmlcatag');
                return;
            }
        }

        require_once __DIR__ . '/helpers/import.php';

        $dryRun = (bool) $app->input->getInt('dry_run', 0);

        try {
            $result = XmlcatagImportHelper::runSingle(JPATH_ROOT . '/import/xmlcatag', $selected, $dryRun, $exclude);
            $app->enqueueMessage($result, 'message');
            // Clear preview for this file after successful import
            $app->setUserState('com_xmlcatag.preview.' . $selected, null);
        } catch (Throwable $e) {
            $app->enqueueMessage('Import fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_xmlcatag');
    }

    public function deletefile()
    {
        $app = $this->getApplicationWithTokenCheck();
        $file = basename($app->input->getCmd('file'));

        $path = JPATH_ROOT . '/import/xmlcatag/' . $file;

        if ($file && is_file($path)) {
            unlink($path);
            $app->enqueueMessage('Bestand verwijderd: ' . $file, 'message');
        }

        // Remove preview so UI refreshes
        $app->setUserState('com_xmlcatag.preview', null);
        $this->setRedirect('index.php?option=com_xmlcatag');
    }

    public function rollback()
    {
        $app = $this->getApplicationWithTokenCheck();
        $app->setUserState('com_xmlcatag.show_rollback', 1);
        $this->setRedirect('index.php?option=com_xmlcatag&show_rollback=1');
    }

    public function refreshsftp()
    {
        $app = $this->getApplicationWithTokenCheck();
        $app->setUserState('com_xmlcatag.selected_file', '');
        $this->setRedirect('index.php?option=com_xmlcatag');
    }

    public function rollbackapply()
    {
        require_once __DIR__ . '/helpers/rollback.php';
        $app = $this->getApplicationWithTokenCheck();

        $type = $app->input->getCmd('rollback_type', 'categories');
        $file = basename((string) $app->input->getString('rollback_file', ''));

        try {
            $result = XmlcatagRollbackHelper::apply($type, $file);
            $app->enqueueMessage($result, 'message');
        } catch (Throwable $e) {
            $app->enqueueMessage('Rollback fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_xmlcatag&show_rollback=1');
    }

    public function resetpreview()
    {
        $app = $this->getApplicationWithTokenCheck();
        $selected = basename((string) $app->input->getString('selected_file', ''));
        if ($selected) {
            $app->setUserState('com_xmlcatag.preview.' . $selected, null);
        }
        $app->setUserState('com_xmlcatag.selected_file', '');
        $this->setRedirect('index.php?option=com_xmlcatag');
    }

    public function export()
    {
        require_once __DIR__ . '/helpers/export.php';
        $app = $this->getApplicationWithTokenCheck();

        try {
            $result = XmlcatagExportHelper::run(JPATH_ROOT . '/export/xmlcatag');
            $app->enqueueMessage($result, 'message');
        } catch (Throwable $e) {
            $app->enqueueMessage('Export fout: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_xmlcatag');
    }

    protected function getApplicationWithTokenCheck(): CMSApplicationInterface
    {
        if (!Session::checkToken()) {
            throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        return Factory::getApplication();
    }
}
