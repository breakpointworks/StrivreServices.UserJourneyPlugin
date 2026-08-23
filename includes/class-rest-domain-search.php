<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /wp-json/strivre-solutions/v1/domain-search?q=example.com
 *
 * Anonymous-accessible (visitors aren't logged in), so "security" here means:
 * a same-origin nonce so the endpoint isn't trivially hot-linked, and a
 * per-IP throttle so it can't be hammered into burning through the Domainr
 * API quota. The API key itself never reaches the browser.
 */
class SSW_REST_Domain_Search {

	const NAMESPACE_ = 'strivre-solutions/v1';
	const THROTTLE_SECONDS = 1.5;

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
			'/domain-search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_nonce' ),
				'args'                => array(
					'q' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
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
		$throttle_key = 'ssw_rl_' . md5( $this->client_ip() );
		if ( get_transient( $throttle_key ) ) {
			return new WP_Error( 'ssw_rate_limited', __( 'Please slow down a little.', 'strivre-solutions-wizard' ), array( 'status' => 429 ) );
		}
		set_transient( $throttle_key, 1, self::THROTTLE_SECONDS );

		$client = new SSW_Domainr_Client();
		$result = $client->check( $request->get_param( 'q' ) );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 200 );
		}
		return new WP_REST_Response( $result, 200 );
	}

	private function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	}
}
