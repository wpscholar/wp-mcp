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

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_MCP_VERSION', '1.0.0' );
define( 'WP_MCP_PLUGIN_FILE', __FILE__ );
define( 'WP_MCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader.
require_once WP_MCP_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize the plugin.
WP_MCP\Plugin::get_instance();
