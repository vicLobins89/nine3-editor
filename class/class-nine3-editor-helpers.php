<?php
/**
 * A collection of helper functions and utils for the editor plugin
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * Class init
 */
final class Nine3_Editor_Helpers {
	/**
	 * Helper function which determines the current object and gets target ID
	 *
	 * @param object $current_object the current queried object.
	 */
	public function get_object_target_id( $current_object ) {
		$target_id = false;
		$wp_class  = get_class( $current_object );
		switch ( $wp_class ) {
			case 'WP_Term':
				$target_id = intval( get_term_meta( $current_object->term_id, '_nine3_editor_target_id', true ) );
				break;
			case 'WP_Post_Type':
				$post_type = $current_object->name;
				$target_id = $this->get_archive_target_id( $post_type );
				break;
			case 'WP_Post':
				global $wp_query;
				if ( $wp_query->is_home() ) {
					$target_id = $this->get_archive_target_id( 'post' );
				}
				break;
		}

		return $target_id;
	}

	/**
	 * Helper function which gets the target ID for custom pages
	 *
	 * @param string $custom_id custom page identifier.
	 */
	public function get_custom_target_id( $custom_id ) {
		return intval( get_option( $custom_id . '_nine3_editor_target_id' ) );
	}

	/**
	 * Helper function to retrieve archive target ID and fix deprecation issues.
	 *
	 * @param string $post_type archive post type.
	 */
	public function get_archive_target_id( $post_type ) {
		$target_id = get_option( $post_type . '-settings_nine3_editor_target_id' );
		if ( $target_id ) {
			$target_id = intval( $target_id );
			delete_option( $post_type . '-settings_nine3_editor_target_id' );
			add_option( $post_type . '_nine3_editor_target_id', $target_id );
			update_post_meta( $target_id, '_nine3_editor_source_id', $post_type );
		} else {
			$target_id = intval( get_option( $post_type . '_nine3_editor_target_id' ) );
		}

		return $target_id;
	}
}