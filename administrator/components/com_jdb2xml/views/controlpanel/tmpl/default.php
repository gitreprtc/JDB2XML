<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

require_once __DIR__ . '/../../../helpers/log.php';

$app = Factory::getApplication();
$input = $app->input;

$importDir = JPATH_ROOT . '/media/com_jdb2xml/import';
if (is_dir($importDir)) {
    clearstatcache(true, $importDir);
}
$files = is_dir($importDir) ? (glob($importDir . '/*.xml', GLOB_NOSORT) ?: []) : [];
$filenames = array_map('basename', $files);

$selected = basename((string) $input->getString('selected_file', (string) $app->getUserState('com_jdb2xml.selected_file', '')));
$showPreview = (int) $input->getInt('show_preview', 0) === 1;

$preview = null;
if ($showPreview && $selected) {
    $preview = $app->getUserState('com_jdb2xml.preview.' . $selected);
}

$changeCategories = Jdb2xmlLogHelper::getChangesByType('categories');
$changeTags = Jdb2xmlLogHelper::getChangesByType('tags');
$changeArticles = Jdb2xmlLogHelper::getChangesByType('articles');
$rollbackCategories = Jdb2xmlLogHelper::getRollbacksByType('categories');
$rollbackTags = Jdb2xmlLogHelper::getRollbacksByType('tags');
$rollbackArticles = Jdb2xmlLogHelper::getRollbacksByType('articles');

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
        if (!empty($data['articles']) && is_array($data['articles'])) {
            foreach ($data['articles'] as $article) {
                $action = (string)($article['action'] ?? '');
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
        $excludeKey = (string)($n['exclude_key'] ?? $path);
        $pathDisplay = (string)($n['path_display'] ?? $path);

        $action = (string)($n['action'] ?? '');
        $reason = (string)($n['reason'] ?? '');

        $prefix = $level > 0 ? str_repeat('-', $level) . ' ' : '';
        $search = strtolower(trim($title . ' ' . $pathDisplay));
        $actionAttr = $action !== '' ? ' data-action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '"' : '';
        echo '<tr class="jdb2xml-row" data-depth="' . (int)$level . '"' . $actionAttr
            . ' data-search="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-reason="' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '">';

        echo '<td class="jdb2xml-cell jdb2xml-check">';
        if ($excludeKey !== '') {
            $checked = !empty($n['exclude']) ? ' checked' : '';
            echo '<label class="jdb2xml-exclude">';
            echo '<input class="jdb2xml-exclude-cb" type="checkbox" name="exclude[' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '][' . htmlspecialchars($excludeKey, ENT_QUOTES, 'UTF-8') . ']" value="1"' . $checked . '>';
            echo '<span>Exclude</span>';
            echo '</label>';
        }
        echo '</td>';

        $indent = $level * 18;
        echo '<td class="jdb2xml-cell jdb2xml-title" style="padding-left:' . (int)$indent . 'px;">';
        echo '<span class="jdb2xml-node">' . htmlspecialchars($prefix . $title, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($action === 'new') {
            echo ' <span class="jdb2xml-new-icon">new</span>';
        }
        echo '</td>';

        echo '<td class="jdb2xml-cell jdb2xml-path">';
        if ($pathDisplay !== '') {
            echo '<code>' . htmlspecialchars($pathDisplay, ENT_QUOTES, 'UTF-8') . '</code>';
        }
        echo '</td>';

        echo '<td class="jdb2xml-cell jdb2xml-actions">';
        if (!empty($n['children'])) {
            echo '<button type="button" class="btn btn-sm btn-outline-danger jdb2xml-exclude-all">Exclude all</button>';
            echo '<button type="button" class="btn btn-sm btn-outline-success jdb2xml-include-all">Include all</button>';
        }
        echo '</td>';

        echo '<td class="jdb2xml-cell jdb2xml-badge-cell">';
        if ($action !== '') {
            $titleAttr = $reason !== '' ? ' title="' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '"' : '';
            echo '<span class="jdb2xml-badge"' . $titleAttr . '>' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</td>';
        echo '</tr>';

        if (!empty($n['children']) && is_array($n['children'])) {
            renderTreeWithExclude($n['children'], $file, $level + 1);
        }
    }
}

function renderLogTable(string $heading, array $entries): void
{
    echo '<h4>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h4>';
    if (empty($entries)) {
        echo '<div class="jdb2xml-empty">No log entries.</div>';
        return;
    }
    echo '<table class="jdb2xml-log-table">';
    echo '<thead><tr><th>Date/time</th><th>Action</th><th>Item</th><th>Details</th></tr></thead><tbody>';
    foreach ($entries as $entry) {
        $timestamp = (string) ($entry['timestamp'] ?? '');
        $action = (string) ($entry['action'] ?? '');
        $title = (string) ($entry['title'] ?? '');
        $identifier = (string) ($entry['identifier'] ?? '');
        $details = $entry['details'] ?? [];
        $detailsText = '';
        if (!empty($details) && is_array($details)) {
            $detailsText = implode(', ', array_map('strval', $details));
        }
        echo '<tr>';
        echo '<td>' . htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(trim($title . ' ' . $identifier), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($detailsText, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
?>

<form action="index.php?option=com_jdb2xml" method="post" name="adminForm" id="adminForm">
  <input type="hidden" name="task" id="task" value="">
  <?php echo HTMLHelper::_('form.token'); ?>

  <div class="jdb2xml">
    <h2>Preview</h2>
    <div class="jdb2xml-tabs" role="tablist">
      <button type="button" class="jdb2xml-tab-btn is-active" data-tab-target="preview" role="tab">Preview</button>
      <button type="button" class="jdb2xml-tab-btn" data-tab-target="log" role="tab">Log</button>
    </div>

    <div class="jdb2xml-tab-panel is-active" data-tab-panel="preview">
      <div class="jdb2xml-filebar">
        <label for="selected_file"><strong>File</strong></label>
        <select name="selected_file" id="selected_file">
          <option value="">-- select --</option>
          <?php foreach ($filenames as $fn): ?>
            <option value="<?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($fn === $selected ? 'selected' : ''); ?>>
              <?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="jdb2xml-hint">Choose a file and then click <strong>Preview</strong>.</span>
      </div>

      <?php if (!empty($preview) && is_array($preview)) : ?>
        <?php $filterActions = collectActions($preview); ?>
        <div class="jdb2xml-filters" data-jdb2xml-filters>
          <span class="jdb2xml-filter-label">Filter:</span>
          <?php foreach ($filterActions as $action) : ?>
            <label class="jdb2xml-filter-item">
              <input type="checkbox" class="jdb2xml-filter-cb" data-action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" checked>
              <span><?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          <?php endforeach; ?>
          <label class="jdb2xml-filter-item jdb2xml-search">
            <span>Search:</span>
            <input type="search" class="jdb2xml-search-input" placeholder="search..." aria-label="Search in preview">
          </label>
        </div>
      <?php endif; ?>

      <?php if (!$showPreview): ?>
        <div class="jdb2xml-empty">Select a file and click <strong>Preview</strong>.</div>
      <?php elseif (empty($preview) || !is_array($preview)): ?>
        <div class="jdb2xml-empty">No preview data available for this file. Click <strong>Preview</strong> again.</div>
      <?php else: ?>

        <?php foreach ($preview as $fileKey => $data): ?>
          <div class="jdb2xml-file">
            <div class="jdb2xml-grid">
              <?php
                $categoryTree = $data['categoryTree'] ?? [];
                $tagTree = $data['tagTree'] ?? [];
                $articleTree = $data['articleTree'] ?? [];
                $warningText = '';
                if (!empty($data['warnings']) && is_array($data['warnings'])) {
                    $warningText = htmlspecialchars('Warnings: ' . implode(' | ', array_map('strval', $data['warnings'])) . '!', ENT_QUOTES, 'UTF-8');
                }
              ?>

              <?php if ($warningText !== ''): ?>
                <div class="jdb2xml-warn" role="alert">
                  <?php echo $warningText; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($categoryTree)) : ?>
                <div class="jdb2xml-left">
                  <h4>Categories</h4>
                  <?php
                    echo '<table class="jdb2xml-table" data-jdb2xml-tree>';
                    echo '<thead><tr>';
                    echo '<th class="jdb2xml-head-check">Action</th>';
                    echo '<th class="jdb2xml-head-title">Title</th>';
                    echo '<th>Path</th>';
                    echo '<th></th>';
                    echo '<th></th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    renderTreeWithExclude($categoryTree, $fileKey);
                    echo '</tbody></table>';
                  ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($tagTree)) : ?>
                <div class="jdb2xml-right">
                  <h4>Tags</h4>
                  <?php
                    echo '<table class="jdb2xml-table" data-jdb2xml-tree>';
                    echo '<thead><tr>';
                    echo '<th class="jdb2xml-head-check">Action</th>';
                    echo '<th class="jdb2xml-head-title">Title</th>';
                    echo '<th>Path</th>';
                    echo '<th></th>';
                    echo '<th></th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    renderTreeWithExclude($tagTree, $fileKey);
                    echo '</tbody></table>';
                  ?>
                </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($articleTree)) : ?>
              <div class="jdb2xml-articles">
                <h4>Articles</h4>
                <?php
                  echo '<table class="jdb2xml-table" data-jdb2xml-tree>';
                  echo '<thead><tr>';
                  echo '<th class="jdb2xml-head-check">Action</th>';
                  echo '<th class="jdb2xml-head-title">Title</th>';
                  echo '<th>Path</th>';
                  echo '<th></th>';
                  echo '<th></th>';
                  echo '</tr></thead>';
                  echo '<tbody>';
                  renderTreeWithExclude($articleTree, $fileKey);
                  echo '</tbody></table>';
                ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </div>

    <div class="jdb2xml-tab-panel" data-tab-panel="log">
      <div class="jdb2xml-log-section">
        <h3>Changes</h3>
        <?php renderLogTable('Categories', $changeCategories); ?>
        <?php renderLogTable('Tags', $changeTags); ?>
        <?php renderLogTable('Articles', $changeArticles); ?>
      </div>
      <div class="jdb2xml-log-section">
        <h3>Rollbacks</h3>
        <?php renderLogTable('Categories', $rollbackCategories); ?>
        <?php renderLogTable('Tags', $rollbackTags); ?>
        <?php renderLogTable('Articles', $rollbackArticles); ?>
      </div>
    </div>
  </div>
</form>
<div class="jdb2xml-footer">Copyright Robin Colbers.</div>

<style>
.jdb2xml { padding: 8px 0; }
.jdb2xml-file { margin: 14px 0; padding: 10px 0; border-top: 1px solid #eee; }
.jdb2xml-grid { display:flex; gap: 18px; align-items:flex-start; }
.jdb2xml-left { flex: 1 1 65%; min-width: 380px; }
.jdb2xml-right { flex: 0 0 35%; min-width: 280px; }
.jdb2xml-tabs { display: flex; gap: 8px; margin: 8px 0 12px; }
.jdb2xml-tab-btn { border: 1px solid #ccc; background: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer; }
.jdb2xml-tab-btn.is-active { background: #2e7d32; color: #fff; border-color: #2e7d32; }
.jdb2xml-tab-panel { display: none; }
.jdb2xml-tab-panel.is-active { display: block; }
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
.jdb2xml-actions button { margin-right: 6px; margin-bottom: 0; }
.jdb2xml-exclude { display:inline-flex; align-items:center; gap: 6px; margin-right: 8px; font-size: 12px; }
.jdb2xml-exclude input { margin:0; }
.jdb2xml-path { padding-left: 80px; }
.jdb2xml-path code, .jdb2xml code { font-size: 12px; white-space: nowrap; }
.jdb2xml-badge { display:inline-block; padding:2px 7px; border:1px solid #ccc; border-radius:999px; font-size:12px; }
.jdb2xml-badge-cell { justify-self: start; }
.jdb2xml-filters { margin-top: 8px; display:flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.jdb2xml-filter-label { font-weight: 600; }
.jdb2xml-filter-item { display:inline-flex; align-items:center; gap: 6px; font-size: 12px; }
.jdb2xml-filter-item input { margin: 0; }
.jdb2xml-search { flex: 1 1 220px; }
.jdb2xml-search-input { width: 100%; max-width: 280px; }
.jdb2xml-filter-actions { display:inline-flex; align-items:center; }
.jdb2xml-new-icon { display:inline-flex; align-items:center; gap: 4px; color: #1b7f1b; font-weight: 700; font-size: 13px; }
.jdb2xml-new-icon::before { content: "🟢"; font-size: 12px; }
.jdb2xml-filter-hidden { display: none; }
.jdb2xml-warn {
  color: #b00020;
  font-weight: 700;
  font-size: 16px;
  margin: 10px 0;
  border: 1px solid #b00020;
  padding: 6px 10px;
  box-sizing: border-box;
  width: 100%;
}
.jdb2xml-articles { margin-top: 18px; }
.jdb2xml-log-section { margin-top: 16px; }
.jdb2xml-log-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.jdb2xml-log-table th, .jdb2xml-log-table td { padding: 6px 8px; border-bottom: 1px solid #e5e5e5; text-align: left; }
.jdb2xml-log-table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: .02em; color: #666; }
.jdb2xml-tags { margin: 8px 0 0 0; padding-left: 18px; }
.jdb2xml-tags li { margin: 6px 0; }
.jdb2xml-footer { margin-top: 16px; font-size: 12px; color: #666; }
</style>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var listToken = "<?php echo Session::getFormToken(); ?>";
  var listUrl = "index.php?option=com_jdb2xml&task=listfiles&format=json&" + listToken + "=1";
  var fileSelect = document.getElementById("selected_file");

  function updateFileOptions(names) {
    if (!fileSelect) return;
    var previous = fileSelect.value;
    var existing = Array.from(fileSelect.querySelectorAll("option")).map(function (opt) {
      return opt.value;
    });

    var keep = new Set(names);
    Array.from(fileSelect.querySelectorAll("option")).forEach(function (opt) {
      if (opt.value === "") return;
      if (!keep.has(opt.value)) {
        opt.remove();
      }
    });

    names.forEach(function (name) {
      if (!existing.includes(name)) {
        var option = document.createElement("option");
        option.value = name;
        option.textContent = name;
        fileSelect.appendChild(option);
      }
    });

    if (previous && keep.has(previous)) {
      fileSelect.value = previous;
    } else if (previous && !keep.has(previous)) {
      fileSelect.value = "";
    }
  }

  function fetchFileList() {
    fetch(listUrl, { credentials: "same-origin" })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data && Array.isArray(data.files)) {
          updateFileOptions(data.files);
        }
      })
      .catch(function () {});
  }

  if (fileSelect) {
    var listInterval = null;

    function startPolling() {
      if (listInterval) return;
      fetchFileList();
      listInterval = window.setInterval(fetchFileList, 2000);
    }

    function stopPolling() {
      if (!listInterval) return;
      window.clearInterval(listInterval);
      listInterval = null;
    }

    fileSelect.addEventListener("focus", startPolling);
    fileSelect.addEventListener("mousedown", startPolling);
    fileSelect.addEventListener("blur", stopPolling);
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) {
        stopPolling();
      }
    });
  }

  var filterContainer = document.querySelector("[data-jdb2xml-filters]");
  if (!filterContainer) {
    return;
  }

  function updateFilter() {
    var searchInput = filterContainer.querySelector(".jdb2xml-search-input");
    var searchTerm = searchInput ? (searchInput.value || "").toLowerCase().trim() : "";
    var checked = Array.from(filterContainer.querySelectorAll(".jdb2xml-filter-cb:checked"))
      .map(function (cb) { return cb.getAttribute("data-action") || ""; })
      .filter(Boolean);

    document.querySelectorAll(".jdb2xml-row[data-action]").forEach(function (row) {
      var action = row.getAttribute("data-action") || "";
      var searchValue = (row.getAttribute("data-search") || "").toLowerCase();
      var actionMatch = action === "" || checked.includes(action);
      var searchMatch = !searchTerm || searchValue.indexOf(searchTerm) !== -1;
      var shouldShow = actionMatch && searchMatch;
      row.classList.toggle("jdb2xml-filter-hidden", !shouldShow);
    });
  }

  filterContainer.addEventListener("change", updateFilter);
  filterContainer.addEventListener("input", updateFilter);
  updateFilter();
});

</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  var tabs = document.querySelectorAll(".jdb2xml-tab-btn");
  var panels = document.querySelectorAll(".jdb2xml-tab-panel");
  if (!tabs.length || !panels.length) {
    return;
  }

  function activateTab(name) {
    tabs.forEach(function (tab) {
      tab.classList.toggle("is-active", tab.getAttribute("data-tab-target") === name);
    });
    panels.forEach(function (panel) {
      panel.classList.toggle("is-active", panel.getAttribute("data-tab-panel") === name);
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener("click", function () {
      var target = tab.getAttribute("data-tab-target");
      activateTab(target);
    });
  });
});
</script>
<?php if (!empty($this->selectedFile)) : ?>
<a class="btn btn-primary"
   href="index.php?option=com_jdb2xml&task=preview&filename=<?php echo urlencode($this->selectedFile); ?>">
   Preview
</a>
<?php endif; ?>
