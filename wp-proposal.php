<?php
/**
 * Plugin name: Proposals
 * Plugin URI: https://site.com
 * Description: A minimal HTML form builder for WordPress; made for developers
 * Version: 1.5.0.3
 * Author: amal oumalek
 * Author URI: https://site.com
 * Text Domain: wp-proposal
 *
 * This plugin is a simple html form maker for WordPress.
 */




if ( ! class_exists( 'WP_Proposal' ) ) :

define( 'WPLF_VERSION', '1.5.0.1' );

class WP_Proposal {
  public static $instance;
  public $plugins;

  public static function init() {
    if ( is_null( self::$instance ) ) {
      self::$instance = new WP_Proposal();
    }
    return self::$instance;
  }

  private function __construct() {
    require_once 'classes/class-cpt-wplf-form.php';
    require_once 'classes/class-cpt-wplf-proposition.php';
    require_once 'classes/class-wplf-dynamic-values.php';
    require_once 'classes/class-wplf-plugins.php';
    require_once 'inc/wplf-ajax.php';

    // default functionality
    require_once 'inc/wplf-form-actions.php';
    require_once 'inc/wplf-form-validation.php';

    // init our plugin classes
    CPT_WPF_Proposal::init();
    CPT_WPLF_proposition::init();
    WPLF_Dynamic_Values::init();

    $this->plugins = WPLF_Plugins::init();

    add_action( 'after_setup_theme', array( $this, 'init_polylang_support' ) );

    add_action( 'plugins_loaded', array( $this, 'load_our_textdomain' ) );

    add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

    // flush rewrites on activation since we have slugs for our cpts
    register_activation_hook( __FILE__, array( 'WP_Proposal', 'flush_rewrites' ) );
    register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
  }

  /**
   * Plugin activation hook
   */
  public static function flush_rewrites() {
    CPT_WPF_Proposal::register_cpt();
    CPT_WPLF_proposition::register_cpt();
    flush_rewrite_rules();
  }

  /**
   * Load our plugin textdomain
   */
  public static function load_our_textdomain() {
    $loaded = load_plugin_textdomain( 'wp-proposal', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    if ( ! $loaded ) {
      $loaded = load_muplugin_textdomain( 'wp-proposal', dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
    }
  }

  public function register_rest_routes() {
    register_rest_route( 'wplf/v1', 'submit', [
      'methods' => 'POST',
      'callback' => 'wplf_ajax_submit_handler', // admin-ajax handler, works but...
      // The REST API handbook discourages from using $_POST, and instead use $request->get_params()
    ]);
  }

  /**
   * Enable Polylang support
   */
  public function init_polylang_support() {
    if ( apply_filters( 'wplf_load_polylang', true ) && class_exists( 'Polylang' ) ) {
      require_once 'classes/class-wplf-polylang.php';
      WPLF_Polylang::init();
    }
  }

  /**
   * Public version of WPF_Proposal
   */
  public function WPF_Proposal( $id, $content = '', $xclass = '' ) {
    return CPT_WPF_Proposal::WPF_Proposal( $id, $content, $xclass );
  }
}

endif;

/**
 * Expose a global function for less awkward usage
 */
function wplf() {
  // init the plugin
  return WP_Proposal::init();
}

wplf();
