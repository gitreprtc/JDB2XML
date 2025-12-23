<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
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

  <div class="jdb2xml-landing-downloads">
    <h3>CSV templates</h3>
    <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_articles_template.csv" download>
      Download artikel CSV-template
    </a>
  </div>

  <h3 class="jdb2xml-hidden">Set-Up</h3>
  <div class="jdb2xml-hidden">
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
  </div>
</div>

<style>
.jdb2xml-landing { padding: 16px 0; }
.jdb2xml-landing-links { display:flex; gap: 12px; flex-wrap: wrap; margin: 12px 0; }
.jdb2xml-landing-downloads { margin-top: 20px; }
.jdb2xml-hidden { display: none; }
</style>
