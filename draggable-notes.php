<?php
/**
 * Plugin Name:       Plugmint Draggable Admin Notes
 * Description:       Create draggable admin notes with checklist tasks in the WP admin.
 * Version:           1.0.0
 * Author:            MD.Ridwan
 * Author URI:        https://github.com/ridwan-shakil
 * Text Domain:       draggable-notes
 * Domain Path:       /languages
 * License:           GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'ADMIN_NOTES_VERSION', '1.0.0' );
define( 'ADMIN_NOTES_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADMIN_NOTES_URL', plugin_dir_url( __FILE__ ) );
define( 'ADMIN_NOTES_FILE', __FILE__ );

// Load activation class.
require_once ADMIN_NOTES_PATH . '/includes/class-admin-notes-activation.php';

// Run activation code.
register_activation_hook( __FILE__, 'admin_notes_on_activate' );
function admin_notes_on_activate() {
	$activation = new Admin_Notes_Activation();
	$activation->run_activation();
}

// Load main plugin loader.
require_once ADMIN_NOTES_PATH . 'includes/class-admin-notes-loader.php';

/**
 * Boot plugin after all plugins are loaded.
 */
function admin_notes_run() {
	$loader = new Admin_Notes_Loader();
	$loader->run();
}
add_action( 'plugins_loaded', 'admin_notes_run' );
