<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Notes_Activation {

	/**
	 * Runs only once on plugin activation.
	 */
	public function run_activation() {
		// Redirect on next page load.
		add_option( 'admin_notes_do_activation_redirect', true );
	}

	/**
	 * Register redirect + settings link hooks.
	 * This must run AFTER plugin is loaded.
	 */
	public function init() {

		// Handle redirect after activation.
		add_action( 'admin_init', array( $this, 'handle_redirect' ) );

		// Add settings link.
		add_filter(
			'plugin_action_links_' . plugin_basename( ADMIN_NOTES_FILE ),
			array( $this, 'add_settings_link' )
		);
	}

	/**
	 * Redirect user to plugin settings after activation.
	 */
	public function handle_redirect() {
		if ( get_option( 'admin_notes_do_activation_redirect', false ) ) {
			delete_option( 'admin_notes_do_activation_redirect' );

			// Do not redirect during network/bulk activations.
			if ( isset( $_GET['activate-multi'] ) ) {
				return;
			}

			wp_safe_redirect( admin_url( 'admin.php?page=admin-notes' ) );
			exit;
		}
	}

	/**
	 * Add "Settings" link on Plugins page.
	 */
	public function add_settings_link( $links ) {
		$link = sprintf(
			'<a href="%s" style="color:#2271b1">%s</a>',
			admin_url( 'admin.php?page=admin-notes' ),
			__( 'Settings', 'wp-dashboard-admin-notes' )
		);

		array_push( $links, $link );
		return $links;
	}
}
