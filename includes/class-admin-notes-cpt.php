<?php
/**
 * Register the admin_note CPT.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Notes_CPT {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		// Ensure default order meta on save.
		add_action( 'save_post_admin_note', array( $this, 'ensure_order_meta' ), 10, 3 );
	}

	/**
	 * Register custom post type admin_note.
	 *
	 * @return void
	 */
	public function register_cpt() {
		$labels = array(
			'name'               => __( 'Admin Notes', 'admin-notes' ),
			'singular_name'      => __( 'Admin Note', 'admin-notes' ),
			'add_new'            => __( 'Add Note', 'admin-notes' ),
			'add_new_item'       => __( 'Add New Note', 'admin-notes' ),
			'edit_item'          => __( 'Edit Note', 'admin-notes' ),
			'new_item'           => __( 'New Note', 'admin-notes' ),
			'all_items'          => __( 'All Notes', 'admin-notes' ),
			'view_item'          => __( 'View Note', 'admin-notes' ),
			'search_items'       => __( 'Search Notes', 'admin-notes' ),
			'not_found'          => __( 'No notes found', 'admin-notes' ),
			'not_found_in_trash' => __( 'No notes found in Trash', 'admin-notes' ),
			'menu_name'          => __( 'Admin Notes', 'admin-notes' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => false, // We will provide custom UI.
			'show_in_menu'    => false,
			'has_archive'     => false,
			'supports'        => array( 'title', 'author' ),
			'capability_type' => 'post',
			'capabilities'    => array(),
			'show_in_rest'    => false,
		);

		register_post_type( 'admin_note', $args );
	}

	/**
	 * Ensure an order meta exists.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Update flag.
	 */
	public function ensure_order_meta( $post_id, $post, $update ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$order = get_post_meta( $post_id, '_admin_notes_order', true );
		if ( $order === '' ) {
			// Place at end by getting max and +1
			global $wpdb;
			$max = $wpdb->get_var( "SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = '_admin_notes_order'" );
			$new = ! empty( $max ) ? ( intval( $max ) + 1 ) : 1;
			update_post_meta( $post_id, '_admin_notes_order', $new );
		}
	}
}
