<?php
/**
 * Tests for Admin_Notes_Activation.
 *
 * @package plugmint-draggable-notes
 */

use PlugmintDraggableNotes\Admin\Admin_Notes_Activation;

require_once dirname( __DIR__ ) . '/includes/class-admin-notes-activation.php';

if ( ! defined( 'PDAN_NOTES_FILE' ) ) {
	define( 'PDAN_NOTES_FILE', dirname( __DIR__ ) . '/plugmint-draggable-notes.php' );
}

class Test_Admin_Notes_Activation extends WP_UnitTestCase {

	/**
	 * @var Admin_Notes_Activation
	 */
	private $activation;

	public function set_up() {
		parent::set_up();
		$this->activation = new Admin_Notes_Activation();
		delete_option( 'pdan_admin_notes_do_activation_redirect' );
	}

	public function tear_down() {
		$plugin_action_links_hook = 'plugin_action_links_' . plugin_basename( PDAN_NOTES_FILE );

		remove_action( 'admin_init', array( $this->activation, 'handle_redirect' ) );
		remove_filter( $plugin_action_links_hook, array( $this->activation, 'add_settings_link' ) );
		delete_option( 'pdan_admin_notes_do_activation_redirect' );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_run_activation_sets_redirect_option() {
		$this->activation->run_activation();

		$this->assertTrue( (bool) get_option( 'pdan_admin_notes_do_activation_redirect', false ) );
	}

	public function test_init_registers_expected_hooks() {
		$plugin_action_links_hook = 'plugin_action_links_' . plugin_basename( PDAN_NOTES_FILE );

		$this->activation->init();

		$this->assertSame( 10, has_action( 'admin_init', array( $this->activation, 'handle_redirect' ) ), 'admin_init hook was not registered with priority 10' );
		$this->assertSame( 10, has_filter( $plugin_action_links_hook, array( $this->activation, 'add_settings_link' ) ) );
	}

	public function test_add_settings_link_appends_plugin_settings_link() {
		$links  = array( '<a href="#">Existing</a>' );
		$result = $this->activation->add_settings_link( $links );

		$this->assertCount( 2, $result );
		$this->assertSame( '<a href="#">Existing</a>', $result[0] );
		$this->assertStringContainsString( admin_url( 'admin.php?page=pdan-admin-notes' ), $result[1] );
		$this->assertStringContainsString( 'Settings', $result[1] );
	}

	public function test_handle_redirect_returns_early_for_user_without_capability() {
		$subscriber_id = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber_id );
		add_option( 'pdan_admin_notes_do_activation_redirect', true );

		$this->activation->handle_redirect();

		$this->assertTrue( (bool) get_option( 'pdan_admin_notes_do_activation_redirect' ) );
	}

	public function test_handle_redirect_does_nothing_when_redirect_option_is_missing() {
		$admin_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $admin_id );
		delete_option( 'pdan_admin_notes_do_activation_redirect' );

		$this->activation->handle_redirect();

		$this->assertFalse( (bool) get_option( 'pdan_admin_notes_do_activation_redirect', false ) );
	}
}
