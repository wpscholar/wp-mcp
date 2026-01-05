<?php
/**
 * REST API class for WP MCP
 *
 * @package WP_MCP
 */

namespace WP_MCP;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RestApi class
 */
class RestApi {

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
	 * Initialize REST API hooks
	 */
	private function init_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		$namespace = 'wp-mcp/v1';

		// Chat endpoints
		register_rest_route(
			$namespace,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat_message' ),
				'permission_callback' => array( $this, 'check_chat_permissions' ),
				'args'                => array(
					'message'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role'       => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'user',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get chat history
		register_rest_route(
			$namespace,
			'/chat/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_chat_history' ),
				'permission_callback' => array( $this, 'check_chat_permissions' ),
				'args'                => array(
					'session_id' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'      => array(
						'required' => false,
						'type'     => 'integer',
						'default'  => 50,
						'minimum'  => 1,
						'maximum'  => 100,
					),
				),
			)
		);

		// AI proxy endpoint for Cloudflare AI Gateway
		register_rest_route(
			$namespace,
			'/ai/chat/completions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'proxy_ai_request' ),
				'permission_callback' => array( $this, 'check_ai_proxy_permissions' ),
				'args'                => array(
					'model'       => array(
						'required' => true,
						'type'     => 'string',
					),
					'messages'    => array(
						'required' => true,
						'type'     => 'array',
					),
					'tools'       => array(
						'required' => false,
						'type'     => 'array',
					),
					'tool_choice' => array(
						'required' => false,
					),
					'stream'      => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
					'max_tokens'  => array(
						'required' => false,
						'type'     => 'integer',
					),
					'temperature' => array(
						'required' => false,
						'type'     => 'number',
					),
				),
			)
		);

		// Note: MCP tools and resources endpoints are now provided by the WordPress MCP Adapter
		// This plugin focuses on the chat interface and ability registration

		// Settings endpoint (admin only)
		register_rest_route(
			$namespace,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);
	}

	/**
	 * Check permissions for admin-level API access.
	 *
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	public function check_admin_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'wp-mcp' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'wp-mcp' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check permissions for chat-related API access (editors and above).
	 *
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	public function check_chat_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'wp-mcp' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use the chat feature.', 'wp-mcp' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check permissions for AI proxy access (more restrictive).
	 *
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	public function check_ai_proxy_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'wp-mcp' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use the AI features.', 'wp-mcp' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check rate limit using transients.
	 *
	 * @param string $action       The action being rate limited.
	 * @param int    $max_requests Maximum requests allowed.
	 * @param int    $period       Time period in seconds.
	 * @return bool True if within limit, false if exceeded.
	 */
	private function check_rate_limit( string $action, int $max_requests, int $period ): bool {
		$user_id       = get_current_user_id();
		$transient_key = "wp_mcp_rate_{$action}_{$user_id}";
		$requests      = get_transient( $transient_key );

		if ( false === $requests ) {
			set_transient( $transient_key, 1, $period );
			return true;
		}

		if ( $requests >= $max_requests ) {
			return false;
		}

		set_transient( $transient_key, $requests + 1, $period );
		return true;
	}

	/**
	 * Legacy permission check (alias for admin permissions).
	 *
	 * @deprecated Use check_admin_permissions() instead.
	 * @return bool|\WP_Error
	 */
	public function check_permissions() {
		return $this->check_admin_permissions();
	}

	/**
	 * Handle chat message - saves message to history
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function handle_chat_message( $request ) {
		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' ) ?: wp_generate_uuid4();
		$role       = $request->get_param( 'role' ) ?: 'user';

		try {
			// Validate role
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
				$role = 'user';
			}

			// Save message to history
			$this->save_message_to_history( $session_id, $message, $role );

			return rest_ensure_response(
				array(
					'success'    => true,
					'session_id' => $session_id,
					'role'       => $role,
					'timestamp'  => current_time( 'mysql' ),
				)
			);

		} catch ( \Exception $e ) {
			return new \WP_Error( 'chat_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Get chat history
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error The response.
	 */
	public function get_chat_history( $request ) {
		$session_id = $request->get_param( 'session_id' );
		$limit      = $request->get_param( 'limit' );

		if ( ! $session_id ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'history' => array(),
				)
			);
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mcp_chat_sessions';
		$user_id    = get_current_user_id();

		// Validate session ownership - users can only access their own sessions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_data = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT session_data FROM ' . $wpdb->prefix . 'mcp_chat_sessions WHERE id = %s AND user_id = %d',
				$session_id,
				$user_id
			)
		);

		if ( ! $session_data ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'history' => array(),
				)
			);
		}

		$messages = json_decode( $session_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_decode_error',
				__( 'Failed to decode session data.', 'wp-mcp' ),
				array( 'status' => 500 )
			);
		}
		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		// Apply limit.
		if ( $limit && count( $messages ) > $limit ) {
			$messages = array_slice( $messages, -$limit );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'history' => $messages,
			)
		);
	}

	/**
	 * Proxy AI requests to Cloudflare AI Gateway
	 */
	public function proxy_ai_request( $request ) {
		// Get the Cloudflare configuration
		$gateway_url  = get_option( 'wp_mcp_cloudflare_gateway_url' );
		$bearer_token = get_option( 'wp_mcp_cloudflare_token' );

		if ( empty( $gateway_url ) || empty( $bearer_token ) ) {
			return new \WP_Error(
				'missing_ai_config',
				__( 'AI Gateway configuration is missing. Please configure in settings.', 'wp-mcp' ),
				array( 'status' => 400 )
			);
		}

		// Prepare the request body
		$body = array(
			'model'    => $request->get_param( 'model' ),
			'messages' => $request->get_param( 'messages' ),
		);

		// Add optional parameters if provided
		$tools = $request->get_param( 'tools' );
		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		$tool_choice = $request->get_param( 'tool_choice' );
		if ( null !== $tool_choice ) {
			$body['tool_choice'] = $tool_choice;
		}

		$stream = $request->get_param( 'stream' );
		if ( null !== $stream ) {
			$body['stream'] = $stream;
		}

		$max_tokens = $request->get_param( 'max_tokens' );
		if ( null !== $max_tokens ) {
			$body['max_tokens'] = $max_tokens;
		}

		$temperature = $request->get_param( 'temperature' );
		if ( null !== $temperature ) {
			$body['temperature'] = $temperature;
		}

		// Make the request to Cloudflare AI Gateway
		$response = wp_remote_post(
			$gateway_url . '/chat/completions',
			array(
				'headers'     => array(
					'Authorization' => 'Bearer ' . $bearer_token,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'timeout'     => 30,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'ai_request_failed',
				$response->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Handle streaming responses
		if ( $stream ) {
			// For streaming, we need to pass through the stream directly
			// This is more complex and requires special handling
			// For now, we'll return an error for streaming requests
			return new \WP_Error(
				'streaming_not_supported',
				__( 'Streaming responses are not yet supported through the proxy.', 'wp-mcp' ),
				array( 'status' => 501 )
			);
		}

		// Parse the response
		$data = json_decode( $response_body, true );

		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'ai_request_error',
				$data['error']['message'] ?? __( 'AI request failed', 'wp-mcp' ),
				array( 'status' => $response_code )
			);
		}

		return rest_ensure_response( $data );
	}

	// MCP tools and resources methods removed - now handled by WordPress MCP Adapter

	/**
	 * Get plugin settings
	 */
	public function get_settings( $request ) {
		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $this->get_plugin_settings(),
			)
		);
	}

	/**
	 * Get plugin settings (sanitized for frontend)
	 */
	private function get_plugin_settings() {
		return array(
			'mcp_server_url'           => rest_url( 'mcp/mcp-adapter-default-server' ),
			'cloudflare_gateway_url'   => get_option( 'wp_mcp_cloudflare_gateway_url', '' ),
			'cloudflare_token'         => ! empty( get_option( 'wp_mcp_cloudflare_token', '' ) ) ? '***' : '',
			'chat_history_enabled'     => (bool) get_option( 'wp_mcp_chat_history_enabled', true ),
			'max_messages_per_session' => (int) get_option( 'wp_mcp_max_messages_per_session', 100 ),
			'openai_api_key'           => ! empty( get_option( 'wp_mcp_openai_api_key', '' ) ) ? '***' : '',
		);
	}

	/**
	 * Save message to chat history.
	 *
	 * @param string $session_id The session ID.
	 * @param string $content    The message content.
	 * @param string $role       The message role (user or assistant).
	 */
	private function save_message_to_history( string $session_id, string $content, string $role ): void {
		if ( ! get_option( 'wp_mcp_chat_history_enabled', true ) ) {
			return;
		}

		// Validate content length to prevent database bloat.
		$max_content_length = 50000;
		if ( strlen( $content ) > $max_content_length ) {
			$content = substr( $content, 0, $max_content_length );
		}

		global $wpdb;
		$user_id = get_current_user_id();

		$message_data = array(
			'id'        => wp_generate_uuid4(),
			'role'      => $role,
			'content'   => $content,
			'timestamp' => current_time( 'mysql' ),
		);

		// Get existing session data for current user only.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$session_data = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT session_data FROM ' . $wpdb->prefix . 'mcp_chat_sessions WHERE id = %s AND user_id = %d',
				$session_id,
				$user_id
			)
		);

		$messages = array();
		if ( $session_data ) {
			$decoded = json_decode( $session_data, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$messages = $decoded;
			}
		}
		$messages[] = $message_data;

		// Limit messages per session.
		$max_messages = (int) get_option( 'wp_mcp_max_messages_per_session', 100 );
		if ( count( $messages ) > $max_messages ) {
			$messages = array_slice( $messages, -$max_messages );
		}

		// Insert or update session.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->replace(
			$wpdb->prefix . 'mcp_chat_sessions',
			array(
				'id'           => $session_id,
				'user_id'      => $user_id,
				'session_data' => wp_json_encode( $messages ),
			),
			array( '%s', '%d', '%s' )
		);
	}
}
