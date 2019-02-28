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
   * Returns current plugin version
   *
   * @return string Plugin version
   */
  public static function get_version() {
    return GINF_Plugin:: VERSION;
  }

  /**
   * Enqueue any scripts and/or styles
   */
  public function enqueue_styles_and_scripts() {
    $version = self::get_version();

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
      __('LRS Settings', 'ginf'),
      __('LRS Settings', 'ginf'),
      'manage_network_options',
      'ginf_lrs_settings',
      [$this, 'lrs_settings_page']
    );
    add_menu_page(
      __('LRS', 'ginf'),
      __('LRS', 'ginf'),
      'manage_network_options',
      'ginf_lrs',
      [$this, 'lrs_statistics']
    );
  }

  /**
   * Display LRS settings page
   */
  public function lrs_settings_page() {
    $version = self::get_version();

    wp_enqueue_script('ginf/lrs-settings', plugin_dir_url(__FILE__) . '../public/js/lrs-settings.js', ['jquery'], $version);
    wp_localize_script('ginf/lrs-settings', 'ginf_h5p_rest_object',
    [
      'api_nonce' => wp_create_nonce( 'wp_rest' ),
      'api_url'   => site_url('/wp-json/ginf/v1/')
    ]);
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

  /**
   * Displays LRS statistics page
   */
  public function lrs_statistics() {
    $version = self::get_version();

    wp_register_script('ginf/d3', '//cdnjs.cloudflare.com/ajax/libs/d3/5.9.1/d3.min.js', [], $version);
    wp_register_script('ginf/c3', '//cdnjs.cloudflare.com/ajax/libs/c3/0.6.12/c3.min.js', ['ginf/d3'], $version);
    wp_register_style('ginf/c3', '//cdnjs.cloudflare.com/ajax/libs/c3/0.6.12/c3.min.css', [], $version);

    wp_enqueue_script('ginf/lrs-statistics', plugin_dir_url(__FILE__) . '../public/js/lrs-statistics.js', ['jquery', 'ginf/c3'], $version);
    wp_enqueue_style('ginf/lrs-statistics', plugin_dir_url(__FILE__) . '../public/css/lrs-statistics.css', ['ginf/c3'], $version);

    wp_localize_script('ginf/lrs-statistics', 'ginfLrsStatistics', [
      'statements' => $this->lrs_statement_statistics_data(),
      'requests' => $this->lrs_http_requests_statistics_data(),
    ]);

    include __DIR__ . '/pages/page-lrs-statistics.php';
  }

  /**
   * Returns data on statements being sent or unsent (only codes 200 and 400 are considered not to get duplicates)
   *
   * @return array An array of objects with code, message and total properties
   */
  public function lrs_statement_statistics_data() {
    static $stats;

    if (isset($stats)) {
      return $stats;
    }

    global $wpdb;

    $stats = $wpdb->get_results("SELECT t1.code, (SELECT t2.message FROM {$wpdb->base_prefix}ginf_xapi_http_log t2 WHERE t1.code = t2.code LIMIT 1) AS message, SUM(t1.statements_count) AS total FROM {$wpdb->base_prefix}ginf_xapi_http_log t1 WHERE t1.code = 200 OR t1.code = 400 GROUP BY code");

    return $stats;
  }

  /**
   * Returns data on LRS HTTP Requests statistics
   *
   * @return array An array of objects with code, message and total properties
   */
  public function lrs_http_requests_statistics_data() {
    static $stats;

    if (isset($stats)) {
      return $stats;
    }

    global $wpdb;

    $stats = $wpdb->get_results("SELECT t1.code, (SELECT t2.message FROM {$wpdb->base_prefix}ginf_xapi_http_log t2 WHERE t1.code = t2.code LIMIT 1) AS message, COUNT(*) AS total FROM {$wpdb->base_prefix}ginf_xapi_http_log t1 GROUP BY code");

    return $stats;
  }
}
