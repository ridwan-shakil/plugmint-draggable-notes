<?php
/**
 * Plugin Name:       WP Dashboard Admin Notes
 * Plugin URI:        https://example.com/
 * Description:       Create draggable admin notes with checklist tasks in the WP admin.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * Text Domain:       admin-notes
 * Domain Path:       /languages
 * License:           GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADMIN_NOTES_VERSION', '1.0.0' );
define( 'ADMIN_NOTES_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADMIN_NOTES_URL', plugin_dir_url( __FILE__ ) );

/* Autoload minimal classes */
require_once ADMIN_NOTES_PATH . 'includes/class-admin-notes-loader.php';

/**
 * Boot plugin.
 */
function admin_notes_run() {
	$loader = new Admin_Notes_Loader();
	$loader->run();
}
add_action( 'plugins_loaded', 'admin_notes_run' );
