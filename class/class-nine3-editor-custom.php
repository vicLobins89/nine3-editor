<?php
/**
 * This class adds the editor to enabled custom page templates
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * Class init
 */
final class Nine3_Editor_Custom extends Nine3_Editor_Base {
	/**
	 * Array of all enabled custom pages
	 *
	 * @var array $custom_pages
	 */
	private $custom_pages = [];

	/**
	 * Contructor
	 *
	 * @param array $custom_pages the arrray of custom pages which have been enabled in settings.
	 */
	public function __construct( $custom_pages ) {
		$this->custom_pages = $custom_pages;

		// Add button markup to selected pages.
		add_action( 'all_admin_notices', [ $this, 'render_button_markup_page' ] );
	}

	/**
	 * 'all_admin_notices' action hook callback
	 * This functions checks the current screen and dependant on settings renders our buttons
	 */
	public function render_button_markup_page() {
		$screen = \get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Since custom pages don't have a home in WordPress lets render the buttons right on our plugin settings page.
		if ( strpos( $screen->base, 'nine3-editor-settings' ) !== false ) {
			ob_start();
			?>
			<div class="wrap nine3-editor">
				<h1><?php esc_html_e( '93digital Custom Page Templates', 'nine3editor' ); ?></h1>
				<?php
				foreach ( $this->custom_pages as $page ) :
					$target_id    = intval( get_option( $page . '_nine3_editor_target_id' ) );
					$data['name'] = ucfirst( str_replace( 'page_', '', $page ) ) . ' ' . __( 'Page', 'nine3editor' );

					?>
					<h2><?php echo esc_html( $data['name'] ); ?></h2>
					<?php $this->render_button_markup( $page, $target_id, 'custom', $data ); ?>
					<?php
				endforeach;
				?>
			</div>
			<br>
			<hr>
			<?php
			echo wp_kses_post( ob_get_clean() );
		}
	}
}