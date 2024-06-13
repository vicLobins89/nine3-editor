<?php
/**
 * Base class containing some shared methods for editor child classes
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * Class init
 */
class Nine3_Editor_Base {
	/**
	 * Echo button markup
	 *
	 * @param mixed  $source_id ID of the source term/object.
	 * @param int    $target_id ID of the editor post.
	 * @param string $type of object.
	 * @param array  $data array of data to pass to JS.
	 */
	protected function render_button_markup( $source_id, $target_id, $type, $data ) {
		ob_start();
		if ( $target_id && get_post_type( $target_id ) === 'nine3_editor' ) :
			?>
			<a href="<?php echo esc_url( get_edit_post_link( $target_id ) ); ?>" class="button edit-editor"><?php esc_html_e( 'Edit Editor Page', 'nine3editor' ); ?></a>
			<button
				class="button delete-editor"
				data-source="<?php echo esc_attr( $source_id ); ?>"
				data-target="<?php echo esc_attr( $target_id ); ?>"
				data-type="<?php echo esc_attr( $type ); ?>">
				<?php esc_html_e( 'Delete Editor Page', 'nine3editor' ); ?>
			</button>

			<?php
			// Add View button for custom pages.
			if ( $type === 'custom' ) :
				$custom_link = get_search_link( '93digital' );
				if ( strpos( $source_id, '404' ) !== false ) {
					$custom_link = home_url( '/404' );
				}
				?>
				<a href="<?php echo esc_url( $custom_link ); ?>" class="button view-editor" target="_blank"><?php esc_html_e( 'View Editor Page', 'nine3editor' ); ?></a>
				<?php
			endif;
			?>
		<?php else : ?>
			<button
				class="button add-editor"
				data-source="<?php echo esc_attr( $source_id ); ?>"
				data-type="<?php echo esc_attr( $type ); ?>"
				data-editor='<?php echo wp_json_encode( $data ); ?>'>
				<?php esc_html_e( 'Add Editor Page', 'nine3editor' ); ?>
			</button>
			<?php
		endif;
		echo wp_kses_post( ob_get_clean() );
	}
}
