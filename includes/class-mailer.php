<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends the two notification emails (admin + customer), substituting merge
 * tags into the templates configured in Strivre Requests → Settings.
 * `email_delivery_mode` (same screen) picks the channel:
 *
 * - "wordpress": wp_mail() only (the original behavior).
 * - "both": wp_mail() always runs, and the Sign-Up Email API
 *   (SSW_Signup_Api_Client) is also attempted for each recipient —
 *   purely additive, its outcome never affects the wp_mail() send.
 * - "api" (the default): the API is tried first for each recipient;
 *   if a given send fails, wp_mail() is used as a fallback for that
 *   one recipient only, so a visitor is never left unnotified.
 *
 * Every API attempt (success or failure) feeds a consecutive-failure
 * counter (see record_api_result()) — after 5 in a row, the API is
 * clearly unreachable/misconfigured, so `email_delivery_mode` is
 * automatically reverted to "wordpress" (and the counter reset) until
 * someone fixes the connection and switches it back on manually in
 * Strivre Requests → Settings.
 */
class SSW_Mailer {

	const API_FAILURE_OPTION    = 'ssw_signup_api_consecutive_failures';
	const API_FAILURE_THRESHOLD = 5;

	public static function send_notifications( array $data ) {
		$tags = self::build_tags( $data );
		$mode = SSW_Admin_Settings::get( 'email_delivery_mode' ) ?: 'wordpress';

		if ( 'wordpress' === $mode ) {
			self::send_admin_wp( $data, $tags );
			self::send_customer_wp( $data, $tags );
			return;
		}

		$admin_result    = self::send_admin_api( $data, $tags );
		$customer_result = self::send_customer_api( $data, $tags );

		if ( 'both' === $mode ) {
			self::send_admin_wp( $data, $tags );
			self::send_customer_wp( $data, $tags );
		} else {
			// "api" mode: wp_mail() is only the fallback for whichever leg
			// the API failed on (a skipped/not-applicable leg is `null`,
			// not a failure, and needs no fallback).
			if ( is_wp_error( $admin_result ) ) {
				self::send_admin_wp( $data, $tags );
			}
			if ( is_wp_error( $customer_result ) ) {
				self::send_customer_wp( $data, $tags );
			}
		}
	}

	private static function send_admin_wp( array $data, array $tags ) {
		self::send(
			self::split_emails( SSW_Admin_Settings::get( 'notification_emails' ) ),
			self::fill( SSW_Admin_Settings::get( 'admin_email_subject' ), $tags ),
			self::fill( SSW_Admin_Settings::get( 'admin_email_body' ), $tags ),
			$data['email'] ?? ''
		);
	}

