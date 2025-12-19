<?php
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

$preview = null;
if ($showPreview && $selected) {
    $preview = $app->getUserState('com_xmlcatag.preview.' . $selected);
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

        $actionAttr = $action !== '' ? ' data-action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<tr class="xmlcatag-row" data-depth="' . (int)$level . '"' . $actionAttr . '>';

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
        echo '<span class="xmlcatag-node">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</td>';

        echo '<td class="xmlcatag-cell xmlcatag-path">';
        if ($path !== '') {
            echo '<code>' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</code>';
        }
        echo '</td>';

        echo '<td class="xmlcatag-cell xmlcatag-reason">';
        if ($reason !== '') {
            echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
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
            echo '<span class="xmlcatag-badge">' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</td>';
        echo '</tr>';

        if (!empty($n['children']) && is_array($n['children'])) {
            renderTreeWithExclude($n['children'], $file, $level + 1);
        }
    }
}
?>

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
  <?php if (!empty($filterActions)) : ?>
    <div class="xmlcatag-filters" data-xmlcatag-filters>
      <span class="xmlcatag-filter-label">Filter:</span>
      <?php foreach ($filterActions as $action) : ?>
        <label class="xmlcatag-filter-item">
          <input type="checkbox" class="xmlcatag-filter-cb" data-action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" checked>
          <span><?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>



    <?php if (!$showPreview): ?>
      <div class="xmlcatag-empty">Selecteer een bestand en klik op <strong>Preview</strong>.</div>
    <?php elseif (empty($preview) || !is_array($preview)): ?>
      <div class=\"xmlcatag-empty\">Geen previewdata beschikbaar voor dit bestand. Klik opnieuw op <strong>Preview</strong>.</div>
    <?php else: ?>

      <?php foreach ($preview as $fileKey => $data): ?>
        <div class="xmlcatag-file">
          <h3><?php echo htmlspecialchars($fileKey, ENT_QUOTES, 'UTF-8'); ?></h3>

          <?php if (!empty($data['warnings']) && is_array($data['warnings'])): ?>
            <div class="xmlcatag-warn" role="alert">
              <strong>Waarschuwingen</strong>
              <ul>
                <?php foreach ($data['warnings'] as $w): ?>
                  <li><?php echo htmlspecialchars((string)$w, ENT_QUOTES, 'UTF-8'); ?>!</li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="xmlcatag-grid">
            <div class="xmlcatag-left">
              <h4>Categorieën</h4>
              <?php
                $tree = $data['categoryTree'] ?? [];
                if (!empty($tree)) {
                    echo '<table class="xmlcatag-table">';
                    echo '<thead><tr>';
                    echo '<th class="xmlcatag-head-check">Actie</th>';
                    echo '<th class="xmlcatag-head-title">Titel</th>';
                    echo '<th>Pad</th>';
                    echo '<th>Reden</th>';
                    echo '<th></th>';
                    echo '<th></th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    renderTreeWithExclude($tree, $fileKey);
                    echo '</tbody></table>';
                } else {
                    echo '<p>Geen categorieën om te tonen.</p>';
                }
              ?>
            </div>

            <div class="xmlcatag-right">
              <h4>Tags</h4>
              <?php if (!empty($data['tags']) && is_array($data['tags'])): ?>
                <ul class="xmlcatag-tags">
                  <?php foreach ($data['tags'] as $tag): ?>
                    <?php $tagAction = (string)($tag['action'] ?? ''); ?>
                    <li class="xmlcatag-tag-row" data-action="<?php echo htmlspecialchars($tagAction, ENT_QUOTES, 'UTF-8'); ?>">
                      <span><?php echo htmlspecialchars((string)($tag['title'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php if ($tagAction === 'nieuw'): ?>
                        <span class="xmlcatag-new-icon">nieuw</span>
                      <?php endif; ?>
                      <?php if ($tagAction !== ''): ?>
                        <span class="xmlcatag-badge"><?php echo htmlspecialchars($tagAction, ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>
</form>

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
.xmlcatag-path code, .xmlcatag code { font-size: 12px; white-space: nowrap; }
.xmlcatag-badge { display:inline-block; padding:2px 7px; border:1px solid #ccc; border-radius:999px; font-size:12px; }
.xmlcatag-badge-cell { justify-self: start; }
.xmlcatag-reason { font-size: 12px; opacity: .8; white-space: nowrap; }
.xmlcatag-filters { margin-top: 8px; display:flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.xmlcatag-filter-label { font-weight: 600; }
.xmlcatag-filter-item { display:inline-flex; align-items:center; gap: 6px; font-size: 12px; }
.xmlcatag-filter-item input { margin: 0; }
.xmlcatag-new-icon { display:inline-flex; align-items:center; gap: 4px; color: #1b7f1b; font-weight: 700; font-size: 13px; }
.xmlcatag-new-icon::before { content: "🟢"; font-size: 12px; }
.xmlcatag-tag-row { display:flex; gap: 8px; align-items:center; margin: 6px 0; }
.xmlcatag-filter-hidden { display: none; }
.xmlcatag-warn {
  color: #b00020;
  font-weight: 700;
  font-size: 16px;
  margin: 10px 0;
}
.xmlcatag-warn strong { display: block; margin-bottom: 4px; }
.xmlcatag-warn ul { margin: 6px 0 0 18px; }
.xmlcatag-tags { margin: 8px 0 0 0; padding-left: 18px; }
.xmlcatag-tags li { margin: 6px 0; }
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var filterContainer = document.querySelector("[data-xmlcatag-filters]");
  if (!filterContainer) return;

  function updateFilter() {
    var checked = Array.from(filterContainer.querySelectorAll(".xmlcatag-filter-cb:checked"))
      .map(function (cb) { return cb.getAttribute("data-action") || ""; })
      .filter(Boolean);

    document.querySelectorAll(".xmlcatag-row[data-action]").forEach(function (row) {
      var action = row.getAttribute("data-action") || "";
      var shouldShow = checked.includes(action);
      row.classList.toggle("xmlcatag-filter-hidden", !shouldShow);
    });

    document.querySelectorAll(".xmlcatag-tag-row[data-action]").forEach(function (row) {
      var action = row.getAttribute("data-action") || "";
      var shouldShow = checked.includes(action);
      row.classList.toggle("xmlcatag-filter-hidden", !shouldShow);
    });
  }

  filterContainer.addEventListener("change", updateFilter);
  updateFilter();
});
</script>
<?php if (!empty($this->selectedFile)) : ?>
<a class="btn btn-primary"
   href="index.php?option=com_xmlcatag&task=preview&filename=<?php echo urlencode($this->selectedFile); ?>">
   Preview
</a>
<?php endif; ?>
