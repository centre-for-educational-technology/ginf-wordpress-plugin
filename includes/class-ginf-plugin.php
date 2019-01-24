<?php

/**
 * GINF main plugin class
 */
class GINF_Plugin {
  /**
   * Plugin version for cache busting and database updates.
   * @var string
   */
  const VERSION = '0.1.0';

  /**
   * Instance of this class
   *
   * @var \GINF_Plugin
   */
  protected static $instance = null;

  /**
   * Initialize all the required plugin functionalities
   */
  private function __construct() {
    add_action('wp_enqueue_scripts', [$this, 'enqueue_styles_and_scripts']);
    add_action('rest_api_init', [$this, 'init_rest_api']);
    add_action('h5p_alter_library_scripts', [$this, 'h5p_alter_library_scripts'], 10, 3);
    add_action('init', ['GINF_Plugin', 'check_for_updates'], 1);
  }

  /**
   * Return an instance of this class or creates one before returning, if one
   * does not yet exist.
   *
   * @return \GINF_Plugin
   */
  public static function get_instance() {
    // If the single instance hasn't been set, set it now.
    if (null == self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * Run activation procedures
   */
  public static function activate() {
    self::check_for_updates();
  }

  /**
   * Run deacivation procedures
   */
  public static function deactivate() {
    // TODO Deal with database tables
    delete_option('ginf_version');
  }

  /**
   * Enqueue any scripts and/or styles
   */
  public function enqueue_styles_and_scripts() {
    $version = self::VERSION;

    wp_enqueue_script('ginf/pressbooks', plugin_dir_url(__FILE__) . '../public/js/pressbooks.js', ['jquery',], $version);
    wp_enqueue_script('ginf/h5p', plugin_dir_url(__FILE__) . '../public/js/h5p.js', [], $version);

    wp_localize_script('ginf/h5p', 'ginf_h5p_rest_object',
    [
      'api_nonce' => wp_create_nonce( 'wp_rest' ),
      'api_url'   => site_url('/wp-json/ginf/v1/')
    ]);

    wp_enqueue_style('ginf/enlighter', plugin_dir_url(__FILE__) . '../public/css/enlighter.css', [], $version);
  }

  /**
   * Implements h5p_alter_library_scripts action
   */
  public function h5p_alter_library_scripts(&$scripts, $libraries, $embed_type) {
    if ($embed_type === 'external') {
      $scripts[] = (object) [
        // Path can be relative to wp-content/uploads/h5p or absolute.
        'path' => plugin_dir_url(__FILE__) . '../public/js/h5p-external.js',
        'version' => '?ver=' . self::VERSION // Cache buster
      ];
    }
  }

  /**
   * Registers REST API ndpoints
   */
  public function init_rest_api() {
    register_rest_route( 'ginf/v1', '/xapi/statements', [
      'methods' => WP_REST_Server::CREATABLE,
      'callback' => [$this, 'rest_xapi_statements'],
    ]);
  }

  /**
   * REST API statements endpoint handler
   * @param  WP_REST_Request $request Request object
   * @return WP_REST_Response
   */
  public function rest_xapi_statements($request) {
    global $wpdb;
    // XXX Second nonce fails in case of logged in user, the rest API is probably to blame for it acting like that
    // It might be needed to define a standalone endpoint that uses ajax API in order to get that working
    if (!(wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest') || wp_verify_nonce($request->get_header('X-H5P-Nonce'), 'h5p_result')))
    {
      return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $statement = $request->get_param('statement');

    if (!$statement) {
      return new WP_REST_Response(['message' => 'Bad Request'], 400);
    }

    $statement['timestamp'] = date(DATE_RFC3339);

    $time = current_time('mysql');

    $result = $wpdb->insert("{$wpdb->base_prefix}ginf_xapi_statements", [
      'blog_id' => get_current_blog_id(),
      'created_at' => $time,
      'updated_at' => $time,
      'statement' => json_encode($statement),
    ], [
      '%d', '%s', '%s', '%s'
    ]);

    if (!$result) {
      return new WP_REST_Response(['message' => 'Internal Server Error'], 500);
    }

    return new WP_REST_Response($statement, 200);
  }

  /**
   * Checks for and applies database updates based on current plugin version
   */
  public static function check_for_updates() {
    $current_version = get_option('ginf_version');

    if ($current_version === self::VERSION) {
      return;
    }

    if (!$current_version) {
      $current_version = '0.0.0';
    }

    self::update_database();

    if ($current_version === '0.0.0') {
      add_option('ginf_version', self::VERSION);
    } else {
      update_option('ginf_version', self::VERSION);
    }
  }

  /**
   * Updates database according to defined schema
   */
  public static function update_database() {
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE {$wpdb->base_prefix}ginf_xapi_statements (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      blog_id BIGINT(20) UNSIGNED NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT 0,
      updated_at TIMESTAMP NOT NULL DEFAULT 0,
      statement LONGTEXT NOT NULL,
      PRIMARY KEY  (id),
      KEY blog_id (blog_id),
      KEY created_at (created_at),
      KEY updated_at (created_at)
    ) {$charset};");
  }
}
