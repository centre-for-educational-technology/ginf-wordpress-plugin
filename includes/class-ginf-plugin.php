<?php

/**
 * GINF main plugin class
 */
class GINF_Plugin {
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
  }

  /**
   * Run deacivation procedures
   */
  public static function deactivate() {
  }

  /**
   * Enqueue any scripts and/or styles
   */
  public function enqueue_styles_and_scripts() {
    wp_enqueue_script('ginf/pressbooks', plugin_dir_url(__FILE__) . '../public/js/pressbooks.js', ['jquery',]);
  }
}
