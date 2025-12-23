<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Throwable;

class Jdb2xmlViewExport extends HtmlView
{
    public function display($tpl = null)
    {
        $doc = Factory::getDocument();
        $doc->addStyleDeclaration('
            #toolbar-home .btn{background:#2e7d32;border-color:#2e7d32;color:#fff;}
        ');
        $db = Factory::getDbo();
        try {
            $phocaColumns = $db->getTableColumns('#__phocagallery_categories', false);
        } catch (Throwable $e) {
            $phocaColumns = [];
        }
        $this->phocaAvailable = !empty($phocaColumns);
        $this->params = ComponentHelper::getParams('com_jdb2xml');
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('JDB2XML - Export', 'stack');
        ToolbarHelper::link('index.php?option=com_jdb2xml&view=landing', 'Main menu', 'home');
    }
}
