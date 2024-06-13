<?php
/**
 * This class initialises the plugin and does the setup legwork
 *
 * @package nine3editor
 */

namespace nine3editor;

/**
 * This is a wrapper class which instantiates everything else required for the plugin to work
 */
final class Nine3_Editor {
	/**
	 * The Nine3_Editor_Helpers object.
	 *
	 * @var string
	 */
	private $helpers;
	
	/**
	 * Array of all plugin options
	 *
	 * @var array $options
	 */
	private $options = [];

	/**
	 * Nonce action string
	 *
	 * @var string $nonce_action
	 */
	private $nonce_action = 'nine3_editor_nonce_action';

	/**
	 * Energise!
	 */
	public function __construct() {
		// Instantiate helpers.
		$this->helpers = new Nine3_Editor_Helpers();

		// Enqueue scripts.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Setup nine3_editor CPT.
		add_action( 'init', [ $this, 'editor_post_type' ] );

		// Instantiate settings class to show plugin settings.
		$settings = new Nine3_Editor_Settings();

		// Add settings array to class property.
		$this->options = get_option( $settings->option_name );

		// Check if tax terms are set in options and instantiate taxonomies editor class.
		if ( isset( $this->options['editor_tax'] ) ) {
			new Nine3_Editor_Tax( $this->options['editor_tax'] );
		}

		// Check if archive settings pages are set in options and instantiate archives editor class.
		if ( isset( $this->options['editor_cpt_archives'] ) ) {
			new Nine3_Editor_Archive( $this->options['editor_cpt_archives'] );
		}

		// Check if any custom page templates are set in options and instantiate custom pages class.
		if ( isset( $this->options['editor_custom_pages'] ) ) {
			new Nine3_Editor_Custom( $this->options['editor_custom_pages'] );
		}

		// Ajax action callback to add editor.
		add_action( 'wp_ajax_nine3-add-editor', [ $this, 'add_editor_callback' ] );

		// Ajax action callback to delete editor.
		add_action( 'wp_ajax_nine3-delete-editor', [ $this, 'delete_editor_callback' ] );

		// If user trashes post, we also need to delete any term/object meta related to it.
		add_action( 'trashed_post', [ $this, 'trashed_editor_post_callback' ], 10, 1 );

		// Hijack page render on template redirect.
		add_action( 'template_redirect', [ $this, 'check_page' ] );

		// Redirect if editor post accessed directly.
		add_action( 'template_redirect', [ $this, 'redirect_nine3_editor' ] );

		// Change nine3_editor permalink.
		add_filter( 'post_type_link', [ $this, 'rewrite_cpt_permalink' ], 10, 2 );
	}

