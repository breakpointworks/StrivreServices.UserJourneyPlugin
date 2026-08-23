<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/strive-solutions/v1/submit
 *
 * Saves a `strive_request` post and fires the admin + customer emails.
 * Anonymous-accessible by design (no user accounts in this flow) — guarded
 * by a same-origin nonce plus an optional honeypot / minimum-time spam check.
 */
class SSW_REST_Submit {

	const NAMESPACE_ = 'strive-solutions/v1';

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
		$nonce = $request->get_header( 'X-SSW-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ssw_wizard' ) ) {
			return new WP_Error( 'ssw_bad_nonce', __( 'Invalid request.', 'strive-solutions-wizard' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function handle( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'ssw_bad_body', __( 'Malformed request.', 'strive-solutions-wizard' ), array( 'status' => 400 ) );
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
			return new WP_Error( 'ssw_invalid_fields', __( 'Please provide at least your name and a valid email.', 'strive-solutions-wizard' ), array( 'status' => 400 ) );
		}

		$phone   = sanitize_text_field( $body['phone'] ?? '' );
		$company = sanitize_text_field( $body['company'] ?? '' );

		$tier_title     = sanitize_text_field( $body['tier_title'] ?? '' );
		$points_included = absint( $body['tier_points'] ?? 0 );
		$template_title = sanitize_text_field( $body['template_title'] ?? '' );
		$domain         = sanitize_text_field( $body['domain'] ?? '' );
		$page_url       = esc_url_raw( $body['page_url'] ?? '' );

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
			return new WP_Error( 'ssw_save_failed', __( 'Something went wrong saving your submission. Please try again.', 'strive-solutions-wizard' ), array( 'status' => 500 ) );
		}

		update_post_meta( $post_id, '_customer_name', $name );
		update_post_meta( $post_id, '_customer_email', $email );
		update_post_meta( $post_id, '_customer_phone', $phone );
		update_post_meta( $post_id, '_company_name', $company );
		update_post_meta( $post_id, '_tier_chosen', $tier_title );
		update_post_meta( $post_id, '_template_chosen', $template_title );
		update_post_meta( $post_id, '_domain_chosen', $domain );
		update_post_meta( $post_id, '_solutions', wp_json_encode( $solutions ) );
		update_post_meta( $post_id, '_points_used', $points_used );
		update_post_meta( $post_id, '_points_included', $points_included );
		update_post_meta( $post_id, '_points_shortfall', $points_shortfall );
		update_post_meta( $post_id, '_source_page_url', $page_url );

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
			)
		);

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
