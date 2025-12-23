<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
?>

<div class="jdb2xml-import-automatic">
  <div class="jdb2xml-brand">
    <img src="<?php echo Uri::root(); ?>administrator/components/com_jdb2xml/assets/jdb2xml_logo.svg" alt="JDB2XML logo">
  </div>
  <h2>Automatic Import</h2>

  <div class="jdb2xml-import-section">
    <h3>Automatic import schedule</h3>
    <?php
      $rawSchedule = $this->params->get('import_schedule', []);
      $schedule = is_object($rawSchedule) ? (array) $rawSchedule : (array) $rawSchedule;
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
    <form action="index.php?option=com_jdb2xml" method="post" class="jdb2xml-import-form">
      <input type="hidden" name="task" value="saveimportschedule">
      <?php echo Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
      <div class="jdb2xml-import-schedule">
        <div class="jdb2xml-import-schedule-row jdb2xml-import-schedule-head">
          <span>Weekday</span>
          <span>Every (minutes)</span>
          <span>From</span>
          <span>To</span>
        </div>
        <?php foreach ($weekdays as $key => $label): ?>
          <?php
            $dayRaw = $schedule[$key] ?? [];
            $day = is_object($dayRaw) ? (array) $dayRaw : (array) $dayRaw;
            $interval = (int) ($day['interval_minutes'] ?? 60);
            if ($interval < 10) {
                $interval = 10;
            }
            $timeFrom = (string) ($day['time_from'] ?? '00:00');
            $timeTo = (string) ($day['time_to'] ?? '23:59');
          ?>
          <div class="jdb2xml-import-schedule-row">
            <span class="jdb2xml-import-schedule-day"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            <input type="number"
                   name="import_schedule[<?php echo $key; ?>][interval_minutes]"
                   min="10"
                   value="<?php echo (int) $interval; ?>">
            <input type="time"
                   name="import_schedule[<?php echo $key; ?>][time_from]"
                   value="<?php echo htmlspecialchars($timeFrom, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="time"
                   name="import_schedule[<?php echo $key; ?>][time_to]"
                   value="<?php echo htmlspecialchars($timeTo, ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-success">Save schedule</button>
    </form>
    <p class="jdb2xml-import-note">
      Automatic import reads XML files from the <code>import/automatic</code> folder. The schedule runs on each weekday every N minutes within the time window. The start time is calculated from the "From" value.
    </p>
  </div>

  <div class="jdb2xml-import-section">
    <h3>Recent import batches</h3>
    <?php if (empty($this->batches)): ?>
      <div class="jdb2xml-empty">No import batches yet.</div>
    <?php else: ?>
      <table class="jdb2xml-import-table">
        <thead>
          <tr>
            <th>Date/time</th>
            <th>Processed</th>
            <th>Skipped</th>
            <th>Failed</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_reverse($this->batches) as $batch): ?>
            <tr>
              <td><?php echo htmlspecialchars((string) ($batch['timestamp'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo (int) ($batch['processed'] ?? 0); ?></td>
              <td><?php echo (int) ($batch['skipped'] ?? 0); ?></td>
              <td><?php echo (int) ($batch['failed'] ?? 0); ?></td>
              <td>
                <?php
                  $status = (string) ($batch['status'] ?? '');
                  $label = $status === 'success' ? 'Success' : 'Failed';
                ?>
                <span class="jdb2xml-import-status jdb2xml-import-status-<?php echo $status === 'success' ? 'success' : 'failed'; ?>">
                  <?php echo $label; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="jdb2xml-copyright">Copyright Robin Colbers</div>
</div>

<style>
.jdb2xml-import-automatic { padding: 16px 0; position: relative; padding-right: 260px; }
.jdb2xml-import-section { margin-bottom: 18px; }
.jdb2xml-import-form { margin-top: 10px; display: flex; flex-direction: column; gap: 10px; }
.jdb2xml-import-schedule { display: grid; gap: 8px; }
.jdb2xml-import-schedule-row { display: grid; grid-template-columns: minmax(120px, 1fr) 140px 120px 120px; gap: 12px; align-items: center; }
.jdb2xml-import-schedule-head { font-weight: 700; color: #444; }
.jdb2xml-import-schedule-row input { width: 100%; }
.jdb2xml-import-schedule-day { font-weight: 600; }
.jdb2xml-import-note { color: #666; font-size: 12px; margin-top: 8px; }
.jdb2xml-import-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.jdb2xml-import-table th, .jdb2xml-import-table td { padding: 6px 8px; border-bottom: 1px solid #e5e5e5; text-align: left; }
.jdb2xml-import-table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: .02em; color: #666; }
.jdb2xml-import-status { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.jdb2xml-import-status-success { background: #e8f5e9; color: #1b5e20; }
.jdb2xml-import-status-failed { background: #ffebee; color: #b71c1c; }
.jdb2xml-empty { color: #666; font-style: italic; }
.jdb2xml-brand { position: absolute; top: 0; right: 0; }
.jdb2xml-brand img { max-width: 220px; height: auto; }
.jdb2xml-copyright { margin-top: 20px; font-size: 12px; color: #666; }
</style>
