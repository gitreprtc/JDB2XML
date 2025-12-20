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
      $schedule = (array) $this->params->get('export_schedule', []);
      $weekdays = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
      ];
    ?>
    <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-export-form">
      <input type="hidden" name="task" value="saveexportschedule">
      <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
      <div class="jdb2xml-export-schedule">
        <div class="jdb2xml-export-schedule-row jdb2xml-export-schedule-head">
          <span>Weekday</span>
          <span>Every (hours)</span>
          <span>From</span>
          <span>To</span>
        </div>
        <?php foreach ($weekdays as $key => $label): ?>
          <?php
            $day = $schedule[$key] ?? [];
            $interval = (int) ($day['interval_hours'] ?? 24);
            $timeFrom = (string) ($day['time_from'] ?? '00:00');
            $timeTo = (string) ($day['time_to'] ?? '23:59');
          ?>
          <div class="jdb2xml-export-schedule-row">
            <span class="jdb2xml-export-schedule-day"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            <input type="number"
                   name="export_schedule[<?php echo $key; ?>][interval_hours]"
                   min="1"
                   value="<?php echo (int) $interval; ?>">
            <input type="time"
                   name="export_schedule[<?php echo $key; ?>][time_from]"
                   value="<?php echo htmlspecialchars($timeFrom, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="time"
                   name="export_schedule[<?php echo $key; ?>][time_to]"
                   value="<?php echo htmlspecialchars($timeTo, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-success">Save schedule</button>
    </form>
    <p class="jdb2xml-export-note">
      The schedule runs on the selected weekday every N hours within the time window. The start time is calculated from the "From" value.
    </p>
  </div>

  <div class="jdb2xml-export-section">
    <h3>Recent export batches</h3>
    <?php if (empty($this->batches)): ?>
      <div class="jdb2xml-empty">No export batches yet.</div>
    <?php else: ?>
      <table class="jdb2xml-export-table">
        <thead>
          <tr>
            <th>Date/time</th>
            <th>Type</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_reverse($this->batches) as $batch): ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($batch['timestamp'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) ($batch['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php
                  $status = (string) ($batch['status'] ?? '');
                  $label = $status === 'success' ? 'Success' : 'Failed';
                ?>
                <span class="jdb2xml-export-status jdb2xml-export-status-<?php echo $status === 'success' ? 'success' : 'failed'; ?>">
                  <?php echo $label; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<style>
.jdb2xml-export { padding: 16px 0; }
.jdb2xml-export-section { margin-bottom: 18px; }
.jdb2xml-export-list { display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-export-row { display: flex; align-items: center; gap: 12px; }
.jdb2xml-export-label { min-width: 120px; font-weight: 600; }
.jdb2xml-export-form { margin-top: 10px; display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-export-schedule { display: grid; gap: 8px; }
.jdb2xml-export-schedule-row { display: grid; grid-template-columns: minmax(120px, 1fr) 140px 120px 120px; gap: 12px; align-items: center; }
.jdb2xml-export-schedule-head { font-weight: 700; color: #444; }
.jdb2xml-export-schedule-row input { width: 100%; }
.jdb2xml-export-schedule-day { font-weight: 600; }
.jdb2xml-export-note { color: #666; font-size: 12px; margin-top: 8px; }
.jdb2xml-export-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.jdb2xml-export-table th, .jdb2xml-export-table td { padding: 6px 8px; border-bottom: 1px solid #e5e5e5; text-align: left; }
.jdb2xml-export-table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: .02em; color: #666; }
.jdb2xml-export-status { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.jdb2xml-export-status-success { background: #e8f5e9; color: #1b5e20; }
.jdb2xml-export-status-failed { background: #ffebee; color: #b71c1c; }
</style>
