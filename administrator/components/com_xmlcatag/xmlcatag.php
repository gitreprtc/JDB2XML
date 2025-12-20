<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/controller.php';

$controller = new XmlcatagController();
$controller->execute(Factory::getApplication()->getInput()->get('task'));
$controller->redirect();
