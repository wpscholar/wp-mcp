<?php
/**
 * Plugin Name: WP MCP
 * Description: WordPress MCP Chat Client with AI integration using Model Context Protocol.
 * Version: 1.0.0
 * Author: Micah Wood
 * Author URI: https://wpscholar.com
 * Text Domain: wp-mcp
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_MCP_VERSION', '1.0.0');
define('WP_MCP_PLUGIN_FILE', __FILE__);
define('WP_MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_MCP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main WP_MCP class
 */
class WP_MCP {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load Composer autoloader if it exists
        if (file_exists(WP_MCP_PLUGIN_DIR . 'vendor/autoload.php')) {
            require_once WP_MCP_PLUGIN_DIR . 'vendor/autoload.php';
            error_log('WP MCP: Composer autoloader loaded successfully');
        } else {
            error_log('WP MCP: Composer autoloader not found at: ' . WP_MCP_PLUGIN_DIR . 'vendor/autoload.php');
        }
        
        // Load plugin classes
        require_once WP_MCP_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WP_MCP_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once WP_MCP_PLUGIN_DIR . 'includes/class-wp-mcp-abilities.php';
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load textdomain for translations
        load_plugin_textdomain('wp-mcp', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize plugin components that need to hook into WordPress APIs
        WP_MCP_Abilities::get_instance(); // Must be initialized before abilities_api_init
        
        // Initialize WordPress APIs
        $this->init_wordpress_apis();
        
        // Initialize remaining components
        if (is_admin()) {
            WP_MCP_Admin::get_instance();
        }
        
        WP_MCP_REST_API::get_instance();
    }
    
    /**
     * Initialize WordPress APIs (Abilities API and MCP Adapter)
     */
    private function init_wordpress_apis() {
        // Initialize WordPress Abilities API if available
        if (file_exists(WP_MCP_PLUGIN_DIR . 'vendor/wordpress/abilities-api/abilities-api.php')) {
            require_once WP_MCP_PLUGIN_DIR . 'vendor/wordpress/abilities-api/abilities-api.php';
            error_log('WP MCP: Abilities API loaded');
        } else {
            error_log('WP MCP: Abilities API not found at expected path');
        }
        
        // Initialize MCP Adapter and create server if available
        if (class_exists('WP\MCP\Core\McpAdapter')) {
            add_action('mcp_adapter_init', function($adapter) {
                error_log('WP MCP: Creating MCP server with WordPress abilities');
                
                $server = $adapter->create_server(
                    'default-server',                                         // Unique server identifier
                    'mcp',                                                   // REST API namespace
                    'mcp-adapter-default-server',                          // REST API route
                    'WordPress MCP Server',                                 // Server name
                    'WordPress MCP server providing access to WordPress abilities', // Server description
                    '1.0.0',                                               // Server version
                    [                                                      // Transport methods
                        \WP\MCP\Transport\HttpTransport::class,            // MCP 2025-06-18 compliant
                    ],
                    \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class, // Error handler
                    \WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class, // Observability handler
                    [                                                      // Abilities to expose as tools
                        'wp-mcp/create-post',
                        'wp-mcp/list-posts', 
                        'wp-mcp/get-post',
                        'wp-mcp/get-site-info',
                    ],
                    [],                                                    // Resources (optional)
                    [],                                                    // Prompts (optional)
                );
                
                error_log('WP MCP: MCP server created - ' . ($server ? 'SUCCESS' : 'FAILED'));
            });
            
            // Abilities will be registered automatically when the API is initialized
            
            // Initialize the MCP Adapter to trigger the mcp_adapter_init action
            error_log('WP MCP: MCP Adapter class found - initializing');
            \WP\MCP\Core\McpAdapter::instance();
            error_log('WP MCP: MCP Adapter initialized');
        } else {
            error_log('WP MCP: MCP Adapter class not found');
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WP MCP requires PHP 8.0 or higher.', 'wp-mcp'));
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WP MCP requires WordPress 6.0 or higher.', 'wp-mcp'));
        }
        
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up any temporary data
        delete_transient('wp_mcp_chat_sessions');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Chat sessions table
        $table_name = $wpdb->prefix . 'mcp_chat_sessions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update version option
        update_option('wp_mcp_db_version', WP_MCP_VERSION);
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'openai_api_key' => '',
            'cloudflare_gateway_url' => '',
            'cloudflare_token' => '',
            'chat_history_enabled' => true,
            'max_messages_per_session' => 100,
        );
        
        foreach ($default_options as $option => $value) {
            if (false === get_option('wp_mcp_' . $option)) {
                update_option('wp_mcp_' . $option, $value);
            }
        }
    }
}

// Initialize the plugin
WP_MCP::get_instance();
