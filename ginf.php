<?php
/**
 * Plugin Name:     GINF
 * Plugin URI:      https://github.com/centre-for-educational-technology/ginf-wordpress-plugin
 * Description:     GINF WordPress plugin
 * Author:          HTK
 * Author URI:      https://github.com/centre-for-educational-technology
 * Text Domain:     ginf
 * Domain Path:     /languages
 * License:         MIT
 * License URI:     http://opensource.org/licenses/MIT
 * Version:         0.5.0
 *
 * @package         GINF
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}

DEFINE('GINF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once plugin_dir_path(__FILE__) . 'includes/class-ginf-plugin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ginf-plugin-admin.php';

register_activation_hook(__FILE__, ['GINF_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['GINF_Plugin', 'deactivate']);
add_action('plugins_loaded', ['GINF_Plugin', 'get_instance']);

if (is_admin()) {
  add_action('plugins_loaded', ['GINF_Plugin_Admin', 'get_instance']);
}
