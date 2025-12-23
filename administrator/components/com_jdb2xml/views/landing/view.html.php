<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;

class Jdb2xmlViewLanding extends HtmlView
{
    public function display($tpl = null)
    {
        $doc = Factory::getDocument();
        $doc->addStyleDeclaration('
            #toolbar-home .btn{background:#2e7d32;border-color:#2e7d32;color:#fff;}
            #toolbar-rollback .btn{background:#c62828;border-color:#b71c1c;color:#fff;}
        ');
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('JDB2XML', 'stack');
        ToolbarHelper::link('index.php?option=com_jdb2xml&view=controlpanel', 'Manual Import', 'upload');
        ToolbarHelper::link('index.php?option=com_jdb2xml&view=export', 'Export', 'download');
        ToolbarHelper::link('index.php?option=com_jdb2xml&view=csvconversion', 'CSV conversie', 'list');
        ToolbarHelper::link('index.php?option=com_jdb2xml&view=rollback', 'Rollback', 'undo');
    }
}
