<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class Jdb2xmlViewLanding extends HtmlView
{
    public function display($tpl = null)
    {
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        ToolbarHelper::title('JDB2XML', 'stack');
    }
}
