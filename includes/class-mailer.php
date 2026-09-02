<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends the two notification emails (admin + customer), substituting merge
 * tags into the templates configured in Strivre Requests → Settings.
 * `email_delivery_mode` (same screen) picks the channel: WordPress's
 * wp_mail(), Strivre's own Sign-Up Email API (SSW_Signup_Api_Client), or
 * both. The API path is purely additive — it never replaces or blocks the
 * wp_mail() path when both are enabled, and any API failure is logged and
 * swallowed rather than surfaced to the visitor.
 */
class SSW_Mailer {

	public static function send_notifications( array $data ) {
		$tags = self::build_tags( $data );
		$mode = SSW_Admin_Settings::get( 'email_delivery_mode' ) ?: 'wordpress';

		if ( 'api' !== $mode ) {
			self::send(
				self::split_emails( SSW_Admin_Settings::get( 'notification_emails' ) ),
				self::fill( SSW_Admin_Settings::get( 'admin_email_subject' ), $tags ),
				self::fill( SSW_Admin_Settings::get( 'admin_email_body' ), $tags ),
				$data['email'] ?? ''
			);

			if ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
				self::send(
					array( $data['email'] ),
					self::fill( SSW_Admin_Settings::get( 'customer_email_subject' ), $tags ),
					self::fill( SSW_Admin_Settings::get( 'customer_email_body' ), $tags ),
					''
				);
			}
		}

		if ( 'wordpress' !== $mode ) {
			self::send_via_api( $data, $tags );
		}
	}

	/**
	 * Mirrors the admin+customer dual-send above, through
	 * SSW_Signup_Api_Client instead of wp_mail(). The API has no dedicated
	 * fields for Marketing/Licenses/Measure Analytics/Bespoke/Enterprise —
	 * those go into `summary` as a compact digest (built from the same tag
	 * values already computed above) since `htmlBody` is always sent blank.
	 */
	private static function send_via_api( array $data, array $tags ) {
		$client = new SSW_Signup_Api_Client();

		$solutions = array_map(
			function ( $item ) {
				return array( 'name' => $item['title'] ?? '', 'points' => (int) ( $item['points'] ?? 0 ) );
			},
			$data['solutions'] ?? array()
		);

		$base_payload = array(
			'htmlBody'      => '',
			'summary'       => self::build_summary( $data, $tags ),
			'customerName'  => $data['name'] ?? '',
			'customerEmail' => $data['email'] ?? '',
			'packageTier'   => $data['tier'] ?? '',
			'domain'        => $data['domain'] ?: ( $data['domain_name'] ?? '' ),
			'solutions'     => $solutions,
			'attachments'   => null,
		);

		$admin_emails = self::split_emails( SSW_Admin_Settings::get( 'notification_emails' ) );
		if ( $admin_emails ) {
			$result = $client->send( array_merge( $base_payload, array(
				'to'      => $admin_emails,
				'subject' => self::fill( SSW_Admin_Settings::get( 'admin_email_subject' ), $tags ),
				'replyTo' => ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) ? $data['email'] : null,
			) ) );
			if ( is_wp_error( $result ) ) {
				error_log( '[SSW Signup API] admin notification failed: ' . $result->get_error_message() );
			}
		}

		if ( ! empty( $data['email'] ) && is_email( $data['email'] ) ) {
			$from_email = SSW_Admin_Settings::get( 'from_email' );
			$result     = $client->send( array_merge( $base_payload, array(
				'to'      => array( $data['email'] ),
				'subject' => self::fill( SSW_Admin_Settings::get( 'customer_email_subject' ), $tags ),
				'replyTo' => is_email( $from_email ) ? $from_email : null,
			) ) );
			if ( is_wp_error( $result ) ) {
				error_log( '[SSW Signup API] customer notification failed: ' . $result->get_error_message() );
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
