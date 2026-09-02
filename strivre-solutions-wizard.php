<?php
/**
 * Plugin Name: Strivre Solutions Wizard
 * Description: Elementor widget for Strivre Services — a multi-step configurator (package tier, website template, domain search, solutions/points selection, checkout) for the Solutions page, plus a single-page "Build Your Business" catalog builder mode with a global pricing catalog. Emails staff and the customer on submission.
 * Version: 0.4.0
 * Author: Paul John Labesores | Strivre Services
 * Text Domain: strivre-solutions-wizard
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSW_VERSION', '0.4.0' );
// Highest Elementor version this plugin has actually been tested against.
// Only the major.minor part is compared — an Elementor patch release
// (4.2.3 -> 4.2.4) is assumed compatible, a minor/major bump (4.2.x -> 4.3.0)
// is not, and shows the compatibility notice on the Plugins screen.
define( 'SSW_TESTED_ELEMENTOR_VERSION', '4.2.3' );
define( 'SSW_PLUGIN_FILE', __FILE__ );
define( 'SSW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SSW_PLUGIN_DIR . 'includes/class-cpt.php';
require_once SSW_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once SSW_PLUGIN_DIR . 'includes/class-catalog-settings.php';
require_once SSW_PLUGIN_DIR . 'includes/class-domain-lookup-client.php';
require_once SSW_PLUGIN_DIR . 'includes/class-domain-provider-hostinger.php';
require_once SSW_PLUGIN_DIR . 'includes/class-signup-api-client.php';
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
		SSW_Catalog_Settings::instance();
		SSW_REST_Domain_Search::instance();
		SSW_REST_Submit::instance();

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
			add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
			add_action( 'after_plugin_row_' . plugin_basename( SSW_PLUGIN_FILE ), array( $this, 'elementor_compat_notice_row' ) );
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

	/**
	 * Only the major.minor part is compared — a patch release is assumed
	 * compatible, a minor/major bump is not. See SSW_TESTED_ELEMENTOR_VERSION.
	 */
	private function elementor_version_compatible( $version ) {
		$tested  = explode( '.', SSW_TESTED_ELEMENTOR_VERSION );
		$current = explode( '.', $version );
		return ( $tested[0] ?? '' ) === ( $current[0] ?? '' ) && ( $tested[1] ?? '' ) === ( $current[1] ?? '' );
	}

	/**
	 * Renders a row under this plugin's entry on the Plugins screen, styled
	 * like WordPress's own inline "update available" notice, when the active
	 * Elementor version hasn't been tested against this plugin. Hooked to
	 * after_plugin_row_{this plugin}, which only fires there.
	 */
	public function elementor_compat_notice_row() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) || $this->elementor_version_compatible( ELEMENTOR_VERSION ) ) {
			return;
		}

		$wp_list_table = function_exists( '_get_list_table' ) ? _get_list_table( 'WP_Plugins_List_Table' ) : null;
		$colspan       = $wp_list_table ? $wp_list_table->get_column_count() : 3;
		$plugin_file   = plugin_basename( SSW_PLUGIN_FILE );
		$active_class  = is_plugin_active( $plugin_file ) ? ' active' : '';

		$message = sprintf(
			/* translators: 1: tested Elementor version, 2: currently active Elementor version, 3: contact link */
			esc_html__( 'Strivre Solutions Wizard has only been tested with Elementor up to version %1$s. You\'re running Elementor %2$s — compatibility is unknown. Please contact %3$s for an update.', 'strivre-solutions-wizard' ),
			esc_html( SSW_TESTED_ELEMENTOR_VERSION ),
			esc_html( ELEMENTOR_VERSION ),
			'<a href="mailto:labesores.pauljohn@gmail.com">Paul John Labesores</a>'
		);

		printf(
			'<tr class="plugin-update-tr%1$s" id="strivre-solutions-wizard-elementor-compat" data-slug="strivre-solutions-wizard" data-plugin="%2$s">
				<td colspan="%3$d" class="plugin-update colspanchange">
					<div class="update-message notice inline notice-warning notice-alt"><p>%4$s</p></div>
				</td>
			</tr>',
			esc_attr( $active_class ),
			esc_attr( $plugin_file ),
			esc_attr( $colspan ),
			$message
		);
	}
}

Strivre_Solutions_Wizard::instance();
