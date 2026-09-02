<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global, site-wide settings: notification email(s), domain lookup API credentials,
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
			'domain_provider'        => 'layered',
			'domainr_api_key'        => '',
			'domainr_api_host'       => 'domains-api.p.rapidapi.com',
			'hostinger_api_token'    => '',
			'email_delivery_mode'      => 'wordpress',
			'signup_api_base_url'      => '',
			'signup_api_login_email'   => '',
			'signup_api_login_password' => '',
			'signup_api_static_token'  => '',
			'spam_guard_enabled'     => 1,
			'admin_email_subject'    => 'New Solutions Wizard submission — {company}',
			'admin_email_body'       => "A new submission came in from {name} ({email}, {phone}) at {company}.\n\nAddress:\n{address}\n\nPackage tier: {tier} ({points_included} points included)\nWebsite template: {template}\nDomain: {domain}\n\nSolutions selected:\n{solutions}\n\nPoints used: {points_used} / {points_included}\nPoints shortfall: {points_shortfall}\n\nSubmitted from: {page_url}",
			'customer_email_subject' => 'Thanks, {name} — we\'ve got your request',
			'customer_email_body'    => "Hi {name},\n\nThanks for putting together your Strivre Services request! Our team will review it and send you a business proposal within 24 hours.\n\nHere's a summary of what you selected:\n\nPackage tier: {tier}\nDomain: {domain}\nSolutions: {solutions}\n\nTalk soon,\nStrivre Services",
		);
	}

	public static function get( $key ) {
		$settings = wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
		return $settings[ $key ] ?? '';
	}

	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . SSW_CPT::POST_TYPE,
			__( 'Strivre Solutions Wizard', 'strivre-solutions-wizard' ),
			__( 'Settings', 'strivre-solutions-wizard' ),
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
		$clean['domain_provider']       = 'hostinger' === ( $input['domain_provider'] ?? '' ) ? 'hostinger' : 'layered';
		$clean['domainr_api_key']       = sanitize_text_field( $input['domainr_api_key'] ?? '' );
		$clean['domainr_api_host']      = sanitize_text_field( $input['domainr_api_host'] ?? '' );
		$clean['hostinger_api_token']   = sanitize_text_field( $input['hostinger_api_token'] ?? '' );
		$clean['email_delivery_mode']   = in_array( $input['email_delivery_mode'] ?? '', array( 'api', 'both' ), true ) ? $input['email_delivery_mode'] : 'wordpress';
		$clean['signup_api_base_url']   = esc_url_raw( trim( $input['signup_api_base_url'] ?? '' ) );
		$clean['signup_api_login_email'] = sanitize_text_field( $input['signup_api_login_email'] ?? '' );
		// Not sanitize_text_field() — that would strip characters a real
		// password might legitimately contain (e.g. leading/trailing
		// significant whitespace is unlikely, but quotes/backslashes aren't).
		$clean['signup_api_login_password'] = (string) ( $input['signup_api_login_password'] ?? '' );
		$clean['signup_api_static_token']   = (string) ( $input['signup_api_static_token'] ?? '' );
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
			<h1><?php esc_html_e( 'Strivre Solutions Wizard — Settings', 'strivre-solutions-wizard' ); ?></h1>
			<p><?php esc_html_e( 'Site-wide settings only. Package tiers, the solutions catalog, points, and template images are configured per-page inside the Elementor widget itself.', 'strivre-solutions-wizard' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'ssw_settings_group' ); ?>
				<h2 class="title"><?php esc_html_e( 'Notifications', 'strivre-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_notification_emails"><?php esc_html_e( 'Notify these emails', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_notification_emails" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_emails]" value="<?php echo esc_attr( $s['notification_emails'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Comma-separated. Staff who should be alerted on every submission.', 'strivre-solutions-wizard' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="ssw_from_name"><?php esc_html_e( 'From name', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_from_name" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="ssw_from_email"><?php esc_html_e( 'From email', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="email" id="ssw_from_email" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" /></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Domain search', 'strivre-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_domain_provider"><?php esc_html_e( 'Provider', 'strivre-solutions-wizard' ); ?></label></th>
						<td>
							<select id="ssw_domain_provider" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[domain_provider]">
								<option value="layered" <?php selected( $s['domain_provider'], 'layered' ); ?>><?php esc_html_e( 'Domains API by Layered (RapidAPI)', 'strivre-solutions-wizard' ); ?></option>
								<option value="hostinger" <?php selected( $s['domain_provider'], 'hostinger' ); ?>><?php esc_html_e( 'Hostinger API', 'strivre-solutions-wizard' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Only the selected provider is used for live lookups — the other one\'s credentials below are just kept on hand so you can switch back without re-entering them.', 'strivre-solutions-wizard' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Domains API by Layered (RapidAPI)', 'strivre-solutions-wizard' ); ?></h3>
				<table class="form-table ssw-provider-fields" data-provider="layered" role="presentation">
					<tr>
						<th><label for="ssw_domainr_api_key"><?php esc_html_e( 'RapidAPI key', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_domainr_api_key" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[domainr_api_key]" value="<?php echo esc_attr( $s['domainr_api_key'] ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Without a key, the domain search step shows a graceful "temporarily unavailable" message rather than failing.', 'strivre-solutions-wizard' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="ssw_domainr_api_host"><?php esc_html_e( 'RapidAPI host', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_domainr_api_host" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[domainr_api_host]" value="<?php echo esc_attr( $s['domainr_api_host'] ); ?>" /></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Hostinger API', 'strivre-solutions-wizard' ); ?></h3>
				<table class="form-table ssw-provider-fields" data-provider="hostinger" role="presentation">
					<tr>
						<th><label for="ssw_hostinger_api_token"><?php esc_html_e( 'API token', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_hostinger_api_token" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hostinger_api_token]" value="<?php echo esc_attr( $s['hostinger_api_token'] ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Bearer token from developers.hostinger.com. Without a token, the domain search step shows a graceful "temporarily unavailable" message rather than failing.', 'strivre-solutions-wizard' ); ?></p></td>
					</tr>
				</table>
				<script>
				( function () {
					var select = document.getElementById( 'ssw_domain_provider' );
					var groups = document.querySelectorAll( '.ssw-provider-fields' );
					function sync() {
						groups.forEach( function ( g ) {
							g.style.display = g.getAttribute( 'data-provider' ) === select.value ? '' : 'none';
						} );
					}
					select.addEventListener( 'change', sync );
					sync();
				} )();
				</script>

				<h2 class="title"><?php esc_html_e( 'Sign-up email API', 'strivre-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_email_delivery_mode"><?php esc_html_e( 'Send notifications via', 'strivre-solutions-wizard' ); ?></label></th>
						<td>
							<select id="ssw_email_delivery_mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_delivery_mode]">
								<option value="wordpress" <?php selected( $s['email_delivery_mode'], 'wordpress' ); ?>><?php esc_html_e( 'WordPress email only (current behavior)', 'strivre-solutions-wizard' ); ?></option>
								<option value="api" <?php selected( $s['email_delivery_mode'], 'api' ); ?>><?php esc_html_e( "Strivre's Sign-Up Email API only", 'strivre-solutions-wizard' ); ?></option>
								<option value="both" <?php selected( $s['email_delivery_mode'], 'both' ); ?>><?php esc_html_e( 'Both', 'strivre-solutions-wizard' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'The API call never blocks a submission or the WordPress email — if it fails, everything else still goes through as normal.', 'strivre-solutions-wizard' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Staff notification email(s)', 'strivre-solutions-wizard' ); ?></th>
						<td><p class="description"><?php esc_html_e( 'Uses the same "Notify these emails" address(es) set under Notifications above, whichever channel(s) are active — there\'s nothing separate to configure here.', 'strivre-solutions-wizard' ); ?></p></td>
					</tr>
				</table>
				<table class="form-table ssw-signup-api-fields" role="presentation">
					<tr>
						<th><label for="ssw_signup_api_base_url"><?php esc_html_e( 'API base URL', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="url" id="ssw_signup_api_base_url" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[signup_api_base_url]" value="<?php echo esc_attr( $s['signup_api_base_url'] ); ?>" placeholder="https://api.example.com" />
						<p class="description"><?php esc_html_e( 'This is Strivre\'s test/dev environment right now — swap it for the production URL once the client\'s own instance is ready, with no other changes needed.', 'strivre-solutions-wizard' ); ?></p></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Option A — Email/password login', 'strivre-solutions-wizard' ); ?></h3>
				<table class="form-table ssw-signup-api-fields" role="presentation">
					<tr>
						<th><label for="ssw_signup_api_login_email"><?php esc_html_e( 'Login email', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_signup_api_login_email" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[signup_api_login_email]" value="<?php echo esc_attr( $s['signup_api_login_email'] ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th><label for="ssw_signup_api_login_password"><?php esc_html_e( 'Login password', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="password" id="ssw_signup_api_login_password" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[signup_api_login_password]" value="<?php echo esc_attr( $s['signup_api_login_password'] ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'The wizard logs in with this email/password to get a token, then caches it until shortly before it expires (per the login response\'s own "expires" field) before logging in again.', 'strivre-solutions-wizard' ); ?></p></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Option B — Static API token', 'strivre-solutions-wizard' ); ?></h3>
				<table class="form-table ssw-signup-api-fields" role="presentation">
					<tr>
						<th><label for="ssw_signup_api_static_token"><?php esc_html_e( 'API token', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="password" id="ssw_signup_api_static_token" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[signup_api_static_token]" value="<?php echo esc_attr( $s['signup_api_static_token'] ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'If the client\'s production setup instead hands you a fixed API key/token rather than a login, paste it here — when this is filled in, it\'s used directly as the bearer token and the login fields above are skipped entirely.', 'strivre-solutions-wizard' ); ?></p></td>
					</tr>
				</table>
				<script>
				( function () {
					var select = document.getElementById( 'ssw_email_delivery_mode' );
					var groups = document.querySelectorAll( '.ssw-signup-api-fields' );
					function sync() {
						var show = select.value === 'api' || select.value === 'both';
						groups.forEach( function ( g ) { g.style.display = show ? '' : 'none'; } );
					}
					select.addEventListener( 'change', sync );
					sync();
				} )();
				</script>

				<h2 class="title"><?php esc_html_e( 'Spam guard', 'strivre-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Honeypot (Spam Guard) + minimum time-on-form', 'strivre-solutions-wizard' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[spam_guard_enabled]" value="1" <?php checked( $s['spam_guard_enabled'], 1 ); ?> /> <?php esc_html_e( 'Enabled', 'strivre-solutions-wizard' ); ?></label></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Admin notification email', 'strivre-solutions-wizard' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Merge tags: {name} {email} {phone} {company} {address} {tier} {points_included} {template} {domain} {solutions} {points_used} {points_shortfall} {page_url} — plus, from "Build Your Business" submissions: {domain_wanted} {marketing_tier} {licenses} {measure_tier} {measure_addons} {bespoke_selected} {bespoke_interest} {enterprise_selected} {monthly_total}', 'strivre-solutions-wizard' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_admin_email_subject"><?php esc_html_e( 'Subject', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_admin_email_subject" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email_subject]" value="<?php echo esc_attr( $s['admin_email_subject'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="ssw_admin_email_body"><?php esc_html_e( 'Body', 'strivre-solutions-wizard' ); ?></label></th>
						<td><textarea id="ssw_admin_email_body" class="large-text" rows="8" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email_body]"><?php echo esc_textarea( $s['admin_email_body'] ); ?></textarea></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Customer confirmation email', 'strivre-solutions-wizard' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ssw_customer_email_subject"><?php esc_html_e( 'Subject', 'strivre-solutions-wizard' ); ?></label></th>
						<td><input type="text" id="ssw_customer_email_subject" class="large-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_email_subject]" value="<?php echo esc_attr( $s['customer_email_subject'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="ssw_customer_email_body"><?php esc_html_e( 'Body', 'strivre-solutions-wizard' ); ?></label></th>
						<td><textarea id="ssw_customer_email_body" class="large-text" rows="8" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[customer_email_body]"><?php echo esc_textarea( $s['customer_email_body'] ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
