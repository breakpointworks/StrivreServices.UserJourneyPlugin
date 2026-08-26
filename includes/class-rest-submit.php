<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/strivre-solutions/v1/submit
 *
 * Saves a `strivre_request` post and fires the admin + customer emails.
 * Anonymous-accessible by design (no user accounts in this flow) — guarded
 * by a same-origin nonce plus an optional honeypot / minimum-time spam check.
 */
class SSW_REST_Submit {

	const NAMESPACE_ = 'strivre-solutions/v1';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_nonce' ),
			)
		);
	}

	public function check_nonce( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'ssw_bad_nonce', __( 'Invalid request.', 'strivre-solutions-wizard' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function handle( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'ssw_bad_body', __( 'Malformed request.', 'strivre-solutions-wizard' ), array( 'status' => 400 ) );
		}

		// Honeypot / min-time spam guard — respond as if it worked so bots don't learn anything.
		if ( SSW_Admin_Settings::get( 'spam_guard_enabled' ) ) {
			if ( ! empty( $body['hp'] ) ) {
				return new WP_REST_Response( array( 'success' => true ), 200 );
			}
			$started_at = (int) ( $body['started_at'] ?? 0 );
			if ( $started_at > 0 && ( time() - (int) ( $started_at / 1000 ) ) < 3 ) {
				return new WP_REST_Response( array( 'success' => true ), 200 );
			}
		}

		$name  = sanitize_text_field( $body['name'] ?? '' );
		$email = sanitize_email( $body['email'] ?? '' );

		if ( '' === $name || ! is_email( $email ) ) {
			return new WP_Error( 'ssw_invalid_fields', __( 'Please provide at least your name and a valid email.', 'strivre-solutions-wizard' ), array( 'status' => 400 ) );
		}

		$phone_raw = sanitize_text_field( $body['phone'] ?? '' );
		$phone_cc  = sanitize_text_field( $body['phone_country_code'] ?? '' );
		$phone     = ( $phone_raw && $phone_cc ) ? trim( $phone_cc . ' ' . $phone_raw ) : $phone_raw;
		$company   = sanitize_text_field( $body['company'] ?? '' );
		$first_name = sanitize_text_field( $body['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $body['last_name'] ?? '' );
		$country    = sanitize_text_field( $body['country'] ?? '' );
		$address_1  = sanitize_text_field( $body['address_1'] ?? '' );
		$address_2  = sanitize_text_field( $body['address_2'] ?? '' );
		$city       = sanitize_text_field( $body['city'] ?? '' );
		$state      = sanitize_text_field( $body['state'] ?? '' );
		$zip        = sanitize_text_field( $body['zip'] ?? '' );

		$tier_title     = sanitize_text_field( $body['tier_title'] ?? '' );
		$points_included = absint( $body['tier_points'] ?? 0 );
		$template_title = sanitize_text_field( $body['template_title'] ?? '' );
		$domain         = sanitize_text_field( $body['domain'] ?? '' );
		$page_url       = esc_url_raw( $body['page_url'] ?? '' );

		// "Build Your Business" single-page builder fields — empty/false when
		// the submission came from the classic wizard instead.
		$domain_wanted       = ! empty( $body['domain_wanted'] );
		$domain_name         = sanitize_text_field( $body['domain_name'] ?? '' );
		$marketing_title     = sanitize_text_field( $body['marketing_title'] ?? '' );
		$measure_title       = sanitize_text_field( $body['measure_title'] ?? '' );
		$bespoke_interested  = ! empty( $body['bespoke_interested'] );
		$bespoke_notes       = sanitize_textarea_field( $body['bespoke_notes'] ?? '' );
		$enterprise_selected = ! empty( $body['enterprise_selected'] );

		$licenses = array();
		foreach ( (array) ( $body['licenses'] ?? array() ) as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}
			$licenses[] = array(
				'title' => sanitize_text_field( $item['title'] ),
				'price' => (float) ( $item['price'] ?? 0 ),
			);
		}

		$measure_addons = array();
		foreach ( (array) ( $body['measure_addons'] ?? array() ) as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}
			$measure_addons[] = array(
				'title' => sanitize_text_field( $item['title'] ),
				'price' => (float) ( $item['price'] ?? 0 ),
			);
		}

		$solutions   = array();
		$points_used = 0;
		foreach ( (array) ( $body['solutions'] ?? array() ) as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}
			$points = absint( $item['points'] ?? 0 );
			$points_used += $points;
			$solutions[] = array(
				'title'  => sanitize_text_field( $item['title'] ),
				'points' => $points,
			);
		}
		$points_shortfall = max( 0, $points_used - $points_included );

		$post_id = wp_insert_post(
			array(
				'post_type'   => SSW_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s — %s', $company ?: $name, current_time( 'Y-m-d H:i' ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'ssw_save_failed', __( 'Something went wrong saving your submission. Please try again.', 'strivre-solutions-wizard' ), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, '_customer_name', $name );
		update_post_meta( $post_id, '_customer_first_name', $first_name );
		update_post_meta( $post_id, '_customer_last_name', $last_name );
		update_post_meta( $post_id, '_customer_email', $email );
		update_post_meta( $post_id, '_customer_phone', $phone );
		update_post_meta( $post_id, '_company_name', $company );
		update_post_meta( $post_id, '_address_country', $country );
		update_post_meta( $post_id, '_address_line1', $address_1 );
		update_post_meta( $post_id, '_address_line2', $address_2 );
		update_post_meta( $post_id, '_address_city', $city );
		update_post_meta( $post_id, '_address_state', $state );
		update_post_meta( $post_id, '_address_zip', $zip );
		update_post_meta( $post_id, '_tier_chosen', $tier_title );
		update_post_meta( $post_id, '_template_chosen', $template_title );
		update_post_meta( $post_id, '_domain_chosen', $domain );
		update_post_meta( $post_id, '_solutions', wp_json_encode( $solutions ) );
		update_post_meta( $post_id, '_points_used', $points_used );
		update_post_meta( $post_id, '_points_included', $points_included );
		update_post_meta( $post_id, '_points_shortfall', $points_shortfall );
		update_post_meta( $post_id, '_source_page_url', $page_url );
		update_post_meta( $post_id, '_domain_wanted', $domain_wanted ? 1 : 0 );
		update_post_meta( $post_id, '_domain_name_wanted', $domain_name );
		update_post_meta( $post_id, '_marketing_chosen', $marketing_title );
		update_post_meta( $post_id, '_licenses', wp_json_encode( $licenses ) );
		update_post_meta( $post_id, '_measure_chosen', $measure_title );
		update_post_meta( $post_id, '_measure_addons', wp_json_encode( $measure_addons ) );
		update_post_meta( $post_id, '_bespoke_interested', $bespoke_interested ? 1 : 0 );
		update_post_meta( $post_id, '_bespoke_notes', $bespoke_notes );
		update_post_meta( $post_id, '_enterprise_selected', $enterprise_selected ? 1 : 0 );
		update_post_meta( $post_id, '_phone_country_code', $phone_cc );

		SSW_Mailer::send_notifications(
			array(
				'name'             => $name,
				'email'            => $email,
				'phone'            => $phone,
				'company'          => $company,
				'tier'             => $tier_title,
				'points_included'  => $points_included,
				'template'         => $template_title,
				'domain'           => $domain,
				'solutions'        => $solutions,
				'points_used'      => $points_used,
				'points_shortfall' => $points_shortfall,
				'page_url'         => $page_url,
				'domain_wanted'       => $domain_wanted,
				'domain_name'         => $domain_name,
				'marketing_title'     => $marketing_title,
				'licenses'            => $licenses,
				'measure_title'       => $measure_title,
				'measure_addons'      => $measure_addons,
				'bespoke_interested'  => $bespoke_interested,
				'bespoke_notes'       => $bespoke_notes,
				'enterprise_selected' => $enterprise_selected,
				'address'          => array(
					'country' => $country,
					'line1'   => $address_1,
					'line2'   => $address_2,
					'city'    => $city,
					'state'   => $state,
					'zip'     => $zip,
				),
			)
		);

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
