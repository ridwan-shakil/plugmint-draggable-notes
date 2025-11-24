<?php
/**
 * Admin page renderer and helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Notes_Admin {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	/**
	 * Add Admin Notes top-level menu.
	 */
	public function add_menu_page() {
		$capability = apply_filters( 'admin_notes_capability', 'edit_posts' );

		add_menu_page(
			__( 'Admin Notes', 'admin-notes' ),
			__( 'Admin Notes', 'admin-notes' ),
			$capability,
			'admin-notes',
			array( $this, 'render_page' ),
			'dashicons-edit',
			3
		);
	}

	/**
	 * Render admin page (notes board).
	 */
	public function render_page() {
		// Capability check
		$capability = apply_filters( 'admin_notes_capability', 'edit_posts' );

		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html_e( 'You do not have permission to view this page.', 'admin-notes' ) );
		}

		// Get notes ordered by meta _admin_notes_order, pinned first
		$notes = $this->get_notes_for_display();

		?>
		<div class="wrap admin-notes-wrap">
			<div class="notes-head-section">
				<div class="left">
					<h1><?php esc_html_e( 'Admin Notes', 'admin-notes' ); ?></h1>
					<div>
						<?php
						esc_html_e(
							'Organize your tasks with draggable sticky notes',
							'admin-notes'
						)
						?>

						<!-- Tooltip user guide  -->
						<div class="tooltip"> &#9432; 
							<div class="tooltiptext">
									
								<strong><?php esc_html_e( 'Quick user Guide', 'admin-notes' ); ?></strong><br/>
								
								<ul>
									<li>
										<strong><?php esc_html_e( 'Create a note:  ', 'admin-notes' ); ?></strong>
										<?php esc_html_e( ' Click “Add New Note”. A new editable note will appear instantly.', 'admin-notes' ); ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Checklists:  ', 'admin-notes' ); ?></strong>
										<?php esc_html_e( ' Add tasks, check them off, delete them, and reorder them as needed.', 'admin-notes' ); ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Drag & reorder:  ', 'admin-notes' ); ?></strong>
										<?php esc_html_e( ' Move notes around freely. The plugin saves their position automatically.', 'admin-notes' ); ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Colors:  ', 'admin-notes' ); ?></strong>
										<?php esc_html_e( ' Use preset colors or the color picker to categorize notes visually.', 'admin-notes' ); ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Visibility:  ', 'admin-notes' ); ?></strong>
										<?php esc_html_e( ' Choose who can see each note (Only Me, All Admins, Editors & above).', 'admin-notes' ); ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Minimize:  ', 'admin-notes' ); ?></strong>
										<?php esc_html_e( ' Click the arrow icon to collapse or expand notes. This state is saved per user.', 'admin-notes' ); ?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Delete:  ', 'admin-notes' ); ?></strong>
										<?php esc_html_e( ' Remove a note using the trash icon.', 'admin-notes' ); ?>
									</li>
								</ul>

								<p>
									<em><?php esc_html_e( 'Tips:  ', 'admin-notes' ); ?></em>
									<?php esc_html_e( ' Keep notes short for better clarity. Use colors to mark urgency or categories. (e.g., yellow for urgent, green for completed, blue for info etc.)', 'admin-notes' ); ?>
								</p>
								
							</div>
						</div>	
					</div>              
				</div>
				<div class="right">
					<p class="admin-notes-actions">
						<button id="admin-notes-add" class="button button-primary"><?php esc_html_e( '+ Add New Note', 'admin-notes' ); ?></button>
					</p>
				</div>
			</div>
			<!-- All Notes  -->
			<div id="admin-notes-board" class="admin-notes-board" aria-live="polite">
				<?php
				if ( empty( $notes ) ) {
					echo '<p class="admin-notes-empty">' . esc_html__( 'No notes yet. Click "Add New Note" to create one.', 'admin-notes' ) . '</p>';
				} else {
					foreach ( $notes as $note ) {
						// Rendering full HTML template; all variables inside are escaped.
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo ( $this->render_note_card( $note ) );
					}
				}
				?>
			</div>
			<!-- placeholder for toasts --> 
			<div id="admin-notes-toast" aria-hidden="true"></div> 
			
		</div>
		<?php
	}

	/**
	 * Retrieve notes for display and filter them by visibility.
	 *
	 * @return WP_Post[]
	 */
	public function get_notes_for_display() {
		$args = array(
			'post_type'      => 'admin_note',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_admin_notes_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
		);

		$query = new WP_Query( $args );
		$posts = $query->posts;

		if ( empty( $posts ) ) {
			return array();
		}

		//Filter notes by visibility
		$filtered        = array();
		$current_user_id = get_current_user_id();

		foreach ( $posts as $post ) {
			if ( $this->current_user_can_view_note( $current_user_id, $post ) ) {
				$filtered[] = $post;
			}
		}

		return $filtered;
	}

	/**
	 * Determine whether a user can view a given note.
	 *
	 * @param int     $user_id
	 * @param WP_Post $post
	 * @return bool
	 */
	protected function current_user_can_view_note( $user_id, $post ) {
		$visibility = get_post_meta( $post->ID, '_admin_note_visibility', true );

		// Default to only_me if not set
		if ( '' === $visibility ) {
			$visibility = 'only_me';
		}

		// Author always can see their own note
		if ( intval( $post->post_author ) === intval( $user_id ) ) {
			return true;
		}

		// Only me: non-authors cannot see
		if ( 'only_me' === $visibility ) {
			return false;
		}

		// All admins: check manage_options or role administrator
		if ( 'all_admins' === $visibility ) {
			// check capability
			if ( user_can( $user_id, 'manage_options' ) ) {
				return true;
			}
			// fallback: check role 'administrator'
			// $user = get_userdata( $user_id );
			// if ( $user && is_array( $user->roles ) && in_array( 'administrator', $user->roles, true ) ) {
			//  return true;
			// }
			return false;
		}

		// Editors & above: check edit_others_posts capability
		if ( 'editors_and_above' === $visibility ) {
			if ( user_can( $user_id, 'edit_others_posts' ) ) {
				return true;
			}
			return false;
		}

		// Default deny
		return false;
	}

	/**
	 * Render single note card markup (server-side helper).
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function render_note_card( $post ) {

		$post_id = intval( $post->ID );
		$title   = get_the_title( $post_id );

		$meta  = get_post_meta( $post_id );
		$color = isset( $meta['_admin_notes_color'][0] ) ? sanitize_hex_color( $meta['_admin_notes_color'][0] ) : '#fff9c4';

		$check_raw = isset( $meta['_admin_notes_checklist'][0] ) ? wp_unslash( $meta['_admin_notes_checklist'][0] ) : '[]';
		$check     = json_decode( $check_raw );

		if ( ! is_array( $check ) ) {
			$check = array();
		}

		// Visibility
		$visibility = get_post_meta( $post_id, '_admin_note_visibility', true );
		$visibility = $visibility ? sanitize_key( $visibility ) : 'only_me';

		// Collapsed state
		$user_min  = get_user_meta( get_current_user_id(), 'admin_notes_minimized', true );
		$collapsed = ( is_array( $user_min ) && in_array( $post_id, $user_min, true ) );

		ob_start();
		?>
	<div class="admin-note-card"
		data-note-id="<?php echo esc_attr( $post_id ); ?>"
		style="background: color-mix(in srgb, <?php echo esc_attr( $color ); ?> 55%, white 45%);">

		<header class="admin-note-header"
				role="heading"
				aria-level="3"
				style="background:<?php echo esc_attr( $color ); ?>; border-top: 4px solid color-mix(in srgb, <?php echo esc_attr( $color ); ?> 80%, black 20%); ">

			<span class="admin-note-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'admin-notes' ); ?>">::</span>

			<input class="admin-note-title"
					value="<?php echo esc_attr( $title ); ?>"
					aria-label="<?php esc_attr_e( 'Note title', 'admin-notes' ); ?>" />

			<div class="admin-note-actions">
				<button class="admin-note-minimize"
						title="<?php esc_attr_e( 'Minimize', 'admin-notes' ); ?>">
					<?php echo $collapsed ? '&#9654;' : '&#9660;'; ?>
				</button>

				<button class="admin-note-delete"
						title="<?php esc_attr_e( 'Delete', 'admin-notes' ); ?>">🗑</button>
			</div>
		</header>

		<div class="admin-note-body" <?php echo $collapsed ? 'style="display:none;"' : ''; ?>>
			<ul class="admin-note-checklist" data-note-id="<?php echo esc_attr( $post_id ); ?>">
				<?php foreach ( $check as $item ) : ?>
					<?php
					$item_id  = isset( $item->id ) ? sanitize_text_field( $item->id ) : '';
					$item_txt = isset( $item->text ) ? sanitize_text_field( $item->text ) : '';
					$done     = ( ! empty( $item->completed ) ) ? 'checked' : '';
					?>
					<li class="admin-note-check-item" data-item-id="<?php echo esc_attr( $item_id ); ?>">
						<span class="check-drag">⋮</span>

						<label>
							<input type="checkbox"
									class="check-toggle"
									<?php echo $done; ?> />

							<span class="check-text" contenteditable="true" <?php echo $done ? 'style="text-decoration:line-through;"' : ''; ?>><?php echo esc_html( $item_txt ); ?></span>

						</label>

						<button class="check-remove"
								aria-label="<?php esc_attr_e( 'Remove task', 'admin-notes' ); ?>">✕
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="admin-note-add">
				<input type="text"
						class="admin-note-add-input"
						placeholder="<?php esc_attr_e( '+ Add a task and press Enter', 'admin-notes' ); ?>" />
			</div>

			<div class="admin-note-footer">
				<div class="admin-note-colors" data-note-id="<?php echo esc_attr( $post_id ); ?>">
					<?php
					$presets = array(
						'#bae6fd',
						'#d9f99d',
						'#bbf7d0',
						'#c7d2fe',
						'#e9d5ff',
						'#fbcfe8',
						'#ffd9d9',
						'#fed7aa',
						'#fef08a',
					);

					foreach ( $presets as $preset ) :
						$preset_color = sanitize_hex_color( $preset );
						?>
						<button class="admin-note-color-swatch"
								data-color="<?php echo esc_attr( $preset_color ); ?>"
								title="<?php echo esc_attr( $preset_color ); ?>"
								style="background:<?php echo esc_attr( $preset_color ); ?>"></button>
					<?php endforeach; ?>

					<input type="color" class="admin-note-color-picker" />
				</div>

				<div class="admin-note-visibility">
					<select class="admin-note-visibility-select" data-note-id="<?php echo esc_attr( $post_id ); ?>">
						<option value="only_me" 
							<?php selected( $visibility, 'only_me' ); ?> style="background: <?php echo esc_attr( $color ); ?> ;" >
							<?php esc_html_e( '🔒 Only Me', 'admin-notes' ); ?>
						</option>

						<option value="all_admins" 
							<?php selected( $visibility, 'all_admins' ); ?> style="background: <?php echo esc_attr( $color ); ?> ;" >
							<?php esc_html_e( ' 👁️ All Admins', 'admin-notes' ); ?>
						</option>

						<option value="editors_and_above" 
							<?php selected( $visibility, 'editors_and_above' ); ?> 
							style="background: <?php echo esc_attr( $color ); ?> " >
							<?php esc_html_e( '👨‍👨‍👦 Editors & above', 'admin-notes' ); ?>
						</option>
					</select>
				</div>
				<div class="total-items">
					<p><?php echo( count( $check ) . ' Tasks' ); ?></p>
				</div>

			</div>
		</div>
	</div>
		<?php

		return ob_get_clean();
	}
}
