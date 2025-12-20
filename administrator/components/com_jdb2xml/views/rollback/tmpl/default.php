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
    <a class="btn btn-success" href="index.php?option=com_jdb2xml&view=landing">Hoofdmenu</a>
  </div>
  <div class="jdb2xml-section">
    <h3>Categorieën</h3>
    <?php if (empty($rollbackCategories)): ?>
      <div class="jdb2xml-empty">Geen herstelpunten voor categorieën.</div>
    <?php else: ?>
      <?php foreach ($rollbackCategories as $log): ?>
        <?php
          $hasSelectable = false;
          foreach ($log['created'] as $item) {
              if (!$item['rolled_back']) { $hasSelectable = true; break; }
          }
          if (!$hasSelectable) {
              foreach ($log['updated'] as $item) {
                  if (!$item['rolled_back']) { $hasSelectable = true; break; }
              }
          }
        ?>
        <details class="jdb2xml-rollback-item">
          <summary>Herstelpunt <?php echo htmlspecialchars($log['label'], ENT_QUOTES, 'UTF-8'); ?></summary>
          <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-rollback-form">
            <input type="hidden" name="task" value="rollbackapply">
            <input type="hidden" name="rollback_type" value="categories">
            <input type="hidden" name="rollback_file" value="<?php echo htmlspecialchars($log['file'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <ul class="jdb2xml-rollback-list">
              <?php foreach ($log['created'] as $item): ?>
                <li>
                  <label class="jdb2xml-rollback-item-label">
                    <input type="checkbox"
                           name="rollback_items[created][]"
                           value="<?php echo (int)$item['id']; ?>"
                           <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                    Aangemaakt: ID <?php echo (int)$item['id']; ?>
                  </label>
                  <?php if ($item['rolled_back']): ?>
                    <span class="jdb2xml-rollback-status">Rollback uitgevoerd</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
              <?php foreach ($log['updated'] as $item): ?>
                <li>
                  <label class="jdb2xml-rollback-item-label">
                    <input type="checkbox"
                           name="rollback_items[updated][]"
                           value="<?php echo (int)$item['id']; ?>"
                           <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                    Gewijzigd: ID <?php echo (int)$item['id']; ?>
                    <?php if (!empty($item['fields'])): ?>
                      <span class="jdb2xml-rollback-fields">
                        (<?php echo htmlspecialchars(implode(', ', array_map(static function ($key, $value) {
                            return $key . '=' . (is_scalar($value) ? (string)$value : json_encode($value));
                        }, array_keys($item['fields']), $item['fields'])), ENT_QUOTES, 'UTF-8'); ?>)
                      </span>
                    <?php endif; ?>
                  </label>
                  <?php if ($item['rolled_back']): ?>
                    <span class="jdb2xml-rollback-status">Rollback uitgevoerd</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <button type="submit" class="btn btn-warning" <?php echo $hasSelectable ? '' : 'disabled'; ?>>
              Rollback deze wijziging
            </button>
          </form>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="jdb2xml-section">
    <h3>Tags</h3>
    <?php if (empty($rollbackTags)): ?>
      <div class="jdb2xml-empty">Geen herstelpunten voor tags.</div>
    <?php else: ?>
      <?php foreach ($rollbackTags as $log): ?>
        <?php
          $hasSelectable = false;
          foreach ($log['created'] as $item) {
              if (!$item['rolled_back']) { $hasSelectable = true; break; }
          }
          if (!$hasSelectable) {
              foreach ($log['updated'] as $item) {
                  if (!$item['rolled_back']) { $hasSelectable = true; break; }
              }
          }
        ?>
        <details class="jdb2xml-rollback-item">
          <summary>Herstelpunt <?php echo htmlspecialchars($log['label'], ENT_QUOTES, 'UTF-8'); ?></summary>
          <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-rollback-form">
            <input type="hidden" name="task" value="rollbackapply">
            <input type="hidden" name="rollback_type" value="tags">
            <input type="hidden" name="rollback_file" value="<?php echo htmlspecialchars($log['file'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <ul class="jdb2xml-rollback-list">
              <?php foreach ($log['created'] as $item): ?>
                <li>
                  <label class="jdb2xml-rollback-item-label">
                    <input type="checkbox"
                           name="rollback_items[created][]"
                           value="<?php echo (int)$item['id']; ?>"
                           <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                    Aangemaakt: ID <?php echo (int)$item['id']; ?>
                  </label>
                  <?php if ($item['rolled_back']): ?>
                    <span class="jdb2xml-rollback-status">Rollback uitgevoerd</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
              <?php foreach ($log['updated'] as $item): ?>
                <li>
                  <label class="jdb2xml-rollback-item-label">
                    <input type="checkbox"
                           name="rollback_items[updated][]"
                           value="<?php echo (int)$item['id']; ?>"
                           <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                    Gewijzigd: ID <?php echo (int)$item['id']; ?>
                    <?php if (!empty($item['fields'])): ?>
                      <span class="jdb2xml-rollback-fields">
                        (<?php echo htmlspecialchars(implode(', ', array_map(static function ($key, $value) {
                            return $key . '=' . (is_scalar($value) ? (string)$value : json_encode($value));
                        }, array_keys($item['fields']), $item['fields'])), ENT_QUOTES, 'UTF-8'); ?>)
                      </span>
                    <?php endif; ?>
                  </label>
                  <?php if ($item['rolled_back']): ?>
                    <span class="jdb2xml-rollback-status">Rollback uitgevoerd</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <button type="submit" class="btn btn-warning" <?php echo $hasSelectable ? '' : 'disabled'; ?>>
              Rollback deze wijziging
            </button>
          </form>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<style>
.jdb2xml { padding: 8px 0; }
.jdb2xml-back { margin: 6px 0 12px; }
.jdb2xml-section { margin-bottom: 18px; }
.jdb2xml-rollback-item { margin: 10px 0; border: 1px solid #e5e5e5; padding: 8px 12px; border-radius: 6px; background: #fff; }
.jdb2xml-rollback-item summary { cursor: pointer; font-weight: 600; }
.jdb2xml-rollback-form { margin-top: 10px; display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-rollback-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
.jdb2xml-rollback-item-label { display: inline-flex; align-items: center; gap: 8px; }
.jdb2xml-rollback-fields { color: #666; font-size: 12px; }
.jdb2xml-rollback-status { margin-left: 8px; color: #2e7d32; font-weight: 600; font-size: 12px; }
.jdb2xml-empty { color: #666; font-style: italic; }
</style>
