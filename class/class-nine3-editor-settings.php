<?php
/**
 * This class registers and displays the settings page for the plugin
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * Class init
 * Currently this only sets up some checkbox fields but this is extendable for other field types
 *
 * - Register menu settings page
 * - Register settings
 * - Render page
 */
final class Nine3_Editor_Settings {
	/**
	 * The Nine3_Editor_Helpers object.
	 *
	 * @var string
	 */
	private $helpers;

	/**
	 * This will be part of the the page URL: /admin.php?page=[$page_slug]
	 *
	 * @var string $page_slug
	 */
	private $page_slug = 'nine3-editor-settings';

	/**
	 * Name of the option where all the data is saved as a serialised array
	 * This is public because we need access to it in other plugin classes
	 *
	 * @var string $option_name
	 */
	public $option_name = 'nine3_editor';

	/**
	 * Group name for editor settings
	 *
	 * @var string $option_group
	 */
	private $option_group = 'nine3_editor_group';

	/**
	 * Defines a section area for settings group
	 *
	 * @var string $option_section
	 */
	private $option_section = 'nine3_editor_group_section';

	/**
	 * Energise!
	 */
	public function __construct() {
		// Instantiate helpers.
		$this->helpers = new Nine3_Editor_Helpers();

		// Menu hook.
		add_action( 'admin_menu', [ $this, 'register_page_menu' ] );

		// Settings sections/fields.
		add_action( 'admin_init', [ $this, 'register_editor_settings' ] );

		// Add links in admin bar menu in backend when editing the nine3_editor page.
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_links_backend_editor' ], 100 );

		// Add links in admin bar menu in front end when viewing the nine3_editor page.
		add_action( 'admin_bar_menu', [ $this, 'admin_bar_links_frontend' ], 100 );
	}

	/**
	 * Register settings page
	 *
	 * @hook admin_menu
	 */
	public function register_page_menu() {
		add_menu_page(
			__( '93digital Editor', 'nine3editor' ),
			__( '93digital Editor', 'nine3editor' ),
			'manage_options',
			$this->page_slug,
			[ $this, 'render_page_options' ],
			'dashicons-edit-page',
			150
		);
	}

