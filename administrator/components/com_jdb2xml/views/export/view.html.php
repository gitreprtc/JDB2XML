<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;

class Jdb2xmlViewExport extends HtmlView
{
    public function display($tpl = null)
    {
        $doc = Factory::getDocument();
        $doc->addStyleDeclaration('
            #toolbar-home{margin-left:auto;}
            #toolbar-home .btn{background:#2e7d32;border-color:#2e7d32;color:#fff;}
        ');
        $this->params = ComponentHelper::getParams('com_jdb2xml');
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('JDB2XML - Export', 'stack');
        ToolbarHelper::custom('export', 'download', 'download', 'Export', false);
        ToolbarHelper::link('index.php?option=com_jdb2xml&view=landing', 'Main menu', 'home');
    }
}
