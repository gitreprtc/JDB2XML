<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
?>

<div class="jdb2xml-landing">
  <div class="jdb2xml-brand">
    <img src="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_logo.svg" alt="JDB2XML logo">
  </div>
  <div class="jdb2xml-landing-content">
    <h2>JDB2XML</h2>
    <p>
      Welcome to JDB2XML, the plugin that allows you to import data into your Joomla database using XML or CSV files.
      Exporting data is also fully supported.
    </p>
    <p>
      CSV files are provided on this page as ready-to-use templates. XML template files can be easily obtained by exporting
      data from your existing database. This can be done for Categories, Tags, and Articles. If you have Phoca Gallery
      installed, you can also import and export data for Phoca Gallery Tags with the same ease.
    </p>
    <p>
      In addition to importing and exporting, JDB2XML also supports rollback functionality. If a change does not produce
      the desired result, a single click is almost enough to restore the previous state. But it goes even further: a new
      feature allows you to precisely select which changes you want to roll back, giving you full control over your data
      management process.
    </p>
  </div>

  <div class="jdb2xml-landing-downloads">
    <h3>CSV templates</h3>
    <div class="jdb2xml-landing-downloads-list">
      <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_tags_template.csv" download>
        Download tags CSV-template
      </a>
      <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_phocagallery_tags_template.csv" download>
        Download Phoca Gallery Tags CSV-template
      </a>
      <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_categories_template.csv" download>
        Download categorieën CSV-template
      </a>
      <a class="btn btn-outline-secondary" href="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_articles_template.csv" download>
        Download artikel CSV-template
      </a>
    </div>
    <div class="jdb2xml-landing-paths">
      <p><strong>Import locatie (XML):</strong> <code>/media/com_jdb2xml/import</code></p>
      <p><strong>Export locatie (XML):</strong> <code>/media/com_jdb2xml/export</code></p>
    </div>
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

  <div class="jdb2xml-copyright">Copyright Robin Colbers</div>
</div>

<style>
.jdb2xml-landing { padding: 8px 0; position: relative; padding-right: 260px; }
.jdb2xml-landing-downloads { margin-top: 20px; }
.jdb2xml-landing-downloads-list { display: flex; gap: 12px; flex-wrap: wrap; }
.jdb2xml-landing-paths { margin-top: 12px; }
.jdb2xml-brand { position: absolute; top: 0; right: 0; }
.jdb2xml-brand img { max-width: 220px; height: auto; }
.jdb2xml-landing-content { max-width: 720px; }
.jdb2xml-copyright { margin-top: 20px; font-size: 12px; color: #666; }
.jdb2xml-hidden { display: none; }
</style>
