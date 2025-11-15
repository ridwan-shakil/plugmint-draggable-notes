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
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Add Admin Notes top-level menu.
	 */
	public function add_menu_page() {
		// Capability: edit_posts allows editors too. Change to manage_options to restrict to admins.
		$capability = apply_filters( 'admin_notes_capability', 'edit_posts' );

		add_menu_page(
			__( 'Admin Notes', 'admin-notes' ),
			__( 'Admin Notes', 'admin-notes' ),
			$capability,
			'admin-notes',
			array( $this, 'render_page' ),
			'dashicons-edit'
		);
	}

	/**
	 * Ensure assets loaded only on our page as fallback.
	 *
	 * @param string $hook
	 */
	public function maybe_enqueue_assets( $hook ) {
		// If page is the admin notes page, assets will already be enqueued by Admin_Notes_Assets, but this ensures correct loading.
		if ( isset( $_GET['page'] ) && 'admin-notes' === $_GET['page'] ) {
			// do nothing; assets loader enqueues them.
		}
	}

	/**
	 * Render admin page (notes board).
	 */
	public function render_page() {
		// Capability check
		if ( ! current_user_can( apply_filters( 'admin_notes_capability', 'edit_posts' ) ) ) {
			wp_die( __( 'You do not have permission to view this page.', 'admin-notes' ) );
		}

		// Get notes ordered by meta _admin_notes_order, pinned first
		$notes = $this->get_notes_for_display();

		?>
		<div class="wrap admin-notes-wrap">
			<h1><?php esc_html_e( 'Admin Notes', 'admin-notes' ); ?></h1>

			<p class="admin-notes-actions">
				<button id="admin-notes-add" class="button button-primary"><?php esc_html_e( '+ Add New Note', 'admin-notes' ); ?></button>
			</p>

			<div id="admin-notes-board" class="admin-notes-board" aria-live="polite">
				<?php
				if ( empty( $notes ) ) {
					echo '<p class="admin-notes-empty">' . esc_html__( 'No notes yet. Click "Add New Note" to create one.', 'admin-notes' ) . '</p>';
				} else {
					foreach ( $notes as $note ) {
						echo $this->render_note_card( $note );
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
	 * Retrieve notes for display.
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
		return $query->posts;
	}

	/**
	 * Render single note card markup (server-side helper).
	 *
	 * @param WP_Post $post
	 * @return string
	 */
	public function render_note_card( $post ) {
		$post_id = intval( $post->ID );
		$title   = get_the_title( $post_id );
		$meta    = get_post_meta( $post_id );
		$color   = isset( $meta['_admin_notes_color'][0] ) ? esc_attr( $meta['_admin_notes_color'][0] ) : '#fff9c4';
		$check   = isset( $meta['_admin_notes_checklist'][0] ) ? wp_unslash( $meta['_admin_notes_checklist'][0] ) : '[]';
		$check   = wp_json_decode( $check );
		if ( ! is_array( $check ) ) {
			$check = array();
		}

		// collapsed state is per-user (user meta)
		$user_min  = get_user_meta( get_current_user_id(), 'admin_notes_minimized', true );
		$collapsed = ( is_array( $user_min ) && in_array( $post_id, $user_min, true ) ) ? true : false;

		ob_start();
		?>
		<div class="admin-note-card" data-note-id="<?php echo esc_attr( $post_id ); ?>" style="background:<?php echo esc_attr( $color ); ?>;">
			<header class="admin-note-header" role="heading" aria-level="3">
				<span class="admin-note-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'admin-notes' ); ?>">☰</span>
				<input class="admin-note-title" value="<?php echo esc_attr( $title ); ?>" aria-label="<?php esc_attr_e( 'Note title', 'admin-notes' ); ?>" />
				<div class="admin-note-actions">
					<button class="admin-note-minimize" title="<?php esc_attr_e( 'Minimize', 'admin-notes' ); ?>"><?php echo $collapsed ? '&#9654;' : '&#9660;'; ?></button>
					<button class="admin-note-delete" title="<?php esc_attr_e( 'Delete', 'admin-notes' ); ?>">🗑</button>
				</div>
			</header>

			<div class="admin-note-body" <?php echo $collapsed ? 'style="display:none;"' : ''; ?>>
				<ul class="admin-note-checklist" data-note-id="<?php echo esc_attr( $post_id ); ?>">
					<?php
					if ( ! empty( $check ) ) {
						foreach ( $check as $item ) {
							$item_id  = isset( $item->id ) ? esc_attr( $item->id ) : '';
							$item_txt = isset( $item->text ) ? esc_html( $item->text ) : '';
							$done     = ! empty( $item->completed ) ? 'checked' : '';
							?>
							<li class="admin-note-check-item" data-item-id="<?php echo $item_id; ?>">
								<span class="check-drag">⋮</span>
								<label>
									<input type="checkbox" class="check-toggle" <?php echo $done; ?> />
									<span class="check-text"><?php echo $item_txt; ?></span>
								</label>
								<button class="check-remove" aria-label="<?php esc_attr_e( 'Remove task', 'admin-notes' ); ?>">✕</button>
							</li>
							<?php
						}
					}
					?>
				</ul>

				<div class="admin-note-add">
					<input type="text" class="admin-note-add-input" placeholder="<?php esc_attr_e( 'Add a task and press Enter', 'admin-notes' ); ?>" />
				</div>

				<div class="admin-note-footer">
					<div class="admin-note-colors" data-note-id="<?php echo esc_attr( $post_id ); ?>">
						<?php
						$presets = array(
							'#FFF9C4',
							'#FFE0B2',
							'#FFE6EE',
							'#E1F5FE',
							'#E8F5E9',
							'#F3E5F5',
							'#FFF3E0',
							'#FCE4EC',
							'#EDE7F6',
							'#F9FBE7',
						);
						foreach ( $presets as $preset ) {
							printf(
								'<button class="admin-note-color-swatch" data-color="%1$s" title="%1$s" style="background:%1$s"></button>',
								esc_attr( $preset )
							);
						}
						// color picker button
						echo '<input type="color" class="admin-note-color-picker" />';
						?>
					</div>

					<div class="admin-note-visibility">
						<select class="admin-note-visibility-select" data-note-id="<?php echo esc_attr( $post_id ); ?>">
							<option value="only_me"><?php esc_html_e( 'Only Me', 'admin-notes' ); ?></option>
							<option value="all_admins"><?php esc_html_e( 'All Admins', 'admin-notes' ); ?></option>
							<option value="editors_and_above"><?php esc_html_e( 'Editors & above', 'admin-notes' ); ?></option>
						</select>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
