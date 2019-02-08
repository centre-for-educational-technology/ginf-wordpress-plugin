<?php

/**
 * GINF main plugin class
 */
class GINF_Plugin {
  /**
   * Plugin version for cache busting and database updates.
   * @var string
   */
  const VERSION = '0.2.0';

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
    add_action('h5p_additional_embed_head_tags', [$this, 'h5p_additional_embed_head_tags'], 10, 3);
    add_action('h5p_alter_library_semantics', [$this, 'h5p_alter_library_semantics'], 10, 4);
    add_action('ginf_process_xapi_statements', [$this, 'process_xapi_statements'], 5);
    add_action('ginf_process_xapi_statements', [$this, 'process_xapi_batches'], 5);
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

    if (!wp_next_scheduled('ginf_process_xapi_statements')) {
      wp_schedule_event(time(), 'hourly', 'ginf_process_xapi_statements');
    }
  }

  /**
   * Run deacivation procedures
   */
  public static function deactivate() {
    // TODO Deal with database tables
    delete_option('ginf_version');
    wp_clear_scheduled_hook('ginf_process_xapi_statements');
    // TODO Remove settings
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
   * Implements h5p_additional_embed_head_tags action
   */
  public function h5p_additional_embed_head_tags(&$tags) {
    $tags[] = '<meta name="api_nonce" content="' . wp_create_nonce( 'wp_rest' ) . '">';
  }

  /**
   * Implements h5p_alter_library_semantics action
   */
  public function h5p_alter_library_semantics(&$semantics, $name, $majorVersion, $minorVersion) {
    // SOURCE: https://h5p.org/comment/11449#comment-11449
    foreach ($semantics as $field) {
      // Go through list fields
      while ($field->type === 'list') {
        $field = $field->field;
      }
      // Go through group fields
      if ($field->type === 'group') {
        $this->h5p_alter_library_semantics($field->fields, $name, $majorVersion, $minorVersion);
      }

      // Check to see if we have the correct type and widget
      if ($field->type === 'text' && isset($field->widget) && $field->widget === 'html') {
        // Found a field. Add support for table tags.
        if (!isset($field->tags)) {
          $field->tags = [];
        }
        $field->tags = array_merge($field->tags, ['pre',]);
      }
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

    if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest'))
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

    dbDelta("CREATE TABLE {$wpdb->base_prefix}ginf_xapi_batches (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      created_at TIMESTAMP NOT NULL DEFAULT 0,
      updated_at TIMESTAMP NOT NULL DEFAULT 0,
      statements LONGTEXT NOT NULL,
      statements_count SMALLINT UNSIGNED NOT NULL,
      retries SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY  (id),
      KEY created_at (created_at),
      KEY updated_at (created_at),
      KEY statements_count (statements_count),
      KEY retries (retries)
    ) {$charset};");
  }

  /**
   * Processes statements and creates batches
   */
  public function process_xapi_statements() {
    global $wpdb;

    $size = (int) get_site_option('ginf_lrs_batch_size');
    if (!(int)$size > 1) {
      $size = 100;
    }

    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}ginf_xapi_statements");
    if ($count > 0) {
      $batches = ceil($count / $size);

      foreach(range(1, $size) as $batch) {
        $statements = $wpdb->get_results("SELECT id, statement FROM {$wpdb->base_prefix}ginf_xapi_statements ORDER BY created_at ASC LIMIT $size");
        $ids = [];
        $data = [];
        foreach ($statements as $statement) {
          $ids[] = $statement->id;
          $data[] = json_decode($statement->statement);
          $time = current_time('mysql');
          $wpdb->insert("{$wpdb->base_prefix}ginf_xapi_batches", [
            'created_at' => $time,
            'updated_at' => $time,
            'statements' => json_encode($data),
            'statements_count' => sizeof($data),
          ], [
            '%s', '%s', '%s', '%d'
          ]);
          $in = implode(',', array_map('intval', $ids)); // TODO See if INTVAL is even needed as data is being fetched directly from the database
          $wpdb->query("DELETE FROM {$wpdb->base_prefix}ginf_xapi_statements WHERE id IN ($in)");
        }
      }
    }
  }

  /**
   * Processes batches and sends the data to LRS
   * @return [type] [description]
   */
  public function process_xapi_batches() {
    global $wpdb;

    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}ginf_xapi_batches");
    if ($count > 0) {
      $endpoint = get_site_option('ginf_lrs_xapi_endpoint');
      $key = get_site_option('ginf_lrs_key');
      $secret = get_site_option('ginf_lrs_secret');
      if (!($endpoint && $key && $secret)) {
        return;
      }

      $url = get_site_option('ginf_lrs_xapi_endpoint') . '/statements';
      $auth = base64_encode("$key:$secret");

      $batches = $wpdb->get_results("SELECT id, statements, statements_count, retries FROM {$wpdb->base_prefix}ginf_xapi_batches ORDER BY created_at ASC");
      foreach ($batches as $batch) {
        $response = wp_remote_request(get_site_option('ginf_lrs_xapi_endpoint') . '/statements', [
          'method' => 'POST',
          'timeout' => 45,
          'headers' => [
            'Content-Type' => 'application/json',
            'X-Experience-API-Version' => '1.0.1',
            'Authorization' => "Basic $auth",
            'Content-Length' => strlen($batch->statements),
          ],
          'body' => $batch->statements,
        ]);

        if ($response instanceof WP_Error) {
          // TODO Extract required data from error object
          // TODO Either raise number of retries or remove from the database (bad request case)
        } else {
          // TODO Remove data from the database
          // TODO Add information to the log table
        }
      }
      // TODO Go through the batches and try to send those
      // Make sure that we only try to send a certain number of those (total time less than an hour or so)
      // If seiding fails, up the number of retries
      // Make sure that response codes are checked (bad requests should just be added into the log and never retried)
      // See if there should be a meaningful number of retries
    }
  }
}
