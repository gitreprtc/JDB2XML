<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;
?>

<div class="jdb2xml-landing">
  <h2>JDB2XML</h2>
  <p>Welcome! This is the landing page. We will fill the content later.</p>

  <h3>Set-Up</h3>
  <p>Configure your cron scheduler to call Joomla’s scheduler runner URL (no CLI needed):</p>
  <ul>
    <li><strong>Scheduler runner</strong>: <code>https://YOUR-DOMAIN/administrator/index.php?option=com_scheduler&amp;task=run&amp;interval=1</code></li>
  </ul>
  <p><strong>Setup steps:</strong></p>
  <ol>
    <li>Replace <code>YOUR-DOMAIN</code> with your site domain.</li>
    <li>Add the URL to your hosting cron scheduler (or system crontab).</li>
    <li>Set the cron interval to run every minute (the scheduler will decide whether tasks should run based on your per-day schedules).</li>
    <li>Make sure the Joomla “System - Scheduler” plugin is enabled.</li>
    <li>Save your Import/Export schedules in this component so tasks are created or updated.</li>
  </ol>
  <p>Import and export schedules use the same runner URL.</p>

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
