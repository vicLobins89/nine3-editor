<?php
/**
 * Returns an object with target id, source id, and other useful data related to the editor post
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * Class init
 */
final class Nine3_Editor_Post {
	/**
	 * Editor source ID
	 *
	 * @var array $source_id
	 */
	public $source_id;

	/**
	 * Editor source type
	 *
	 * @var array $source_type
	 */
	public $source_type;

	/**
	 * Editor source additional data
	 *
	 * @var array $source_data
	 */
	public $source_data = [];

	/**
	 * Editor post ID
	 *
	 * @var array $target_id
	 */
	public $target_id;

	/**
	 * Whether the target post exists
	 *
	 * @var array $target_exists
	 */
	public $target_status;

	/**
	 * Create new post or return exsting ID
	 *
	 * @param array $args array of options.
	 */
	public function __construct( array $args ) {
		// Set vars.
		$this->source_type = isset( $args['source_type'] ) ? $args['source_type'] : 'custom';
		$this->source_data = isset( $args['source_data'] ) ? $args['source_data'] : [];

		if ( isset( $args['source_id'] ) ) {
			$this->source_id = $args['source_id'];
		}

		if ( isset( $args['target_id'] ) ) {
			$this->set_object_props( $args['target_id'] );
			return;
		}

		// Checks if source ID is set.
		if ( ! isset( $this->source_id ) ) {
			return new WP_Error( 'no_source_id', __( 'Source ID not provided.', 'nine3editor' ) );
		}

		// Are we deleting or creating/returning?
		if ( isset( $args['delete'] ) && is_numeric( $args['delete'] ) ) {
			$this->target_id     = wp_delete_post( $args['delete'], true );
			$this->source_id     = $this->delete_source_meta();
			$this->target_status = 'deleted';
		} else {
			// Check if post exists.
			$editor_args = [
				'post_type'   => 'nine3_editor',
				'post_status' => 'any',
				'numberposts' => 1,
				'meta_key'    => '_nine3_editor_source_id',
				'meta_value'  => $this->source_id,
			];
			$target      = get_posts( $editor_args );

			if ( isset( $target[0] ) ) {
				// If post exists, set object props.
				$this->set_object_props( $target[0] );
			} else {
				// If not create new post and set object props.
				$post_title = isset( $this->source_data['name'] ) ? $this->source_data['name'] : $this->source_type . ' ' . $this->source_id;
				$post_name  = $this->source_type . '-' . $this->source_id;
				$meta_array = [
					'_nine3_editor_source_id'   => $this->source_id,
					'_nine3_editor_source_type' => $this->source_type,
				];

				if ( isset( $this->source_data['taxonomy'] ) ) {
					$post_name = $this->source_type . '-' . $this->source_data['taxonomy'] . '-' . $this->source_id;
					$meta_array['_nine3_editor_source_taxonomy'] = $this->source_data['taxonomy'];
				}

				$post_data = [
					'post_type'  => 'nine3_editor',
					'post_title' => $post_title,
					'post_name'  => $post_name,
					'meta_input' => $meta_array,
				];

				$this->target_id     = wp_insert_post( $post_data, true );
				$this->target_status = 'created';
			}
		}
	}

	/**
	 * Sets up needed properties for the object
	 *
	 * @param int $target_id the ID of editor post.
	 */
	private function set_object_props( $target_id ) {
		$this->target_id     = $target_id;
		$this->target_status = 'exists';
		$this->source_id     = get_post_meta( $target_id, '_nine3_editor_source_id', true );
		$this->source_type   = get_post_meta( $target_id, '_nine3_editor_source_type', true );
	}

	/**
	 * Adds target ID to source meta
	 */
	public function add_target_to_source() {
		switch ( $this->source_type ) {
			case 'term':
				update_term_meta( $this->source_id, '_nine3_editor_target_id', intval( $this->target_id ) );
				break;
			case 'archive':
			case 'custom':
				update_option( $this->source_id . '_nine3_editor_target_id', intval( $this->target_id ) );
				break;
		}
	}

	/**
	 * Deletes all the source meta
	 */
	public function delete_source_meta() {
		$source_delete = false;
		switch ( $this->source_type ) {
			case 'term':
				$source_delete = delete_metadata( 'term', $this->source_id, '_nine3_editor_target_id' );
				break;
			case 'archive':
			case 'custom':
				$source_delete = delete_option( $this->source_id . '_nine3_editor_target_id' );
				break;
		}

		return $source_delete;
	}

	/**
	 * Sets redirect for target editor post to source if post is accessed directly
	 */
	public function set_redirect() {
		$redirect = false;
		switch ( $this->source_type ) {
			case 'term':
				$redirect = get_term_link( (int) $this->source_id );
				break;
			case 'archive':
				$redirect = get_post_type_archive_link( $this->source_id );
				break;
			case 'custom':
				$redirect = get_search_link( '93digital' );
				if ( strpos( $this->source_id, '404' ) !== false ) {
					$redirect = home_url( '/404' );
				}
				break;
		}

		return $redirect;
	}

	/**
	 * Returns term or archive label
	 *
	 * @param bool $single whetehr to get singular or plural label.
	 */
	public function get_label( $single = false ) {
		switch ( $this->source_type ) {
			case 'term':
				$taxonomy = get_term( (int) $this->source_id )->taxonomy;
				$label    = $single ? get_taxonomy( $taxonomy )->labels->singular_name : get_taxonomy( $taxonomy )->label;
				break;
			case 'archive':
				$label = $single ? get_post_type_object( $this->source_id )->labels->singular_name : get_post_type_object( $this->source_id )->labels->name;
				break;
			default:
				$label = $this->source_id;
		}

		return $label;
	}
}
