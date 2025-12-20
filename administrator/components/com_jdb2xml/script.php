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
        $processedPath = $importPath . '/processed';
        $automaticPath = $importPath . '/automatic';
        $rejectedPath = $importPath . '/rejected';
        $errorPath = $importPath . '/error';

        if (!Folder::exists($importPath)) {
            Folder::create($importPath);
        }

        if (!Folder::exists($exportPath)) {
            Folder::create($exportPath);
        }

        if (!Folder::exists($processedPath)) {
            Folder::create($processedPath);
        }

        if (!Folder::exists($automaticPath)) {
            Folder::create($automaticPath);
        }

        if (!Folder::exists($rejectedPath)) {
            Folder::create($rejectedPath);
        }

        if (!Folder::exists($errorPath)) {
            Folder::create($errorPath);
        }
    }
}
