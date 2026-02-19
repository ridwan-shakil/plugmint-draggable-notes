<?php
/**
 * Tests for Admin_Notes_Admin.
 *
 * @package plugmint-draggable-notes
 */

use PlugmintDraggableNotes\Admin\Admin_Notes_Admin;
use PlugmintDraggableNotes\Admin\Admin_Notes_CPT;

require_once dirname( __DIR__ ) . '/includes/class-admin-notes-admin.php';
require_once dirname( __DIR__ ) . '/includes/class-admin-notes-cpt.php';

class Test_Admin_Notes_Admin extends WP_UnitTestCase {

	/**
	 * @var Admin_Notes_Admin
	 */
	private $admin;

	/**
	 * @var Admin_Notes_CPT
	 */
	private $cpt;

	public function set_up() {
		parent::set_up();

		$this->admin = new Admin_Notes_Admin();
		$this->cpt   = new Admin_Notes_CPT();
		$this->cpt->register_cpt();
	}

	public function tear_down() {
		remove_action( 'admin_menu', array( $this->admin, 'add_menu_page' ) );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_init_registers_admin_menu_hook() {
		$this->admin->init();

		$this->assertSame( 10, has_action( 'admin_menu', array( $this->admin, 'add_menu_page' ) ) );
	}

	public function test_get_notes_for_display_returns_empty_array_when_no_notes_exist() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$this->assertSame( array(), $this->admin->get_notes_for_display() );
	}

	public function test_get_notes_for_display_filters_visibility_for_editor_and_preserves_order() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$note_hidden = $this->create_note( $author_id, 'all_admins', 1 );
		$note_visible_for_role = $this->create_note( $author_id, 'editors_and_above', 2 );
		$note_visible_as_owner = $this->create_note( $editor_id, 'only_me', 3 );

		$notes = $this->admin->get_notes_for_display();
		$ids   = wp_list_pluck( $notes, 'ID' );

		$this->assertSame( array( $note_visible_for_role, $note_visible_as_owner ), $ids );
		$this->assertNotContains( $note_hidden, $ids );
	}

	public function test_get_notes_for_display_allows_admin_for_all_admins_and_denies_default_visibility_for_non_author() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$note_for_admins = $this->create_note( $author_id, 'all_admins', 1 );
		$default_only_me = $this->create_note( $author_id, '', 2 );
		delete_post_meta( $default_only_me, 'pdan_visibility_meta' );

		$notes = $this->admin->get_notes_for_display();
		$ids   = wp_list_pluck( $notes, 'ID' );

		$this->assertContains( $note_for_admins, $ids );
		$this->assertNotContains( $default_only_me, $ids );
	}

	/**
	 * Create note helper.
	 *
	 * @param int    $author_id  Post author user ID.
	 * @param string $visibility Visibility value.
	 * @param int    $order      Sort order value.
	 * @return int
	 */
	private function create_note( $author_id, $visibility, $order ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'pdan_admin_note',
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);

		if ( '' !== $visibility ) {
			update_post_meta( $post_id, 'pdan_visibility_meta', $visibility );
		}

		update_post_meta( $post_id, 'pdan_order_meta', $order );

		return $post_id;
	}
}
