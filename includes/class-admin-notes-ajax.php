<?php
/**
 * AJAX handlers for Admin Notes.
 *
 * Provides simple endpoints:
 * - add_note
 * - delete_note
 * - save_title
 * - save_checklist
 * - save_color
 * - toggle_minimize
 * - save_order
 * - save_visibility (new)
 *
 * All endpoints expect a valid nonce and capability checks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Notes_Ajax {

	public function init() {
		add_action( 'wp_ajax_admin_notes_add', array( $this, 'ajax_add_note' ) );
		add_action( 'wp_ajax_admin_notes_delete', array( $this, 'ajax_delete_note' ) );
		add_action( 'wp_ajax_admin_notes_save_title', array( $this, 'ajax_save_title' ) );
		add_action( 'wp_ajax_admin_notes_save_checklist', array( $this, 'ajax_save_checklist' ) );
		add_action( 'wp_ajax_admin_notes_save_color', array( $this, 'ajax_save_color' ) );
		add_action( 'wp_ajax_admin_notes_toggle_minimize', array( $this, 'ajax_toggle_minimize' ) );
		add_action( 'wp_ajax_admin_notes_save_order', array( $this, 'ajax_save_order' ) );

		//  save visibility
		add_action( 'wp_ajax_admin_notes_save_visibility', array( $this, 'ajax_save_visibility' ) );
	}

	/**
	 * Add new note (creates CPT post ).
	 */
	public function ajax_add_note() {
		$this->verify_request();

		$defaults = array(
			'post_title'  => __( 'Untitled Note', 'admin-notes' ),
			'post_status' => 'publish',
			'post_type'   => 'admin_note',
			'post_author' => get_current_user_id(),
		);

		$post_id = wp_insert_post( $defaults, true );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'id'         => $post_id,
				'title'      => __( 'Untitled Note', 'admin-notes' ),
				'color'      => '#FFF9C4',
				'visibility' => 'only_me',
				'checklist'  => array(),
			)
		);
	}

	/**
	 * Delete note.
	 */
	public function ajax_delete_note() {
		$this->verify_request();
		wp_verify_nonce( $_POST['nonce'], 'admin_notes_nonce' );

		$post_id = isset( $_POST['note_id'] ) ? intval( $_POST['note_id'] ) : 0;

		if ( ! $post_id || 'admin_note' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid note ID', 'admin-notes' ) ) );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'admin-notes' ) ) );
		}

		wp_delete_post( $post_id, true );

		wp_send_json_success();
	}

	/**
	 * Save title.
	 */
	public function ajax_save_title() {
		$this->verify_request();
		wp_verify_nonce( $_POST['nonce'], 'admin_notes_nonce' );

		$post_id = isset( $_POST['note_id'] ) ? intval( $_POST['note_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

		if ( ! $post_id || 'admin_note' !== get_post_type( $post_id ) ) {
			wp_send_json_error();
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'admin-notes' ) ) );
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);

		wp_send_json_success();
	}

	/**
	 * Save checklist (expects JSON array).
	 */
	public function ajax_save_checklist() {
		$this->verify_request();
		wp_verify_nonce( $_POST['nonce'], 'admin_notes_nonce' );

		$post_id  = isset( $_POST['note_id'] ) ? intval( $_POST['note_id'] ) : 0;
		$check_js = isset( $_POST['checklist'] ) ? wp_unslash( $_POST['checklist'] ) : '[]';

		if ( ! $post_id || 'admin_note' !== get_post_type( $post_id ) ) {
			wp_send_json_error();
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'admin-notes' ) ) );
		}

		$decoded = json_decode( wp_unslash( $check_js ) );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$clean = array();
		foreach ( $decoded as $item ) {
			$id      = isset( $item->id ) ? sanitize_text_field( wp_unslash( $item->id ) ) : wp_generate_uuid4();
			$text    = isset( $item->text ) ? sanitize_text_field( wp_unslash( $item->text ) ) : '';
			$done    = ! empty( $item->completed ) ? 1 : 0;
			$clean[] = array(
				'id'        => $id,
				'text'      => $text,
				'completed' => $done,
			);
		}

		update_post_meta( $post_id, '_admin_notes_checklist', wp_json_encode( $clean ) );

		wp_send_json_success();
	}

	/**
	 * Save color.
	 */
	public function ajax_save_color() {
		$this->verify_request();
		wp_verify_nonce( $_POST['nonce'], 'admin_notes_nonce' );

		$post_id = isset( $_POST['note_id'] ) ? intval( $_POST['note_id'] ) : 0;
		$color   = isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '';

		if ( ! $post_id || 'admin_note' !== get_post_type( $post_id ) ) {
			wp_send_json_error();
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error();
		}

		if ( $color ) {
			update_post_meta( $post_id, '_admin_notes_color', $color );
		}

		wp_send_json_success();
	}

	/**
	 * Toggle minimize state (saved per-user).
	 */
	public function ajax_toggle_minimize() {
		$this->verify_request();
		wp_verify_nonce( $_POST['nonce'], 'admin_notes_nonce' );

		$post_id = isset( $_POST['note_id'] ) ? intval( $_POST['note_id'] ) : 0;
		$state   = isset( $_POST['state'] ) ? boolval( $_POST['state'] ) : false;

		if ( ! $post_id || 'admin_note' !== get_post_type( $post_id ) ) {
			wp_send_json_error();
		}
		$user_id = get_current_user_id();
		$meta    = get_user_meta( $user_id, 'admin_notes_minimized', true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		if ( $state ) {
			// add
			if ( ! in_array( $post_id, $meta, true ) ) {
				$meta[] = $post_id;
			}
		} else {
			$meta = array_diff( $meta, array( $post_id ) );
		}

		update_user_meta( $user_id, 'admin_notes_minimized', array_values( $meta ) );

		wp_send_json_success();
	}

	/**
	 * Save order of notes (expects 'order' => array of post IDs in desired order).
	 */
	public function ajax_save_order() {
		$this->verify_request();
		wp_verify_nonce( $_POST['nonce'], 'admin_notes_nonce' );

		$order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : '';
		$ids   = array();

		if ( is_string( $order ) && '' !== $order ) {
			$decoded = json_decode( $order );
			if ( is_array( $decoded ) ) {
				$ids = array_map( 'intval', $decoded );
			} else {
				$parts = explode( ',', $order );
				$ids   = array_map( 'intval', $parts );
			}
		}

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data', 'admin-notes' ) ) );
		}

		$index = 1;
		foreach ( $ids as $post_id ) {
			if ( $post_id && 'admin_note' === get_post_type( $post_id ) ) {
				update_post_meta( $post_id, '_admin_notes_order', $index );
				++$index;
			}
		}

		wp_send_json_success();
	}

	/**
	 * Save visibility setting for a note.
	 */
	public function ajax_save_visibility() {
		$this->verify_request();
		wp_verify_nonce( $_POST['nonce'], 'admin_notes_nonce' );

		$post_id    = isset( $_POST['note_id'] ) ? intval( $_POST['note_id'] ) : 0;
		$visibility = isset( $_POST['visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['visibility'] ) ) : '';

		$allowed = array( 'only_me', 'all_admins', 'editors_and_above' );

		if ( ! $post_id || 'admin_note' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid note ID', 'admin-notes' ) ) );
		}

		if ( ! in_array( $visibility, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid visibility value', 'admin-notes' ) ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'admin-notes' ) ) );
		}

		// Check if the value is actually changing
		$current_visibility = get_post_meta( $post_id, '_admin_note_visibility', true );
		if ( $current_visibility === $visibility ) {
			// No update needed, return success anyway since the desired state is met
			wp_send_json_success();
		}
		$check = update_post_meta( $post_id, '_admin_note_visibility', $visibility );

		wp_send_json_success();
	}

	/**
	 * Verify nonce & capability.
	 */
	protected function verify_request() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'admin_notes_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'admin-notes' ) ) );
		}

		$capability = apply_filters( 'admin_notes_capability', 'edit_posts' );
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'admin-notes' ) ) );
		}
	}
}