	/**
	 * 'admin_enqueue_scripts' action hook callback
	 * Enqueue and localize our script
	 *
	 * @param string $hook hook suffix for the current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		// First scripts.
		$data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( $this->nonce_action ),
		];
		wp_register_script( 'nine3_editor_script', NINE3_EDITOR_URI . '/js/scripts.js', [], '1.0', true );
		wp_localize_script( 'nine3_editor_script', 'nine3_editor', $data );
		wp_enqueue_script( 'nine3_editor_script' );

		// Then styles.
		wp_register_style( 'nine3_editor_style', NINE3_EDITOR_URI . '/css/style.css', false, '1.0' );
		wp_enqueue_style( 'nine3_editor_style' );
	}

	/**
	 * 'init' action hook callback
	 * Callback for cpt register
	 */
	public function editor_post_type() {
		$args = [
			'label'               => __( '93editor', 'nine3editor' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'show_in_graphql'     => true,
			'graphql_single_name' => 'nine3_editor', 
			'graphql_plural_name' => 'nine3_editors',
			'has_archive'         => false,
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
			'rewrite'             => [
				'with_front' => false,
				'slug'       => 'nine3_editor',
			],
		];
		register_post_type( 'nine3_editor', $args );
	}

	/**
	 * Verifies nonce in ajax call and returns json response
	 */
	private function verify_nonce() {
		// phpcs:ignore
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], $this->nonce_action ) ) {
			$response = [
				'success' => false,
				'message' => __( 'Invalid request. Unable to verify nonce.', 'nine3editor' ),
			];
			echo wp_json_encode( $response );
			wp_die();
		}
	}

	/**
	 * 'wp_ajax_nine3-add-editor' action hook callback
	 * Receives source ID and data and creates nine3_editor post
	 * Then attaches post ID to source meta
	 */
	public function add_editor_callback() {
		// Verify nonce.
		$this->verify_nonce();

		// Setup response array.
		$response['success'] = false;

		if ( isset( $_REQUEST['source'] ) ) {
			$source_id   = sanitize_text_field( wp_unslash( $_REQUEST['source'] ) );
			$source_type = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : 'term';
			$source_data = isset( $_REQUEST['data'] ) ? json_decode( wp_unslash( $_REQUEST['data'] ), true ) : []; // phpcs:ignore
			$target      = new Nine3_Editor_Post(
				[
					'source_id'   => $source_id,
					'source_type' => $source_type,
					'source_data' => $source_data,
				]
			);

			// Post created succesfully.
			if ( isset( $target->target_id ) && ! is_wp_error( $target->target_id ) ) {
				// Add target ID to source meta.
				$target->add_target_to_source();

				$response = [
					'success' => true,
					'message' => __( 'Editor created successfully.', 'nine3editor' ),
					'target'  => get_edit_post_link( $target->target_id ),
				];
			} else {
				$response['message'] = $target->target_id->get_error_message() ?? __( 'Tagret ID not provided.', 'nine3editor' );
			}

			// Change response message if post exists.
			if ( isset( $this->target_status ) && $this->target_status === 'exists' ) {
				$response['message'] = __( 'Editor already exists, redirecting...', 'nine3editor' );
			}
		} else {
			$response['message'] = __( 'Source ID not provided.', 'nine3editor' );
		}

		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * 'wp_ajax_nine3-delete-editor' action hook callback
	 * Receives source ID and target ID and removes post / source meta
	 */
	public function delete_editor_callback() {
		// Verify nonce.
		$this->verify_nonce();

		// Setup response array.
		$response['success'] = false;

		if ( isset( $_REQUEST['source'] ) && isset( $_REQUEST['target'] ) ) {
			$target_id   = sanitize_text_field( wp_unslash( $_REQUEST['target'] ) );
			$source_id   = sanitize_text_field( wp_unslash( $_REQUEST['source'] ) );
			$source_type = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : 'term';

			// Delete source and target.
			$target = new Nine3_Editor_Post(
				[
					'source_id'   => $source_id,
					'source_type' => $source_type,
					'delete'      => $target_id,
				]
			);

			if ( ! $target->target_id ) {
				$response['message'] = __( 'Unable to delete target post.', 'nine3editor' );
			} elseif ( ! $target->source_id ) {
				$response['message'] = __( 'Unable to delete source meta.', 'nine3editor' );
			} else {
				$response = [
					'success' => true,
					'message' => __( 'Editor succesfully deleted.', 'nine3editor' ),
				];
			}
		} else {
			$response['message'] = __( 'Source and Target IDs not provided.', 'nine3editor' );
		}

		echo wp_json_encode( $response );
		wp_die();
	}

	/**
	 * 'trashed_post' action hook callback
	 * When admin trashes nine3_editor post we need to force permanent delete
	 *
	 * @param int $post_id the deleted post ID.
	 */
	public function trashed_editor_post_callback( $post_id ) {
		if ( get_post_type( $post_id ) === 'nine3_editor' ) {
			$source_id   = get_post_meta( $post_id, '_nine3_editor_source_id', true );
			$source_type = get_post_meta( $post_id, '_nine3_editor_source_type', true );

			// Delete source and target.
			$target = new Nine3_Editor_Post(
				[
					'source_id'   => $source_id,
					'source_type' => $source_type,
					'delete'      => $post_id,
				]
			);
		}
	}

	/**
	 * 'template_redirect' action hook callback
	 * Checks which page is being loaded
	 */
	public function check_page() {
		// If this is a feed, return early.
		if ( is_feed() ) {
			return;
		}

		$current_object = get_queried_object();

		if ( $current_object ) {
			$target_id = $this->helpers->get_object_target_id( $current_object );
		} elseif ( is_search() ) {
			$target_id = $this->helpers->get_custom_target_id( 'page_search' );
		} elseif ( is_404() ) {
			// Check if broken term page.
			// Get URL and check if contains term.
			$uri = $_SERVER['REQUEST_URI'];
			$uri = array_filter( explode( '/', $uri ) );
			if ( in_array( 'page', $uri ) ) {
				foreach ( $uri as $part ) {
					$tax            = isset( $uri[1] ) ? $uri[1] : '';
					$current_object = get_term_by( 'slug', $part, $tax );
					if ( $current_object ) {
						global $wp_query;
						status_header( 200 );
						$wp_query->is_page = true;
						$wp_query->is_404  = false;
						$target_id = $this->helpers->get_object_target_id( $current_object );
						break;
					} else {
						$target_id = $this->helpers->get_custom_target_id( 'page_404' );
					}
				}
			} else {
				$target_id = $this->helpers->get_custom_target_id( 'page_404' );
			}
		}

		// Once we have the target ID we can render it's contents.
		if ( isset( $target_id ) && $target_id > 0 ) {
			$content = get_the_content( null, false, $target_id );
			$blocks  = parse_blocks( $content );

			get_header();

			foreach ( $blocks as $block ) {
				echo do_shortcode( render_block( $block ) ); // phpcs:ignore
			}

			get_footer();
			exit;
		}
	}

	/**
	 * 'template_redirect' action hook callback
	 * Redirect nine3_editor posts if accessed directly
	 */
	public function redirect_nine3_editor() {
		if ( ! is_singular( 'nine3_editor' ) ) {
			return;
		}

		$post_id = get_the_ID();

		// Get target object.
		$target = new Nine3_Editor_Post(
			[
				'target_id' => $post_id,
			]
		);

		$redirect = $target->set_redirect();

		if ( $redirect && ! is_wp_error( $redirect ) ) {
			wp_redirect( $redirect );
			exit();
		}
	}

	/**
	 * 'post_type_link' filter hook callback
	 * Changes the permalink for nine3_editor post type
	 *
	 * @param string  $post_link the current permalink.
	 * @param WP_Port $post the post object.
	 */
	public function rewrite_cpt_permalink( $post_link, $post ) {
		if ( $post->post_type === 'nine3_editor' && $post->post_status === 'publish' ) {
			// Get target object.
			$target = new Nine3_Editor_Post(
				[
					'target_id' => $post->ID,
				]
			);

			$target_url = $target->set_redirect();
			if ( $target_url && ! is_wp_error( $target_url ) ) {
				$post_link = $target_url;
			}
		}

		return $post_link;
	}
}
