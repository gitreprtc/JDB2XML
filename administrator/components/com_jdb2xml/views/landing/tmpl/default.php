<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;
?>

<div class="jdb2xml-landing">
  <h2>JDB2XML</h2>
  <p>Welcome! This is the landing page. We will fill the content later.</p>

  <h3>Set-Up</h3>
  <p>Configure your cron scheduler to call Joomla’s scheduler runner. Use your site base URL:</p>
  <ul>
    <li><strong>Import schedule</strong>: <code>https://YOUR-DOMAIN/administrator/index.php?option=com_scheduler&amp;task=run&amp;interval=1</code></li>
    <li><strong>Export schedule</strong>: <code>https://YOUR-DOMAIN/administrator/index.php?option=com_scheduler&amp;task=run&amp;interval=1</code></li>
  </ul>
  <p>Set the cron interval to 1 minute so the scheduled tasks can run at the configured minute-based cadence.</p>

  <div class="jdb2xml-landing-links">
    <a class="btn btn-primary" href="index.php?option=com_jdb2xml&view=controlpanel">Manual Import</a>
    <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=importautomatic">Automatic Import</a>
    <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=export">Export</a>
    <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=rollback">Rollback</a>
  </div>
</div>

<style>
.jdb2xml-landing { padding: 16px 0; }
.jdb2xml-landing-links { display:flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
</style>
