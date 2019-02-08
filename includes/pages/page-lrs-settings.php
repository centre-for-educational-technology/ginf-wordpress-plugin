<?php
if(!current_user_can('manage_network_options')) wp_die('...');
?>

<div class="wrap">
  <h1><?php _e('LRS Settings', 'ginf'); ?></h1>
  <form method="POST" action="edit.php?action=ginf_lrs_settings" novalidate="novalidate">
    <?php wp_nonce_field('ginf_lrs_settings'); ?>
    <table class="form-table">
      <tbody>
        <tr>
          <th scope="row"><label for="xapi_endpoint"><?php _e('xAPI Endpoint', 'gifn'); ?></label></th>
          <td>
            <input name="xapi_endpoint" type="url" id="xapi_endpoint" class="regular-text" value="<?php echo esc_attr(get_site_option('ginf_lrs_xapi_endpoint')); ?>">
            <p class="description" id="xapi-endpoint-desc">
              <?php _e('xAPI Endpoint URL (no trailing slash)', 'ginf'); ?>
            </p>
          </td>
        </tr>

        <tr>
          <th scope="row"><label for="key"><?php _e('Key', 'gifn'); ?></label></th>
          <td>
            <input name="key" type="text" id="key" class="regular-text" value="<?php echo esc_attr(get_site_option('ginf_lrs_key')); ?>">
            <p class="description" id="key-desc">
              <?php _e('LRS Client Key', 'ginf'); ?>
            </p>
          </td>
        </tr>

        <tr>
          <th scope="row"><label for="secret"><?php _e('Secret', 'gifn'); ?></label></th>
          <td>
            <input name="secret" type="text" id="secret" class="regular-text" value="<?php echo esc_attr(get_site_option('ginf_lrs_secret')); ?>">
            <p class="description" id="secret-desc">
              <?php _e('LRS Client Secret', 'ginf'); ?>
            </p>
          </td>
        </tr>

        <tr>
          <th scope="row"><label for="batch_size"><?php _e('Batch size', 'gifn'); ?></label></th>
          <td>
            <input name="batch_size" type="number" id="batch_size" class="regular-text" value="<?php echo (int) get_site_option('ginf_lrs_batch_size'); ?>">
            <p class="description" id="batch-size-desc">
              <?php _e('Batch size to be used when sending statements to the LRS (defaults to 100 if not set).', 'ginf'); ?>
            </p>
          </td>
        </tr>
      </tbody>
    </table>
    <?php submit_button(); ?>
  </form>
</div>
