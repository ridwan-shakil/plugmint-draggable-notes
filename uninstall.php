<?php
/**
 * Uninstall handler for WP Dashboard Admin Notes.
 *
 * This file is executed when the plugin is uninstalled.
 *
 * We do not remove posts automatically to avoid accidental data loss.
 * If you'd like to remove all plugin data on uninstall, implement it here.
 *
 * @package admin-notes
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Example: Remove plugin options or usermeta if you choose.
// delete_option( 'admin_notes_some_option' );
