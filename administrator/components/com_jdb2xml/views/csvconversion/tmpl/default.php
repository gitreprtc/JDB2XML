<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

?>

<div class="jdb2xml-tagconversion">
  <h2>CSV conversie</h2>
  <p>Upload CSV-bestanden om tags, categorieën en artikelen om te zetten naar het XML-formaat voor import.</p>

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
      <p class="jdb2xml-tagconversion-status" data-file-status="csv_file_tags">
        Kies een CSV-bestand om te laden.
      </p>
    </form>
  </div>

  <div class="jdb2xml-tagconversion-divider" role="presentation"></div>

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
      <p class="jdb2xml-tagconversion-status" data-file-status="csv_file_categories">
        Kies een CSV-bestand om te laden.
      </p>
    </form>
  </div>

  <div class="jdb2xml-tagconversion-divider" role="presentation"></div>

  <div class="jdb2xml-tagconversion-section">
    <h3>Artikelen</h3>
    <form action="index.php?option=com_jdb2xml" method="post" enctype="multipart/form-data" class="jdb2xml-tagconversion-form">
      <input type="hidden" name="task" value="csvconversionupload">
      <input type="hidden" name="conversion_type" value="articles">
      <?php echo HTMLHelper::_('form.token'); ?>

      <div class="jdb2xml-tagconversion-row">
        <label for="csv_file_articles"><strong>CSV-bestand</strong></label>
        <input type="file" name="csv_file" id="csv_file_articles" accept=".csv,text/csv">
        <button type="submit" class="btn btn-primary">Maak XML</button>
      </div>
      <p class="jdb2xml-tagconversion-status" data-file-status="csv_file_articles">
        Kies een CSV-bestand om te laden.
      </p>
    </form>
  </div>
</div>

<style>
.jdb2xml-tagconversion { padding: 16px 0; }
.jdb2xml-tagconversion-form { margin-top: 12px; }
.jdb2xml-tagconversion-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.jdb2xml-tagconversion-section { margin-top: 18px; }
.jdb2xml-tagconversion-divider { margin: 20px 0; border-top: 1px solid #e5e5e5; }
.jdb2xml-tagconversion-status { margin: 8px 0 0; color: #555; font-size: 12px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[type="file"][name="csv_file"]').forEach(function (input) {
    input.addEventListener('change', function () {
      var status = document.querySelector('[data-file-status="' + input.id + '"]');
      if (!status) {
        return;
      }
      if (input.files && input.files.length > 0) {
        status.textContent = 'CSV geladen: ' + input.files[0].name + '. Klik op "Maak XML".';
      } else {
        status.textContent = 'Kies een CSV-bestand om te laden.';
      }
    });
  });
});
</script>
