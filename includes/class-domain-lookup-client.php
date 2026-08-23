<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the "Domains API" (by Layered) on RapidAPI. The API key
 * never leaves the server — the REST route in class-rest-domain-search.php is
 * the only thing that talks to this class.
 *
 * This provider only offers a single-domain lookup
 * (GET /domains/{domain_name.extension}) — no bulk search/suggestions
 * endpoint — so "suggestions" are built by checking the same keyword against
 * a short list of alternate TLDs via repeated calls to that same endpoint.
 */
class SSW_Domain_Lookup_Client {

	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;
	const ALT_TLDS   = array( 'com', 'net', 'org', 'io', 'co' );
	const MAX_ALTS    = 4;

	/**
	 * @return array|WP_Error {
	 *   available: bool,
	 *   suggestions: array<{domain: string, available: bool}>
	 * }
	 */
	public function check( $query ) {
		$query = strtolower( trim( $query ) );
		if ( '' === $query ) {
			return new WP_Error( 'ssw_empty_query', __( 'Please enter a domain to search.', 'strivre-solutions-wizard' ) );
		}

		$api_key = SSW_Admin_Settings::get( 'domainr_api_key' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'ssw_no_api_key', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		$cache_key = 'ssw_domain_' . md5( $query );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		list( $keyword, $exact_tld ) = $this->split_domain( $query );
		$exact_domain = $keyword . '.' . $exact_tld;

		$exact_status = $this->lookup( $exact_domain, $api_key );
		if ( is_wp_error( $exact_status ) ) {
			return $exact_status;
		}

		$result = array(
			'available'   => $exact_status,
			'suggestions' => array(),
		);

		$alt_tlds = array_slice( array_diff( self::ALT_TLDS, array( $exact_tld ) ), 0, self::MAX_ALTS );
		foreach ( $alt_tlds as $tld ) {
			$candidate = $keyword . '.' . $tld;
			$status    = $this->lookup( $candidate, $api_key );
			if ( is_wp_error( $status ) ) {
				continue; // skip a failed alt lookup rather than failing the whole request
			}
			$result['suggestions'][] = array(
				'domain'    => $candidate,
				'available' => $status,
			);
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Splits "example.co.uk" style input into a best-effort [keyword, tld]
	 * pair. Only the last label is treated as the TLD — good enough for the
	 * common .com/.net/.io/etc case this wizard targets.
	 */
	private function split_domain( $query ) {
		$parts = explode( '.', $query );
		if ( count( $parts ) < 2 ) {
			return array( $parts[0], 'com' );
		}
		$tld     = array_pop( $parts );
		$keyword = implode( '.', $parts );
		return array( $keyword, $tld );
	}

	/**
	 * @return bool|WP_Error true if available, false if taken, WP_Error on failure.
	 */
	private function lookup( $domain, $api_key ) {
		$response = wp_remote_get(
			add_query_arg( 'mode', 'detailed', 'https://' . $this->host() . '/domains/' . rawurlencode( $domain ) ),
			array(
				'headers' => array(
					'X-RapidAPI-Key'  => $api_key,
					'X-RapidAPI-Host' => $this->host(),
				),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ssw_domain_unreachable', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );

		// This provider (RDAP-backed) 404s for domains with no registration
		// record at all — that means available, not an error.
		if ( 404 === $code ) {
			return true;
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'ssw_domain_error', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['availability'] ) ) {
			return new WP_Error( 'ssw_domain_bad_response', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		return 'available' === strtolower( $body['availability'] );
	}

	private function host() {
		return SSW_Admin_Settings::get( 'domainr_api_host' ) ?: 'domains-api.p.rapidapi.com';
	}
}
