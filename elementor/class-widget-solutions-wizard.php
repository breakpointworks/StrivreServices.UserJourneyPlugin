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
		return __( 'Strive Solutions Wizard', 'strive-solutions-wizard' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'strive' );
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
				'label' => __( 'Wizard Steps', 'strive-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'enable_tier_step',
			array(
				'label'        => __( 'Enable Package Tier + Website Template step', 'strive-solutions-wizard' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'label_on'     => __( 'On', 'strive-solutions-wizard' ),
				'label_off'    => __( 'Off', 'strive-solutions-wizard' ),
			)
		);

		$this->add_control(
			'enable_domain_step',
			array(
				'label'     => __( 'Enable Domain Search step', 'strive-solutions-wizard' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'label_on'  => __( 'On', 'strive-solutions-wizard' ),
				'label_off' => __( 'Off', 'strive-solutions-wizard' ),
			)
		);

		$this->add_control(
			'preselect_param',
			array(
				'label'       => __( 'URL preselect parameter name', 'strive-solutions-wizard' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'solution',
				'description' => __( 'e.g. ?solution=crm-solutions will pre-check the matching solution card by its Slug field.', 'strive-solutions-wizard' ),
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
				'label'     => __( 'Package Tiers', 'strive-solutions-wizard' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array( 'enable_tier_step' => 'yes' ),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'tier_title',
			array(
				'label'   => __( 'Title', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Tier', 'strive-solutions-wizard' ),
			)
		);
		$repeater->add_control(
			'tier_tagline',
			array(
				'label'   => __( 'Tagline', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
			)
		);
		$repeater->add_control(
			'tier_description',
			array(
				'label' => __( 'Description', 'strive-solutions-wizard' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$repeater->add_control(
			'tier_points',
			array(
				'label'   => __( 'Points included', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
			)
		);
		$repeater->add_control(
			'tier_template_image',
			array(
				'label'   => __( 'Template thumbnail', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => array( 'url' => SSW_PLUGIN_URL . 'assets/img/placeholder-template.svg' ),
			)
		);
		$repeater->add_control(
			'tier_gallery',
			array(
				'label' => __( 'Lightbox gallery images', 'strive-solutions-wizard' ),
				'type'  => Controls_Manager::GALLERY,
			)
		);

		$this->add_control(
			'tiers',
			array(
				'label'       => __( 'Tiers', 'strive-solutions-wizard' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ tier_title }}}',
				'default'     => array(
					array(
						'tier_title'       => 'Bronze',
						'tier_tagline'     => 'Your Essential Online Presence',
						'tier_description' => 'Up to 3 pages with hosting, SSL, maintenance, security updates, backups, and a Measure Marketing Report.',
						'tier_points'      => 200,
					),
					array(
						'tier_title'       => 'Silver',
						'tier_tagline'     => 'More Space & More Support',
						'tier_description' => 'Up to 5 pages with all Bronze features, plus priority support.',
						'tier_points'      => 400,
					),
					array(
						'tier_title'       => 'Gold',
						'tier_tagline'     => 'Built for Growing Businesses',
						'tier_description' => 'Up to 10 pages with all Silver features, plus premium support.',
						'tier_points'      => 800,
					),
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
				'label'     => __( 'Domain Search', 'strive-solutions-wizard' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array( 'enable_domain_step' => 'yes' ),
			)
		);

		$this->add_control(
			'domain_heading',
			array(
				'label'   => __( 'Heading', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( "Let's find your domain", 'strive-solutions-wizard' ),
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
				'label' => __( 'Solutions Catalog', 'strive-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'sol_icon',
			array(
				'label' => __( 'Icon', 'strive-solutions-wizard' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);
		$repeater->add_control(
			'sol_title',
			array(
				'label'   => __( 'Title', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Solution', 'strive-solutions-wizard' ),
			)
		);
		$repeater->add_control(
			'sol_description',
			array(
				'label' => __( 'Short description', 'strive-solutions-wizard' ),
				'type'  => Controls_Manager::TEXTAREA,
			)
		);
		$repeater->add_control(
			'sol_points',
			array(
				'label'   => __( 'Points cost', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 0,
			)
		);
		$repeater->add_control(
			'sol_slug',
			array(
				'label'       => __( 'Slug (for ?solution= deep links)', 'strive-solutions-wizard' ),
				'type'        => Controls_Manager::TEXT,
			)
		);
		$repeater->add_control(
			'sol_enabled',
			array(
				'label'     => __( 'Enabled', 'strive-solutions-wizard' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
			)
		);
		$repeater->add_control(
			'sol_default_checked',
			array(
				'label'     => __( 'Checked by default', 'strive-solutions-wizard' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => '',
			)
		);

		$this->add_control(
			'solutions',
			array(
				'label'       => __( 'Solutions', 'strive-solutions-wizard' ),
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
				'label' => __( 'Checkout Form', 'strive-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		foreach ( array(
			'name'    => __( 'Name required', 'strive-solutions-wizard' ),
			'email'   => __( 'Email required', 'strive-solutions-wizard' ),
			'phone'   => __( 'Phone required', 'strive-solutions-wizard' ),
			'company' => __( 'Company required', 'strive-solutions-wizard' ),
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
			'success_message',
			array(
				'label'   => __( 'Success message', 'strive-solutions-wizard' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => __( "Thanks! We've got your request — our team will send you a business proposal within 24 hours.", 'strive-solutions-wizard' ),
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
				'label' => __( 'Colors', 'strive-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$colors = array(
			'primary_color'          => array( __( 'Primary', 'strive-solutions-wizard' ), '#002144', '--ssw-primary' ),
			'secondary_color'        => array( __( 'Secondary / Accent', 'strive-solutions-wizard' ), '#801414', '--ssw-secondary' ),
			'text_color'             => array( __( 'Text', 'strive-solutions-wizard' ), '#1E2933', '--ssw-text' ),
			'background_color'       => array( __( 'Background', 'strive-solutions-wizard' ), '#F4F6F8', '--ssw-bg' ),
			'surface_color'          => array( __( 'Card surface', 'strive-solutions-wizard' ), '#FFFFFF', '--ssw-surface' ),
			'border_color'           => array( __( 'Border', 'strive-solutions-wizard' ), '#64748B', '--ssw-border' ),
			'selected_tint_color'    => array( __( 'Selected card tint', 'strive-solutions-wizard' ), '#F6EAEA', '--ssw-selected-tint' ),
			'points_ok_color'        => array( __( 'Points balance (OK)', 'strive-solutions-wizard' ), '#17845A', '--ssw-points-ok' ),
			'points_shortfall_color' => array( __( 'Points balance (shortfall)', 'strive-solutions-wizard' ), '#B36B00', '--ssw-points-shortfall' ),
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
				'label' => __( 'Typography', 'strive-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'label'    => __( 'Headings', 'strive-solutions-wizard' ),
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
				'label'    => __( 'Body', 'strive-solutions-wizard' ),
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
				'label'    => __( 'Buttons', 'strive-solutions-wizard' ),
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
				'label' => __( 'Shape', 'strive-solutions-wizard' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => __( 'Card corner radius', 'strive-solutions-wizard' ),
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
				'label'      => __( 'Button corner radius', 'strive-solutions-wizard' ),
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

		$tiers = array();
		if ( 'yes' === $settings['enable_tier_step'] ) {
			foreach ( $settings['tiers'] as $tier ) {
				$gallery = array();
				foreach ( (array) ( $tier['tier_gallery'] ?? array() ) as $image ) {
					$gallery[] = $image['url'] ?? '';
				}
				$tiers[] = array(
					'title'       => $tier['tier_title'],
					'tagline'     => $tier['tier_tagline'],
					'description' => $tier['tier_description'],
					'points'      => (int) $tier['tier_points'],
					'image'       => $tier['tier_template_image']['url'] ?? '',
					'gallery'     => array_filter( $gallery ),
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
			'restUrl'        => esc_url_raw( rest_url( 'strive-solutions/v1' ) ),
			'nonce'           => wp_create_nonce( 'ssw_wizard' ),
			'enableTierStep'  => 'yes' === $settings['enable_tier_step'],
			'enableDomainStep' => 'yes' === $settings['enable_domain_step'],
			'preselectParam'  => $settings['preselect_param'] ?: 'solution',
			'domainHeading'   => $settings['domain_heading'] ?? '',
			'tiers'           => $tiers,
			'solutions'       => $solutions,
			'fields'          => array(
				'name'    => 'yes' === $settings['field_name_required'],
				'email'   => 'yes' === $settings['field_email_required'],
				'phone'   => 'yes' === $settings['field_phone_required'],
				'company' => 'yes' === $settings['field_company_required'],
			),
			'successMessage'  => $settings['success_message'],
			'pageUrl'         => esc_url_raw( home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ?? '' ) ) ),
		);
		?>
		<div class="ssw-wizard" data-widget-id="<?php echo esc_attr( $this->get_id() ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
			<noscript><?php esc_html_e( 'Please enable JavaScript to use this form.', 'strive-solutions-wizard' ); ?></noscript>
		</div>
		<?php
	}
}
