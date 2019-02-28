<?php
if(!current_user_can('manage_network_options')) wp_die('...');

$admin = GINF_Plugin_Admin::get_instance();

$statements = $admin->lrs_statement_statistics_data();
$requests = $admin->lrs_http_requests_statistics_data();
?>

<div class="wrap">
  <h1><?php _e('LRS Statistics', 'ginf'); ?></h1>
  <div class="statement-statistics">
    <div class="graph-container"></div>
    <table>
      <thead>
        <tr>
          <th><?php _e('Code', 'ginf'); ?></th>
          <th><?php _e('Reason', 'ginf'); ?></th>
          <th><?php _e('Total (statements)', 'ginf'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($statements && sizeof($statements) > 0): ?>
          <?php foreach ($statements as $stat): ?>
            <tr>
              <td><?php echo $stat->code; ?></td>
              <td><?php echo $stat->message; ?></td>
              <td><?php echo $stat->total; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="request-statistics">
    <div class="graph-container"></div>
    <table>
      <thead>
        <tr>
          <th><?php _e('Code', 'ginf'); ?></th>
          <th><?php _e('Reason', 'ginf'); ?></th>
          <th><?php _e('Total', 'ginf'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($requests && sizeof($requests) > 0): ?>
          <?php foreach ($requests as $stat): ?>
            <tr>
              <td><?php echo $stat->code; ?></td>
              <td><?php echo $stat->message; ?></td>
              <td><?php echo $stat->total; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
