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

/**
 * Render a category tree with per-node exclude checkbox.
 * Exclude keys are category paths (import helper uses $exclude[$path]).
 */
function renderTreeWithExclude(array $nodes, string $file, int $level = 0): void
{
    if (empty($nodes)) return;

    echo '<ul class="xmlcatag-tree">';
    foreach ($nodes as $n) {
        $title = (string)($n['title'] ?? '-');
        $path  = (string)($n['path'] ?? $n['id'] ?? '');

        $action = (string)($n['action'] ?? '');
        $reason = (string)($n['reason'] ?? '');

        echo '<li class="xmlcatag-li" data-depth="' . (int)$level . '">';
        echo '<div class="xmlcatag-node">';

        echo '<span class="xmlcatag-cell xmlcatag-check">';
        if ($path !== '') {
            $checked = !empty($n['exclude']) ? ' checked' : '';
            echo '<label class="xmlcatag-exclude">';
            echo '<input class="xmlcatag-exclude-cb" type="checkbox" name="exclude[' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '][' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . ']" value="1"' . $checked . '>';
            echo '<span>Uitsluiten</span>';
            echo '</label>';
        }
        echo '</span>';

        echo '<span class="xmlcatag-cell xmlcatag-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>';

        echo '<span class="xmlcatag-cell xmlcatag-path">';
        if ($path !== '') {
            echo '<code>' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</code>';
        }
        echo '</span>';

        echo '<span class="xmlcatag-cell xmlcatag-reason">';
        if ($reason !== '') {
            echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        }
        echo '</span>';

        echo '<span class="xmlcatag-cell xmlcatag-badge-cell">';
        if ($action !== '') {
            echo '<span class="xmlcatag-badge">' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</span>';

        echo '<span class="xmlcatag-cell xmlcatag-actions">';
        if (!empty($n['children'])) {
            echo '<button type="button" class="btn btn-sm btn-outline-danger xmlcatag-exclude-all">Alles uitsluiten</button>';
            echo '<button type="button" class="btn btn-sm btn-outline-success xmlcatag-include-all">Alles insluiten</button>';
        }
        echo '</span>';

        echo '</div>';

        if (!empty($n['children']) && is_array($n['children'])) {
            renderTreeWithExclude($n['children'], $file, $level + 1);
        }
        echo '</li>';
    }
    echo '</ul>';
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



    <?php if (!$showPreview): ?>
      <div class="xmlcatag-empty">Selecteer een bestand en klik op <strong>Preview</strong>.</div>
    <?php elseif (empty($preview) || !is_array($preview)): ?>
      <div class=\"xmlcatag-empty\">Geen previewdata beschikbaar voor dit bestand. Klik opnieuw op <strong>Preview</strong>.</div>
    <?php else: ?>

      <?php foreach ($preview as $fileKey => $data): ?>
        <div class="xmlcatag-file">
          <h3><?php echo htmlspecialchars($fileKey, ENT_QUOTES, 'UTF-8'); ?></h3>

          <?php if (!empty($data['warnings']) && is_array($data['warnings'])): ?>
            <div class="xmlcatag-warn">
              <strong>Waarschuwingen</strong>
              <ul>
                <?php foreach ($data['warnings'] as $w): ?>
                  <li><?php echo htmlspecialchars((string)$w, ENT_QUOTES, 'UTF-8'); ?></li>
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
                    renderTreeWithExclude($tree, $fileKey);
                } else {
                    echo '<p>Geen categorieën om te tonen.</p>';
                }
              ?>
            </div>

            <div class="xmlcatag-right">
              <h4>Tags</h4>
              <?php if (!empty($data['tags']) && is_array($data['tags'])): ?>
                <ul class="xmlcatag-tags">
                  <?php foreach ($data['tags'] as $tag): 
                    $tTitle = (string)($tag['title'] ?? '-');
                    $alias  = (string)($tag['alias'] ?? '');
                    $tAction = (string)($tag['action'] ?? '');
                    $tReason = (string)($tag['reason'] ?? '');
                    $checked = !empty($tag['exclude']) ? ' checked' : '';
                  ?>
                    <li>
                      <?php if ($alias !== ''): ?>
                        <label class="xmlcatag-exclude">
                          <input class="xmlcatag-exclude-cb" type="checkbox" name="exclude[<?php echo htmlspecialchars($fileKey, ENT_QUOTES, 'UTF-8'); ?>][<?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?>]" value="1"<?php echo $checked; ?>>
                          <span>Uitsluiten</span>
                        </label>
                      <?php endif; ?>
                      <strong><?php echo htmlspecialchars($tTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                      <?php if ($alias !== ''): ?>
                        <code><?php echo htmlspecialchars($alias, ENT_QUOTES, 'UTF-8'); ?></code>
                      <?php endif; ?>
                      <?php if ($tAction !== ''): ?>
                        <span class="xmlcatag-badge"><?php echo htmlspecialchars($tAction, ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                      <?php if ($tReason !== ''): ?>
                        <span class="xmlcatag-reason"><?php echo htmlspecialchars($tReason, ENT_QUOTES, 'UTF-8'); ?></span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p>Geen tags om te tonen.</p>
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
.xmlcatag-tree { margin: 8px 0 16px 18px; padding: 0; }
.xmlcatag-tree li { list-style: disc; margin: 4px 0; }
.xmlcatag-node { display:grid; grid-template-columns: minmax(90px, 120px) minmax(160px, 1fr) minmax(160px, 1.4fr) minmax(180px, 1.4fr) auto auto; gap: 10px; align-items:center; }
.xmlcatag-cell { display:block; min-height: 26px; }
.xmlcatag-check { min-width: 90px; }
.xmlcatag-title { font-weight: 600; }
.xmlcatag-actions button { margin-right: 6px; }
.xmlcatag-exclude { display:inline-flex; align-items:center; gap: 6px; margin-right: 8px; font-size: 12px; }
.xmlcatag-exclude input { margin:0; }
.xmlcatag-path code, .xmlcatag code { font-size: 12px; }
.xmlcatag-badge { display:inline-block; padding:2px 7px; border:1px solid #ccc; border-radius:999px; font-size:12px; }
.xmlcatag-reason { font-size: 12px; opacity: .8; }
.xmlcatag-warn { background:#fff7e6; border:1px solid #ffe3a3; padding: 8px 10px; border-radius: 6px; margin: 10px 0; }
.xmlcatag-tags { margin: 8px 0 0 0; padding-left: 18px; }
.xmlcatag-tags li { margin: 6px 0; }
</style>
<?php if (!empty($this->selectedFile)) : ?>
<a class="btn btn-primary"
   href="index.php?option=com_xmlcatag&task=preview&filename=<?php echo urlencode($this->selectedFile); ?>">
   Preview
</a>
<?php endif; ?>
