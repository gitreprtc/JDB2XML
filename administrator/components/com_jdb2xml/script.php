<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Filesystem\Folder;

class com_jdb2xmlInstallerScript
{
    public function postflight(string $type, $parent): void
    {
        $importPath = JPATH_ROOT . '/media/com_jdb2xml/import';
        $exportPath = JPATH_ROOT . '/media/com_jdb2xml/export';

        if (!Folder::exists($importPath)) {
            Folder::create($importPath);
        }

        if (!Folder::exists($exportPath)) {
            Folder::create($exportPath);
        }
    }
}
