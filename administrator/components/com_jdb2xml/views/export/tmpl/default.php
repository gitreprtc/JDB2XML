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
    </div>
  </div>

  <div class="jdb2xml-export-section">
    <h3>Automatic export schedule</h3>
    <?php
      $weekday = $this->params->get('export_weekday', 'monday');
      $interval = (int) $this->params->get('export_interval_hours', 24);
      $timeFrom = $this->params->get('export_time_from', '00:00');
      $timeTo = $this->params->get('export_time_to', '23:59');
    ?>
    <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-export-form">
      <input type="hidden" name="task" value="saveexportschedule">
      <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
      <div class="jdb2xml-export-grid">
        <div class="jdb2xml-export-weekdays">
          <span class="jdb2xml-export-weekday-label">Weekday</span>
          <label class="jdb2xml-export-weekday-option">
            <input type="radio" name="export_weekday" value="monday" <?php echo $weekday === 'monday' ? 'checked' : ''; ?>>
            Monday
          </label>
          <label class="jdb2xml-export-weekday-option">
            <input type="radio" name="export_weekday" value="tuesday" <?php echo $weekday === 'tuesday' ? 'checked' : ''; ?>>
            Tuesday
          </label>
          <label class="jdb2xml-export-weekday-option">
            <input type="radio" name="export_weekday" value="wednesday" <?php echo $weekday === 'wednesday' ? 'checked' : ''; ?>>
            Wednesday
          </label>
          <label class="jdb2xml-export-weekday-option">
            <input type="radio" name="export_weekday" value="thursday" <?php echo $weekday === 'thursday' ? 'checked' : ''; ?>>
            Thursday
          </label>
          <label class="jdb2xml-export-weekday-option">
            <input type="radio" name="export_weekday" value="friday" <?php echo $weekday === 'friday' ? 'checked' : ''; ?>>
            Friday
          </label>
          <label class="jdb2xml-export-weekday-option">
            <input type="radio" name="export_weekday" value="saturday" <?php echo $weekday === 'saturday' ? 'checked' : ''; ?>>
            Saturday
          </label>
          <label class="jdb2xml-export-weekday-option">
            <input type="radio" name="export_weekday" value="sunday" <?php echo $weekday === 'sunday' ? 'checked' : ''; ?>>
            Sunday
          </label>
        </div>
        <label>
          Every (hours)
          <input type="number" name="export_interval_hours" min="1" value="<?php echo (int) $interval; ?>">
        </label>
        <label>
          From
          <input type="time" name="export_time_from" value="<?php echo htmlspecialchars($timeFrom, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
          To
          <input type="time" name="export_time_to" value="<?php echo htmlspecialchars($timeTo, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
      </div>
      <button type="submit" class="btn btn-success">Save schedule</button>
    </form>
    <p class="jdb2xml-export-note">
      The schedule runs on the selected weekday every N hours within the time window. The start time is calculated from the "From" value.
    </p>
  </div>
</div>

<style>
.jdb2xml-export { padding: 16px 0; }
.jdb2xml-export-section { margin-bottom: 18px; }
.jdb2xml-export-list { display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-export-row { display: flex; align-items: center; gap: 12px; }
.jdb2xml-export-label { min-width: 120px; font-weight: 600; }
.jdb2xml-export-form { margin-top: 10px; display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-export-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.jdb2xml-export-grid label { display: flex; flex-direction: column; gap: 6px; font-weight: 600; }
.jdb2xml-export-note { color: #666; font-size: 12px; margin-top: 8px; }
.jdb2xml-export-weekdays { display: flex; flex-direction: column; gap: 6px; font-weight: 600; }
.jdb2xml-export-weekday-label { font-weight: 600; }
.jdb2xml-export-weekday-option { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
</style>
