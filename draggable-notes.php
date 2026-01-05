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
define( 'PLUGMINT_NOTES_VERSION', '1.0.0' );
define( 'PLUGMINT_NOTES_PATH', plugin_dir_path( __FILE__ ) );
define( 'PLUGMINT_NOTES_URL', plugin_dir_url( __FILE__ ) );
define( 'PLUGMINT_NOTES_FILE', __FILE__ );

// Load activation class.
require_once PLUGMINT_NOTES_PATH . '/includes/class-admin-notes-activation.php';

// Run activation code.
register_activation_hook( __FILE__, 'plugmint_notes_on_activate' );
function plugmint_notes_on_activate() {
	$activation = new Draggable_Notes\Admin\Admin_Notes_Activation();
	$activation->run_activation();
}

// Load main plugin loader.
require_once PLUGMINT_NOTES_PATH . 'includes/class-admin-notes-loader.php';

// Include required core files.
require_once PLUGMINT_NOTES_PATH . 'includes/class-admin-notes-activation.php';
require_once PLUGMINT_NOTES_PATH . 'includes/class-admin-notes-cpt.php';
require_once PLUGMINT_NOTES_PATH . 'includes/class-admin-notes-admin.php';
require_once PLUGMINT_NOTES_PATH . 'includes/class-admin-notes-assets.php';
require_once PLUGMINT_NOTES_PATH . 'includes/class-admin-notes-ajax.php';


/**
 * Boot plugin after all plugins are loaded.
 */
function plugmint_notes_run() {
	$loader = new Draggable_Notes\Admin\Admin_Notes_Loader();
	$loader->run();
}
add_action( 'plugins_loaded', 'plugmint_notes_run' );
