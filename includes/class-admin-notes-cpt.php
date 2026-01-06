<?php
/**
 * Register the admin_note CPT.
 *
 * @package draggable-notes
 * @since 1.0.0
 * @author MD.Ridwan <ridwansweb@email.com>
 */

namespace Draggable_Notes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT genaration class
 */
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
			'name'               => __( 'Admin Notes', 'draggable-notes' ),
			'singular_name'      => __( 'Admin Note', 'draggable-notes' ),
			'add_new'            => __( 'Add Note', 'draggable-notes' ),
			'add_new_item'       => __( 'Add New Note', 'draggable-notes' ),
			'edit_item'          => __( 'Edit Note', 'draggable-notes' ),
			'new_item'           => __( 'New Note', 'draggable-notes' ),
			'all_items'          => __( 'All Notes', 'draggable-notes' ),
			'view_item'          => __( 'View Note', 'draggable-notes' ),
			'search_items'       => __( 'Search Notes', 'draggable-notes' ),
			'not_found'          => __( 'No notes found', 'draggable-notes' ),
			'not_found_in_trash' => __( 'No notes found in Trash', 'draggable-notes' ),
			'menu_name'          => __( 'Admin Notes', 'draggable-notes' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => false, // We provided custom UI.
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
	 * @param int $post_id Post ID.
	 */
	public function ensure_order_meta( $post_id ) {

		// 1. Don't run during autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// 2. Check existing meta.
		$order = get_post_meta( $post_id, '_admin_notes_order', true );

		// If the note already has order, do nothing.
		if ( '' !== $order ) {
			return;
		}

		// 3. Query for the highest existing order using WP_Query.
		$args = array(
			'post_type'      => 'admin_note',
			'posts_per_page' => 1,
			'meta_key'       => '_admin_notes_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'fields'         => 'ids',  // faster.
			'no_found_rows'  => true,   // performance.
			'cache_results'  => true,   // uses WP caching layer.
		);

		$query = new \WP_Query( $args );

		$max_order = 0;

		if ( ! empty( $query->posts ) ) {
			$max_id    = $query->posts[0];
			$max_order = intval( get_post_meta( $max_id, '_admin_notes_order', true ) );
		}

		// 4. New order = max + 1 or 1.
		$new = ( $max_order > 0 ) ? $max_order + 1 : 1;

		// 5. Save new order.
		update_post_meta( $post_id, '_admin_notes_order', $new );
	}
}
