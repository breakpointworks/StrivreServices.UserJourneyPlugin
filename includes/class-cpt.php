<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The `strivre_request` CPT: one post per wizard submission, plus the admin
 * list/detail screens (columns, read-only detail meta box, archive status,
 * CSV/JSON export). Submissions are records, not authored content, so there's
 * no editor and no front-end visibility.
 */
class SSW_CPT {

	const POST_TYPE      = 'strivre_request';
	const STATUS_ARCHIVED = 'strivre-archived';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_post_status' ) );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'list_table_primary_column', array( $this, 'primary_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'status_views' ) );
		add_action( 'pre_get_posts', array( $this, 'include_archived_in_all_view' ) );

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );

		add_filter( 'bulk_actions-edit-' . self::POST_TYPE, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE, array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );

		add_action( 'admin_post_ssw_export', array( $this, 'stream_export' ) );
		add_action( 'admin_post_ssw_archive', array( $this, 'handle_single_status_change' ) );
		add_action( 'admin_post_ssw_restore', array( $this, 'handle_single_status_change' ) );
		add_action( 'restrict_manage_posts', array( $this, 'export_all_button' ) );
		add_action( 'admin_menu', array( $this, 'reposition_menu_below_elementor' ), 9999 );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'                  => __( 'Strivre Requests', 'strivre-solutions-wizard' ),
					'singular_name'         => __( 'Strivre Request', 'strivre-solutions-wizard' ),
					'all_items'             => __( 'Submissions', 'strivre-solutions-wizard' ),
					'edit_item'             => __( 'Submission Details', 'strivre-solutions-wizard' ),
					'search_items'          => __( 'Search Submissions', 'strivre-solutions-wizard' ),
					'not_found'             => __( 'No requests found.', 'strivre-solutions-wizard' ),
					'not_found_in_trash'    => __( 'No requests found in Trash.', 'strivre-solutions-wizard' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-clipboard',
				'supports'     => array( 'title' ),
				'has_archive'  => false,
				'rewrite'      => false,
				'capability_type' => 'post',
				// Submissions only ever get created by the wizard's own REST
				// handler (wp_insert_post() called directly, no capability
				// gate) — there's no "Add New" UI worth having since only the
				// title field is editable there, everything else is read-only
				// wizard data. Disabling create_posts hides the Add New
				// submenu/button entirely without touching edit/delete/list.
				'capabilities' => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Runs after every plugin has registered its admin menu, so it finds
	 * Elementor's actual slot regardless of what position it registered at,
	 * and slots this plugin's top-level menu in immediately after it.
	 *
	 * Elementor registers two top-level entries both labelled "Elementor":
	 * `elementor-home` (the prominent one near the top of the sidebar) and
	 * `elementor` (a second, lower one). "Below Elementor" means below the
	 * visible top one, so `elementor-home` is matched first.
	 */
	public function reposition_menu_below_elementor() {
		global $menu;
		$our_slug      = 'edit.php?post_type=' . self::POST_TYPE;
		$our_pos       = null;
		$elementor_pos = null;
		foreach ( $menu as $position => $item ) {
			if ( isset( $item[2] ) && $our_slug === $item[2] ) {
				$our_pos = $position;
			}
			if ( isset( $item[2] ) && 'elementor-home' === $item[2] ) {
				$elementor_pos = $position;
			}
		}
		if ( null === $elementor_pos ) {
			foreach ( $menu as $position => $item ) {
				if ( isset( $item[2] ) && 'elementor' === $item[2] ) {
					$elementor_pos = $position;
					break;
				}
			}
		}
		if ( null === $our_pos || null === $elementor_pos ) {
			return;
		}
		$our_item = $menu[ $our_pos ];
		unset( $menu[ $our_pos ] );
		$new_position = $elementor_pos + 0.001;
		while ( isset( $menu[ $new_position ] ) ) {
			$new_position += 0.001;
		}
		$menu[ $new_position ] = $our_item;
		ksort( $menu );
	}

	public static function register_post_status() {
		register_post_status(
			self::STATUS_ARCHIVED,
			array(
				'label'                     => _x( 'Archived', 'submission status', 'strivre-solutions-wizard' ),
				'public'                    => false,
				'internal'                  => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of archived submissions */
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'strivre-solutions-wizard' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * List table
	 * ------------------------------------------------------------------ */

	public function columns( $columns ) {
		unset( $columns['title'], $columns['date'] );
		return array_merge(
			$columns,
			array(
				'ssw_name'    => __( 'Name', 'strivre-solutions-wizard' ),
				'ssw_email'   => __( 'Email', 'strivre-solutions-wizard' ),
				'ssw_company' => __( 'Company', 'strivre-solutions-wizard' ),
				'ssw_tier'    => __( 'Tier', 'strivre-solutions-wizard' ),
				'ssw_points'  => __( 'Points', 'strivre-solutions-wizard' ),
				'ssw_domain'  => __( 'Domain', 'strivre-solutions-wizard' ),
				'date'        => __( 'Date', 'strivre-solutions-wizard' ),
			)
		);
	}

	public function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'ssw_name':
				$name = get_post_meta( $post_id, '_customer_name', true );
				echo '<strong><a class="row-title" href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $name ? $name : '(no name)' ) . '</a></strong>';
				break;
			case 'ssw_email':
				echo esc_html( get_post_meta( $post_id, '_customer_email', true ) );
				break;
			case 'ssw_company':
				echo esc_html( get_post_meta( $post_id, '_company_name', true ) );
				break;
			case 'ssw_tier':
				echo esc_html( get_post_meta( $post_id, '_tier_chosen', true ) );
				break;
			case 'ssw_points':
				$used      = (int) get_post_meta( $post_id, '_points_used', true );
				$included  = (int) get_post_meta( $post_id, '_points_included', true );
				$shortfall = (int) get_post_meta( $post_id, '_points_shortfall', true );
				if ( $included > 0 || $used > 0 ) {
					echo esc_html( $used . ' / ' . $included );
					if ( $shortfall > 0 ) {
						echo ' <span style="color:#B36B00;">(&minus;' . esc_html( $shortfall ) . ')</span>';
					}
				} else {
					echo '&#8212;';
				}
				break;
			case 'ssw_domain':
				echo esc_html( get_post_meta( $post_id, '_domain_chosen', true ) ?: '—' );
				break;
		}
	}

	public function primary_column( $column, $screen_id ) {
		if ( 'edit-' . self::POST_TYPE === $screen_id ) {
			return 'ssw_name';
		}
		return $column;
	}

	public function row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'], $actions['view'] );

		if ( self::STATUS_ARCHIVED === $post->post_status ) {
			$actions['ssw_restore'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'ssw_restore', $post->ID ) ),
				esc_html__( 'Restore', 'strivre-solutions-wizard' )
			);
		} else {
			$actions['ssw_archive'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'ssw_archive', $post->ID ) ),
				esc_html__( 'Archive', 'strivre-solutions-wizard' )
			);
		}
		return $actions;
	}

	private function action_url( $action, $post_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'post'   => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $post_id
		);
	}

	/**
	 * WordPress never includes a custom post status in the admin "All" view's
	 * default query, regardless of show_in_admin_all_list — that flag only
	 * affects whether a count/link shows in the views row. Without this,
	 * archived submissions would look like they vanished from "All".
	 */
	public function include_archived_in_all_view( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( empty( $_GET['post_status'] ) ) {
			$query->set( 'post_status', array( 'publish', self::STATUS_ARCHIVED ) );
		}
	}

	public function status_views( $views ) {
		// Core already auto-generates an "Archived" view because the status is
		// registered with show_in_admin_status_list => true — only the
		// "Published" -> "New" relabel needs doing here.
		if ( isset( $views['publish'] ) ) {
			$views['publish'] = preg_replace( '/>' . preg_quote( __( 'Published' ), '/' ) . '(\s|<)/', '>' . esc_html__( 'New', 'strivre-solutions-wizard' ) . '$1', $views['publish'], 1 );
		}
		return $views;
	}

	/* ---------------------------------------------------------------------
	 * Detail view
	 * ------------------------------------------------------------------ */

	public function add_meta_box() {
		remove_post_type_support( self::POST_TYPE, 'editor' );
		add_meta_box(
			'ssw_submission_details',
			__( 'Submission Details', 'strivre-solutions-wizard' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		$address_parts = array_filter( array(
			get_post_meta( $post->ID, '_address_line1', true ),
			get_post_meta( $post->ID, '_address_line2', true ),
			trim( get_post_meta( $post->ID, '_address_city', true ) . ( get_post_meta( $post->ID, '_address_state', true ) ? ', ' . get_post_meta( $post->ID, '_address_state', true ) : '' ) . ' ' . get_post_meta( $post->ID, '_address_zip', true ) ),
			get_post_meta( $post->ID, '_address_country', true ),
		) );

		$fields = array(
			__( 'Name', 'strivre-solutions-wizard' )              => get_post_meta( $post->ID, '_customer_name', true ),
			__( 'Email', 'strivre-solutions-wizard' )              => get_post_meta( $post->ID, '_customer_email', true ),
			__( 'Phone', 'strivre-solutions-wizard' )              => get_post_meta( $post->ID, '_customer_phone', true ),
			__( 'Company', 'strivre-solutions-wizard' )            => get_post_meta( $post->ID, '_company_name', true ),
			__( 'Address', 'strivre-solutions-wizard' )            => $address_parts ? implode( ', ', $address_parts ) : '',
			__( 'Package Tier', 'strivre-solutions-wizard' )       => get_post_meta( $post->ID, '_tier_chosen', true ),
			__( 'Website Template', 'strivre-solutions-wizard' )   => get_post_meta( $post->ID, '_template_chosen', true ),
			__( 'Domain', 'strivre-solutions-wizard' )             => get_post_meta( $post->ID, '_domain_chosen', true ),
			__( 'Points Included', 'strivre-solutions-wizard' )    => get_post_meta( $post->ID, '_points_included', true ),
			__( 'Points Used', 'strivre-solutions-wizard' )        => get_post_meta( $post->ID, '_points_used', true ),
			__( 'Points Shortfall', 'strivre-solutions-wizard' )   => get_post_meta( $post->ID, '_points_shortfall', true ),
			__( 'Domain Wanted?', 'strivre-solutions-wizard' )     => get_post_meta( $post->ID, '_domain_wanted', true ) ? __( 'Yes', 'strivre-solutions-wizard' ) . ' — ' . get_post_meta( $post->ID, '_domain_name_wanted', true ) : __( 'No', 'strivre-solutions-wizard' ),
			__( 'Marketing', 'strivre-solutions-wizard' )          => get_post_meta( $post->ID, '_marketing_chosen', true ),
			__( 'Measure Analytics', 'strivre-solutions-wizard' )  => get_post_meta( $post->ID, '_measure_chosen', true )
				? get_post_meta( $post->ID, '_measure_chosen', true ) . ' (' . get_post_meta( $post->ID, '_measure_license_qty', true ) . ' licenses, $' . get_post_meta( $post->ID, '_measure_price', true ) . '/mo)'
				: '',
			__( 'Bespoke Dev. Interest', 'strivre-solutions-wizard' ) => get_post_meta( $post->ID, '_bespoke_interested', true ) ? __( 'Yes', 'strivre-solutions-wizard' ) . ' — ' . get_post_meta( $post->ID, '_bespoke_notes', true ) : __( 'No', 'strivre-solutions-wizard' ),
			__( 'Enterprise Bundle', 'strivre-solutions-wizard' )  => get_post_meta( $post->ID, '_enterprise_selected', true ) ? __( 'Yes', 'strivre-solutions-wizard' ) : __( 'No', 'strivre-solutions-wizard' ),
			__( 'Pay Annually?', 'strivre-solutions-wizard' )      => get_post_meta( $post->ID, '_pay_annually', true )
				? sprintf( 'Yes — $%s due upfront (20%% off $%s/mo)', get_post_meta( $post->ID, '_annual_total', true ), get_post_meta( $post->ID, '_monthly_total', true ) )
				: __( 'No', 'strivre-solutions-wizard' ),
			__( 'Source Page', 'strivre-solutions-wizard' )        => get_post_meta( $post->ID, '_source_page_url', true ),
		);

		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $label => $value ) {
			echo '<tr><th style="width:220px;text-align:left;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ?: '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$solutions = json_decode( get_post_meta( $post->ID, '_solutions', true ), true );
		echo '<h3 style="margin-top:20px;">' . esc_html__( 'Selected Solutions', 'strivre-solutions-wizard' ) . '</h3>';
		if ( $solutions ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Solution', 'strivre-solutions-wizard' ) . '</th><th>' . esc_html__( 'Qty', 'strivre-solutions-wizard' ) . '</th><th>' . esc_html__( 'Points', 'strivre-solutions-wizard' ) . '</th></tr></thead><tbody>';
			foreach ( $solutions as $item ) {
				echo '<tr><td>' . esc_html( $item['title'] ?? '' ) . '</td><td>' . esc_html( $item['qty'] ?? '1' ) . '</td><td>' . esc_html( $item['points'] ?? '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'None selected.', 'strivre-solutions-wizard' ) . '</p>';
		}

		$this->render_json_list_table( $post->ID, '_licenses', __( 'Licenses', 'strivre-solutions-wizard' ), __( 'License', 'strivre-solutions-wizard' ), 'price', '$', true );
		$this->render_json_list_table( $post->ID, '_measure_addons', __( 'Measure Analytics Add-Ons', 'strivre-solutions-wizard' ), __( 'Report', 'strivre-solutions-wizard' ), 'price', '$', true );

		$bespoke_selected = json_decode( get_post_meta( $post->ID, '_bespoke_selected', true ), true );
		if ( $bespoke_selected ) {
			echo '<h3 style="margin-top:20px;">' . esc_html__( 'Bespoke Development — Interested In', 'strivre-solutions-wizard' ) . '</h3>';
			echo '<p>' . esc_html__( 'Not priced yet — follow up with a quotation.', 'strivre-solutions-wizard' ) . '</p>';
			echo '<ul style="list-style:disc;margin-left:20px;">';
			foreach ( $bespoke_selected as $title ) {
				echo '<li>' . esc_html( $title ) . '</li>';
			}
			echo '</ul>';
		}
	}

	/** Shared renderer for the {title, price} JSON-array meta fields (licenses, measure add-ons). */
	private function render_json_list_table( $post_id, $meta_key, $heading, $col_label, $value_key, $value_prefix, $show_qty = false ) {
		$items = json_decode( get_post_meta( $post_id, $meta_key, true ), true );
		if ( ! $items ) {
			return;
		}
		echo '<h3 style="margin-top:20px;">' . esc_html( $heading ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html( $col_label ) . '</th>';
		if ( $show_qty ) {
			echo '<th>' . esc_html__( 'Qty', 'strivre-solutions-wizard' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Price', 'strivre-solutions-wizard' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			echo '<tr><td>' . esc_html( $item['title'] ?? '' ) . '</td>';
			if ( $show_qty ) {
				echo '<td>' . esc_html( $item['qty'] ?? '1' ) . '</td>';
			}
			echo '<td>' . esc_html( $value_prefix . ( $item[ $value_key ] ?? '' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* ---------------------------------------------------------------------
	 * Bulk actions: archive / restore / export
	 * ------------------------------------------------------------------ */

	public function bulk_actions( $actions ) {
		unset( $actions['edit'] );
		$actions['ssw_archive']     = __( 'Archive', 'strivre-solutions-wizard' );
		$actions['ssw_restore']     = __( 'Restore', 'strivre-solutions-wizard' );
		$actions['ssw_export_csv']  = __( 'Export to CSV', 'strivre-solutions-wizard' );
		$actions['ssw_export_json'] = __( 'Export to JSON', 'strivre-solutions-wizard' );
		return $actions;
	}

	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		$post_ids = array_map( 'absint', $post_ids );

		if ( 'ssw_archive' === $action ) {
			foreach ( $post_ids as $id ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => self::STATUS_ARCHIVED ) );
			}
			return add_query_arg( 'ssw_archived', count( $post_ids ), $redirect_to );
		}

		if ( 'ssw_restore' === $action ) {
			foreach ( $post_ids as $id ) {
				wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
			}
			return add_query_arg( 'ssw_restored', count( $post_ids ), $redirect_to );
		}

		if ( 'ssw_export_csv' === $action || 'ssw_export_json' === $action ) {
			$format = 'ssw_export_csv' === $action ? 'csv' : 'json';
			$key    = 'ssw_export_' . wp_generate_password( 12, false );
			set_transient( $key, array( 'ids' => $post_ids, 'format' => $format ), 5 * MINUTE_IN_SECONDS );
			return add_query_arg(
				array(
					'action' => 'ssw_export',
					'key'    => $key,
					'_wpnonce' => wp_create_nonce( 'ssw_export_' . $key ),
				),
				admin_url( 'admin-post.php' )
			);
		}

		return $redirect_to;
	}

	public function bulk_action_notices() {
		if ( ! empty( $_GET['ssw_archived'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%d submission archived.', '%d submissions archived.', (int) $_GET['ssw_archived'], 'strivre-solutions-wizard' ), (int) $_GET['ssw_archived'] ) ) );
		}
		if ( ! empty( $_GET['ssw_restored'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( sprintf( _n( '%d submission restored.', '%d submissions restored.', (int) $_GET['ssw_restored'], 'strivre-solutions-wizard' ), (int) $_GET['ssw_restored'] ) ) );
		}
	}

	/**
	 * Handles single-row Archive/Restore links from row_actions() — these
	 * hit admin-post.php?action=ssw_archive|ssw_restore directly.
	 */
	public function handle_single_status_change() {
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$post_id = absint( $_GET['post'] ?? 0 );
		check_admin_referer( $action . '_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'strivre-solutions-wizard' ) );
		}
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'ssw_archive' === $action ? self::STATUS_ARCHIVED : 'publish' ) );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
		exit;
	}

	/**
	 * Handles the redirect step for bulk/"export all" CSV/JSON export
	 * (streams the file and exits). Reached via admin-post.php?action=ssw_export.
	 */
	public function stream_export() {
		if ( isset( $_GET['export_all'] ) ) {
			check_admin_referer( 'ssw_export_all' );
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html__( 'You are not allowed to do that.', 'strivre-solutions-wizard' ) );
			}
			$format = 'json' === $_GET['export_all'] ? 'json' : 'csv';
			$query_args = array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : array( 'publish', self::STATUS_ARCHIVED ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				's'              => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			);
			$ids = get_posts( $query_args );
			$this->do_export( $ids, $format );
			return;
		}

		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		check_admin_referer( 'ssw_export_' . $key );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'strivre-solutions-wizard' ) );
		}

		$data = get_transient( $key );
		if ( ! $data ) {
			wp_die( esc_html__( 'This export link has expired. Please try again.', 'strivre-solutions-wizard' ) );
		}
		delete_transient( $key );

		$this->do_export( $data['ids'], $data['format'] );
	}

	public function export_all_button( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}
		$base_args = $_GET;
		unset( $base_args['action'], $base_args['action2'], $base_args['_wpnonce'] );
		foreach ( array( 'csv', 'json' ) as $format ) {
			$url = wp_nonce_url(
				add_query_arg( array_merge( $base_args, array( 'action' => 'ssw_export', 'export_all' => $format ) ), admin_url( 'admin-post.php' ) ),
				'ssw_export_all'
			);
			printf(
				'<a href="%s" class="button" style="margin-left:6px;">%s</a>',
				esc_url( $url ),
				esc_html( sprintf( __( 'Export all (%s)', 'strivre-solutions-wizard' ), strtoupper( $format ) ) )
			);
		}
	}

	private function do_export( $post_ids, $format ) {
		$rows = array();
		foreach ( $post_ids as $id ) {
			$solutions = json_decode( get_post_meta( $id, '_solutions', true ), true );
			$rows[]    = array(
				'id'                => $id,
				'date'              => get_the_date( 'c', $id ),
				'status'            => get_post_status( $id ),
				'name'              => get_post_meta( $id, '_customer_name', true ),
				'email'             => get_post_meta( $id, '_customer_email', true ),
				'phone'             => get_post_meta( $id, '_customer_phone', true ),
				'company'           => get_post_meta( $id, '_company_name', true ),
				'address_country'   => get_post_meta( $id, '_address_country', true ),
				'address_line1'     => get_post_meta( $id, '_address_line1', true ),
				'address_line2'     => get_post_meta( $id, '_address_line2', true ),
				'address_city'      => get_post_meta( $id, '_address_city', true ),
				'address_state'     => get_post_meta( $id, '_address_state', true ),
				'address_zip'       => get_post_meta( $id, '_address_zip', true ),
				'tier'              => get_post_meta( $id, '_tier_chosen', true ),
				'template'          => get_post_meta( $id, '_template_chosen', true ),
				'domain'            => get_post_meta( $id, '_domain_chosen', true ),
				'points_included'   => get_post_meta( $id, '_points_included', true ),
				'points_used'       => get_post_meta( $id, '_points_used', true ),
				'points_shortfall'  => get_post_meta( $id, '_points_shortfall', true ),
				'solutions'         => $solutions ? wp_json_encode( $solutions ) : '',
				'domain_wanted'     => get_post_meta( $id, '_domain_wanted', true ) ? 'yes' : 'no',
				'domain_name_wanted' => get_post_meta( $id, '_domain_name_wanted', true ),
				'marketing_chosen'  => get_post_meta( $id, '_marketing_chosen', true ),
				'licenses'          => get_post_meta( $id, '_licenses', true ),
				'measure_chosen'    => get_post_meta( $id, '_measure_chosen', true ),
				'measure_license_qty' => get_post_meta( $id, '_measure_license_qty', true ),
				'measure_price'     => get_post_meta( $id, '_measure_price', true ),
				'measure_addons'    => get_post_meta( $id, '_measure_addons', true ),
				'bespoke_selected'  => get_post_meta( $id, '_bespoke_selected', true ),
				'bespoke_interested' => get_post_meta( $id, '_bespoke_interested', true ) ? 'yes' : 'no',
				'bespoke_notes'     => get_post_meta( $id, '_bespoke_notes', true ),
				'enterprise_selected' => get_post_meta( $id, '_enterprise_selected', true ) ? 'yes' : 'no',
				'pay_annually'      => get_post_meta( $id, '_pay_annually', true ) ? 'yes' : 'no',
				'monthly_total'     => get_post_meta( $id, '_monthly_total', true ),
				'annual_total'      => get_post_meta( $id, '_annual_total', true ),
				'phone_country_code' => get_post_meta( $id, '_phone_country_code', true ),
				'source_page_url'   => get_post_meta( $id, '_source_page_url', true ),
			);
		}

		$filename = 'strivre-submissions-' . gmdate( 'Y-m-d-His' ) . '.' . $format;

		nocache_headers();
		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			echo wp_json_encode( $rows, JSON_PRETTY_PRINT );
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$out = fopen( 'php://output', 'w' );
		if ( ! empty( $rows ) ) {
			fputcsv( $out, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $out, $row );
			}
		}
		fclose( $out );
		exit;
	}
}
