<?php

/**
 * GINF main plugin class
 */
class GINF_Plugin {
  /**
   * Plugin version for cache busting and database updates.
   * @var string
   */
  const VERSION = '0.4.1';

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
    add_action('h5p_alter_library_styles', [$this, 'h5p_alter_library_styles'], 10, 3);
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
   * Determines if Enlighter plugin is active (currently checks if the constants
   * are defined).
   *
   * @return boolean
   */
  public function is_enlighter_active() {
    return defined('ENLIGHTER_VERSION') && defined('ENLIGHTER_PLUGIN_URL');
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

    wp_enqueue_script('ginf/pressbooks', GINF_PLUGIN_URL . 'public/js/pressbooks.js', ['jquery',], $version);
    wp_enqueue_script('ginf/h5p', GINF_PLUGIN_URL . 'public/js/h5p.js', [], $version);

    wp_localize_script('ginf/h5p', 'ginf_h5p_rest_object',
    [
      'api_nonce' => wp_create_nonce( 'wp_rest' ),
      'api_url'   => site_url('/wp-json/ginf/v1/')
    ]);

    wp_enqueue_style('ginf/enlighter', GINF_PLUGIN_URL . 'public/css/enlighter.css', [], $version);
  }

  /**
   * Implements h5p_alter_library_scripts action
   */
  public function h5p_alter_library_scripts(&$scripts, $libraries, $embed_type) {
    $version = self::VERSION;

    if ($embed_type === 'external') {
      $scripts[] = (object) [
        'path' => GINF_PLUGIN_URL . 'public/js/h5p-external.js',
        'version' => '?ver=' . $version,
      ];
    } else if ($embed_type === 'iframe') {
      if ($this->is_enlighter_active()) {
        $scripts[] = (object) [
          'path' => ENLIGHTER_PLUGIN_URL . 'resources/mootools-core-yc.js',
          'version' => '?ver=' . ENLIGHTER_VERSION,
        ];
        $scripts[] = (object) [
          'path' => ENLIGHTER_PLUGIN_URL . 'resources/EnlighterJS.min.js',
          'version' => '?ver=' . ENLIGHTER_VERSION,
        ];
        $scripts[] = (object) [
          'path' => GINF_PLUGIN_URL . 'public/js/enlighter.js',
          'version' => '?ver=' . $version,
        ];
      }
    }
  }

