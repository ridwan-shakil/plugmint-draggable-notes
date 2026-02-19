<?php
/**
 * Tests for Admin_Notes_CPT.
 *
 * @package plugmint-draggable-notes
 */

use PlugmintDraggableNotes\Admin\Admin_Notes_CPT;

require_once dirname( __DIR__ ) . '/includes/class-admin-notes-cpt.php';

class Test_Admin_Notes_CPT extends WP_UnitTestCase {

	/**
	 * @var Admin_Notes_CPT
	 */
	private $cpt;

	public function set_up() {
		parent::set_up();
		$this->cpt = new Admin_Notes_CPT();
		$this->cpt->register_cpt();
		delete_transient( 'pdan_notes_max_order' );
	}

	public function tear_down() {
		delete_transient( 'pdan_notes_max_order' );
		parent::tear_down();
	}

	public function test_register_adds_expected_hooks() {
		$this->cpt->register();

		$this->assertSame( 10, has_action( 'init', array( $this->cpt, 'register_cpt' ) ) );
		$this->assertSame(
			10,
			has_action( 'save_post_pdan_admin_note', array( $this->cpt, 'ensure_order_meta_for_new_notes' ) )
		);
	}

	public function test_register_cpt_registers_pdan_admin_note_post_type() {
		$this->cpt->register_cpt();

		$post_type = get_post_type_object( 'pdan_admin_note' );

		$this->assertNotNull( $post_type );
		$this->assertFalse( $post_type->public );
		$this->assertFalse( $post_type->show_ui );
		$this->assertFalse( $post_type->show_in_rest );
		$this->assertTrue( post_type_supports( 'pdan_admin_note', 'title' ) );
		$this->assertTrue( post_type_supports( 'pdan_admin_note', 'author' ) );
	}

	public function test_ensure_order_meta_uses_transient_when_available() {
		$user_id = self::factory()->user->create();

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);

		set_transient( 'pdan_notes_max_order', 41, MINUTE_IN_SECONDS );

		$this->cpt->ensure_order_meta_for_new_notes( $post_id );

		$this->assertSame( '42', get_post_meta( $post_id, 'pdan_order_meta', true ) );
		$this->assertSame( 42, (int) get_transient( 'pdan_notes_max_order' ) );
	}

	public function test_ensure_order_meta_calculates_from_existing_non_trashed_notes() {
		$user_id = self::factory()->user->create();

		$first = self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);
		update_post_meta( $first, 'pdan_order_meta', 2 );

		$second = self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);
		update_post_meta( $second, 'pdan_order_meta', 7 );

		$trashed = self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);
		update_post_meta( $trashed, 'pdan_order_meta', 999 );
		wp_trash_post( $trashed );

		$new_note = self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);

		$this->cpt->ensure_order_meta_for_new_notes( $new_note );

		$this->assertSame( '8', get_post_meta( $new_note, 'pdan_order_meta', true ) );
		$this->assertSame( 8, (int) get_transient( 'pdan_notes_max_order' ) );
	}

	public function test_ensure_order_meta_does_not_overwrite_existing_order_meta() {
		// 1) Prepare Date
		$user_id = self::factory()->user->create();

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $user_id,
			)
		);
		update_post_meta( $post_id, 'pdan_order_meta', 15 );

		set_transient( 'pdan_notes_max_order', 100, MINUTE_IN_SECONDS );

		// 2) Call the function
		$this->cpt->ensure_order_meta_for_new_notes( $post_id );

		// 3) Assertions
		$this->assertSame( '15', get_post_meta( $post_id, 'pdan_order_meta', true ) );
		$this->assertSame( 100, (int) get_transient( 'pdan_notes_max_order' ) );
	}
}
