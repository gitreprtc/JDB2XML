<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;
?>

<div class="jdb2xml-export">
  <h2>Export</h2>

  <div class="jdb2xml-export-section">
    <h3>Manual export</h3>
    <div class="jdb2xml-export-list">
      <div class="jdb2xml-export-row">
        <span class="jdb2xml-export-label">Tags</span>
        <form action="index.php?option=com_jdb2xml" method="post">
          <input type="hidden" name="task" value="exportmanual">
          <input type="hidden" name="export_type" value="tags">
          <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
          <button type="submit" class="btn btn-primary">Manual export</button>
        </form>
      </div>
      <div class="jdb2xml-export-row">
        <span class="jdb2xml-export-label">Categories</span>
        <form action="index.php?option=com_jdb2xml" method="post">
          <input type="hidden" name="task" value="exportmanual">
          <input type="hidden" name="export_type" value="categories">
          <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
          <button type="submit" class="btn btn-primary">Manual export</button>
        </form>
      </div>
      <div class="jdb2xml-export-row">
        <span class="jdb2xml-export-label">Phoca Gallery Categories</span>
        <form action="index.php?option=com_jdb2xml" method="post">
          <input type="hidden" name="task" value="exportmanual">
          <input type="hidden" name="export_type" value="phocagallerycategories">
          <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
          <button type="submit" class="btn btn-primary">Manual export</button>
        </form>
      </div>
      <div class="jdb2xml-export-row">
        <span class="jdb2xml-export-label">Phoca Gallery Images</span>
        <form action="index.php?option=com_jdb2xml" method="post">
          <input type="hidden" name="task" value="exportmanual">
          <input type="hidden" name="export_type" value="phocagalleryimages">
          <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
          <button type="submit" class="btn btn-primary">Manual export</button>
        </form>
      </div>
      <div class="jdb2xml-export-row">
        <span class="jdb2xml-export-label">Articles</span>
        <form action="index.php?option=com_jdb2xml" method="post">
          <input type="hidden" name="task" value="exportmanual">
          <input type="hidden" name="export_type" value="articles">
          <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
          <button type="submit" class="btn btn-primary">Manual export</button>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
.jdb2xml-export { padding: 16px 0; }
.jdb2xml-export-section { margin-bottom: 18px; }
.jdb2xml-export-list { display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-export-row { display: flex; align-items: center; gap: 12px; }
.jdb2xml-export-label { min-width: 120px; font-weight: 600; }
</style>
