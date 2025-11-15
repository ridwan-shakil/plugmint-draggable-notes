<?php
/**
 * Loader class for Admin Notes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ADMIN_NOTES_PATH . 'includes/class-admin-notes-cpt.php';
require_once ADMIN_NOTES_PATH . 'includes/class-admin-notes-admin.php';
require_once ADMIN_NOTES_PATH . 'includes/class-admin-notes-assets.php';
require_once ADMIN_NOTES_PATH . 'includes/class-admin-notes-ajax.php';

class Admin_Notes_Loader {

	/** @var Admin_Notes_CPT */
	protected $cpt;

	/** @var Admin_Notes_Admin */
	protected $admin;

	/** @var Admin_Notes_Assets */
	protected $assets;

	/** @var Admin_Notes_Ajax */
	protected $ajax;

	public function __construct() {
		$this->cpt    = new Admin_Notes_CPT();
		$this->admin  = new Admin_Notes_Admin();
		$this->assets = new Admin_Notes_Assets();
		$this->ajax   = new Admin_Notes_Ajax();
	}

	public function run() {
		$this->cpt->register();
		$this->admin->init();
		$this->assets->init();
		$this->ajax->init();
		// load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'admin-notes', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
	}
}
