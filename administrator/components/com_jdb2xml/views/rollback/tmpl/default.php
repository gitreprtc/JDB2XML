<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

require_once __DIR__ . '/../../../helpers/rollback.php';

$rollbackCategories = Jdb2xmlRollbackHelper::listLogsByType('categories');
$rollbackTags = Jdb2xmlRollbackHelper::listLogsByType('tags');
?>

<div class="jdb2xml">
  <h2>Rollback</h2>
  <div class="jdb2xml-back">
    <a class="btn btn-secondary" href="index.php?option=com_jdb2xml&view=landing">Terug naar landingspagina</a>
  </div>
  <div class="jdb2xml-grid">
    <div class="jdb2xml-left">
      <h4>Categorieën</h4>
      <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-rollback-form">
        <input type="hidden" name="task" value="rollbackapply">
        <input type="hidden" name="rollback_type" value="categories">
        <?php echo HTMLHelper::_('form.token'); ?>
        <select name="rollback_file" class="jdb2xml-rollback-select">
          <option value="">-- selecteer --</option>
          <?php foreach ($rollbackCategories as $file): ?>
            <option value="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-warning">Rollback categorieën</button>
      </form>
    </div>
    <div class="jdb2xml-right">
      <h4>Tags</h4>
      <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-rollback-form">
        <input type="hidden" name="task" value="rollbackapply">
        <input type="hidden" name="rollback_type" value="tags">
        <?php echo HTMLHelper::_('form.token'); ?>
        <select name="rollback_file" class="jdb2xml-rollback-select">
          <option value="">-- selecteer --</option>
          <?php foreach ($rollbackTags as $file): ?>
            <option value="<?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-warning">Rollback tags</button>
      </form>
    </div>
  </div>
</div>

<style>
.jdb2xml { padding: 8px 0; }
.jdb2xml-back { margin: 6px 0 12px; }
.jdb2xml-grid { display:flex; gap: 18px; align-items:flex-start; }
.jdb2xml-left { flex: 1 1 65%; min-width: 380px; }
.jdb2xml-right { flex: 0 0 35%; min-width: 280px; }
.jdb2xml-rollback-form { display:flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.jdb2xml-rollback-select { min-width: 240px; }
</style>