  /**
   * Implements h5p_alter_library_scripts action
   */
  public function h5p_alter_library_styles(&$styles, $libraries, $embed_type) {
    $version = self::VERSION;

    if ($embed_type === 'iframe') {
      if ($this->is_enlighter_active()) {
        $styles[] = (object) [
          'path' => GINF_PLUGIN_URL . 'public/css/enlighter.css',
          'version' => '?ver=' . $version,
        ];
        $styles[] = (object) [
          'path' => ENLIGHTER_PLUGIN_URL . 'resources/EnlighterJS.min.css',
          'version' => '?ver=' . ENLIGHTER_VERSION,
        ];
        $styles[] = (object) [
          'path' => GINF_PLUGIN_URL . 'public/css/enlighter.css',
          'version' => '?ver=' . $version,
        ];
      }
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
        $field->tags = array_merge($field->tags, ['pre', 'code',]);
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
    register_rest_route( 'ginf/v1', '/xapi/connection/test', [
      'methods' => WP_REST_Server::CREATABLE,
      'callback' => [$this, 'rest_xapi_connection_test'],
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

    $statement = json_decode($statement, TRUE);

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
   * REST API LRS connection test
   * @param  WP_REST_Request $request Request object
   * @return WP_REST_Response
   */
  public function rest_xapi_connection_test($request) {
    if (!current_user_can('manage_network_options')) {
      return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest'))
    {
      return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    $endpoint = $request->get_param('endpoint');
    $key = $request->get_param('key');
    $secret = $request->get_param('secret');

    if (!($endpoint && $key && $secret)) {
      return new WP_REST_Response(['message' => 'Bad Request'], 400);
    }

    $data = json_encode([]);

    $response = $this->send_statements_to_lrs($endpoint, $key, $secret, $data);

    if (is_wp_error($response)) {
      $code = $response->get_error_code();
      $message = $response->get_error_message();
    } else {
      $code = (int)wp_remote_retrieve_response_code($response);
      $message = wp_remote_retrieve_response_message($response);
    }

    return new WP_REST_Response(['response' => ['code' => $code, 'message' => $message]], 200);
  }

  /**
   * Splits server version into parts (semver format is required)
   * @param  stirng $version Version number according to semantic versioning
   * @return object|FALSE    Either object with major, minor, patch or FALSE
   */
  private static function split_version($version) {
    $parts = explode('.', $version);

    if (count($parts) !== 3) {
      return FALSE;
    }

    return (object) array(
      'major' => (int) $parts[0],
      'minor' => (int) $parts[1],
      'patch' => (int) $parts[2]
    );
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

    $version = self::split_version($current_version);

    self::update_database();

    // Schedule cron job if version below 0.3.1
    if ($version->major <= 0 && $version->minor <= 3 && $version->patch < 1) {
      if (!wp_next_scheduled('ginf_process_xapi_statements')) {
        wp_schedule_event(time(), 'hourly', 'ginf_process_xapi_statements');
      }
    }

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
      PRIMARY KEY  (id),
      KEY created_at (created_at),
      KEY updated_at (created_at),
      KEY statements_count (statements_count)
    ) {$charset};");
    dbDelta("CREATE TABLE {$wpdb->base_prefix}ginf_xapi_http_log (
      code SMALLINT DEFAULT NULL,
      message TEXT DEFAULT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT 0,
      statements LONGTEXT DEFAULT NULL,
      statements_count SMALLINT UNSIGNED NOT NULL,
      KEY code (code),
      KEY created_at (created_at),
      KEY statements_count (statements_count)
    ) {$charset};");
  }

  /**
   * Determines if current blog can run the cron jobs.
   * Only initial site/blog with identifier of 1 is allowed to do that.
   * @return bool TRUE if main site, FALSE otherwise
   */
  private function can_run_cron_jobs() {
    return get_current_blog_id() === 1;
  }

  /**
   * Fixes numerical and boolean values that were converted to a string due to a bug.
   * The fixes are currently being made for the "result" data
   * @param  array $statement Statement
   * @return array            Statement with fixed numerical and boolean values
   */
  private static function fix_statement($statement) {
    // TODO Might make sure that only old statement get fixed
    if (isset($statement['result'])) {
      if (isset($statement['result']['completion']) && !is_bool($statement['result']['completion'])) {
        $statement['result']['completion'] = ($statement['result']['completion'] == 'true') ? TRUE : FALSE;
      }
      if (isset($statement['result']['success']) && !is_bool($statement['result']['success'])) {
        $statement['result']['success'] = ($statement['result']['success'] == 'true') ? TRUE : FALSE;
      }
      if (isset($statement['result']['score'])) {
        if (isset($statement['result']['score']['min'])) {
          $statement['result']['score']['min'] = (float) $statement['result']['score']['min'];
        }
        if (isset($statement['result']['score']['max'])) {
          $statement['result']['score']['max'] = (float) $statement['result']['score']['max'];
        }
        if (isset($statement['result']['score']['raw'])) {
          $statement['result']['score']['raw'] = (float) $statement['result']['score']['raw'];
        }
        if (isset($statement['result']['score']['scaled'])) {
          $statement['result']['score']['scaled'] = (float) $statement['result']['score']['scaled'];
        }
      }
    }

    return $statement;
  }

  /**
   * Processes statements and creates batches
   */
  public function process_xapi_statements() {
    if (!$this->can_run_cron_jobs()) return;

    global $wpdb;

    $size = (int) get_site_option('ginf_lrs_batch_size');
    if (!(int)$size > 1) {
      $size = 100;
    }

    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}ginf_xapi_statements");
    if ($count > 0) {
      $batches = ceil($count / $size);

      foreach(range(1, $batches) as $batch) {
        $statements = $wpdb->get_results("SELECT id, statement FROM {$wpdb->base_prefix}ginf_xapi_statements ORDER BY created_at ASC LIMIT $size");
        $ids = [];
        $data = [];
        foreach ($statements as $statement) {
          $ids[] = $statement->id;
          $data[] = $this->fix_statement(json_decode($statement->statement, TRUE)); // TODO See if it would make sense to write a migration instead
        }
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

  /**
   * Delete batch from the database.
   * @param  int    $id Batch unique identifier
   * @return int|false  Number of rows affected/selected or false on error
   */
  private function delete_batch(int $id) {
    global $wpdb;

    return $wpdb->query("DELETE FROM {$wpdb->base_prefix}ginf_xapi_batches WHERE id=$id");
  }

  /**
   * Add an entry to LRS HTTP log.
   * @param int    $code       Response code
   * @param string $message    Response message
   * @param int    $count      Number of statements
   * @param string $statements JSON-encoded array of statements
   */
  private function add_to_http_log(int $code, string $message, int $count, string $statements = '') {
    global $wpdb;

    return $wpdb->insert("{$wpdb->base_prefix}ginf_xapi_http_log", [
      'code' => $code,
      'message' => $message,
      'created_at' => current_time('mysql'),
      'statements' => $statements,
      'statements_count' => $count,
    ], [
      '%d', '%s', '%s', '%s', '%d'
    ]);
  }

  /**
   * Sends statements to LRS xAPI endpoint
   * @param  string $endpoint   LRS xAPI ednpoint base URL
   * @param  string $key        Auth key
   * @param  string $secret     Auth secret
   * @param  string $statements JSON-encoded array of statements
   * @return WP_Error|array     The response or WP_Error on failure
   */
  private function send_statements_to_lrs(string $endpoint, string $key, string $secret, string $statements) {
    $auth = base64_encode("$key:$secret");

    return wp_remote_request($endpoint . '/statements', [
      'method' => 'POST',
      'timeout' => 45,
      'headers' => [
        'Content-Type' => 'application/json',
        'X-Experience-API-Version' => '1.0.1',
        'Authorization' => "Basic $auth",
        'Content-Length' => strlen($statements),
      ],
      'body' => $statements,
    ]);
  }

  /**
   * Sends next batch to the LRS if run time limit has not been exceeded and next batch exists.
   * Will recursively call itself until conditions prevent that from happening
   * @param  string $endpoint URL to the LRS xAPI general endpoint
   * @param  string $key      Auth key
   * @param  string $secret   Auth secret
   * @param  int    $start    Timestamp when process began
   * @param  int    $allowed  Allowed time to run in seconds
   * @return void
   */
  private function send_batch_to_lrs(string $endpoint, string $key, string $secret, int $start, int $allowed) {
    if (time() - $start >= $allowed) return;

    global $wpdb;

    $batch = $wpdb->get_row("SELECT id, statements, statements_count FROM {$wpdb->base_prefix}ginf_xapi_batches ORDER BY created_at ASC");

    if (NULL === $batch) return;

    $response = $this->send_statements_to_lrs($endpoint, $key, $secret, $batch->statements);

    if (is_wp_error($response)) {
      $this->add_to_http_log(0, $response->get_error_message(), $batch->statements_count);
    } else {
      $code = (int)wp_remote_retrieve_response_code($response);
      $message = wp_remote_retrieve_response_message($response);

      if ($code === 200) {
        $this->delete_batch($batch->id);
        $this->add_to_http_log($code, $message, $batch->statements_count);
      } else if ($code === 400) {
        $this->delete_batch($batch->id);
        $this->add_to_http_log($code, $message, $batch->statements_count, $batch->statements);
      } else if ($code === 401) {
        $this->add_to_http_log($code, $messge, $batch->statements_count);
        return;
      } else {
        // XXX Need to determine what is covered by this case
        $this->add_to_http_log($code, $messge, $batch->statements_count);
      }
    }

    $this->send_batch_to_lrs($endpoint, $key, $secret, $start, $allowed);
  }

  /**
   * Processes batches and sends the data to LRS
   */
  public function process_xapi_batches() {
    if (!$this->can_run_cron_jobs()) return;

    global $wpdb;

    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}ginf_xapi_batches");

    if ($count > 0) {
      $endpoint = get_site_option('ginf_lrs_xapi_endpoint');
      $key = get_site_option('ginf_lrs_key');
      $secret = get_site_option('ginf_lrs_secret');
      if (!($endpoint && $key && $secret)) {
        return;
      }

      $start = time();
      $allowed = 50 * 60;

      $this->send_batch_to_lrs($endpoint, $key, $secret, $start, $allowed);
    }
  }
}
