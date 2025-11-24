<?php
/**
 * Enqueue assets for Admin Notes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Notes_Assets {

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook = '' ) {
		// Only enqueue on our admin page(s). We check page parameter.
		// if ( isset( $_GET['page'] ) && 'admin-notes' === $_GET['page'] )

		if ( $hook === 'toplevel_page_admin-notes' ) {
			// CSS
			wp_enqueue_style(
				'admin-notes-style',
				ADMIN_NOTES_URL . 'assets/css/admin-notes.css',
				array(),
				ADMIN_NOTES_VERSION
			);

			// JS - use vanilla JS but we include wp-i18n and wp-api if needed later. Use wp_localize_script for nonce.
			wp_enqueue_script(
				'admin-notes-script',
				ADMIN_NOTES_URL . 'assets/js/admin-notes.js',
				array(),
				ADMIN_NOTES_VERSION,
				true
			);

			// Localize for AJAX
			wp_localize_script(
				'admin-notes-script',
				'AdminNotes',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'admin_notes_nonce' ),
					'strings'  => array(
						'saving' => __( 'Saving...', 'admin-notes' ),
						'saved'  => __( 'Saved', 'admin-notes' ),
					),
				)
			);

			// JS - visibility change script
			wp_enqueue_script(
				'admin-notes-visibility-script',
				ADMIN_NOTES_URL . 'assets/js/admin-notes-visibility.js',
				array( 'jquery' ),
				ADMIN_NOTES_VERSION,
				true
			);
			// wp_localize_script(
			//  'admin-notes-visibility-script',
			//  'notesVisibility',
			//  array(
			//      'ajax_url' => admin_url( 'admin-ajax.php' ),
			//      'nonce'    => wp_create_nonce( 'notes_visibility_nonce' ),
			//  )
			// );
		}
	}
}
