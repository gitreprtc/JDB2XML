<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
?>

<div class="jdb2xml-export">
  <div class="jdb2xml-brand">
    <img src="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_logo.svg" alt="JDB2XML logo">
  </div>
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
      <?php if (!empty($this->phocaAvailable)) : ?>
        <div class="jdb2xml-export-row">
          <span class="jdb2xml-export-label">Phoca Gallery Categories</span>
          <form action="index.php?option=com_jdb2xml" method="post">
            <input type="hidden" name="task" value="exportmanual">
            <input type="hidden" name="export_type" value="phocagallerycategories">
            <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
            <button type="submit" class="btn btn-primary">Manual export</button>
          </form>
        </div>
      <?php endif; ?>
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
  <div class="jdb2xml-copyright">Copyright Robin Colbers</div>
</div>

<style>
.jdb2xml-export { padding: 16px 0; }
.jdb2xml-export-section { margin-bottom: 18px; }
.jdb2xml-export-list { display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-export-row { display: flex; align-items: center; gap: 8px; }
.jdb2xml-export-row form { margin: 0; }
.jdb2xml-export-label { font-weight: 600; min-width: 220px; }
.jdb2xml-brand { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.jdb2xml-brand img { max-width: 220px; height: auto; }
.jdb2xml-copyright { margin-top: 20px; font-size: 12px; color: #666; }
</style>
