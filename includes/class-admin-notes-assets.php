<?php
/**
 * Enqueue assets for Admin Notes.
 *
 * Handles the registration and enqueuing of CSS styles and JavaScript scripts
 * required for the Admin Notes plugin administration pages.
 *
 * @package draggable-notes
 * @since 1.0.0
 * @author MD.Ridwan <ridwansweb@email.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin assets CSS & JS
 */
class Admin_Notes_Assets {

	/**
	 * Enqueue hook fires on plugin init.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues CSS and JavaScript files for the admin interface.
	 *
	 * Assets are only loaded on the plugin's top-level admin page.
	 * Uses wp_localize_script to pass necessary AJAX variables.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook = '' ) {
		// Only enqueue on our admin page(s).
		if ( 'toplevel_page_admin-notes' === $hook ) {
			// CSS.
			wp_enqueue_style(
				'admin-notes-style',
				ADMIN_NOTES_URL . 'assets/css/admin-notes.css',
				array(),
				ADMIN_NOTES_VERSION
			);

			// Enqueue jQuery UI Sortable dependency.
			wp_enqueue_script(
				'jquery-ui-sortable'
			);
			// Main jQuery file.
			wp_enqueue_script(
				'admin-notes-script',
				ADMIN_NOTES_URL . 'assets/js/admin-notes.js',
				array( 'jquery', 'jquery-ui-sortable' ),
				ADMIN_NOTES_VERSION,
				true
			);
			// Pass AJAX and localization variables to the script.
			wp_localize_script(
				'admin-notes-script',
				'AdminNotes',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'admin_notes_nonce' ),
					'strings'  => array(
						'saving' => __( 'Saving...', 'draggable-notes' ),
						'saved'  => __( 'Saved', 'draggable-notes' ),
					),
				)
			);
		}
	}
}
