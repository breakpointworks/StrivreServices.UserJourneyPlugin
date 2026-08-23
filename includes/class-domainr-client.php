<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around Domainr's RapidAPI endpoints. The API key never leaves
 * the server — the REST route in class-rest-domain-search.php is the only
 * thing that talks to this class.
 */
class SSW_Domainr_Client {

	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

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

		$cache_key = 'ssw_domainr_' . md5( $query );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$search_results = $this->search( $query, $api_key );
		if ( is_wp_error( $search_results ) ) {
			return $search_results;
		}

		$candidates = array( $query );
		foreach ( array_slice( $search_results, 0, 6 ) as $result ) {
			if ( ! empty( $result['domain'] ) && ! in_array( $result['domain'], $candidates, true ) ) {
				$candidates[] = $result['domain'];
			}
		}

		$statuses = $this->status( $candidates, $api_key );
		if ( is_wp_error( $statuses ) ) {
			return $statuses;
		}

		$is_available = static function ( $status_summary ) {
			return false !== strpos( $status_summary, 'inactive' ) || false !== strpos( $status_summary, 'undelegated' );
		};

		$exact_status = $statuses[ $query ] ?? '';
		$result       = array(
			'available'   => $is_available( $exact_status ),
			'suggestions' => array(),
		);

		foreach ( $candidates as $candidate ) {
			if ( $candidate === $query ) {
				continue;
			}
			$result['suggestions'][] = array(
				'domain'    => $candidate,
				'available' => $is_available( $statuses[ $candidate ] ?? '' ),
			);
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	private function search( $query, $api_key ) {
		$response = wp_remote_get(
			add_query_arg( 'query', rawurlencode( $query ), 'https://' . $this->host() . '/v2/search' ),
			array(
				'headers' => $this->headers( $api_key ),
				'timeout' => 8,
			)
		);
		$body = $this->decode( $response );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		return $body['results'] ?? array();
	}

	private function status( $domains, $api_key ) {
		$response = wp_remote_get(
			add_query_arg( 'domain', rawurlencode( implode( ',', $domains ) ), 'https://' . $this->host() . '/v2/status' ),
			array(
				'headers' => $this->headers( $api_key ),
				'timeout' => 8,
			)
		);
		$body = $this->decode( $response );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$map = array();
		foreach ( $body['status'] ?? array() as $entry ) {
			if ( ! empty( $entry['domain'] ) ) {
				$map[ $entry['domain'] ] = $entry['status'] ?? '';
			}
		}
		return $map;
	}

	private function headers( $api_key ) {
		return array(
			'X-RapidAPI-Key'  => $api_key,
			'X-RapidAPI-Host' => $this->host(),
		);
	}

	private function host() {
		return SSW_Admin_Settings::get( 'domainr_api_host' ) ?: 'domainr.p.rapidapi.com';
	}

	private function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ssw_domainr_unreachable', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'ssw_domainr_error', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'ssw_domainr_bad_response', __( 'Domain search is temporarily unavailable.', 'strivre-solutions-wizard' ) );
		}
		return $body;
	}
}
