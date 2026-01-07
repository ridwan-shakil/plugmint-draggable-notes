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
		// Ensure default order meta for new notes on save.
		add_action( 'save_post_admin_note', array( $this, 'ensure_order_meta_for_new_notes' ), 10, 3 );
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
	 * Setting order meta for new notes from the highest existing order + 1.
	 *
	 * @param int $post_id Post ID.
	 */
	public function ensure_order_meta_for_new_notes( $post_id ) {

		// Don't run during autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// If order already exists, do nothing.
		if ( '' !== get_post_meta( $post_id, '_admin_notes_order', true ) ) {
			return;
		}

		global $wpdb;
		$cache_key = 'admin_notes_max_order';
		$max_order = get_transient( $cache_key );

		if ( false === $max_order ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- MAX() aggregate query; cached via transients.
			$max_order = (int) $wpdb->get_var(
				$wpdb->prepare(
					" SELECT MAX(CAST(pm.meta_value AS UNSIGNED))
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s
					AND p.post_type = %s
					AND p.post_status != 'trash'
					",
					'_admin_notes_order',
					'admin_note'
				)
			);
		}

		$new_order = $max_order + 1;
		update_post_meta( $post_id, '_admin_notes_order', $new_order );

		// Always update cache with the new max value.
		set_transient( $cache_key, $new_order, MINUTE_IN_SECONDS * 30 );
	}
}
