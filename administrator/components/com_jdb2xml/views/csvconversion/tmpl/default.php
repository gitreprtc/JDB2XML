<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

?>

<div class="jdb2xml-tagconversion">
  <h2>CSV conversie</h2>
  <p>Upload CSV-bestanden om tags en categorieën om te zetten naar het XML-formaat voor import.</p>

  <div class="jdb2xml-tagconversion-section">
    <h3>Tags</h3>
    <form action="index.php?option=com_jdb2xml" method="post" enctype="multipart/form-data" class="jdb2xml-tagconversion-form">
      <input type="hidden" name="task" value="csvconversionupload">
      <input type="hidden" name="conversion_type" value="tags">
      <?php echo HTMLHelper::_('form.token'); ?>

      <div class="jdb2xml-tagconversion-row">
        <label for="csv_file_tags"><strong>CSV-bestand</strong></label>
        <input type="file" name="csv_file" id="csv_file_tags" accept=".csv,text/csv">
        <button type="submit" class="btn btn-primary">Maak XML</button>
      </div>
    </form>
  </div>

  <div class="jdb2xml-tagconversion-section">
    <h3>Categorieën</h3>
    <form action="index.php?option=com_jdb2xml" method="post" enctype="multipart/form-data" class="jdb2xml-tagconversion-form">
      <input type="hidden" name="task" value="csvconversionupload">
      <input type="hidden" name="conversion_type" value="categories">
      <?php echo HTMLHelper::_('form.token'); ?>

      <div class="jdb2xml-tagconversion-row">
        <label for="csv_file_categories"><strong>CSV-bestand</strong></label>
        <input type="file" name="csv_file" id="csv_file_categories" accept=".csv,text/csv">
        <button type="submit" class="btn btn-primary">Maak XML</button>
      </div>
    </form>
  </div>
</div>

<style>
.jdb2xml-tagconversion { padding: 16px 0; }
.jdb2xml-tagconversion-form { margin-top: 12px; }
.jdb2xml-tagconversion-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.jdb2xml-tagconversion-section { margin-top: 18px; }
</style>
