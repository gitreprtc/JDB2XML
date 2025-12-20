<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;
?>

<div class="jdb2xml-landing">
  <h2>JDB2XML</h2>
  <p>Welcome! This is the landing page. We will fill the content later.</p>

  <h3>Set-Up</h3>
  <p>Configure your cron scheduler to call Joomla’s CLI scheduler runner (recommended):</p>
  <ul>
    <li><strong>Scheduler runner</strong>: <code>/usr/bin/php /path/to/joomla/administrator/cli/joomla.php scheduler:run</code></li>
  </ul>
  <p>Set the cron interval to 1 minute so the scheduled tasks can run at the configured minute-based cadence. Import and export schedules use the same runner command.</p>

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
