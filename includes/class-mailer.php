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
		$solutions_lines = array();
		foreach ( $data['solutions'] ?? array() as $item ) {
			$solutions_lines[] = sprintf( '- %s (%s pts)', $item['title'] ?? '', $item['points'] ?? 0 );
		}

		$addr       = $data['address'] ?? array();
		$addr_lines = array_filter( array(
			$addr['line1'] ?? '',
			$addr['line2'] ?? '',
			trim( ( $addr['city'] ?? '' ) . ( ! empty( $addr['state'] ) ? ', ' . $addr['state'] : '' ) . ' ' . ( $addr['zip'] ?? '' ) ),
			$addr['country'] ?? '',
		) );

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
		);
	}

	private static function fill( $template, $tags ) {
		return strtr( (string) $template, $tags );
	}

	private static function split_emails( $csv ) {
		$emails = array_filter( array_map( 'trim', explode( ',', (string) $csv ) ), 'is_email' );
		return array_values( $emails );
	}

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

		wp_mail( $to, $subject, $body, $headers );
	}
}
