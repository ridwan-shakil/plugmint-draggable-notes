<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Deletes the Custom Post Type, all associated posts, and post meta.
 *
 * @package draggable-notes
 * @since 1.0.0
 * @author MD.Ridwan <ridwansweb@email.com>
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * 1. Delete all posts of the custom post type
 */
$admin_notes = get_posts(
	array(
		'post_type'      => 'admin_note',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);

if ( $admin_notes ) {
	foreach ( $admin_notes as $note_id ) {
		wp_delete_post( $post_id, true );
	}
}
