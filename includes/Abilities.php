<?php
/**
 * Custom WordPress MCP Chat Abilities
 *
 * @package WP_MCP
 */

namespace WP_MCP;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abilities class
 */
class Abilities {

	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		error_log( 'WP MCP: Abilities class hooks being initialized' );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ), 5 );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ), 5 ); // Early priority
		error_log( 'WP MCP: Abilities hooks added for wp_abilities_api_init with priority 5' );
	}

	/**
	 * Register ability categories
	 */
	public function register_ability_categories() {
		error_log( 'WP MCP: Registering custom abilities categories' );

		// Register the ability category
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'wp-mcp',
				array(
					'label'       => __( 'WP MCP', 'wp-mcp' ),
					'description' => __( 'WordPress MCP plugin abilities for AI-powered content management.', 'wp-mcp' ),
				)
			);
			error_log( 'WP MCP: Ability category registered' );
		} else {
			error_log( 'WP MCP: wp_register_ability_category function not available' );
		}
	}

	/**
	 * Register all custom abilities
	 */
	public function register_abilities() {
		// Only register if both Abilities API and MCP Adapter are available
		if ( ! function_exists( 'wp_register_ability' ) ) {
			error_log( 'WP MCP: wp_register_ability function not available' );
			return;
		}

		error_log( 'WP MCP: Registering custom abilities' );

		$this->register_create_post_ability();
		$this->register_list_posts_ability();
		$this->register_get_post_ability();
		$this->register_update_post_ability();
		$this->register_delete_post_ability();
		$this->register_list_users_ability();
		$this->register_get_site_info_ability();
		$this->register_manage_plugins_ability();

		error_log( 'WP MCP: Finished registering abilities' );
	}

	/**
	 * Register create post ability
	 */
	private function register_create_post_ability() {
		wp_register_ability(
			'wp-mcp/create-post',
			array(
				'label'               => __( 'Create WordPress Post', 'wp-mcp' ),
				'description'         => __( 'Create a new WordPress post with title, content, and optional metadata.', 'wp-mcp' ),
				'category'            => 'wp-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'              => array(
							'type'        => 'string',
							'description' => 'The post title',
							'minLength'   => 1,
							'maxLength'   => 200,
						),
						'content'            => array(
							'type'        => 'string',
							'description' => 'The post content (HTML allowed)',
						),
						'excerpt'            => array(
							'type'        => 'string',
							'description' => 'Optional post excerpt',
						),
						'status'             => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'private' ),
							'default'     => 'draft',
							'description' => 'Post status',
						),
						'post_type'          => array(
							'type'        => 'string',
							'default'     => 'post',
							'description' => 'Post type',
						),
						'categories'         => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Category names to assign to the post',
						),
						'tags'               => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Tag names to assign to the post',
						),
						'featured_image_url' => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => 'Optional featured image URL',
						),
					),
					'required'   => array( 'title', 'content' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'post_url' => array( 'type' => 'string' ),
						'edit_url' => array( 'type' => 'string' ),
						'status'   => array( 'type' => 'string' ),
						'message'  => array( 'type' => 'string' ),
					),
					'required'   => array( 'post_id', 'status', 'message' ),
				),
				'execute_callback'    => array( $this, 'execute_create_post' ),
				'permission_callback' => array( $this, 'check_edit_posts_permission' ),
				'meta'                => array(
					'annotations' => array(
						'destructive' => true,
						'idempotent'  => false,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register list posts ability
	 */
	private function register_list_posts_ability() {
		wp_register_ability(
			'wp-mcp/list-posts',
			array(
				'label'               => __( 'List WordPress Posts', 'wp-mcp' ),
				'description'         => __( 'Retrieve a list of WordPress posts with optional filtering.', 'wp-mcp' ),
				'category'            => 'wp-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'   => array(
							'type'        => 'string',
							'default'     => 'post',
							'description' => 'Post type to filter by',
						),
						'post_status' => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'private', 'any' ),
							'default'     => 'publish',
							'description' => 'Post status to filter by',
						),
						'limit'       => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 10,
							'description' => 'Number of posts to return',
						),
						'search'      => array(
							'type'        => 'string',
							'description' => 'Search term to filter posts',
						),
						'category'    => array(
							'type'        => 'string',
							'description' => 'Category slug to filter by',
						),
						'author'      => array(
							'type'        => 'string',
							'description' => 'Author username to filter by',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'posts'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'        => array( 'type' => 'integer' ),
									'title'     => array( 'type' => 'string' ),
									'excerpt'   => array( 'type' => 'string' ),
									'status'    => array( 'type' => 'string' ),
									'date'      => array( 'type' => 'string' ),
									'author'    => array( 'type' => 'string' ),
									'post_type' => array( 'type' => 'string' ),
									'permalink' => array( 'type' => 'string' ),
									'edit_url'  => array( 'type' => 'string' ),
								),
							),
						),
						'total_found' => array( 'type' => 'integer' ),
						'message'     => array( 'type' => 'string' ),
					),
					'required'   => array( 'posts', 'total_found', 'message' ),
				),
				'execute_callback'    => array( $this, 'execute_list_posts' ),
				'permission_callback' => array( $this, 'check_read_posts_permission' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register get post ability
	 */
	private function register_get_post_ability() {
		wp_register_ability(
			'wp-mcp/get-post',
			array(
				'label'               => __( 'Get WordPress Post', 'wp-mcp' ),
				'description'         => __( 'Retrieve detailed information about a specific WordPress post.', 'wp-mcp' ),
				'category'            => 'wp-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'         => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'The ID of the post to retrieve',
						),
						'include_content' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Whether to include full post content',
						),
						'include_meta'    => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Whether to include post metadata',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array( 'type' => 'integer' ),
								'title'          => array( 'type' => 'string' ),
								'content'        => array( 'type' => 'string' ),
								'excerpt'        => array( 'type' => 'string' ),
								'status'         => array( 'type' => 'string' ),
								'date'           => array( 'type' => 'string' ),
								'modified'       => array( 'type' => 'string' ),
								'author'         => array( 'type' => 'string' ),
								'post_type'      => array( 'type' => 'string' ),
								'permalink'      => array( 'type' => 'string' ),
								'edit_url'       => array( 'type' => 'string' ),
								'categories'     => array( 'type' => 'array' ),
								'tags'           => array( 'type' => 'array' ),
								'featured_image' => array( 'type' => 'string' ),
								'meta'           => array( 'type' => 'object' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
					'required'   => array( 'post', 'message' ),
				),
				'execute_callback'    => array( $this, 'execute_get_post' ),
				'permission_callback' => array( $this, 'check_read_posts_permission' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register get site info ability
	 */
	private function register_get_site_info_ability() {
		wp_register_ability(
			'wp-mcp/get-site-info',
			array(
				'label'               => __( 'Get Site Information', 'wp-mcp' ),
				'description'         => __( 'Retrieve general information about the WordPress site.', 'wp-mcp' ),
				'category'            => 'wp-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_theme'   => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Include active theme information',
						),
						'include_plugins' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Include active plugins list',
						),
						'include_stats'   => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Include basic site statistics',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site'    => array(
							'type'       => 'object',
							'properties' => array(
								'name'              => array( 'type' => 'string' ),
								'description'       => array( 'type' => 'string' ),
								'url'               => array( 'type' => 'string' ),
								'admin_email'       => array( 'type' => 'string' ),
								'language'          => array( 'type' => 'string' ),
								'wordpress_version' => array( 'type' => 'string' ),
								'theme'             => array( 'type' => 'object' ),
								'plugins'           => array( 'type' => 'array' ),
								'statistics'        => array( 'type' => 'object' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
					'required'   => array( 'site', 'message' ),
				),
				'execute_callback'    => array( $this, 'execute_get_site_info' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}


	// Add placeholder for additional abilities
	private function register_update_post_ability() {
		// Implementation similar to create_post but for updating
	}

	private function register_delete_post_ability() {
		// Implementation for deleting posts
	}

	private function register_list_users_ability() {
		// Implementation for listing users
	}

	private function register_manage_plugins_ability() {
		// Implementation for plugin management
	}

	/**
	 * Execute create post ability
	 */
	public function execute_create_post( $input = array() ) {
		try {
			$post_data = array(
				'post_title'   => sanitize_text_field( $input['title'] ?? '' ),
				'post_content' => wp_kses_post( $input['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $input['excerpt'] ?? '' ),
				'post_status'  => sanitize_text_field( $input['status'] ?? 'draft' ),
				'post_type'    => sanitize_text_field( $input['post_type'] ?? 'post' ),
				'post_author'  => get_current_user_id(),
			);

			if ( empty( $post_data['post_title'] ) ) {
				return new WP_Error( 'missing_title', __( 'Post title is required', 'wp-mcp' ) );
			}

			$post_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			// Handle categories
			if ( ! empty( $input['categories'] ) ) {
				$category_ids = array();
				foreach ( $input['categories'] as $category_name ) {
					$term = get_term_by( 'name', $category_name, 'category' );
					if ( $term ) {
						$category_ids[] = $term->term_id;
					}
				}
				if ( ! empty( $category_ids ) ) {
					wp_set_post_categories( $post_id, $category_ids );
				}
			}

			// Handle tags
			if ( ! empty( $input['tags'] ) ) {
				wp_set_post_tags( $post_id, $input['tags'] );
			}

			return array(
				'post_id'  => $post_id,
				'post_url' => get_permalink( $post_id ),
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
				'status'   => get_post_status( $post_id ),
				'message'  => sprintf( __( 'Successfully created post "%1$s" with ID %2$d', 'wp-mcp' ), $post_data['post_title'], $post_id ),
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'execution_failed', $e->getMessage() );
		}
	}

	/**
	 * Execute list posts ability
	 */
	public function execute_list_posts( $input = array() ) {
		try {
			$args = array(
				'post_type'      => sanitize_text_field( $input['post_type'] ?? 'post' ),
				'post_status'    => sanitize_text_field( $input['post_status'] ?? 'publish' ),
				'posts_per_page' => min( (int) ( $input['limit'] ?? 10 ), 100 ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			);

			if ( ! empty( $input['search'] ) ) {
				$args['s'] = sanitize_text_field( $input['search'] );
			}

			if ( ! empty( $input['author'] ) ) {
				$user = get_user_by( 'login', sanitize_text_field( $input['author'] ) );
				if ( $user ) {
					$args['author'] = $user->ID;
				}
			}

			if ( ! empty( $input['category'] ) ) {
				$args['category_name'] = sanitize_text_field( $input['category'] );
			}

			$query = new WP_Query( $args );
			$posts = array();

			foreach ( $query->posts as $post ) {
				$posts[] = array(
					'id'        => $post->ID,
					'title'     => $post->post_title,
					'excerpt'   => get_the_excerpt( $post ),
					'status'    => $post->post_status,
					'date'      => $post->post_date,
					'author'    => get_the_author_meta( 'display_name', $post->post_author ),
					'post_type' => $post->post_type,
					'permalink' => get_permalink( $post ),
					'edit_url'  => get_edit_post_link( $post, 'raw' ),
				);
			}

			return array(
				'posts'       => $posts,
				'total_found' => $query->found_posts,
				'message'     => sprintf( __( 'Found %d posts', 'wp-mcp' ), $query->found_posts ),
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'execution_failed', $e->getMessage() );
		}
	}

	/**
	 * Execute get post ability
	 */
	public function execute_get_post( $input = array() ) {
		try {
			$post_id = (int) ( $input['post_id'] ?? 0 );

			if ( empty( $post_id ) ) {
				return new WP_Error( 'missing_post_id', __( 'Post ID is required', 'wp-mcp' ) );
			}

			$post = get_post( $post_id );

			if ( ! $post ) {
				return new WP_Error( 'post_not_found', __( 'Post not found', 'wp-mcp' ) );
			}

			$post_data = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'excerpt'    => get_the_excerpt( $post ),
				'status'     => $post->post_status,
				'date'       => $post->post_date,
				'modified'   => $post->post_modified,
				'author'     => get_the_author_meta( 'display_name', $post->post_author ),
				'post_type'  => $post->post_type,
				'permalink'  => get_permalink( $post ),
				'edit_url'   => get_edit_post_link( $post, 'raw' ),
				'categories' => get_the_category_list( ', ', '', '', $post->ID ),
				'tags'       => get_the_tag_list( '', ', ', '', $post->ID ),
			);

			if ( $input['include_content'] ?? true ) {
				$post_data['content'] = $post->post_content;
			}

			if ( $input['include_meta'] ?? false ) {
				$post_data['meta'] = get_post_meta( $post_id );
			}

			$featured_image_id = get_post_thumbnail_id( $post );
			if ( $featured_image_id ) {
				$post_data['featured_image'] = wp_get_attachment_url( $featured_image_id );
			}

			return array(
				'post'    => $post_data,
				'message' => sprintf( __( 'Retrieved post "%s"', 'wp-mcp' ), $post->post_title ),
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'execution_failed', $e->getMessage() );
		}
	}

	/**
	 * Execute get site info ability
	 */
	public function execute_get_site_info( $input = array() ) {
		try {
			$site_data = array(
				'name'              => get_bloginfo( 'name' ),
				'description'       => get_bloginfo( 'description' ),
				'url'               => home_url(),
				'admin_email'       => get_bloginfo( 'admin_email' ),
				'language'          => get_bloginfo( 'language' ),
				'wordpress_version' => get_bloginfo( 'version' ),
			);

			if ( $input['include_theme'] ?? true ) {
				$theme              = wp_get_theme();
				$site_data['theme'] = array(
					'name'        => $theme->get( 'Name' ),
					'version'     => $theme->get( 'Version' ),
					'author'      => $theme->get( 'Author' ),
					'description' => $theme->get( 'Description' ),
				);
			}

			if ( $input['include_stats'] ?? true ) {
				$site_data['statistics'] = array(
					'total_posts'    => wp_count_posts()->publish ?? 0,
					'total_pages'    => wp_count_posts( 'page' )->publish ?? 0,
					'total_users'    => count_users()['total_users'] ?? 0,
					'total_comments' => wp_count_comments()->approved ?? 0,
				);
			}

			if ( $input['include_plugins'] ?? false ) {
				if ( current_user_can( 'activate_plugins' ) ) {
					$active_plugins       = get_option( 'active_plugins', array() );
					$site_data['plugins'] = array();
					foreach ( $active_plugins as $plugin ) {
						$plugin_data            = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
						$site_data['plugins'][] = array(
							'name'    => $plugin_data['Name'],
							'version' => $plugin_data['Version'],
							'author'  => $plugin_data['Author'],
						);
					}
				}
			}

			return array(
				'site'    => $site_data,
				'message' => __( 'Retrieved site information', 'wp-mcp' ),
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'execution_failed', $e->getMessage() );
		}
	}


	/**
	 * Check if user can edit posts
	 */
	public function check_edit_posts_permission( $input = array() ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'authentication_required', __( 'User must be authenticated', 'wp-mcp' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'User cannot edit posts', 'wp-mcp' ) );
		}

		return true;
	}

	/**
	 * Check if user can read posts
	 */
	public function check_read_posts_permission( $input = array() ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'authentication_required', __( 'User must be authenticated', 'wp-mcp' ) );
		}

		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'User cannot read content', 'wp-mcp' ) );
		}

		return true;
	}

	/**
	 * Check basic read permission
	 */
	public function check_read_permission( $input = array() ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'authentication_required', __( 'User must be authenticated', 'wp-mcp' ) );
		}

		return true;
	}
}
