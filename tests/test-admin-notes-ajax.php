<?php
/**
 * Tests for Admin_Notes_Ajax.
 *
 * @package plugmint-draggable-notes
 */

use PlugmintDraggableNotes\Admin\Admin_Notes_Ajax;
use PlugmintDraggableNotes\Admin\Admin_Notes_CPT;

require_once dirname( __DIR__ ) . '/includes/class-admin-notes-ajax.php';
require_once dirname( __DIR__ ) . '/includes/class-admin-notes-cpt.php';

class Test_Admin_Notes_Ajax extends WP_Ajax_UnitTestCase {

	/**
	 * @var Admin_Notes_Ajax
	 */
	private $ajax;

	/**
	 * @var Admin_Notes_CPT
	 */
	private $cpt;

	/**
	 * @var int
	 */
	private $admin_id;

	public function set_up() {
		parent::set_up();

		$this->ajax = new Admin_Notes_Ajax();
		$this->ajax->init();

		$this->cpt = new Admin_Notes_CPT();
		$this->cpt->register_cpt();

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_init_registers_core_ajax_hooks() {
		$this->assertSame( 10, has_action( 'wp_ajax_pdan_admin_notes_add', array( $this->ajax, 'ajax_add_note' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_pdan_admin_notes_save_order', array( $this->ajax, 'ajax_save_order' ) ) );
		$this->assertSame( 10, has_action( 'wp_ajax_pdan_admin_notes_save_visibility', array( $this->ajax, 'ajax_save_visibility' ) ) );
	}

	public function test_ajax_add_note_creates_note_with_expected_defaults() {
		$response = $this->dispatch_ajax(
			'pdan_admin_notes_add',
			array(
				'nonce' => wp_create_nonce( 'pdan_admin_notes_nonce' ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Untitled Note', $response['data']['title'] );
		$this->assertSame( 'only_me', $response['data']['visibility'] );
		$this->assertSame( '#FFF9C4', $response['data']['color'] );
		$this->assertIsArray( $response['data']['checklist'] );

		$post = get_post( (int) $response['data']['id'] );
		$this->assertNotNull( $post );
		$this->assertSame( 'pdan_admin_note', $post->post_type );
		$this->assertSame( $this->admin_id, (int) $post->post_author );
	}

	public function test_ajax_add_note_requires_valid_nonce() {
		$response = $this->dispatch_ajax(
			'pdan_admin_notes_add',
			array(
				'nonce' => 'invalid-nonce',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid nonce', $response['data']['message'] );
	}

	public function test_ajax_save_order_updates_meta_in_submitted_order() {
		$note_one = $this->create_note();
		$note_two = $this->create_note();
		$note_three = $this->create_note();

		$response = $this->dispatch_ajax(
			'pdan_admin_notes_save_order',
			array(
				'nonce' => wp_create_nonce( 'pdan_admin_notes_nonce' ),
				'order' => wp_json_encode( array( $note_three, $note_one, $note_two ) ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( '1', get_post_meta( $note_three, 'pdan_order_meta', true ) );
		$this->assertSame( '2', get_post_meta( $note_one, 'pdan_order_meta', true ) );
		$this->assertSame( '3', get_post_meta( $note_two, 'pdan_order_meta', true ) );
	}

	public function test_ajax_save_visibility_rejects_invalid_value_and_keeps_existing_meta() {
		$note_id = $this->create_note();
		update_post_meta( $note_id, 'pdan_visibility_meta', 'only_me' );

		$response = $this->dispatch_ajax(
			'pdan_admin_notes_save_visibility',
			array(
				'nonce'      => wp_create_nonce( 'pdan_admin_notes_nonce' ),
				'note_id'    => $note_id,
				'visibility' => 'invalid_value',
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'only_me', get_post_meta( $note_id, 'pdan_visibility_meta', true ) );
	}

	public function test_ajax_toggle_minimize_adds_and_removes_note_for_current_user() {
		$note_id = $this->create_note();

		$add_response = $this->dispatch_ajax(
			'pdan_admin_notes_toggle_minimize',
			array(
				'nonce'   => wp_create_nonce( 'pdan_admin_notes_nonce' ),
				'note_id' => $note_id,
				'state'   => 'true',
			)
		);

		$this->assertTrue( $add_response['success'] );
		$this->assertContains( $note_id, get_user_meta( $this->admin_id, 'pdan_minimized', true ) );

		$remove_response = $this->dispatch_ajax(
			'pdan_admin_notes_toggle_minimize',
			array(
				'nonce'   => wp_create_nonce( 'pdan_admin_notes_nonce' ),
				'note_id' => $note_id,
				'state'   => 'false',
			)
		);

		$this->assertTrue( $remove_response['success'] );
		$this->assertNotContains( $note_id, get_user_meta( $this->admin_id, 'pdan_minimized', true ) );
	}

	/**
	 * Trigger an AJAX action and return decoded JSON response.
	 *
	 * @param string $action Ajax action name.
	 * @param array  $payload Request payload.
	 * @return array
	 */
	private function dispatch_ajax( $action, $payload ) {
		$_POST = $payload;

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected for successful JSON responses.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected for JSON errors in ajax context.
		}

		$decoded = json_decode( $this->_last_response, true );

		$this->assertIsArray( $decoded, 'Expected JSON response from AJAX endpoint.' );

		return $decoded;
	}

	/**
	 * Create a note post for AJAX tests.
	 *
	 * @return int
	 */
	private function create_note() {
		return self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $this->admin_id,
			)
		);
	}
}
