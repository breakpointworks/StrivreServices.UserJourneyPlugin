<?php
/**
 * Plugin Name: Strivre Solutions Wizard
 * Description: Elementor widget for Strivre Services' Solutions page — a multi-step configurator (package tier, website template, domain search, solutions/points selection, checkout) that emails staff and the customer on submission.
 * Version: 0.1.0
 * Author: Strivre Services
 * Text Domain: strivre-solutions-wizard
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSW_VERSION', '0.1.3' );
define( 'SSW_PLUGIN_FILE', __FILE__ );
define( 'SSW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SSW_PLUGIN_DIR . 'includes/class-cpt.php';
require_once SSW_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once SSW_PLUGIN_DIR . 'includes/class-domain-lookup-client.php';
require_once SSW_PLUGIN_DIR . 'includes/class-mailer.php';
require_once SSW_PLUGIN_DIR . 'includes/class-rest-domain-search.php';
require_once SSW_PLUGIN_DIR . 'includes/class-rest-submit.php';

/**
 * Main plugin bootstrap. Everything else is wired up from here so activation,
 * Elementor detection, and asset loading all happen in one obvious place.
 */
final class Strivre_Solutions_Wizard {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( SSW_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( SSW_PLUGIN_FILE, array( __CLASS__, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public static function activate() {
		SSW_CPT::register_post_type();
		SSW_CPT::register_post_status();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function init() {
		SSW_CPT::instance();
		SSW_Admin_Settings::instance();
		SSW_REST_Domain_Search::instance();
		SSW_REST_Submit::instance();

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
			add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		} else {
			add_action( 'admin_notices', array( $this, 'elementor_missing_notice' ) );
		}
	}

	/**
	 * Registers (does not enqueue) the widget's JS/CSS so Elementor can pull
	 * them in on demand via get_script_depends()/get_style_depends() — only
	 * pages that actually contain the widget end up loading these.
	 */
	public function register_assets() {
		// Cache-busted off each file's own mtime rather than SSW_VERSION, so
		// an edit to wizard.css/js always invalidates cached copies without
		// relying on remembering to bump a version constant by hand.
		$css_path = SSW_PLUGIN_DIR . 'assets/css/wizard.css';
		$js_path  = SSW_PLUGIN_DIR . 'assets/js/wizard.js';
		wp_register_style( 'ssw-wizard', SSW_PLUGIN_URL . 'assets/css/wizard.css', array(), file_exists( $css_path ) ? filemtime( $css_path ) : SSW_VERSION );
		wp_register_script( 'ssw-wizard', SSW_PLUGIN_URL . 'assets/js/wizard.js', array( 'elementor-frontend' ), file_exists( $js_path ) ? filemtime( $js_path ) : SSW_VERSION, true );
	}

	public function register_widget( $widgets_manager ) {
		require_once SSW_PLUGIN_DIR . 'elementor/class-widget-solutions-wizard.php';
		$widgets_manager->register( new SSW_Widget_Solutions_Wizard() );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'strivre',
			array(
				'title' => __( 'Strivre', 'strivre-solutions-wizard' ),
				'icon'  => 'eicon-form-horizontal',
			)
		);
	}

	public function elementor_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'Strivre Solutions Wizard requires Elementor to be installed and active. The plugin is otherwise idle.', 'strivre-solutions-wizard' );
		echo '</p></div>';
	}
}

Strivre_Solutions_Wizard::instance();
