<?php
/**
 * This class adds the editor to enabled archive pages
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * Class init
 */
final class Nine3_Editor_Archive extends Nine3_Editor_Base {
	/**
	 * The Nine3_Editor_Helpers object.
	 *
	 * @var array $helpers
	 */
	private $helpers;

	/**
	 * Array of all enabled archive pages
	 *
	 * @var array $archive_pages
	 */
	private $archive_pages = [];

	/**
	 * Contructor
	 *
	 * @param array $archive_pages the arrray of archive settings pages which have been enabled in settings.
	 */
	public function __construct( $archive_pages ) {
		// Instantiate helpers.
		$this->helpers = new Nine3_Editor_Helpers();

		$this->archive_pages = $archive_pages;

		// Add button markup to selected pages.
		add_action( 'all_admin_notices', [ $this, 'render_button_markup_page' ] );
	}

	/**
	 * 'all_admin_notices' action hook callback
	 * This functions checks the current screen and dependant on settings renders our buttons
	 */
	public function render_button_markup_page() {
		$screen = \get_current_screen();

		// Is edit page?
		if ( ! $screen || $screen->base !== 'edit' ) {
			return;
		}

		foreach ( $this->archive_pages as $page ) {
			// Only fire on enabled archive pages.
			if ( $page === $screen->post_type ) {
				// Deprecation check. Old plugin version archives were affixed with '-settings'.
				$target_id    = $this->helpers->get_archive_target_id( $page );
				$data['name'] = get_post_type_object( $page )->labels->name . ' ' . __( 'Page', 'nine3editor' );

				ob_start();
				?>
				<div class="wrap nine3-editor">
					<h1><?php esc_html_e( '93digital Editor', 'nine3editor' ); ?></h1>
					<?php $this->render_button_markup( $page, $target_id, 'archive', $data ); ?>
				</div>
				<?php
				echo wp_kses_post( ob_get_clean() );
			}
		}
	}
}