	private static function send_customer_wp( array $data, array $tags ) {
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return;
		}
		self::send(
			array( $data['email'] ),
			self::fill( SSW_Admin_Settings::get( 'customer_email_subject' ), $tags ),
			self::fill( SSW_Admin_Settings::get( 'customer_email_body' ), $tags ),
			''
		);
	}

	/** @return true|WP_Error|null null = nothing to send (no configured admin emails) */
	private static function send_admin_api( array $data, array $tags ) {
		$admin_emails = self::split_emails( SSW_Admin_Settings::get( 'notification_emails' ) );
		if ( ! $admin_emails ) {
			return null;
		}
		$result = ( new SSW_Signup_Api_Client() )->send( self::api_payload( $data, $tags, array(
			'to'      => $admin_emails,
			'subject' => self::fill( SSW_Admin_Settings::get( 'admin_email_subject' ), $tags ),
			'replyTo' => ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) ? $data['email'] : null,
		) ) );
		self::record_api_result( $result );
		if ( is_wp_error( $result ) ) {
			error_log( '[SSW Signup API] admin notification failed: ' . $result->get_error_message() );
		}
		return $result;
	}

	/** @return true|WP_Error|null null = nothing to send (no/invalid customer email) */
	private static function send_customer_api( array $data, array $tags ) {
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return null;
		}
		$from_email = SSW_Admin_Settings::get( 'from_email' );
		$result     = ( new SSW_Signup_Api_Client() )->send( self::api_payload( $data, $tags, array(
			'to'      => array( $data['email'] ),
			'subject' => self::fill( SSW_Admin_Settings::get( 'customer_email_subject' ), $tags ),
			'replyTo' => is_email( $from_email ) ? $from_email : null,
		) ) );
		self::record_api_result( $result );
		if ( is_wp_error( $result ) ) {
			error_log( '[SSW Signup API] customer notification failed: ' . $result->get_error_message() );
		}
		return $result;
	}

	/**
	 * The API has no dedicated fields for Marketing/Licenses/Measure
	 * Analytics/Bespoke/Enterprise — those go into `summary` as a compact
	 * digest (built from the same tag values computed for wp_mail) since
	 * `htmlBody` is always sent blank.
	 */
	private static function api_payload( array $data, array $tags, array $overrides ) {
		$solutions = array_map(
			function ( $item ) {
				return array( 'name' => $item['title'] ?? '', 'points' => (int) ( $item['points'] ?? 0 ) );
			},
			$data['solutions'] ?? array()
		);

		return array_merge(
			array(
				'htmlBody'      => '',
				'summary'       => self::build_summary( $data, $tags ),
				'customerName'  => $data['name'] ?? '',
				'customerEmail' => $data['email'] ?? '',
				'packageTier'   => $data['tier'] ?? '',
				'domain'        => $data['domain'] ?: ( $data['domain_name'] ?? '' ),
				'solutions'     => $solutions,
				'attachments'   => null,
			),
			$overrides
		);
	}

	/**
	 * Tracks consecutive API failures across sends (any success resets it
	 * to zero); at API_FAILURE_THRESHOLD in a row, automatically switches
	 * `email_delivery_mode` back to "wordpress" so a persistently broken
	 * or misconfigured API doesn't keep slowing down/losing every
	 * submission's notifications.
	 */
	private static function record_api_result( $result ) {
		if ( ! is_wp_error( $result ) ) {
			if ( get_option( self::API_FAILURE_OPTION, 0 ) ) {
				update_option( self::API_FAILURE_OPTION, 0, false );
			}
			return;
		}

		$count = (int) get_option( self::API_FAILURE_OPTION, 0 ) + 1;
		update_option( self::API_FAILURE_OPTION, $count, false );

		if ( $count >= self::API_FAILURE_THRESHOLD ) {
			update_option( self::API_FAILURE_OPTION, 0, false );
			$settings = get_option( SSW_Admin_Settings::OPTION_KEY, array() );
			if ( 'wordpress' !== ( $settings['email_delivery_mode'] ?? '' ) ) {
				$settings['email_delivery_mode'] = 'wordpress';
				update_option( SSW_Admin_Settings::OPTION_KEY, $settings );
				error_log( '[SSW Signup API] ' . self::API_FAILURE_THRESHOLD . ' consecutive failures — automatically reverted "Send notifications via" to WordPress email. Fix the API connection, then switch it back manually in Strivre Requests → Settings.' );
			}
		}
	}

	private static function build_summary( array $data, array $tags ) {
		$parts = array();
		if ( ! empty( $data['marketing_title'] ) ) {
			$parts[] = 'Marketing: ' . $tags['{marketing_tier}'];
		}
		if ( ! empty( $data['licenses'] ) ) {
			$parts[] = 'Licenses: ' . $tags['{licenses}'];
		}
		if ( ! empty( $data['measure_title'] ) ) {
			$parts[] = 'Measure Analytics: ' . $tags['{measure_tier}'];
		}
		if ( ! empty( $data['bespoke_selected'] ) ) {
			$parts[] = 'Bespoke Development interest: ' . $tags['{bespoke_selected}'];
		}
		if ( ! empty( $data['bespoke_interested'] ) ) {
			$parts[] = 'Additional notes: ' . ( $data['bespoke_notes'] ?: __( 'none', 'strivre-solutions-wizard' ) );
		}
		if ( ! empty( $data['enterprise_selected'] ) ) {
			$parts[] = 'Enterprise bundle selected';
		}
		if ( ! empty( $data['monthly_total'] ) ) {
			$parts[] = 'Monthly total: ' . $tags['{monthly_total}'];
		}
		return $parts ? 'Website sign-up via contact form — ' . implode( '; ', $parts ) . '.' : 'Website sign-up via contact form.';
	}

	private static function build_tags( array $data ) {
		$unit_labels     = array( 'user' => 'users', 'license' => 'licenses', 'month' => 'months', 'report' => 'reports' );
		$solutions_lines = array();
		foreach ( $data['solutions'] ?? array() as $item ) {
			$qty        = (int) ( $item['qty'] ?? 1 );
			$website_qty = (int) ( $item['websiteQty'] ?? 1 );
			$unit       = $unit_labels[ $item['unit'] ?? '' ] ?? 'units';
			$parts = array();
			if ( $website_qty > 1 ) {
				$parts[] = "{$website_qty} websites";
			}
			if ( $qty > 1 ) {
				$parts[] = "{$qty} {$unit}";
			}
			$suffix = $parts ? ' (×' . implode( ' × ', $parts ) . ')' : '';
			$solutions_lines[] = sprintf( '- %s%s (%s pts)', $item['title'] ?? '', $suffix, $item['points'] ?? 0 );
		}

		$addr       = $data['address'] ?? array();
		$addr_lines = array_filter( array(
			$addr['line1'] ?? '',
			$addr['line2'] ?? '',
			trim( ( $addr['city'] ?? '' ) . ( ! empty( $addr['state'] ) ? ', ' . $addr['state'] : '' ) . ' ' . ( $addr['zip'] ?? '' ) ),
			$addr['country'] ?? '',
		) );

		$licenses_lines = array();
		foreach ( $data['licenses'] ?? array() as $item ) {
			$qty        = (int) ( $item['qty'] ?? 1 );
			$month_qty  = (int) ( $item['monthQty'] ?? 1 );
			$lic_parts = array();
			if ( $qty > 1 ) {
				$lic_parts[] = "{$qty} users";
			}
			if ( $month_qty > 1 ) {
				$lic_parts[] = "{$month_qty} months";
			}
			$lic_suffix = $lic_parts ? ' (×' . implode( ' × ', $lic_parts ) . ')' : '';
			$licenses_lines[] = sprintf( '- %s%s ($%s/mo)', $item['title'] ?? '', $lic_suffix, $item['price'] ?? 0 );
		}

		$measure_addon_lines = array();
		foreach ( $data['measure_addons'] ?? array() as $item ) {
			$measure_addon_lines[] = sprintf( '- %s (%s licenses, $%s)', $item['title'] ?? '', $item['qty'] ?? 5, $item['price'] ?? 0 );
		}

		return array(
			'{name}'              => $data['name'] ?? '',
			'{email}'             => $data['email'] ?? '',
			'{phone}'             => $data['phone'] ?? '',
			'{company}'           => $data['company'] ?? '',
			'{address}'           => $addr_lines ? implode( "\n", $addr_lines ) : __( 'not provided', 'strivre-solutions-wizard' ),
			'{tier}'              => $data['tier'] ?? '',
			'{points_included}'   => (string) ( $data['points_included'] ?? 0 ),
			'{template}'          => $data['template'] ?? '',
			'{domain}'            => $data['domain'] ?: __( 'not selected', 'strivre-solutions-wizard' ),
			'{solutions}'         => $solutions_lines ? implode( "\n", $solutions_lines ) : __( 'none selected', 'strivre-solutions-wizard' ),
			'{points_used}'       => (string) ( $data['points_used'] ?? 0 ),
			'{points_shortfall}'  => (string) ( $data['points_shortfall'] ?? 0 ),
			'{page_url}'          => $data['page_url'] ?? '',
			'{domain_wanted}'     => ! empty( $data['domain_wanted'] )
				? sprintf( __( 'Yes — %s', 'strivre-solutions-wizard' ), $data['domain_name'] ?: __( 'no name given yet', 'strivre-solutions-wizard' ) )
				: __( 'No', 'strivre-solutions-wizard' ),
			'{marketing_tier}'    => $data['marketing_title'] ?: __( 'not selected', 'strivre-solutions-wizard' ),
			'{licenses}'          => $licenses_lines ? implode( "\n", $licenses_lines ) : __( 'none selected', 'strivre-solutions-wizard' ),
			'{measure_tier}'      => $data['measure_title']
				? $data['measure_title'] . ( ( $data['measure_license_qty'] ?? 0 ) > 0 ? sprintf( ' (%d licenses, $%s/mo)', $data['measure_license_qty'], $data['measure_price'] ?? 0 ) : '' )
				: __( 'not selected', 'strivre-solutions-wizard' ),
			'{measure_addons}'    => $measure_addon_lines ? implode( "\n", $measure_addon_lines ) : __( 'none selected', 'strivre-solutions-wizard' ),
			'{bespoke_selected}'  => ! empty( $data['bespoke_selected'] ) ? implode( "\n", array_map( function ( $t ) { return '- ' . $t; }, $data['bespoke_selected'] ) ) : __( 'none selected', 'strivre-solutions-wizard' ),
			'{bespoke_interest}'  => ! empty( $data['bespoke_interested'] )
				? __( 'Yes', 'strivre-solutions-wizard' ) . ( ! empty( $data['bespoke_notes'] ) ? ' — ' . $data['bespoke_notes'] : '' )
				: __( 'No', 'strivre-solutions-wizard' ),
			'{enterprise_selected}' => ! empty( $data['enterprise_selected'] ) ? __( 'Yes', 'strivre-solutions-wizard' ) : __( 'No', 'strivre-solutions-wizard' ),
			'{monthly_total}'     => isset( $data['monthly_total'] ) ? '$' . $data['monthly_total'] . '/mo' : '',
		);
	}

	private static function fill( $template, $tags ) {
		return strtr( (string) $template, $tags );
	}

	private static function split_emails( $csv ) {
		$emails = array_filter( array_map( 'trim', explode( ',', (string) $csv ) ), 'is_email' );
		return array_values( $emails );
	}

	/**
	 * Every outgoing email funnels through here — this is the one place a
	 * future email API integration needs to touch. Two extension points,
	 * so that integration can live in its own mu-plugin/snippet instead of
	 * editing this file:
	 *
	 * - `ssw_mailer_message` (filter) — adjust to/subject/body/headers
	 *   (e.g. add a provider's custom headers or a tracking param) before
	 *   send. Return the same shape: array( 'to', 'subject', 'body',
	 *   'headers' ).
	 * - `ssw_mailer_send` (filter, default null) — return anything other
	 *   than null to fully replace wp_mail() with a direct API call (e.g.
	 *   SendGrid's/Postmark's HTTP API instead of SMTP). Most providers
	 *   don't need this — installing their official plugin (SendGrid,
	 *   Mailgun, Postmark, WP Mail SMTP, SES, etc.) reroutes wp_mail()
	 *   itself, so nothing here needs to change at all.
	 */
	private static function send( $to, $subject, $body, $reply_to ) {
		if ( empty( $to ) ) {
			return;
		}
		$from_name  = SSW_Admin_Settings::get( 'from_name' );
		$from_email = SSW_Admin_Settings::get( 'from_email' );

		$headers = array();
		if ( is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
		}
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$message = apply_filters( 'ssw_mailer_message', array(
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
			'headers' => $headers,
		) );

		$handled = apply_filters( 'ssw_mailer_send', null, $message );
		if ( null !== $handled ) {
			return;
		}

		wp_mail( $message['to'], $message['subject'], $message['body'], $message['headers'] );
	}
}
