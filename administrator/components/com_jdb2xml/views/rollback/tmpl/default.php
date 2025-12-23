<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/../../../helpers/rollback.php';

$rollbackCategories = Jdb2xmlRollbackHelper::listLogsByType('categories');
$rollbackTags = Jdb2xmlRollbackHelper::listLogsByType('tags');
$rollbackArticles = Jdb2xmlRollbackHelper::listLogsByType('articles');
?>

<div class="jdb2xml">
  <div class="jdb2xml-brand">
    <img src="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_logo.svg" alt="JDB2XML logo">
  </div>
  <h2>Rollback</h2>
  <div class="jdb2xml-section">
    <h3>Categories</h3>
    <?php if (empty($rollbackCategories)): ?>
      <div class="jdb2xml-empty">No restore points for categories.</div>
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
          $entityIds = [];
          foreach ($log['created'] as $item) {
              $entityIds[] = (int)$item['id'];
          }
          foreach ($log['updated'] as $item) {
              $entityIds[] = (int)$item['id'];
          }
          $entities = Jdb2xmlRollbackHelper::fetchEntities('categories', $entityIds);
        ?>
        <details class="jdb2xml-rollback-item">
          <summary>Restore point <?php echo htmlspecialchars($log['label'], ENT_QUOTES, 'UTF-8'); ?></summary>
          <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-rollback-form">
            <input type="hidden" name="task" value="rollbackapply">
            <input type="hidden" name="rollback_type" value="categories">
            <input type="hidden" name="rollback_file" value="<?php echo htmlspecialchars($log['file'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <table class="jdb2xml-table">
              <thead>
                <tr>
                  <th class="jdb2xml-head-check">Action</th>
                  <th class="jdb2xml-head-title">Title</th>
                  <th>Path</th>
                  <th></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($log['created'] as $item): ?>
                  <?php
                    $entity = $entities[(int)$item['id']] ?? ['title' => '', 'path' => ''];
                  ?>
                  <tr class="jdb2xml-row">
                    <td class="jdb2xml-cell jdb2xml-check">
                      <label class="jdb2xml-exclude">
                        <input type="checkbox"
                               name="rollback_items[created][]"
                               value="<?php echo (int)$item['id']; ?>"
                               <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                        <span>Created</span>
                      </label>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-title">
                      <?php echo htmlspecialchars((string)$entity['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-path">
                      <?php if (!empty($entity['path'])): ?>
                        <code><?php echo htmlspecialchars((string)$entity['path'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-actions"></td>
                    <td class="jdb2xml-cell jdb2xml-badge-cell">
                      <?php if ($item['rolled_back']): ?>
                        <span class="jdb2xml-badge jdb2xml-rollback-status">Rollback completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($log['updated'] as $item): ?>
                  <?php
                    $entity = $entities[(int)$item['id']] ?? ['title' => '', 'path' => ''];
                    $fieldsText = '';
                    if (!empty($item['fields'])) {
                        $fieldsText = implode(', ', array_map(static function ($key, $value) {
                            return $key . '=' . (is_scalar($value) ? (string)$value : json_encode($value));
                        }, array_keys($item['fields']), $item['fields']));
                    }
                  ?>
                  <tr class="jdb2xml-row">
                    <td class="jdb2xml-cell jdb2xml-check">
                      <label class="jdb2xml-exclude">
                        <input type="checkbox"
                               name="rollback_items[updated][]"
                               value="<?php echo (int)$item['id']; ?>"
                               <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                        <span>Updated</span>
                      </label>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-title">
                      <?php echo htmlspecialchars((string)$entity['title'], ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($fieldsText !== ''): ?>
                        <div class="jdb2xml-rollback-fields"><?php echo htmlspecialchars($fieldsText, ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-path">
                      <?php if (!empty($entity['path'])): ?>
                        <code><?php echo htmlspecialchars((string)$entity['path'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-actions"></td>
                    <td class="jdb2xml-cell jdb2xml-badge-cell">
                      <?php if ($item['rolled_back']): ?>
                        <span class="jdb2xml-badge jdb2xml-rollback-status">Rollback completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <button type="submit" class="btn btn-warning" <?php echo $hasSelectable ? '' : 'disabled'; ?>>
              Rollback selected changes
            </button>
          </form>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="jdb2xml-section">
    <h3>Tags</h3>
    <?php if (empty($rollbackTags)): ?>
      <div class="jdb2xml-empty">No restore points for tags.</div>
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
          $entityIds = [];
          foreach ($log['created'] as $item) {
              $entityIds[] = (int)$item['id'];
          }
          foreach ($log['updated'] as $item) {
              $entityIds[] = (int)$item['id'];
          }
          $entities = Jdb2xmlRollbackHelper::fetchEntities('tags', $entityIds);
        ?>
        <details class="jdb2xml-rollback-item">
          <summary>Restore point <?php echo htmlspecialchars($log['label'], ENT_QUOTES, 'UTF-8'); ?></summary>
          <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-rollback-form">
            <input type="hidden" name="task" value="rollbackapply">
            <input type="hidden" name="rollback_type" value="tags">
            <input type="hidden" name="rollback_file" value="<?php echo htmlspecialchars($log['file'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <table class="jdb2xml-table">
              <thead>
                <tr>
                  <th class="jdb2xml-head-check">Action</th>
                  <th class="jdb2xml-head-title">Title</th>
                  <th>Path</th>
                  <th></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($log['created'] as $item): ?>
                  <?php
                    $entity = $entities[(int)$item['id']] ?? ['title' => '', 'path' => ''];
                  ?>
                  <tr class="jdb2xml-row">
                    <td class="jdb2xml-cell jdb2xml-check">
                      <label class="jdb2xml-exclude">
                        <input type="checkbox"
                               name="rollback_items[created][]"
                               value="<?php echo (int)$item['id']; ?>"
                               <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                        <span>Created</span>
                      </label>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-title">
                      <?php echo htmlspecialchars((string)$entity['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-path">
                      <?php if (!empty($entity['path'])): ?>
                        <code><?php echo htmlspecialchars((string)$entity['path'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-actions"></td>
                    <td class="jdb2xml-cell jdb2xml-badge-cell">
                      <?php if ($item['rolled_back']): ?>
                        <span class="jdb2xml-badge jdb2xml-rollback-status">Rollback completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($log['updated'] as $item): ?>
                  <?php
                    $entity = $entities[(int)$item['id']] ?? ['title' => '', 'path' => ''];
                    $fieldsText = '';
                    if (!empty($item['fields'])) {
                        $fieldsText = implode(', ', array_map(static function ($key, $value) {
                            return $key . '=' . (is_scalar($value) ? (string)$value : json_encode($value));
                        }, array_keys($item['fields']), $item['fields']));
                    }
                  ?>
                  <tr class="jdb2xml-row">
                    <td class="jdb2xml-cell jdb2xml-check">
                      <label class="jdb2xml-exclude">
                        <input type="checkbox"
                               name="rollback_items[updated][]"
                               value="<?php echo (int)$item['id']; ?>"
                               <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                        <span>Updated</span>
                      </label>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-title">
                      <?php echo htmlspecialchars((string)$entity['title'], ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($fieldsText !== ''): ?>
                        <div class="jdb2xml-rollback-fields"><?php echo htmlspecialchars($fieldsText, ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-path">
                      <?php if (!empty($entity['path'])): ?>
                        <code><?php echo htmlspecialchars((string)$entity['path'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-actions"></td>
                    <td class="jdb2xml-cell jdb2xml-badge-cell">
                      <?php if ($item['rolled_back']): ?>
                        <span class="jdb2xml-badge jdb2xml-rollback-status">Rollback completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <button type="submit" class="btn btn-warning" <?php echo $hasSelectable ? '' : 'disabled'; ?>>
              Rollback selected changes
            </button>
          </form>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="jdb2xml-section">
    <h3>Articles</h3>
    <?php if (empty($rollbackArticles)): ?>
      <div class="jdb2xml-empty">No restore points for articles.</div>
    <?php else: ?>
      <?php foreach ($rollbackArticles as $log): ?>
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
          $entityIds = [];
          foreach ($log['created'] as $item) {
              $entityIds[] = (int)$item['id'];
          }
          foreach ($log['updated'] as $item) {
              $entityIds[] = (int)$item['id'];
          }
          $entities = Jdb2xmlRollbackHelper::fetchEntities('articles', $entityIds);
        ?>
        <details class="jdb2xml-rollback-item">
          <summary>Restore point <?php echo htmlspecialchars($log['label'], ENT_QUOTES, 'UTF-8'); ?></summary>
          <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-rollback-form">
            <input type="hidden" name="task" value="rollbackapply">
            <input type="hidden" name="rollback_type" value="articles">
            <input type="hidden" name="rollback_file" value="<?php echo htmlspecialchars($log['file'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
            <table class="jdb2xml-table">
              <thead>
                <tr>
                  <th class="jdb2xml-head-check">Action</th>
                  <th class="jdb2xml-head-title">Title</th>
                  <th>Alias</th>
                  <th></th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($log['created'] as $item): ?>
                  <?php
                    $entity = $entities[(int)$item['id']] ?? ['title' => '', 'path' => ''];
                  ?>
                  <tr class="jdb2xml-row">
                    <td class="jdb2xml-cell jdb2xml-check">
                      <label class="jdb2xml-exclude">
                        <input type="checkbox"
                               name="rollback_items[created][]"
                               value="<?php echo (int)$item['id']; ?>"
                               <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                        <span>Created</span>
                      </label>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-title">
                      <?php echo htmlspecialchars((string)$entity['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-path">
                      <?php if (!empty($entity['path'])): ?>
                        <code><?php echo htmlspecialchars((string)$entity['path'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-actions"></td>
                    <td class="jdb2xml-cell jdb2xml-badge-cell">
                      <?php if ($item['rolled_back']): ?>
                        <span class="jdb2xml-badge jdb2xml-rollback-status">Rollback completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach ($log['updated'] as $item): ?>
                  <?php
                    $entity = $entities[(int)$item['id']] ?? ['title' => '', 'path' => ''];
                    $fieldsText = '';
                    if (!empty($item['fields'])) {
                        $fieldsText = implode(', ', array_map(static function ($key, $value) {
                            return $key . '=' . (is_scalar($value) ? (string)$value : json_encode($value));
                        }, array_keys($item['fields']), $item['fields']));
                    }
                  ?>
                  <tr class="jdb2xml-row">
                    <td class="jdb2xml-cell jdb2xml-check">
                      <label class="jdb2xml-exclude">
                        <input type="checkbox"
                               name="rollback_items[updated][]"
                               value="<?php echo (int)$item['id']; ?>"
                               <?php echo $item['rolled_back'] ? 'disabled' : ''; ?>>
                        <span>Updated</span>
                      </label>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-title">
                      <?php echo htmlspecialchars((string)$entity['title'], ENT_QUOTES, 'UTF-8'); ?>
                      <?php if ($fieldsText !== ''): ?>
                        <div class="jdb2xml-rollback-fields"><?php echo htmlspecialchars($fieldsText, ENT_QUOTES, 'UTF-8'); ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-path">
                      <?php if (!empty($entity['path'])): ?>
                        <code><?php echo htmlspecialchars((string)$entity['path'], ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php endif; ?>
                    </td>
                    <td class="jdb2xml-cell jdb2xml-actions"></td>
                    <td class="jdb2xml-cell jdb2xml-badge-cell">
                      <?php if ($item['rolled_back']): ?>
                        <span class="jdb2xml-badge jdb2xml-rollback-status">Rollback completed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <button type="submit" class="btn btn-warning" <?php echo $hasSelectable ? '' : 'disabled'; ?>>
              Rollback selected changes
            </button>
          </form>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="jdb2xml-copyright">Copyright Robin Colbers</div>
</div>

<style>
.jdb2xml { padding: 8px 0; }
.jdb2xml-section { margin-bottom: 18px; }
.jdb2xml-rollback-item { margin: 10px 0; border: 1px solid #e5e5e5; padding: 8px 12px; border-radius: 6px; }
.jdb2xml-rollback-item summary { cursor: pointer; font-weight: 600; }
.jdb2xml-rollback-form { margin-top: 10px; display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-rollback-fields { color: #666; font-size: 12px; margin-top: 4px; }
.jdb2xml-rollback-status { color: #000; font-weight: 600; font-size: 12px; }
.jdb2xml-empty { color: #666; font-style: italic; }
.jdb2xml-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.jdb2xml-table th, .jdb2xml-table td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e5e5; vertical-align: middle; }
.jdb2xml-table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: .02em; color: #666; }
.jdb2xml-head-check { width: 120px; }
.jdb2xml-head-title { width: 220px; }
.jdb2xml-cell { min-height: 26px; }
.jdb2xml-check { min-width: 90px; }
.jdb2xml-title { font-weight: 600; }
.jdb2xml-actions { white-space: nowrap; }
.jdb2xml-actions { justify-self: start; }
.jdb2xml-exclude { display:inline-flex; align-items:center; gap: 6px; margin-right: 8px; font-size: 12px; }
.jdb2xml-exclude input { margin:0; }
.jdb2xml-path { padding-left: 80px; }
.jdb2xml-path code, .jdb2xml code { font-size: 12px; white-space: nowrap; }
.jdb2xml-badge { display:inline-block; padding:2px 7px; border:1px solid #ccc; border-radius:999px; font-size:12px; }
.jdb2xml-badge-cell { justify-self: start; }
.jdb2xml-brand { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.jdb2xml-brand img { max-width: 220px; height: auto; }
.jdb2xml-copyright { margin-top: 20px; font-size: 12px; color: #666; }
</style>
