<?php
/**
 * Admin class for WP MCP
 *
 * @package WP_MCP
 */

namespace WP_MCP;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class
 */
class Admin {
    
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
    }
    
    /**
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('MCP Chat', 'wp-mcp'),
            __('MCP Chat', 'wp-mcp'),
            'manage_options',
            'wp-mcp-chat',
            array($this, 'render_chat_page'),
            'dashicons-format-chat',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'wp-mcp-chat',
            __('MCP Settings', 'wp-mcp'),
            __('Settings', 'wp-mcp'),
            'manage_options',
            'wp-mcp-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'wp-mcp') === false) {
            return;
        }
        
        // Check if we're in development mode (has src dir but no built assets)
        $is_dev = defined('WP_DEBUG') && WP_DEBUG && file_exists(WP_MCP_PLUGIN_DIR . 'src') && !file_exists(WP_MCP_PLUGIN_DIR . 'dist/.vite/manifest.json');
        
        // Debug information
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WP MCP Debug: Development mode: ' . ($is_dev ? 'YES' : 'NO'));
            error_log('WP MCP Debug: Manifest exists: ' . (file_exists(WP_MCP_PLUGIN_DIR . 'dist/.vite/manifest.json') ? 'YES' : 'NO'));
            error_log('WP MCP Debug: Plugin dir: ' . WP_MCP_PLUGIN_DIR);
        }
        
        $script_loaded = false;
        
        if ($is_dev) {
            // Development mode: load from Vite dev server
            $script_loaded = $this->enqueue_dev_assets();
        } else {
            // Production mode: load built assets
            $script_loaded = $this->enqueue_prod_assets();
        }
        
        // Localize script with WordPress data only if script was loaded
        if ($script_loaded) {
            wp_localize_script('wp-mcp-app', 'wpMcp', array(
                'restUrl' => rest_url('wp-mcp/v1/'),
                'mcpUrl' => rest_url('mcp/mcp-adapter-default-server'),
                'nonce' => wp_create_nonce('wp_rest'),
                'currentUser' => wp_get_current_user(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'pluginUrl' => WP_MCP_PLUGIN_URL,
                'isDebug' => defined('WP_DEBUG') && WP_DEBUG,
            ));
        }
    }
    
    /**
     * Enqueue development assets (Vite dev server)
     */
    private function enqueue_dev_assets() {
        // Vite development client
        wp_enqueue_script(
            'wp-mcp-vite-client',
            'http://localhost:3000/@vite/client',
            array(),
            WP_MCP_VERSION,
            true
        );
        
        // Main application
        wp_enqueue_script(
            'wp-mcp-app',
            'http://localhost:3000/src/main.tsx',
            array(),
            WP_MCP_VERSION,
            true
        );
        
        // Add module type
        add_filter('script_loader_tag', function($tag, $handle) {
            if (strpos($handle, 'wp-mcp') !== false) {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);
        
        return true;
    }
    
    /**
     * Enqueue production assets (built files)
     */
    private function enqueue_prod_assets() {
        $manifest_path = WP_MCP_PLUGIN_DIR . 'dist/.vite/manifest.json';
        
        if (!file_exists($manifest_path)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('WP MCP: Production assets not found. Please run "npm run build".', 'wp-mcp');
                echo '</p></div>';
            });
            return false;
        }
        
        $manifest = json_decode(file_get_contents($manifest_path), true);
        
        if (isset($manifest['src/main.tsx'])) {
            $entry = $manifest['src/main.tsx'];
            
            // Enqueue main JS file
            wp_enqueue_script(
                'wp-mcp-app',
                WP_MCP_PLUGIN_URL . 'dist/' . $entry['file'],
                array(),
                WP_MCP_VERSION,
                true
            );
            
            // Enqueue CSS file if exists
            if (isset($entry['css'])) {
                foreach ($entry['css'] as $css_file) {
                    wp_enqueue_style(
                        'wp-mcp-styles',
                        WP_MCP_PLUGIN_URL . 'dist/' . $css_file,
                        array(),
                        WP_MCP_VERSION
                    );
                }
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Render chat page
     */
    public function render_chat_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div id="wp-mcp-chat-app" class="wp-mcp-chat" style="height: calc(100vh - 200px); border: 1px solid #ddd; border-radius: 8px; background: white;">
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;">
                    <div style="text-align: center;">
                        <div style="font-size: 24px; margin-bottom: 10px;">🚀</div>
                        <div>Loading MCP Chat...</div>
                        <div style="font-size: 12px; margin-top: 5px;">If this persists, check the browser console for errors.</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_mcp_settings');
                do_settings_sections('wp_mcp_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register setting group
        register_setting('wp_mcp_settings', 'wp_mcp_openai_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('wp_mcp_settings', 'wp_mcp_cloudflare_token', array(
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('wp_mcp_settings', 'wp_mcp_cloudflare_gateway_url', array(
            'sanitize_callback' => 'esc_url_raw',
        ));
        
        
        register_setting('wp_mcp_settings', 'wp_mcp_chat_history_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox'),
        ));
        
        register_setting('wp_mcp_settings', 'wp_mcp_max_messages_per_session', array(
            'sanitize_callback' => 'intval',
        ));
        
        // Add settings section
        add_settings_section(
            'wp_mcp_api_settings',
            __('API Settings', 'wp-mcp'),
            array($this, 'settings_section_callback'),
            'wp_mcp_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'wp_mcp_openai_api_key',
            __('OpenAI API Key', 'wp-mcp'),
            array($this, 'render_api_key_field'),
            'wp_mcp_settings',
            'wp_mcp_api_settings'
        );
        
        add_settings_field(
            'wp_mcp_cloudflare_token',
            __('Cloudflare Bearer Token', 'wp-mcp'),
            array($this, 'render_cloudflare_token_field'),
            'wp_mcp_settings',
            'wp_mcp_api_settings'
        );
        
        add_settings_field(
            'wp_mcp_cloudflare_gateway_url',
            __('Cloudflare AI Gateway URL', 'wp-mcp'),
            array($this, 'render_gateway_url_field'),
            'wp_mcp_settings',
            'wp_mcp_api_settings'
        );
        
        
        add_settings_field(
            'wp_mcp_chat_history_enabled',
            __('Enable Chat History', 'wp-mcp'),
            array($this, 'render_chat_history_field'),
            'wp_mcp_settings',
            'wp_mcp_api_settings'
        );
        
        add_settings_field(
            'wp_mcp_max_messages_per_session',
            __('Max Messages per Session', 'wp-mcp'),
            array($this, 'render_max_messages_field'),
            'wp_mcp_settings',
            'wp_mcp_api_settings'
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure your MCP chat settings below.', 'wp-mcp') . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $value = get_option('wp_mcp_openai_api_key', '');
        echo '<input type="password" name="wp_mcp_openai_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your OpenAI API key for chat functionality. Not required when using Cloudflare AI Gateway.', 'wp-mcp') . '</p>';
    }
    
    /**
     * Render Cloudflare token field
     */
    public function render_cloudflare_token_field() {
        $value = get_option('wp_mcp_cloudflare_token', '');
        echo '<input type="password" name="wp_mcp_cloudflare_token" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your Cloudflare bearer token for AI Gateway authentication (required when using Cloudflare).', 'wp-mcp') . '</p>';
    }
    
    /**
     * Render gateway URL field
     */
    public function render_gateway_url_field() {
        $value = get_option('wp_mcp_cloudflare_gateway_url', '');
        echo '<input type="url" name="wp_mcp_cloudflare_gateway_url" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your Cloudflare AI Gateway endpoint URL (optional).', 'wp-mcp') . '</p>';
    }
    
    /**
     * Render chat history field
     */
    public function render_chat_history_field() {
        $value = get_option('wp_mcp_chat_history_enabled', true);
        echo '<input type="checkbox" name="wp_mcp_chat_history_enabled" value="1" ' . checked(1, $value, false) . ' />';
        echo '<span class="description">' . __('Store chat history in the database.', 'wp-mcp') . '</span>';
    }
    
    /**
     * Render max messages field
     */
    public function render_max_messages_field() {
        $value = get_option('wp_mcp_max_messages_per_session', 100);
        echo '<input type="number" name="wp_mcp_max_messages_per_session" value="' . esc_attr($value) . '" min="10" max="500" class="small-text" />';
        echo '<p class="description">' . __('Maximum number of messages to keep per chat session.', 'wp-mcp') . '</p>';
    }
    
    /**
     * Sanitize checkbox value
     */
    public function sanitize_checkbox($value) {
        return !empty($value) ? 1 : 0;
    }
}