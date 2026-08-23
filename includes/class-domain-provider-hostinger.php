<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain availability via Hostinger's own API (developers.hostinger.com),
 * for when the client moves domain hosting over to Hostinger. Built directly
 * against Hostinger's published OpenAPI spec (github.com/hostinger/api) since
 * we don't yet have a live API token to test against — the client only
 * confirmed the domain will eventually be hosted there, not the credentials.
 * Once a token is available, paste it into Settings and this provider is
 * ready to go with no code changes; if Hostinger's contract has since
 * shifted, this is the file to revisit.
 *
 * Endpoint: POST /api/domains/v1/availability
 * Body:     { domain: "keyword", tlds: ["com"], with_alternatives: true }
 * Auth:     Authorization: Bearer {token}
 * Response: array<{ domain, is_available, is_alternative, restriction }>
 *
 * Unlike the Layered provider, a single call with with_alternatives=true
 * returns both the exact match and alternate-TLD suggestions together, so
 * this doesn't need the same N-sequential-lookups fallback.
 */
class SSW_Domain_Provider_Hostinger {

	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;
	const API_BASE  = 'https://developers.hostinger.com';
	const MAX_ALTS  = 4;

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

		$token = SSW_Admin_Settings::get( 'hostinger_api_token' );
		if ( empty( $token ) ) {
			return new WP_Error( 'ssw_no_api_key', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		$cache_key = 'ssw_domain_hostinger_' . md5( $query );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		list( $keyword, $tld ) = $this->split_domain( $query );
		$exact_domain = $keyword . '.' . $tld;

		$response = wp_remote_post(
			self::API_BASE . '/api/domains/v1/availability',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'domain'            => $keyword,
						'tlds'              => array( $tld ),
						'with_alternatives' => true,
					)
				),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ssw_domain_unreachable', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'ssw_domain_error', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		$items = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'ssw_domain_bad_response', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}

		$result = array(
			'available'   => false,
			'suggestions' => array(),
		);

		foreach ( $items as $item ) {
			if ( empty( $item['domain'] ) ) {
				continue; // null domain = unclaimed free-domain slot, not useful here
			}
			$domain = strtolower( $item['domain'] );
			if ( $domain === $exact_domain && empty( $item['is_alternative'] ) ) {
				$result['available'] = ! empty( $item['is_available'] );
				continue;
			}
			if ( ! empty( $item['is_available'] ) && count( $result['suggestions'] ) < self::MAX_ALTS ) {
				$result['suggestions'][] = array(
					'domain'    => $domain,
					'available' => true,
				);
			}
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
}
