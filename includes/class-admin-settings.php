<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global, site-wide settings: notification email(s), Domainr API credentials,
 * and the two email templates. Everything else (catalog, pricing/points,
 * templates) is intentionally per-widget-instance, not here — see the
 * Elementor widget's Content controls.
 */
class SSW_Admin_Settings {

	const OPTION_KEY = 'ssw_settings';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function defaults() {
		return array(
			'notification_emails'   => get_option( 'admin_email' ),
			'from_name'              => get_bloginfo( 'name' ),
			'from_email'             => get_option( 'admin_email' ),
			'domainr_api_key'        => '',
			'domainr_api_host'       => 'domainr.p.rapidapi.com',
			'spam_guard_enabled'     => 1,
			'admin_email_subject'    => 'New Solutions Wizard submission — {company}',
			'admin_email_body'       => "A new submission came in from {name} ({email}, {phone}) at {company}.\n\nPackage tier: {tier} ({points_included} points included)\nWebsite template: {template}\nDomain: {domain}\n\nSolutions selected:\n{solutions}\n\nPoints used: {points_used} / {points_included}\nPoints shortfall: {points_shortfall}\n\nSubmitted from: {page_url}",
			'customer_email_subject' => 'Thanks, {name} — we\'ve got your request',
			'customer_email_body'    => "Hi {name},\n\nThanks for putting together your Strivre Services request! Our team will review it and send you a business proposal within 24 hours.\n\nHere's a summary of what you selected:\n\nPackage tier: {tier}\nDomain: {domain}\nSolutions: {solutions}\n\nTalk soon,\nStrivre Services",
		);
	}

	public static function get( $key ) {
		$settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
		return $settings[ $key ] ?? '';
	}

	public function add_settings_page() {
		add_options_page(
			__( 'Strive Solutions Wizard', 'strive-solutions-wizard' ),
			__( 'Strive Solutions', 'strive-solutions-wizard' ),
			'manage_options',
			'ssw-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'ssw_settings_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ) {
		$clean = array();
		$clean['notification_emails']   = sanitize_text_field( $input['notification_emails'] ?? '' );
		$clean['from_name']             = sanitize_text_field( $input['from_name'] ?? '' );
		$clean['from_email']            = sanitize_email( $input['from_email'] ?? '' );
		$clean['domainr_api_key']       = sanitize_text_field( $input['domainr_api_key'] ?? '' );
		$clean['domainr_api_host']      = sanitize_text_field( $input['domainr_api_host'] ?? '' );
		$clean['spam_guard_enabled']    = empty( $input['spam_guard_enabled'] ) ? 0 : 1;
		$clean['admin_email_subject']   = sanitize_text_field( $input['admin_email_subject'] ?? '' );
		$clean['admin_email_body']      = sanitize_textarea_field( $input['admin_email_body'] ?? '' );
		$clean['customer_email_subject'] = sanitize_text_field( $input['customer_email_subject'] ?? '' );
		$clean['customer_email_body']   = sanitize_textarea_field( $input['customer_email_body'] ?? '' );
		return $clean;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Strive Solutions Wizard — Settings', 'strive-solutions-wizard' ); ?></h1>
			<p><?php esc_html_e( 'Site-wide settings only. Package tiers, the solutions catalog, points, and template images are configured per-page inside the Elementor widget itself.', 'strive-solutions-wizard' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'ssw_settings_group' ); ?>
				<h2 class="title"><?php esc_html_e( 'Notifications', 'strive-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_notification_emails"><?php esc_html_e( 'Notify these emails', 'strive-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_notification_emails" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_emails]" value="<?php echo esc_attr( $s['notification_emails'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Comma-separated. Staff who should be alerted on every submission.', 'strive-solutions-wizard' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="ssw_from_name"><?php esc_html_e( 'From name', 'strive-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_from_name" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="ssw_from_email"><?php esc_html_e( 'From email', 'strive-solutions-wizard' ); ?></label></th>
						<td><input type="email" id="ssw_from_email" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" /></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Domain search (Domainr / RapidAPI)', 'strive-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_domainr_api_key"><?php esc_html_e( 'RapidAPI key', 'strive-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_domainr_api_key" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[domainr_api_key]" value="<?php echo esc_attr( $s['domainr_api_key'] ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Without a key, the domain search step shows a graceful "temporarily unavailable" message rather than failing.', 'strive-solutions-wizard' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="ssw_domainr_api_host"><?php esc_html_e( 'RapidAPI host', 'strive-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_domainr_api_host" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[domainr_api_host]" value="<?php echo esc_attr( $s['domainr_api_host'] ); ?>" /></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Spam guard', 'strive-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Honeypot + minimum time-on-form', 'strive-solutions-wizard' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[spam_guard_enabled]" value="1" <?php checked( $s['spam_guard_enabled'], 1 ); ?> /> <?php esc_html_e( 'Enabled', 'strive-solutions-wizard' ); ?></label></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Admin notification email', 'strive-solutions-wizard' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Merge tags: {name} {email} {phone} {company} {tier} {points_included} {template} {domain} {solutions} {points_used} {points_shortfall} {page_url}', 'strive-solutions-wizard' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_admin_email_subject"><?php esc_html_e( 'Subject', 'strive-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_admin_email_subject" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email_subject]" value="<?php echo esc_attr( $s['admin_email_subject'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="ssw_admin_email_body"><?php esc_html_e( 'Body', 'strive-solutions-wizard' ); ?></label></th>
						<td><textarea id="ssw_admin_email_body" class="large-text" rows="8" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email_body]"><?php echo esc_textarea( $s['admin_email_body'] ); ?></textarea></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Customer confirmation email', 'strive-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_customer_email_subject"><?php esc_html_e( 'Subject', 'strive-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_customer_email_subject" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_email_subject]" value="<?php echo esc_attr( $s['customer_email_subject'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="ssw_customer_email_body"><?php esc_html_e( 'Body', 'strive-solutions-wizard' ); ?></label></th>
						<td><textarea id="ssw_customer_email_body" class="large-text" rows="8" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_email_body]"><?php echo esc_textarea( $s['customer_email_body'] ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
