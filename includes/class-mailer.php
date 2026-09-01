<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends the two notification emails (admin + customer) via wp_mail(),
 * substituting merge tags into the templates configured in
 * Strivre Requests → Settings.
 */
class SSW_Mailer {

	public static function send_notifications( array $data ) {
		$tags = self::build_tags( $data );

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
			$qty = (int) ( $item['qty'] ?? 1 );
			$licenses_lines[] = sprintf( '- %s%s ($%s/mo)', $item['title'] ?? '', $qty > 1 ? " (×{$qty} licenses)" : '', $item['price'] ?? 0 );
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