	/**
	 * Render settings page markup
	 */
	public function render_page_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html_e( 'You do not have sufficient permissions to access this page.', 'nine3editor' ) );
		}

		ob_start();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php" enctype="multipart/form-data">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( $this->page_slug );
				submit_button();
				?>
			</form>

			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=nine3_editor' ) ); ?>" class="button"><?php esc_html_e( 'View All Editor Posts', 'nine3editor' ); ?></a>
		</div>
		<?php
		ob_flush();
	}

	/**
	 * Register settings fields
	 *
	 * @hook admin_init
	 */
	public function register_editor_settings() {
		// Create an array to loop through and register each setting field.
		$taxonomies = get_taxonomies( [ 'public' => true ] );
		$tax_array  = [];
		foreach ( $taxonomies as $tax ) {
			$tax_array[ $tax ] = get_taxonomy_labels( get_taxonomy( $tax ) )->name;
		}

		$editor_fields = [
			[
				'name'  => 'editor_tax',
				'title' => __( 'Enable Taxonomies', 'nine3editor' ),
				'type'  => 'checkbox',
				'value' => $tax_array,
			],
		];

		// Add custom page options (i.e. 404 and search pages).
		$custom_page_fields = [
			'name'  => 'editor_custom_pages',
			'title' => __( 'Enable Custom Page Templates', 'nine3editor' ),
			'type'  => 'checkbox',
			'value' => [
				'page_search' => __( 'Search Page', 'nine3editor' ),
				'page_404'    => __( '404 Page', 'nine3editor' ),
			],
		];
		$editor_fields[]    = $custom_page_fields;

		// Get CPTs with has_archive param.
		$cpts = [ 'post' => 'post' ];
		$cpts = array_merge( $cpts, get_post_types( [ 'has_archive' => true ] ) );

		foreach ( $cpts as &$cpt ) {
			$cpt = get_post_type_object( $cpt )->labels->name;
		}

		$archive_fields  = [
			'name'  => 'editor_cpt_archives',
			'title' => __( 'Enable CPT Archives', 'nine3editor' ),
			'type'  => 'checkbox',
			'value' => $cpts,
		];
		$editor_fields[] = $archive_fields;

		register_setting(
			$this->option_group,
			$this->option_name
		);

		add_settings_section(
			$this->option_section,
			__( 'Plugin Settings', 'nine3editor' ),
			null,
			$this->page_slug
		);

		foreach ( $editor_fields as $field ) {
			add_settings_field(
				$field['name'],
				$field['title'],
				[ $this, 'render_field' ],
				$this->page_slug,
				$this->option_section,
				[
					'option' => $this->option_name,
					'name'   => $field['name'],
					'label'  => isset( $field['label'] ) ? $field['label'] : false,
					'title'  => $field['title'],
					'type'   => $field['type'],
					'value'  => isset( $field['value'] ) ? $field['value'] : false,
				],
			);
		}
	}

	/**
	 * Render the HTML for settings
	 * Callback for: add_settings_field
	 *
	 * @param array $args the values passed from callback.
	 */
	public function render_field( array $args ) {
		$options     = get_option( $args['option'] );
		$option_name = esc_html( $args['option'] ) . '[' . esc_html( $args['name'] ) . ']';

		ob_start();

		if ( ! empty( $args['label'] ) ) :
			?>
			<label for="<?php echo esc_html( $option_name ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
			<br>
			<?php
		endif;

		switch ( $args['type'] ) :
			case 'checkbox':
				if ( isset( $args['value'] ) ) :
					foreach ( $args['value'] as $value => $label ) :
						?>
						<label for="<?php echo esc_html( $option_name ) . '_' . esc_html( $value ); ?>">
							<input
								type="checkbox"
								name="<?php echo esc_html( $option_name ) . '[]'; ?>"
								value="<?php echo esc_html( $value ); ?>"
								id="<?php echo esc_html( $option_name ) . '_' . esc_html( $value ); ?>"
								<?php echo ( isset( $options[ $args['name'] ] ) && in_array( $value, $options[ $args['name'] ], true ) ) ? 'checked' : ''; ?>>
							<?php echo esc_html( $label ); ?>
						</label>
						<br>
						<?php
					endforeach;
				endif;
				break;
		endswitch;

		echo ob_get_clean(); // phpcs:ignore
	}

	/**
	 * 'admin_bar_menu' action hook callback
	 * Adds some contextual navigation links in backend
	 *
	 * @param WP_Admin_Bar $admin_bar object for the admin bar.
	 */
	public function admin_bar_links_backend_editor( $admin_bar ) {
		// Return if not admin.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Return if not nine3_editor.
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . '/wp-admin/includes/screen.php';
		}
		$screen = \get_current_screen();
		if ( ! $screen || $screen->id !== 'nine3_editor' ) {
			return;
		}

		// We need post ID, so return if not set.
		if ( ! isset( $_GET['post'] ) ) {
			return;
		}

		$post_id     = sanitize_title( wp_unslash( $_GET['post'] ) );
		$source_id   = get_post_meta( $post_id, '_nine3_editor_source_id', true );
		$source_type = get_post_meta( $post_id, '_nine3_editor_source_type', true );

		// Setup admin menu array.
		$view_page_args['id'] = 'nine3_editor-view';
		$edit_page_args['id'] = 'nine3_editor-edit';

		switch ( $source_type ) {
			case 'term':
				$term_link = get_term_link( (int) $source_id );
				$view_page_args['title'] = __( 'View Term Page', 'nine3editor' );
				$view_page_args['href']  = ! is_wp_error( $term_link ) ? $term_link : NULL;
				$edit_page_args['title'] = __( 'Edit Term Page', 'nine3editor' );
				$edit_page_args['href']  = get_edit_term_link( (int) $source_id );
				break;
			case 'archive':
				$view_page_args['title'] = __( 'View Archive Page', 'nine3editor' );
				$view_page_args['href']  = get_post_type_archive_link( $source_id );
				break;
			case 'custom':
				$custom_link = get_search_link( '93digital' );
				if ( strpos( $source_id, '404' ) !== false ) {
					$custom_link = home_url( '/404' );
				}
				$view_page_args['title'] = __( 'View Page', 'nine3editor' );
				$view_page_args['href']  = $custom_link;
				break;
		}

		// Add menu item.
		$admin_bar->add_menu( $view_page_args );
		$admin_bar->add_menu( $edit_page_args );
	}

	/**
	 * 'admin_bar_menu' action hook callback
	 * Adds some contextual navigation links in frontend
	 *
	 * @param WP_Admin_Bar $admin_bar object for the admin bar.
	 */
	public function admin_bar_links_frontend( $admin_bar ) {
		// Return if not admin.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_object = get_queried_object();

		if ( $current_object ) {
			$target_id = $this->helpers->get_object_target_id( $current_object );
		} elseif ( is_search() ) {
			$target_id = $this->helpers->get_custom_target_id( 'page_search' );
		} elseif ( is_404() ) {
			$target_id = $this->helpers->get_custom_target_id( 'page_404' );
		}

		// Once we have the target ID we get edit page urls and add to admin bar.
		if ( isset( $target_id ) && $target_id > 0 ) {
			// Setup admin menu array.
			$edit_page_args = [
				'id'    => 'nine3_editor-edit-fe',
				'title' => __( 'Edit Editor Page', 'nine3editor' ),
				'href'  => get_edit_post_link( $target_id ),
			];

			// Add menu item.
			$admin_bar->add_menu( $edit_page_args );
		}
	}
}
