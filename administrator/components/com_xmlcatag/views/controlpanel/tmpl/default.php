<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

$app = Factory::getApplication();
$input = $app->input;

$importDir = JPATH_ROOT . '/import/xmlcatag';
$files = is_dir($importDir) ? (glob($importDir . '/*.xml') ?: []) : [];
$filenames = array_map('basename', $files);

$selected = basename((string) $input->getString('selected_file', (string) $app->getUserState('com_xmlcatag.selected_file', '')));
$showPreview = (int) $input->getInt('show_preview', 0) === 1;
$showRollback = (int) $input->getInt('show_rollback', (int) $app->getUserState('com_xmlcatag.show_rollback', 0)) === 1;

$preview = null;
if ($showPreview && $selected) {
    $preview = $app->getUserState('com_xmlcatag.preview.' . $selected);
}

$rollbackCategories = [];
$rollbackTags = [];
if ($showRollback) {
    require_once __DIR__ . '/../../helpers/rollback.php';
    $rollbackCategories = XmlcatagRollbackHelper::listLogsByType('categories');
    $rollbackTags = XmlcatagRollbackHelper::listLogsByType('tags');
}

function collectActionsFromTree(array $nodes, array &$actions): void
{
    foreach ($nodes as $n) {
        $action = (string)($n['action'] ?? '');
        if ($action !== '') {
            $actions[$action] = true;
        }
        if (!empty($n['children']) && is_array($n['children'])) {
            collectActionsFromTree($n['children'], $actions);
        }
    }
}

function collectActions(array $preview): array
{
    $actions = [];
    foreach ($preview as $data) {
        if (!empty($data['categoryTree']) && is_array($data['categoryTree'])) {
            collectActionsFromTree($data['categoryTree'], $actions);
        }
        if (!empty($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tag) {
                $action = (string)($tag['action'] ?? '');
                if ($action !== '') {
                    $actions[$action] = true;
                }
            }
        }
    }
    return array_keys($actions);
}

/**
 * Render a category tree with per-node exclude checkbox.
 * Exclude keys are category paths (import helper uses $exclude[$path]).
 */
