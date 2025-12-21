<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$preview = $this->preview ?? null;
$previewXml = is_array($preview) ? (string) ($preview['xml'] ?? '') : '';
$previewFile = is_array($preview) ? (string) ($preview['filename'] ?? '') : '';
$previewCount = is_array($preview) ? (int) ($preview['count'] ?? 0) : 0;
?>

<div class="jdb2xml-tagconversion">
  <h2>Tag conversie</h2>
  <p>Upload een CSV-bestand om tags om te zetten naar het XML-formaat voor import.</p>

  <form action="index.php?option=com_jdb2xml" method="post" enctype="multipart/form-data" class="jdb2xml-tagconversion-form">
    <input type="hidden" name="task" value="tagconversionupload">
    <?php echo HTMLHelper::_('form.token'); ?>

    <div class="jdb2xml-tagconversion-row">
      <label for="csv_file"><strong>CSV-bestand</strong></label>
      <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv">
      <button type="submit" class="btn btn-primary">Maak preview XML</button>
    </div>
  </form>

  <?php if ($previewXml !== '') : ?>
    <div class="jdb2xml-tagconversion-preview">
      <h3>Preview</h3>
      <p>
        <?php echo htmlspecialchars($previewCount . ' tags gegenereerd', ENT_QUOTES, 'UTF-8'); ?>
        <?php if ($previewFile !== '') : ?>
          — opgeslagen als <strong><?php echo htmlspecialchars($previewFile, ENT_QUOTES, 'UTF-8'); ?></strong> in de importmap.
        <?php endif; ?>
      </p>
      <textarea readonly rows="16"><?php echo htmlspecialchars($previewXml, ENT_QUOTES, 'UTF-8'); ?></textarea>
      <?php if ($previewFile !== '') : ?>
        <p>
          <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=controlpanel&selected_file=<?php echo rawurlencode($previewFile); ?>&show_preview=1">
            Ga naar import preview
          </a>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<style>
.jdb2xml-tagconversion { padding: 16px 0; }
.jdb2xml-tagconversion-form { margin-top: 12px; }
.jdb2xml-tagconversion-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.jdb2xml-tagconversion-preview { margin-top: 18px; }
.jdb2xml-tagconversion-preview textarea { width: 100%; max-width: 100%; font-family: monospace; }
</style>
