<?php

/**
 * GINF admin main plugin class
 */
class GINF_Plugin_Admin {
  /**
   * Instance of this class
   *
   * @var \GINF_Plugin_Admin
   */
  protected static $instance = null;

  /**
   * Initialize all the required plugin functionalities
   */
  private function __construct() {
    add_action('admin_enqueue_scripts', [$this, 'enqueue_styles_and_scripts']);
    add_action('h5p_alter_library_scripts', [$this, 'h5p_alter_library_scripts'], 10, 3);
    add_filter('http_request_args', [$this, 'http_request_args'], 999, 2);
    add_action('network_admin_menu', [$this, 'network_admin_menu']);
    add_action('network_admin_edit_ginf_lrs_settings',  [$this, 'lrs_save_settings'], 10, 0);
  }

  /**
   * Return an instance of this class or creates one before returning, if one
   * does not yet exist.
   *
   * @return \GINF_Plugin_Admin
   */
  public static function get_instance() {
    // If the single instance hasn't been set, set it now.
    if (null == self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * Enqueue any scripts and/or styles
   */
  public function enqueue_styles_and_scripts() {
    $version = GINF_Plugin::VERSION;

    //wp_enqueue_script('ginf/h5p/ckeditor/extraplugins', plugin_dir_url(__FILE__) . '../public/js/h5p-editor.js', [], $version);
  }

  /**
   * Implements h5p_alter_library_scripts action
   */
  public function h5p_alter_library_scripts(&$scripts, $libraries, $embed_type) {
     if ($embed_type === 'editor') {
      /*$scripts[] = (object) [
        'path' => plugin_dir_url(__FILE__) . '../public/ckeditor/extraplugins.js',
        'version' => '?ver=' . GINF_Plugin::VERSION
      ];*/
    }
  }

  /**
   * Make sure to set longer timeout to H5P API cURL calls
   * @param  array  $r   Array of arguments
   * @param  string $url URL of the request
   * @return array       Array of arguments
   */
  public function http_request_args($r, $url) {
    if (isset($r['timeout']) && (int)$r['timeout'] < 90 && stripos($url, 'api.h5p.org') !== FALSE) {
      $r['timeout'] = 90;
    }

    return $r;
  }

  /**
   * Add pages to network admin menu
   */
  public function network_admin_menu() {
    add_submenu_page(
      'settings.php',
      'LRS Settings',
      'LRS Settings',
      'manage_network_options',
      'ginf_lrs_settings',
      [$this, 'lrs_settings_page']
    );
  }

  /**
   * Display LRS settings page
   */
  public function lrs_settings_page() {
    include __DIR__ . '/pages/page-lrs-settings.php';
  }

  /**
   * Store LRS settings
   */
  public function lrs_save_settings() {
    check_admin_referer('ginf_lrs_settings');
    if(!current_user_can('manage_network_options')) wp_die('...');

    update_site_option('ginf_lrs_xapi_endpoint', $_POST['xapi_endpoint']);
    update_site_option('ginf_lrs_key', $_POST['key']);
    update_site_option('ginf_lrs_secret', $_POST['secret']);
    update_site_option('ginf_lrs_batch_size', $_POST['batch_size']);

    wp_redirect(add_query_arg([
      'page' => 'ginf_lrs_settings',
      'updated' => 'true'], network_admin_url('settings.php')
    ));
    exit;
  }
}