function renderTreeWithExclude(array $nodes, string $file, int $level = 0): void
{
    if (empty($nodes)) return;

    foreach ($nodes as $n) {
        $title = (string)($n['title'] ?? '-');
        $path  = (string)($n['path'] ?? $n['id'] ?? '');

        $action = (string)($n['action'] ?? '');
        $reason = (string)($n['reason'] ?? '');

        $prefix = $level > 0 ? str_repeat('-', $level) . ' ' : '';
        $search = strtolower(trim($title . ' ' . $path));
        $actionAttr = $action !== '' ? ' data-action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<tr class="xmlcatag-row" data-depth="' . (int)$level . '"' . $actionAttr
            . ' data-search="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-reason="' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '">';

        echo '<td class="xmlcatag-cell xmlcatag-check">';
        if ($path !== '') {
            $checked = !empty($n['exclude']) ? ' checked' : '';
            echo '<label class="xmlcatag-exclude">';
            echo '<input class="xmlcatag-exclude-cb" type="checkbox" name="exclude[' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '][' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . ']" value="1"' . $checked . '>';
            echo '<span>Uitsluiten</span>';
            echo '</label>';
        }
        echo '</td>';

        $indent = $level * 18;
        echo '<td class="xmlcatag-cell xmlcatag-title" style="padding-left:' . (int)$indent . 'px;">';
        echo '<span class="xmlcatag-node">' . htmlspecialchars($prefix . $title, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($action === 'nieuw') {
            echo ' <span class="xmlcatag-new-icon">nieuw</span>';
        }
        echo '</td>';

        echo '<td class="xmlcatag-cell xmlcatag-path">';
        if ($path !== '') {
            echo '<code>' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</code>';
        }
        echo '</td>';

        echo '<td class="xmlcatag-cell xmlcatag-actions">';
        if (!empty($n['children'])) {
            echo '<button type="button" class="btn btn-sm btn-outline-danger xmlcatag-exclude-all">Alles uitsluiten</button>';
            echo '<button type="button" class="btn btn-sm btn-outline-success xmlcatag-include-all">Alles insluiten</button>';
        }
        echo '</td>';

        echo '<td class="xmlcatag-cell xmlcatag-badge-cell">';
        if ($action !== '') {
            $titleAttr = $reason !== '' ? ' title="' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '"' : '';
            echo '<span class="xmlcatag-badge"' . $titleAttr . '>' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</td>';
        echo '</tr>';

        if (!empty($n['children']) && is_array($n['children'])) {
            renderTreeWithExclude($n['children'], $file, $level + 1);
        }
    }
}
?>

<?php if ($showRollback): ?>
  <div class="xmlcatag">
    <h2>Rollback</h2>
    <div class="xmlcatag-grid">
      <div class="xmlcatag-left">
        <h4>Categorieën</h4>
        <form action="index.php?option=com_xmlcatag" method="post" class="xmlcatag-rollback-form">
          <input type="hidden" name="task" value="rollbackapply">
          <input type="hidden" name="rollback_type" value="categories">
          <?php echo HTMLHelper::_('form.token'); ?>
          <select name="rollback_file" class="xmlcatag-rollback-select">
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
      <div class="xmlcatag-right">
        <h4>Tags</h4>
        <form action="index.php?option=com_xmlcatag" method="post" class="xmlcatag-rollback-form">
          <input type="hidden" name="task" value="rollbackapply">
          <input type="hidden" name="rollback_type" value="tags">
          <?php echo HTMLHelper::_('form.token'); ?>
          <select name="rollback_file" class="xmlcatag-rollback-select">
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
<?php endif; ?>

<form action="index.php?option=com_xmlcatag" method="post" name="adminForm" id="adminForm">
  <input type="hidden" name="task" id="task" value="">
  <?php echo HTMLHelper::_('form.token'); ?>

  <div class="xmlcatag">
    <h2>Preview</h2>

<div class="xmlcatag-filebar">
  <label for="selected_file"><strong>Bestand</strong></label>
  <select name="selected_file" id="selected_file">
    <option value="">-- selecteer --</option>
    <?php foreach ($filenames as $fn): ?>
      <option value="<?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($fn === $selected ? 'selected' : ''); ?>>
        <?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <span class="xmlcatag-hint">Kies een bestand en klik daarna op <strong>Preview</strong>.</span>
</div>

<?php if (!empty($preview) && is_array($preview)) : ?>
  <?php $filterActions = collectActions($preview); ?>
  <div class="xmlcatag-filters" data-xmlcatag-filters>
    <span class="xmlcatag-filter-label">Filter:</span>
    <?php foreach ($filterActions as $action) : ?>
      <label class="xmlcatag-filter-item">
        <input type="checkbox" class="xmlcatag-filter-cb" data-action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" checked>
        <span><?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?></span>
      </label>
    <?php endforeach; ?>
    <label class="xmlcatag-filter-item xmlcatag-search">
      <span>Zoek:</span>
      <input type="search" class="xmlcatag-search-input" placeholder="zoek..." aria-label="Zoek in preview">
    </label>
  </div>
<?php endif; ?>



    <?php if (!$showPreview): ?>
      <div class="xmlcatag-empty">Selecteer een bestand en klik op <strong>Preview</strong>.</div>
    <?php elseif (empty($preview) || !is_array($preview)): ?>
      <div class=\"xmlcatag-empty\">Geen previewdata beschikbaar voor dit bestand. Klik opnieuw op <strong>Preview</strong>.</div>
    <?php else: ?>

      <?php foreach ($preview as $fileKey => $data): ?>
        <div class="xmlcatag-file">
          <div class="xmlcatag-grid">
            <?php
              $tree = $data['categoryTree'] ?? [];
              $hasTags = !empty($data['tags']) && is_array($data['tags']);
              $warningText = '';
              if (!empty($data['warnings']) && is_array($data['warnings'])) {
                  $warningText = htmlspecialchars('Waarschuwingen: ' . implode(' | ', array_map('strval', $data['warnings'])) . '!', ENT_QUOTES, 'UTF-8');
              }
              $warningOnTags = $hasTags;
            ?>
            <?php if ($hasTags): ?>
              <div class="xmlcatag-left">
                <?php if ($warningText !== '' && $warningOnTags): ?>
                  <div class="xmlcatag-warn" role="alert">
                    <?php echo $warningText; ?>
                  </div>
                <?php endif; ?>
                <h4>Tags</h4>
                <table class="xmlcatag-table">
                  <thead><tr>
                    <th class="xmlcatag-head-check">Actie</th>
                    <th class="xmlcatag-head-title">Titel</th>
                    <th>Alias</th>
                    <th></th>
                    <th></th>
                  </tr></thead>
                  <tbody>
                    <?php foreach ($data['tags'] as $tag): ?>
                      <?php $tagAction = (string)($tag['action'] ?? ''); ?>
                      <?php
                        $tagTitle = (string)($tag['title'] ?? '-');
                        $tagAlias = (string)($tag['id'] ?? '');
                        $tagExclude = !empty($tag['exclude']);
                        $tagReason = (string)($tag['reason'] ?? '');
                        $tagSearch = strtolower(trim($tagTitle . ' ' . $tagAlias));
                      ?>
                      <tr class="xmlcatag-tag-row"
                          data-action="<?php echo htmlspecialchars($tagAction, ENT_QUOTES, 'UTF-8'); ?>"
                          data-search="<?php echo htmlspecialchars($tagSearch, ENT_QUOTES, 'UTF-8'); ?>"
                          data-reason="<?php echo htmlspecialchars($tagReason, ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="xmlcatag-cell xmlcatag-check">
                          <?php if ($tagAlias !== ''): ?>
                            <label class="xmlcatag-exclude">
                              <input class="xmlcatag-exclude-cb" type="checkbox"
                                     name="exclude[<?php echo htmlspecialchars($fileKey, ENT_QUOTES, 'UTF-8'); ?>][<?php echo htmlspecialchars($tagAlias, ENT_QUOTES, 'UTF-8'); ?>]"
                                     value="1"<?php echo $tagExclude ? ' checked' : ''; ?>>
                              <span>Uitsluiten</span>
                            </label>
                          <?php endif; ?>
                        </td>
                        <td class="xmlcatag-cell xmlcatag-title">
                          <?php echo htmlspecialchars($tagTitle, ENT_QUOTES, 'UTF-8'); ?>
                          <?php if ($tagAction === 'nieuw'): ?>
                            <span class="xmlcatag-new-icon">nieuw</span>
                          <?php endif; ?>
                        </td>
                        <td class="xmlcatag-cell xmlcatag-path">
                          <?php if ($tagAlias !== ''): ?>
                            <code><?php echo htmlspecialchars($tagAlias, ENT_QUOTES, 'UTF-8'); ?></code>
                          <?php endif; ?>
                        </td>
                        <td class="xmlcatag-cell xmlcatag-actions"></td>
                        <td class="xmlcatag-cell xmlcatag-badge-cell">
                          <?php if ($tagAction !== ''): ?>
                            <span class="xmlcatag-badge" title="<?php echo htmlspecialchars($tagReason, ENT_QUOTES, 'UTF-8'); ?>">
                              <?php echo htmlspecialchars($tagAction, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

            <?php if (!empty($tree)) : ?>
              <div class="<?php echo $hasTags ? 'xmlcatag-right' : 'xmlcatag-left'; ?>">
                <?php if ($warningText !== '' && !$warningOnTags): ?>
                  <div class="xmlcatag-warn" role="alert">
                    <?php echo $warningText; ?>
                  </div>
                <?php endif; ?>
                <h4>Categorieën</h4>
                <?php
                  echo '<table class="xmlcatag-table">';
                  echo '<thead><tr>';
                  echo '<th class="xmlcatag-head-check">Actie</th>';
                  echo '<th class="xmlcatag-head-title">Titel</th>';
                  echo '<th>Pad</th>';
                  echo '<th></th>';
                  echo '<th></th>';
                  echo '</tr></thead>';
                  echo '<tbody>';
                  renderTreeWithExclude($tree, $fileKey);
                  echo '</tbody></table>';
                ?>
              </div>
            <?php elseif (!$hasTags && $warningText !== ''): ?>
              <div class="xmlcatag-left">
                <div class="xmlcatag-warn" role="alert">
                  <?php echo $warningText; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>
</form>
<div class="xmlcatag-footer">Copyright Robin Colbers.</div>

<style>
.xmlcatag { padding: 8px 0; }
.xmlcatag-file { margin: 14px 0; padding: 10px 0; border-top: 1px solid #eee; }
.xmlcatag-grid { display:flex; gap: 18px; align-items:flex-start; }
.xmlcatag-left { flex: 1 1 65%; min-width: 380px; }
.xmlcatag-right { flex: 0 0 35%; min-width: 280px; }
.xmlcatag-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.xmlcatag-table th, .xmlcatag-table td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e5e5; vertical-align: middle; }
.xmlcatag-table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: .02em; color: #666; }
.xmlcatag-head-check { width: 120px; }
.xmlcatag-head-title { width: 220px; }
.xmlcatag-cell { min-height: 26px; }
.xmlcatag-check { min-width: 90px; }
.xmlcatag-title { font-weight: 600; }
.xmlcatag-actions { white-space: nowrap; }
.xmlcatag-actions { justify-self: start; }
.xmlcatag-actions button { margin-right: 6px; margin-bottom: 0; }
.xmlcatag-exclude { display:inline-flex; align-items:center; gap: 6px; margin-right: 8px; font-size: 12px; }
.xmlcatag-exclude input { margin:0; }
.xmlcatag-path { padding-left: 80px; }
.xmlcatag-path code, .xmlcatag code { font-size: 12px; white-space: nowrap; }
.xmlcatag-badge { display:inline-block; padding:2px 7px; border:1px solid #ccc; border-radius:999px; font-size:12px; }
.xmlcatag-badge-cell { justify-self: start; }
.xmlcatag-filters { margin-top: 8px; display:flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.xmlcatag-filter-label { font-weight: 600; }
.xmlcatag-filter-item { display:inline-flex; align-items:center; gap: 6px; font-size: 12px; }
.xmlcatag-filter-item input { margin: 0; }
.xmlcatag-search { flex: 1 1 220px; }
.xmlcatag-search-input { width: 100%; max-width: 280px; }
.xmlcatag-filter-actions { display:inline-flex; align-items:center; }
.xmlcatag-new-icon { display:inline-flex; align-items:center; gap: 4px; color: #1b7f1b; font-weight: 700; font-size: 13px; }
.xmlcatag-new-icon::before { content: "🟢"; font-size: 12px; }
.xmlcatag-filter-hidden { display: none; }
.xmlcatag-warn {
  color: #b00020;
  font-weight: 700;
  font-size: 16px;
  margin: 10px 0;
  border: 1px solid #b00020;
  padding: 6px 10px;
  box-sizing: border-box;
  width: 100%;
}
.xmlcatag-tags { margin: 8px 0 0 0; padding-left: 18px; }
.xmlcatag-tags li { margin: 6px 0; }
.xmlcatag-rollback-form { display:flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.xmlcatag-rollback-select { min-width: 240px; }
.xmlcatag-footer { margin-top: 16px; font-size: 12px; color: #666; }
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var filterContainer = document.querySelector("[data-xmlcatag-filters]");
  if (!filterContainer) return;

  function updateFilter() {
    var searchInput = filterContainer.querySelector(".xmlcatag-search-input");
    var searchTerm = searchInput ? (searchInput.value || "").toLowerCase().trim() : "";
    var checked = Array.from(filterContainer.querySelectorAll(".xmlcatag-filter-cb:checked"))
      .map(function (cb) { return cb.getAttribute("data-action") || ""; })
      .filter(Boolean);

    document.querySelectorAll(".xmlcatag-row[data-action]").forEach(function (row) {
      var action = row.getAttribute("data-action") || "";
      var searchValue = (row.getAttribute("data-search") || "").toLowerCase();
      var actionMatch = checked.includes(action);
      var searchMatch = !searchTerm || searchValue.indexOf(searchTerm) !== -1;
      var shouldShow = actionMatch && searchMatch;
      row.classList.toggle("xmlcatag-filter-hidden", !shouldShow);
    });

    document.querySelectorAll(".xmlcatag-tag-row[data-action]").forEach(function (row) {
      var action = row.getAttribute("data-action") || "";
      var searchValue = (row.getAttribute("data-search") || "").toLowerCase();
      var actionMatch = checked.includes(action);
      var searchMatch = !searchTerm || searchValue.indexOf(searchTerm) !== -1;
      var shouldShow = actionMatch && searchMatch;
      row.classList.toggle("xmlcatag-filter-hidden", !shouldShow);
    });
  }

  filterContainer.addEventListener("change", updateFilter);
  filterContainer.addEventListener("input", updateFilter);
  updateFilter();
});

</script>
<?php if (!empty($this->selectedFile)) : ?>
<a class="btn btn-primary"
   href="index.php?option=com_xmlcatag&task=preview&filename=<?php echo urlencode($this->selectedFile); ?>">
   Preview
</a>
<?php endif; ?>
