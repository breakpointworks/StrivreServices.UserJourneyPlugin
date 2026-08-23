<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;

/**
 * The Solutions Wizard widget. Everything content-wise (tiers, solutions
 * catalog, points, template images) lives in per-instance repeaters here
 * rather than global settings, because the same widget gets dropped on
 * different pages with different content. See the plan for the reasoning.
 */
class SSW_Widget_Solutions_Wizard extends Widget_Base {

	public function get_name() {
		return 'ssw-solutions-wizard';
	}

	public function get_title() {
		return __( 'Strivre Solutions Wizard', 'strivre-solutions-wizard' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'strivre' );
	}

	public function get_script_depends() {
		return array( 'ssw-wizard' );
	}

	public function get_style_depends() {
		return array( 'ssw-wizard' );
	}

	protected function register_controls() {
		$this->register_steps_section();
		$this->register_tiers_section();
		$this->register_template_gallery_section();
		$this->register_domain_section();
		$this->register_solutions_section();
		$this->register_checkout_section();
		$this->register_colors_section();
		$this->register_typography_section();
		$this->register_shape_section();
	}

	/* ---------------------------------------------------------------------
	 * CONTENT: steps
	 * ------------------------------------------------------------------ */

	private function register_steps_section() {
		$this->start_controls_section(
			'section_steps',
			array(
				'label' => __( 'Wizard Steps', 'strivre-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'enable_tier_step',
			array(
				'label'        => __( 'Enable Package Tier step', 'strivre-solutions-wizard' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'label_on'     => __( 'On', 'strivre-solutions-wizard' ),
				'label_off'    => __( 'Off', 'strivre-solutions-wizard' ),
			)
		);

		$this->add_control(
			'enable_template_step',
			array(
				'label'        => __( 'Enable Website Template step', 'strivre-solutions-wizard' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'label_on'     => __( 'On', 'strivre-solutions-wizard' ),
				'label_off'    => __( 'Off', 'strivre-solutions-wizard' ),
				'description'  => __( 'A gallery of mockups the visitor picks from after choosing a package.', 'strivre-solutions-wizard' ),
			)
		);

		$this->add_control(
			'enable_domain_step',
			array(
				'label'     => __( 'Enable Domain Search step', 'strivre-solutions-wizard' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'label_on'  => __( 'On', 'strivre-solutions-wizard' ),
				'label_off' => __( 'Off', 'strivre-solutions-wizard' ),
			)
		);

		$this->add_control(
			'preselect_param',
			array(
				'label'       => __( 'URL preselect parameter name', 'strivre-solutions-wizard' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'solution',
				'description' => __( 'e.g. ?solution=crm-solutions will pre-check the matching solution card by its Slug field.', 'strivre-solutions-wizard' ),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * CONTENT: package tiers
	 * ------------------------------------------------------------------ */

	private function register_tiers_section() {
		$this->start_controls_section(
			'section_tiers',
			array(
				'label'     => __( 'Package Tiers', 'strivre-solutions-wizard' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array( 'enable_tier_step' => 'yes' ),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'tier_title',
			array(
				'label'   => __( 'Title', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Tier', 'strivre-solutions-wizard' ),
			)
		);
		$repeater->add_control(
			'tier_tagline',
			array(
				'label'   => __( 'Tagline', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
			)
		);
		$repeater->add_control(
			'tier_description',
			array(
				'label' => __( 'Description', 'strivre-solutions-wizard' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$repeater->add_control(
			'tier_points',
			array(
				'label'   => __( 'Points included', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
			)
		);
		$repeater->add_control(
			'tier_badge_color',
			array(
				'label'   => __( 'Badge color', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#002144',
			)
		);

		$this->add_control(
			'tiers',
			array(
				'label'       => __( 'Tiers', 'strivre-solutions-wizard' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ tier_title }}}',
				'default'     => array(
					array(
						'tier_title'       => 'Bronze',
						'tier_tagline'     => 'Your Essential Online Presence',
						'tier_description' => 'Up to 3 pages with hosting, SSL, maintenance, security updates, backups, and a Measure Marketing Report.',
						'tier_points'      => 200,
						'tier_badge_color' => '#8B5E34',
					),
					array(
						'tier_title'       => 'Silver',
						'tier_tagline'     => 'More Space & More Support',
						'tier_description' => 'Up to 5 pages with all Bronze features, plus priority support.',
						'tier_points'      => 400,
						'tier_badge_color' => '#B4B8BE',
					),
					array(
						'tier_title'       => 'Gold',
						'tier_tagline'     => 'Built for Growing Businesses',
						'tier_description' => 'Up to 10 pages with all Silver features, plus premium support.',
						'tier_points'      => 800,
						'tier_badge_color' => '#D6A419',
					),
				),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * CONTENT: website template gallery
	 * ------------------------------------------------------------------ */

	private function register_template_gallery_section() {
		$this->start_controls_section(
			'section_templates',
			array(
				'label'     => __( 'Website Templates', 'strivre-solutions-wizard' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array( 'enable_template_step' => 'yes' ),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'tmpl_title',
			array(
				'label'   => __( 'Title', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Template', 'strivre-solutions-wizard' ),
			)
		);
		$repeater->add_control(
			'tmpl_gallery',
			array(
				'label'       => __( 'Gallery images (up to 5)', 'strivre-solutions-wizard' ),
				'type'        => Controls_Manager::GALLERY,
				'description' => __( 'The first image is used as the card thumbnail; all of them open in the lightbox when clicked.', 'strivre-solutions-wizard' ),
			)
		);

		$this->add_control(
			'templates',
			array(
				'label'       => __( 'Templates', 'strivre-solutions-wizard' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ tmpl_title }}}',
				'default'     => array(
					array( 'tmpl_title' => 'Template 1' ),
					array( 'tmpl_title' => 'Template 2' ),
					array( 'tmpl_title' => 'Template 3' ),
				),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * CONTENT: domain step
	 * ------------------------------------------------------------------ */

	private function register_domain_section() {
		$this->start_controls_section(
			'section_domain',
			array(
				'label'     => __( 'Domain Search', 'strivre-solutions-wizard' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array( 'enable_domain_step' => 'yes' ),
			)
		);

		$this->add_control(
			'domain_heading',
			array(
				'label'   => __( 'Heading', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( "Let's find your domain", 'strivre-solutions-wizard' ),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * CONTENT: solutions catalog
	 * ------------------------------------------------------------------ */

	private function register_solutions_section() {
		$this->start_controls_section(
			'section_solutions',
			array(
				'label' => __( 'Solutions Catalog', 'strivre-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'sol_icon',
			array(
				'label' => __( 'Icon', 'strivre-solutions-wizard' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);
		$repeater->add_control(
			'sol_title',
			array(
				'label'   => __( 'Title', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Solution', 'strivre-solutions-wizard' ),
			)
		);
		$repeater->add_control(
			'sol_description',
			array(
				'label' => __( 'Short description', 'strivre-solutions-wizard' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$repeater->add_control(
			'sol_points',
			array(
				'label'   => __( 'Points cost', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
			)
		);
		$repeater->add_control(
			'sol_slug',
			array(
				'label'       => __( 'Slug (for ?solution= deep links)', 'strivre-solutions-wizard' ),
				'type'        => Controls_Manager::TEXT,
			)
		);
		$repeater->add_control(
			'sol_enabled',
			array(
				'label'     => __( 'Enabled', 'strivre-solutions-wizard' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
			)
		);
		$repeater->add_control(
			'sol_default_checked',
			array(
				'label'     => __( 'Checked by default', 'strivre-solutions-wizard' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => '',
			)
		);

		$this->add_control(
			'solutions',
			array(
				'label'       => __( 'Solutions', 'strivre-solutions-wizard' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ sol_title }}}',
				'default'     => $this->default_solutions(),
			)
		);

		$this->end_controls_section();
	}

	private function default_solutions() {
		$items = array(
			array( 'sol_title' => 'Diagnostics', 'sol_slug' => 'diagnostics', 'sol_points' => 100, 'sol_description' => 'A clear read on where your website, marketing, and talent acquisition stand today.' ),
			array( 'sol_title' => 'Websites', 'sol_slug' => 'websites', 'sol_points' => 200, 'sol_description' => 'A professional, credible website built and maintained for you.' ),
			array( 'sol_title' => 'Tailored Industry Platforms', 'sol_slug' => 'tailored-industry-platforms', 'sol_points' => 500, 'sol_description' => 'Purpose-built platforms for your specific industry.' ),
			array( 'sol_title' => 'Marketing Services', 'sol_slug' => 'marketing-services', 'sol_points' => 300, 'sol_description' => 'Ongoing marketing support to grow your reach.' ),
			array( 'sol_title' => 'Measure Analytics Platform', 'sol_slug' => 'measure-analytics-platform', 'sol_points' => 250, 'sol_description' => 'Track and measure what matters across your business.' ),
			array( 'sol_title' => 'Strivre Coaching Platform', 'sol_slug' => 'strivre-coaching-platform', 'sol_points' => 300, 'sol_description' => 'Coaching and enablement for your team.' ),
			array( 'sol_title' => 'Telephony Solutions', 'sol_slug' => 'telephony-solutions', 'sol_points' => 200, 'sol_description' => 'Business phone systems that work the way you do.' ),
			array( 'sol_title' => 'CRM Solutions', 'sol_slug' => 'crm-solutions', 'sol_points' => 700, 'sol_description' => 'A CRM set up around your sales process.' ),
			array( 'sol_title' => 'Microsoft 365 Integration', 'sol_slug' => 'microsoft-365-integration', 'sol_points' => 200, 'sol_description' => 'Get your business running smoothly on Microsoft 365.' ),
		);
		foreach ( $items as &$item ) {
			$item['sol_enabled'] = 'yes';
			$item['sol_icon']    = array( 'url' => SSW_PLUGIN_URL . 'assets/img/icons/' . $item['sol_slug'] . '.svg' );
		}
		return $items;
	}

	/* ---------------------------------------------------------------------
	 * CONTENT: checkout
	 * ------------------------------------------------------------------ */

	private function register_checkout_section() {
		$this->start_controls_section(
			'section_checkout',
			array(
				'label' => __( 'Checkout Form', 'strivre-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		foreach ( array(
			'name'    => __( 'Name required', 'strivre-solutions-wizard' ),
			'email'   => __( 'Email required', 'strivre-solutions-wizard' ),
			'phone'   => __( 'Phone required', 'strivre-solutions-wizard' ),
			'company' => __( 'Company required', 'strivre-solutions-wizard' ),
		) as $field => $label ) {
			$this->add_control(
				'field_' . $field . '_required',
				array(
					'label'     => $label,
					'type'      => Controls_Manager::SWITCHER,
					'default'   => 'yes',
				)
			);
		}

		$this->add_control(
			'field_address_enabled',
			array(
				'label'     => __( 'Collect full address (country, street, city, state, ZIP)', 'strivre-solutions-wizard' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
			)
		);

		$this->add_control(
			'success_message',
			array(
				'label'   => __( 'Success message', 'strivre-solutions-wizard' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => __( "Thanks! We've got your request — our team will send you a business proposal within 24 hours.", 'strivre-solutions-wizard' ),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * STYLE
	 * ------------------------------------------------------------------ */

	private function register_colors_section() {
		$this->start_controls_section(
			'section_colors',
			array(
				'label' => __( 'Colors', 'strivre-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$colors = array(
			'primary_color'          => array( __( 'Primary', 'strivre-solutions-wizard' ), '#002144', '--ssw-primary' ),
			'secondary_color'        => array( __( 'Secondary / Accent', 'strivre-solutions-wizard' ), '#801414', '--ssw-secondary' ),
			'text_color'             => array( __( 'Text', 'strivre-solutions-wizard' ), '#1E2933', '--ssw-text' ),
			'background_color'       => array( __( 'Background', 'strivre-solutions-wizard' ), '#F4F6F8', '--ssw-bg' ),
			'surface_color'          => array( __( 'Card surface', 'strivre-solutions-wizard' ), '#FFFFFF', '--ssw-surface' ),
			'border_color'           => array( __( 'Border', 'strivre-solutions-wizard' ), '#64748B', '--ssw-border' ),
			'selected_tint_color'    => array( __( 'Selected card tint', 'strivre-solutions-wizard' ), '#F6EAEA', '--ssw-selected-tint' ),
			'points_ok_color'        => array( __( 'Points balance (OK)', 'strivre-solutions-wizard' ), '#17845A', '--ssw-points-ok' ),
			'points_shortfall_color' => array( __( 'Points balance (shortfall)', 'strivre-solutions-wizard' ), '#B36B00', '--ssw-points-shortfall' ),
		);

		foreach ( $colors as $key => list( $label, $default, $var ) ) {
			$this->add_control(
				$key,
				array(
					'label'     => $label,
					'type'      => Controls_Manager::COLOR,
					'default'   => $default,
					'selectors' => array(
						'{{WRAPPER}} .ssw-wizard' => $var . ': {{VALUE}};',
					),
				)
			);
		}

		$this->end_controls_section();
	}

	private function register_typography_section() {
		$this->start_controls_section(
			'section_typography',
			array(
				'label' => __( 'Typography', 'strivre-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'label'    => __( 'Headings', 'strivre-solutions-wizard' ),
				'selector' => '{{WRAPPER}} .ssw-wizard .ssw-heading',
				'fields_options' => array(
					'font_family' => array( 'default' => 'Poppins' ),
					'font_weight' => array( 'default' => '600' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'body_typography',
				'label'    => __( 'Body', 'strivre-solutions-wizard' ),
				'selector' => '{{WRAPPER}} .ssw-wizard',
				'fields_options' => array(
					'font_family' => array( 'default' => 'Inter' ),
					'font_weight' => array( 'default' => '400' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'label'    => __( 'Buttons', 'strivre-solutions-wizard' ),
				'selector' => '{{WRAPPER}} .ssw-wizard .ssw-btn',
				'fields_options' => array(
					'font_family' => array( 'default' => 'Poppins' ),
					'font_weight' => array( 'default' => '600' ),
				),
			)
		);

		$this->end_controls_section();
	}

	private function register_shape_section() {
		$this->start_controls_section(
			'section_shape',
			array(
				'label' => __( 'Shape', 'strivre-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => __( 'Card corner radius', 'strivre-solutions-wizard' ),
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'default'    => array( 'size' => 14 ),
				'selectors'  => array(
					'{{WRAPPER}} .ssw-wizard' => '--ssw-card-radius: {{SIZE}}px;',
				),
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'      => __( 'Button corner radius', 'strivre-solutions-wizard' ),
				'type'       => Controls_Manager::SLIDER,
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 999 ) ),
				'default'    => array( 'size' => 999 ),
				'selectors'  => array(
					'{{WRAPPER}} .ssw-wizard' => '--ssw-btn-radius: {{SIZE}}px;',
				),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * RENDER
	 * ------------------------------------------------------------------ */

	protected function render() {
		$settings = $this->get_settings_for_display();

		$templates = array();
		if ( 'yes' === $settings['enable_template_step'] ) {
			foreach ( $settings['templates'] as $tmpl ) {
				$gallery = array();
				foreach ( (array) ( $tmpl['tmpl_gallery'] ?? array() ) as $image ) {
					$gallery[] = $image['url'] ?? '';
				}
				$gallery = array_slice( array_values( array_filter( $gallery ) ), 0, 5 );
				if ( empty( $gallery ) ) {
					for ( $i = 1; $i <= 5; $i++ ) {
						$gallery[] = SSW_PLUGIN_URL . 'assets/img/placeholder-template-' . $i . '.svg';
					}
				}
				$templates[] = array(
					'title'   => $tmpl['tmpl_title'],
					'image'   => $gallery[0],
					'gallery' => $gallery,
				);
			}
		}

		$tiers = array();
		if ( 'yes' === $settings['enable_tier_step'] ) {
			foreach ( $settings['tiers'] as $tier ) {
				$tiers[] = array(
					'title'       => $tier['tier_title'],
					'tagline'     => $tier['tier_tagline'],
					'description' => $tier['tier_description'],
					'points'      => (int) $tier['tier_points'],
					'badgeColor'  => $tier['tier_badge_color'] ?: '#002144',
				);
			}
		}

		$solutions = array();
		foreach ( $settings['solutions'] as $sol ) {
			if ( 'yes' !== $sol['sol_enabled'] ) {
				continue;
			}
			$solutions[] = array(
				'title'       => $sol['sol_title'],
				'description' => $sol['sol_description'],
				'points'      => (int) $sol['sol_points'],
				'slug'        => sanitize_title( $sol['sol_slug'] ?: $sol['sol_title'] ),
				'icon'        => $sol['sol_icon']['url'] ?? '',
				'checked'     => 'yes' === $sol['sol_default_checked'],
			);
		}

		$config = array(
			'restUrl'        => esc_url_raw( rest_url( 'strivre-solutions/v1' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'enableTemplateStep' => 'yes' === $settings['enable_template_step'],
			'enableTierStep'  => 'yes' === $settings['enable_tier_step'],
			'enableDomainStep' => 'yes' === $settings['enable_domain_step'],
			'preselectParam'  => $settings['preselect_param'] ?: 'solution',
			'domainHeading'   => $settings['domain_heading'] ?? '',
			'templates'       => $templates,
			'tiers'           => $tiers,
			'solutions'       => $solutions,
			'fields'          => array(
				'name'    => 'yes' === $settings['field_name_required'],
				'email'   => 'yes' === $settings['field_email_required'],
				'phone'   => 'yes' === $settings['field_phone_required'],
				'company' => 'yes' === $settings['field_company_required'],
				'address' => 'yes' === $settings['field_address_enabled'],
			),
			'successMessage'  => $settings['success_message'],
			'pageUrl'         => esc_url_raw( ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? wp_parse_url( home_url(), PHP_URL_HOST ) ) . ( $_SERVER['REQUEST_URI'] ?? '' ) ),
		);
		?>
		<div class="ssw-wizard" data-widget-id="<?php echo esc_attr( $this->get_id() ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<noscript><?php esc_html_e( 'Please enable JavaScript to use this form.', 'strivre-solutions-wizard' ); ?></noscript>
		</div>
		<?php
	}
}
