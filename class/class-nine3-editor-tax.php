<?php
/**
 * This class adds the editor to enabled term pages
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * Class init
 */
final class Nine3_Editor_Tax extends Nine3_Editor_Base {
	/**
	 * Contructor
	 *
	 * @param array $editor_tax the arrray of taxonomy terms which have been enabled in settings.
	 */
	public function __construct( $editor_tax ) {
		foreach ( $editor_tax as $tax ) {
			// Adding buttons to the edit screen of tax terms.
			add_action( $tax . '_edit_form_fields', [ $this, 'render_button_markup_term' ] );
		}
	}

	/**
	 * '[$taxonomy]_edit_form_fields' action hook callback
	 * Renders markup using our term meta
	 *
	 * @param WP_Term $term current object.
	 */
	public function render_button_markup_term( $term ) {
		$target_id = intval( get_term_meta( $term->term_id, '_nine3_editor_target_id', true ) );
		$data      = [
			'name'     => $term->name,
			'taxonomy' => $term->taxonomy,
		];

		ob_start();
		?>
		<tr class="form-field nine3-editor">
			<th scope="row"><?php esc_html_e( 'Editor Page', 'nine3editor' ); ?></th>
			<td><?php $this->render_button_markup( $term->term_id, $target_id, 'term', $data ); ?></td>
		</tr>
		<?php
		echo wp_kses_post( ob_get_clean() );
	}
}