<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around Strivre's own "sign-up email" backend (per the
 * StrivreServicesAPI Postman collection): login with email/password to get
 * a bearer token, cache it, then POST the notification through
 * /api/communications/send-signup-email. Used by SSW_Mailer when
 * `email_delivery_mode` is "api" or "both" — see class-mailer.php.
 *
 * Every public method returns true on success or WP_Error on failure and
 * never throws — a hiccup on this service must never block a visitor's
 * submission or take down the rest of the notification flow.
 */
class SSW_Signup_Api_Client {

	const TOKEN_TRANSIENT = 'ssw_signup_api_token';
	const TOKEN_TTL       = 45 * MINUTE_IN_SECONDS;

	/**
	 * @param array $payload {
	 *   to: string[], subject: string, htmlBody: string, replyTo: ?string,
	 *   summary: string, customerName: string, customerEmail: string,
	 *   packageTier: string, domain: string,
	 *   solutions: array<{name: string, points: int}>, attachments: null
	 * }
	 */
	public function send( array $payload ) {
		$base_url = trim( (string) SSW_Admin_Settings::get( 'signup_api_base_url' ), '/' );
		if ( '' === $base_url ) {
			return new WP_Error( 'ssw_signup_api_no_url', __( 'Sign-up email API base URL is not configured.', 'strivre-solutions-wizard' ) );
		}

		// A static token (settings "Option B") always wins over login-based
		// auth when both happen to be filled in, since it's the simpler,
		// more explicit choice — used as-is, no login call or caching, and
		// no auto-refresh possible if it's ever revoked or expires.
		$static_token = trim( (string) SSW_Admin_Settings::get( 'signup_api_static_token' ) );
		if ( '' !== $static_token ) {
			return $this->send_signup_email( $base_url, $static_token, $payload );
		}

		$token = get_transient( self::TOKEN_TRANSIENT );
		if ( ! $token ) {
			$token = $this->login( $base_url );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
		}

		$result = $this->send_signup_email( $base_url, $token, $payload );

		// Token may have expired server-side even though our cache thought
		// it was still valid — one clean retry with a fresh login.
		if ( is_wp_error( $result ) && 'ssw_signup_api_unauthorized' === $result->get_error_code() ) {
			delete_transient( self::TOKEN_TRANSIENT );
			$token = $this->login( $base_url );
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$result = $this->send_signup_email( $base_url, $token, $payload );
		}

		return $result;
	}

	/** @return string|WP_Error */
	private function login( $base_url ) {
		$email    = SSW_Admin_Settings::get( 'signup_api_login_email' );
		$password = SSW_Admin_Settings::get( 'signup_api_login_password' );
		if ( '' === $email || '' === $password ) {
			return new WP_Error( 'ssw_signup_api_no_creds', __( 'Sign-up email API login email/password is not configured.', 'strivre-solutions-wizard' ) );
		}

		$response = wp_remote_post(
			$base_url . '/api/auth/login',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'email' => $email, 'password' => $password ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ssw_signup_api_unreachable', __( 'Sign-up email API is unreachable (login).', 'strivre-solutions-wizard' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'ssw_signup_api_login_failed', sprintf( __( 'Sign-up email API login failed (HTTP %d).', 'strivre-solutions-wizard' ), $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		// Confirmed against the real test environment: { token, expires, user }.
		// The extra fallback keys below are just cheap insurance in case a
		// future environment/version of this API shapes it differently.
		$token = $body['token']
			?? $body['access_token']
			?? ( $body['data']['token'] ?? null )
			?? ( $body['data']['access_token'] ?? null );

		if ( ! is_string( $token ) || '' === $token ) {
			return new WP_Error( 'ssw_signup_api_bad_login_response', __( 'Sign-up email API login succeeded but no token was found in the response — check the real response shape and adjust SSW_Signup_Api_Client::login().', 'strivre-solutions-wizard' ) );
		}

		// Cache until just before the token's real expiry (from the "expires"
		// field) rather than a guessed duration, falling back to the guess
		// only if that field is ever missing/malformed.
		$ttl = self::TOKEN_TTL;
		if ( ! empty( $body['expires'] ) ) {
			$expires_at = strtotime( $body['expires'] );
			if ( $expires_at ) {
				$ttl = max( 60, $expires_at - time() - 60 );
			}
		}

		set_transient( self::TOKEN_TRANSIENT, $token, $ttl );
		return $token;
	}

	/** @return true|WP_Error */
	private function send_signup_email( $base_url, $token, array $payload ) {
		$response = wp_remote_post(
			$base_url . '/api/communications/send-signup-email',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ssw_signup_api_unreachable', __( 'Sign-up email API is unreachable (send).', 'strivre-solutions-wizard' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'ssw_signup_api_unauthorized', __( 'Sign-up email API rejected the token.', 'strivre-solutions-wizard' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'ssw_signup_api_send_failed', sprintf( __( 'Sign-up email API send failed (HTTP %d).', 'strivre-solutions-wizard' ), $code ) );
		}

		return true;
	}
}
