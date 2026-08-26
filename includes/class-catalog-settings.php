<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global pricing catalog for the "Build Your Business" single-page builder
 * mode — Website Package, Website Modules, Marketing, Licenses, Measure
 * Analytics, Bespoke Development, and the Enterprise bundle. This is real,
 * ~40-line-item business pricing (not per-page marketing copy), so unlike
 * the original Solutions Wizard's tiers/solutions — which stay in the
 * Elementor widget's own repeaters, per-page, on purpose — this catalog has
 * one editable source of truth here instead of being re-entered in
 * Elementor on every page. See class-widget-solutions-wizard.php's
 * `builder_mode` control for how a widget instance opts into reading it.
 */
class SSW_Catalog_Settings {

	const OPTION_KEY = 'ssw_bizbuilder_catalog';

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

	/**
	 * Seeded directly from the client's pricing PDF. Two intentional
	 * corrections vs. the PDF as-given: the Measure "Marketing" tier's
	 * description no longer references "SLIM" (a copy error in the client's
	 * own document — Marketing is cheaper and has fewer licenses than Slim).
	 */
	public static function defaults() {
		$icons = SSW_PLUGIN_URL . 'assets/img/icons/catalog/';
		return array(
			'tiers' => array(
				array( 'title' => 'Bronze', 'price' => 150, 'points' => 3, 'pages_note' => 'Up to 3 pages, hosting, SSL, maintenance, security updates, backups, Measure Marketing report', 'badge_color' => '#8B5E34' ),
				array( 'title' => 'Silver', 'price' => 220, 'points' => 6, 'pages_note' => 'Up to 5 pages, hosting, SSL, maintenance, priority support, security updates, backups, Measure Marketing report', 'badge_color' => '#B4B8BE' ),
				array( 'title' => 'Gold', 'price' => 350, 'points' => 9, 'pages_note' => 'Up to 10 pages, hosting, SSL, maintenance, premium support, security updates, backups, Measure Marketing report', 'badge_color' => '#D6A419' ),
			),
			'modules' => array(
				array( 'title' => 'Online Booking & Scheduling', 'price' => 20, 'points' => 3, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-booking.svg' ),
				array( 'title' => 'Contact & Lead Generation Forms', 'price' => 20, 'points' => 3, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-forms.svg' ),
				array( 'title' => 'Multi-language Website', 'price' => 20, 'points' => 3, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-multilang.svg' ),
				array( 'title' => 'Additional Website Pages', 'price' => 20, 'points' => 3, 'unit_note' => 'Per website page / month', 'icon' => $icons . 'modules-pages.svg' ),
				array( 'title' => 'Website Diagnostic', 'price' => 20, 'points' => 3, 'unit_note' => 'Per report generated — free for first report', 'icon' => $icons . 'modules-website-diagnostic.svg' ),
				array( 'title' => 'Marketing Diagnostic', 'price' => 20, 'points' => 3, 'unit_note' => 'Per report generated — free for first report', 'icon' => $icons . 'modules-marketing-diagnostic.svg' ),
				array( 'title' => 'TA Diagnostics', 'price' => 25, 'points' => 5, 'unit_note' => 'Per report generated — free for first report', 'icon' => $icons . 'modules-ta-diagnostics.svg' ),
				array( 'title' => 'Live Chat / Chatbot', 'price' => 25, 'points' => 5, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-livechat.svg' ),
				array( 'title' => 'AI Quote Generator', 'price' => 25, 'points' => 5, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-ai-quote.svg' ),
				array( 'title' => 'Candidate Management System', 'price' => 30, 'points' => 6, 'unit_note' => 'Per user / month', 'icon' => $icons . 'modules-candidate-mgmt.svg' ),
				array( 'title' => 'Job Board Integration', 'price' => 30, 'points' => 6, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-job-board.svg' ),
				array( 'title' => 'Skills Testing / Candidate Assessment', 'price' => 30, 'points' => 6, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-skills-testing.svg' ),
				array( 'title' => 'Invoicing Digital Proposal & E-Signature', 'price' => 30, 'points' => 6, 'unit_note' => 'Per user / month', 'icon' => $icons . 'modules-invoicing.svg' ),
				array( 'title' => 'Payment Gateway Integration', 'price' => 35, 'points' => 7, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-payment.svg' ),
				array( 'title' => 'Task Management Module', 'price' => 35, 'points' => 7, 'unit_note' => 'Per user / month', 'icon' => $icons . 'modules-task-mgmt.svg' ),
				array( 'title' => 'E-Commerce (up to 5 products listing)', 'price' => 35, 'points' => 7, 'unit_note' => 'Per website / month', 'icon' => $icons . 'modules-ecommerce.svg' ),
			),
			'marketing' => array(
				array(
					'title' => 'Bronze', 'price' => 600, 'badge_color' => '#8B5E34',
					'features' => "Social Media Posting (3 posts/week)\nSocial Media Posting (5 stories or short-form posts/week)\nEmail Marketing (1 marketing mailer/month)\nMonthly Performance Report\nFREE Measure Marketing Analytics Dashboard",
				),
				array(
					'title' => 'Silver', 'price' => 1200, 'badge_color' => '#B4B8BE',
					'features' => "Social Media Posting (7 posts/week)\nCommunity Management & Engagement\nEmail Marketing (2 marketing mailers/month)\nSEO Setup & Optimisation\n1 SEO Blog Article/month\nWebsite Blog Posts (2/month)\nMarketing Materials (brochures, flyers, presentations, pitch decks)\nMonthly Strategy Review\nFREE Measure Marketing Analytics Dashboard",
				),
				array(
					'title' => 'Gold', 'price' => 2400, 'badge_color' => '#D6A419',
					'features' => "Social Media Posting (7 posts/week)\nCommunity Management & Engagement\nWeekly Email Newsletter\nWeekly SEO Blog Article\nSEO Setup & Optimisation\n1 Google Ads / Meta Ads Campaign Management (ads expenses not included)\nCampaign Optimisation\nLanding Page Optimisation\nMarketing Materials (brochures, flyers, presentations, pitch decks)\nMonthly Strategy Review\nPriority Support\nFREE Measure Marketing Analytics Dashboard",
				),
			),
			'licenses' => array(
				array( 'title' => 'Microsoft 365 Business Standard', 'price' => 35, 'unit_note' => 'Per user / month', 'icon' => $icons . 'license-microsoft365.svg' ),
				array( 'title' => 'CRM Platform', 'price' => 150, 'unit_note' => 'Per user / month', 'icon' => $icons . 'license-crm.svg' ),
				array( 'title' => 'Cloud Phone System', 'price' => 30, 'unit_note' => 'Per user / month — includes 100 call minutes/month', 'icon' => $icons . 'license-cloudphone.svg' ),
			),
			'measure_tiers' => array(
				array( 'title' => 'Forever Free', 'price' => 0, 'license_count' => 1, 'addon_price' => 0, 'features' => 'TBA', 'icon' => $icons . 'measure-tier-free.svg' ),
				array( 'title' => 'Marketing', 'price' => 25, 'license_count' => 1, 'addon_price' => 25, 'features' => 'Marketing Analytics report', 'icon' => $icons . 'measure-tier-marketing.svg' ),
				array( 'title' => 'Slim', 'price' => 100, 'license_count' => 5, 'addon_price' => 25, 'features' => "Operations: HR, Attendance, Marketing, Finance\nReports: Sales", 'icon' => $icons . 'measure-tier-slim.svg' ),
				array( 'title' => 'Pro', 'price' => 200, 'license_count' => 5, 'addon_price' => 45, 'features' => "All in Slim plus Sales Product\nAdditional Reports: ROI, Leaderboards", 'icon' => $icons . 'measure-tier-pro.svg' ),
				array( 'title' => 'Enterprise', 'price' => 250, 'license_count' => 5, 'addon_price' => 55, 'features' => 'Everything including all the add-on reports', 'icon' => $icons . 'measure-tier-enterprise.svg' ),
			),
			'measure_addons' => array(
				array( 'title' => 'Customer', 'price' => 25, 'licenses_included' => 5, 'icon' => $icons . 'addon-customer.svg' ),
				array( 'title' => 'KPI', 'price' => 25, 'licenses_included' => 5, 'icon' => $icons . 'addon-kpi.svg' ),
				array( 'title' => 'Pipeline', 'price' => 25, 'licenses_included' => 5, 'icon' => $icons . 'addon-pipeline.svg' ),
				array( 'title' => 'Calls', 'price' => 25, 'licenses_included' => 5, 'icon' => $icons . 'addon-calls.svg' ),
				array( 'title' => 'Jobs', 'price' => 25, 'licenses_included' => 5, 'icon' => $icons . 'addon-jobs.svg' ),
				array( 'title' => 'Scorecard', 'price' => 25, 'licenses_included' => 5, 'icon' => $icons . 'addon-scorecard.svg' ),
			),
			'bespoke' => array(
				array( 'title' => 'Discovery & Solution Design', 'price_label' => 'From US$1,100', 'description' => '', 'icon' => $icons . 'bespoke-discovery.svg' ),
				array( 'title' => 'Custom Development', 'price_label' => 'US$90/hour', 'description' => '', 'icon' => $icons . 'bespoke-custom-dev.svg' ),
				array( 'title' => 'Fixed Project Development', 'price_label' => 'From US$3,700', 'description' => '', 'icon' => $icons . 'bespoke-fixed-project.svg' ),
				array( 'title' => 'Enterprise Solutions', 'price_label' => 'Quotation', 'description' => '', 'icon' => $icons . 'bespoke-enterprise-solutions.svg' ),
			),
			'enterprise' => array(
				'title'           => 'Enterprise Plan',
				'price_label'     => 'Starting From US$3,000/month',
				'tier_title'      => 'Gold',
				'marketing_title' => 'Gold',
				'measure_title'   => 'Enterprise',
				'icon'            => $icons . 'enterprise-bundle.svg',
			),
		);
	}

	public static function get_all() {
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
	}

	private static function repeater_categories() {
		return array( 'tiers', 'modules', 'marketing', 'licenses', 'measure_tiers', 'measure_addons', 'bespoke' );
	}

	private static function fields_by_category() {
		return array(
			'tiers'          => array( 'title' => 'text', 'price' => 'number', 'points' => 'number', 'pages_note' => 'text', 'badge_color' => 'color' ),
			'modules'        => array( 'icon' => 'icon', 'title' => 'text', 'price' => 'number', 'points' => 'number', 'unit_note' => 'text' ),
			'marketing'      => array( 'title' => 'text', 'price' => 'number', 'badge_color' => 'color', 'features' => 'textarea' ),
			'licenses'       => array( 'icon' => 'icon', 'title' => 'text', 'price' => 'number', 'unit_note' => 'text' ),
			'measure_tiers'  => array( 'icon' => 'icon', 'title' => 'text', 'price' => 'number', 'license_count' => 'number', 'addon_price' => 'number', 'features' => 'textarea' ),
			'measure_addons' => array( 'icon' => 'icon', 'title' => 'text', 'price' => 'number', 'licenses_included' => 'number' ),
			'bespoke'        => array( 'icon' => 'icon', 'title' => 'text', 'price_label' => 'text', 'description' => 'text' ),
		);
	}

	private static function category_labels() {
		return array(
			'tiers'          => __( 'Website Package (tiers)', 'strivre-solutions-wizard' ),
			'modules'        => __( 'Website Modules', 'strivre-solutions-wizard' ),
			'marketing'      => __( 'Marketing (tiers)', 'strivre-solutions-wizard' ),
			'licenses'       => __( 'Licenses', 'strivre-solutions-wizard' ),
			'measure_tiers'  => __( 'Measure Analytics (tiers)', 'strivre-solutions-wizard' ),
			'measure_addons' => __( 'Measure Analytics — report add-ons', 'strivre-solutions-wizard' ),
			'bespoke'        => __( 'Bespoke Development (informational)', 'strivre-solutions-wizard' ),
		);
	}

	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . SSW_CPT::POST_TYPE,
			__( 'Build Your Business — Catalog', 'strivre-solutions-wizard' ),
			__( 'Catalog', 'strivre-solutions-wizard' ),
			'manage_options',
			'ssw-catalog',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'ssw_catalog_group', self::OPTION_KEY, array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ) {
		$clean  = array();
		$fields = self::fields_by_category();

		foreach ( self::repeater_categories() as $cat ) {
			$clean[ $cat ] = array();
			foreach ( (array) ( $input[ $cat ] ?? array() ) as $row ) {
				if ( empty( $row['title'] ) ) {
					continue; // row was removed client-side, or left blank — drop it
				}
				$clean_row = array();
				foreach ( $fields[ $cat ] as $key => $type ) {
					$raw = $row[ $key ] ?? '';
					if ( 'number' === $type ) {
						$clean_row[ $key ] = is_numeric( $raw ) ? $raw + 0 : 0;
					} elseif ( 'color' === $type ) {
						$clean_row[ $key ] = sanitize_hex_color( $raw ) ?: '#002144';
					} elseif ( 'icon' === $type ) {
						$clean_row[ $key ] = esc_url_raw( $raw );
					} elseif ( 'textarea' === $type ) {
						$clean_row[ $key ] = sanitize_textarea_field( $raw );
					} else {
						$clean_row[ $key ] = sanitize_text_field( $raw );
					}
				}
				$clean[ $cat ][] = $clean_row;
			}
		}

		$ent = $input['enterprise'] ?? array();
		$clean['enterprise'] = array(
			'icon'            => esc_url_raw( $ent['icon'] ?? '' ),
			'title'           => sanitize_text_field( $ent['title'] ?? '' ),
			'price_label'     => sanitize_text_field( $ent['price_label'] ?? '' ),
			'tier_title'      => sanitize_text_field( $ent['tier_title'] ?? '' ),
			'marketing_title' => sanitize_text_field( $ent['marketing_title'] ?? '' ),
			'measure_title'   => sanitize_text_field( $ent['measure_title'] ?? '' ),
		);

		return $clean;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_media();
		$c      = self::get_all();
		$fields = self::fields_by_category();
		$labels = self::category_labels();
		?>
		<div class="wrap ssw-catalog-wrap">
			<h1><?php esc_html_e( 'Build Your Business — Catalog', 'strivre-solutions-wizard' ); ?></h1>
			<p><?php esc_html_e( 'The pricing catalog for the "Build Your Business" page — one source of truth, editable here instead of per-page in Elementor. The original Solutions Wizard page is unaffected by anything on this screen.', 'strivre-solutions-wizard' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'ssw_catalog_group' ); ?>

				<?php foreach ( self::repeater_categories() as $cat ) : ?>
					<details class="ssw-catalog-cat" open>
						<summary><?php echo esc_html( $labels[ $cat ] ); ?> <span class="ssw-catalog-count">(<?php echo count( $c[ $cat ] ); ?>)</span></summary>
						<table class="widefat ssw-catalog-table" data-cat="<?php echo esc_attr( $cat ); ?>">
							<thead>
								<tr>
									<?php foreach ( array_keys( $fields[ $cat ] ) as $key ) : ?>
										<th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
									<?php endforeach; ?>
									<th></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $c[ $cat ] as $i => $row ) : ?>
									<?php echo $this->render_row( $cat, $i, $row, $fields[ $cat ] ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>
						<button type="button" class="button ssw-catalog-add" data-cat="<?php echo esc_attr( $cat ); ?>"><?php esc_html_e( '+ Add row', 'strivre-solutions-wizard' ); ?></button>
						<template id="ssw-catalog-tpl-<?php echo esc_attr( $cat ); ?>">
							<?php echo $this->render_row( $cat, '__INDEX__', array(), $fields[ $cat ] ); ?>
						</template>
					</details>
				<?php endforeach; ?>

				<details class="ssw-catalog-cat" open>
					<summary><?php esc_html_e( 'Enterprise bundle', 'strivre-solutions-wizard' ); ?></summary>
					<table class="form-table" role="presentation">
						<tr>
							<th><label><?php esc_html_e( 'Icon', 'strivre-solutions-wizard' ); ?></label></th>
							<td><?php echo $this->render_icon_field( self::OPTION_KEY . '[enterprise][icon]', $c['enterprise']['icon'] ?? '' ); ?></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Title', 'strivre-solutions-wizard' ); ?></label></th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enterprise][title]" value="<?php echo esc_attr( $c['enterprise']['title'] ); ?>" /></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Price label', 'strivre-solutions-wizard' ); ?></label></th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enterprise][price_label]" value="<?php echo esc_attr( $c['enterprise']['price_label'] ); ?>" /></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Bundled Website Package tier title', 'strivre-solutions-wizard' ); ?></label></th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enterprise][tier_title]" value="<?php echo esc_attr( $c['enterprise']['tier_title'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Must exactly match a title in Website Package above — selecting the bundle auto-selects and locks this tier.', 'strivre-solutions-wizard' ); ?></p></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Bundled Marketing tier title', 'strivre-solutions-wizard' ); ?></label></th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enterprise][marketing_title]" value="<?php echo esc_attr( $c['enterprise']['marketing_title'] ); ?>" /></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Bundled Measure Analytics tier title', 'strivre-solutions-wizard' ); ?></label></th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enterprise][measure_title]" value="<?php echo esc_attr( $c['enterprise']['measure_title'] ); ?>" /></td>
						</tr>
					</table>
				</details>

				<?php submit_button(); ?>
			</form>
		</div>

		<style>
			.ssw-catalog-cat { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 0 16px 16px; margin-bottom: 16px; }
			.ssw-catalog-cat summary { cursor: pointer; padding: 14px 0; font-size: 15px; font-weight: 600; }
			.ssw-catalog-count { font-weight: 400; color: #646970; }
			.ssw-catalog-table { border-collapse: collapse; }
			.ssw-catalog-table th, .ssw-catalog-table td { padding: 6px 8px; vertical-align: top; }
			.ssw-catalog-table input[type="text"], .ssw-catalog-table input[type="number"] { width: 100%; min-width: 120px; }
			.ssw-catalog-table textarea { width: 100%; min-width: 220px; height: 60px; }
			.ssw-catalog-table input[type="color"] { width: 44px; height: 30px; padding: 2px; }
			.ssw-catalog-add { margin-top: 4px; }
			.ssw-catalog-remove { color: #b32d2e; }
			.ssw-icon-field { display: flex; align-items: center; gap: 6px; }
			.ssw-icon-preview { width: 28px; height: 28px; object-fit: contain; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; }
		</style>
		<script>
		( function () {
			document.querySelectorAll( '.ssw-catalog-add' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var cat   = btn.getAttribute( 'data-cat' );
					var tpl   = document.getElementById( 'ssw-catalog-tpl-' + cat );
					var tbody = document.querySelector( '.ssw-catalog-table[data-cat="' + cat + '"] tbody' );
					var index = tbody.children.length;
					var html  = tpl.innerHTML.split( '__INDEX__' ).join( String( index ) );
					var frag  = document.createElement( 'tbody' );
					frag.innerHTML = html;
					tbody.appendChild( frag.firstElementChild );
				} );
			} );
			document.addEventListener( 'click', function ( e ) {
				if ( e.target.classList.contains( 'ssw-catalog-remove' ) ) {
					e.preventDefault();
					e.target.closest( 'tr' ).remove();
				}
				if ( e.target.classList.contains( 'ssw-icon-pick' ) ) {
					e.preventDefault();
					var wrap  = e.target.closest( '.ssw-icon-field' );
					var input = wrap.querySelector( 'input[type="hidden"]' );
					var img   = wrap.querySelector( '.ssw-icon-preview' );
					var clear = wrap.querySelector( '.ssw-icon-clear' );
					var frame = wp.media( { title: 'Select an icon', multiple: false, library: { type: 'image' } } );
					frame.on( 'select', function () {
						var att = frame.state().get( 'selection' ).first().toJSON();
						var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
						input.value = att.url;
						img.src = url;
						img.style.display = '';
						clear.style.display = '';
					} );
					frame.open();
				}
				if ( e.target.classList.contains( 'ssw-icon-clear' ) ) {
					e.preventDefault();
					var w2 = e.target.closest( '.ssw-icon-field' );
					w2.querySelector( 'input[type="hidden"]' ).value = '';
					var img2 = w2.querySelector( '.ssw-icon-preview' );
					img2.style.display = 'none';
					img2.src = '';
					e.target.style.display = 'none';
				}
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Small WP-media-backed icon picker: a hidden input holding the icon URL
	 * plus a preview thumbnail, wired up by the delegated click handler in
	 * render_settings_page() (so it works for rows added dynamically too).
	 */
	private function render_icon_field( $name, $value ) {
		ob_start();
		?>
		<span class="ssw-icon-field">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<img class="ssw-icon-preview" src="<?php echo esc_url( $value ); ?>" style="<?php echo $value ? '' : 'display:none;'; ?>" alt="" />
			<button type="button" class="button ssw-icon-pick"><?php esc_html_e( 'Icon', 'strivre-solutions-wizard' ); ?></button>
			<button type="button" class="button-link ssw-icon-clear" style="<?php echo $value ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Clear', 'strivre-solutions-wizard' ); ?></button>
		</span>
		<?php
		return ob_get_clean();
	}

	private function render_row( $cat, $index, $row, $fields ) {
		ob_start();
		?>
		<tr>
			<?php foreach ( $fields as $key => $type ) :
				$name  = self::OPTION_KEY . '[' . $cat . '][' . $index . '][' . $key . ']';
				$value = $row[ $key ] ?? '';
				?>
				<td>
					<?php if ( 'icon' === $type ) : ?>
						<?php echo $this->render_icon_field( $name, $value ); ?>
					<?php elseif ( 'textarea' === $type ) : ?>
						<textarea name="<?php echo esc_attr( $name ); ?>" placeholder="One per line"><?php echo esc_textarea( $value ); ?></textarea>
					<?php elseif ( 'color' === $type ) : ?>
						<input type="color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ?: '#002144' ); ?>" />
					<?php elseif ( 'number' === $type ) : ?>
						<input type="number" step="any" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
					<?php else : ?>
						<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
			<td><button type="button" class="button-link ssw-catalog-remove"><?php esc_html_e( 'Remove', 'strivre-solutions-wizard' ); ?></button></td>
		</tr>
		<?php
		return ob_get_clean();
	}
}
