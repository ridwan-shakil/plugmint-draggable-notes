<?php
/**
 * Plugin activation and initial setup handler.
 *
 * This file is responsible for setting up initial options,
 * managing the post-activation redirect, and adding the settings link.
 *
 * @package plugmint-draggable-notes
 * @since 1.0.0
 * @author MD.Ridwan <ridwansweb@email.com>
 */

namespace PlugmintDraggableNotes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation, redirect, and setup links.
 *
 * Contains methods hooked to the activation hook, 'admin_init', and the
 * 'plugin_action_links_' filter.
 */
class Admin_Notes_Activation {

	/**
	 * Runs only once on plugin activation.
	 */
	public function run_activation() {
		// Redirect on next page load.
		add_option( 'pdan_admin_notes_do_activation_redirect', true );
	}

	/**
	 * Register redirect + settings link hooks.
	 * This must run AFTER plugin is loaded.
	 */
	public function init() {

		// Handle redirect after activation.
		add_action( 'admin_init', array( $this, 'handle_redirect' ));

		// Add settings link.
		add_filter(
			'plugin_action_links_' . plugin_basename( PDAN_NOTES_FILE ),
			array( $this, 'add_settings_link' )
		);
	}


	/**
	 * Redirect user to plugin settings after activation.
	 */
	public function handle_redirect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_option( 'pdan_admin_notes_do_activation_redirect' ) ) {
			delete_option( 'pdan_admin_notes_do_activation_redirect' );

			wp_safe_redirect( admin_url( 'admin.php?page=pdan-admin-notes' ) );
			exit;
		}
	}

	/**
	 * Add "Settings" link on Plugins page.
	 *
	 * @param array $links links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$link = sprintf(
			'<a href="%s" style="color:#2271b1">%s</a>',
			admin_url( 'admin.php?page=pdan-admin-notes' ),
			__( 'Settings', 'plugmint-draggable-notes' )
		);

		array_push( $links, $link );
		return $links;
	}
}
