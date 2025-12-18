<?php
defined('_JEXEC') or die;
require_once __DIR__ . '/controller.php';
$controller = new XmlcatagController();
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
