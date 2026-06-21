<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;
?>

<div class="jdb2xml-landing">
  <h2>JDB2XML</h2>
  <p>Welcome! This is the landing page. We will fill the content later.</p>

  <div class="jdb2xml-landing-links">
    <a class="btn btn-primary" href="index.php?option=com_jdb2xml&view=controlpanel">Manual Import</a>
    <a class="btn btn-secondary jdb2xml-hidden" href="index.php?option=com_jdb2xml&view=importautomatic">Automatic Import</a>
    <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=export">Export</a>
    <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=csvconversion">CSV conversie</a>
    <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=rollback">Rollback</a>
  </div>
</div>

<style>
.jdb2xml-landing { padding: 16px 0; }
.jdb2xml-landing-links { display:flex; gap: 12px; flex-wrap: wrap; margin: 12px 0; }
.jdb2xml-hidden { display: none; }
</style>
