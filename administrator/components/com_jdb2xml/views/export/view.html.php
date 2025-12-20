<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class Jdb2xmlViewExport extends HtmlView
{
    public function display($tpl = null)
    {
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('JDB2XML - Export', 'stack');
        ToolbarHelper::custom('export', 'download', 'download', 'Export', false);
    }
}
