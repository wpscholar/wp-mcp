# WordPress MCP Chat Client

A WordPress plugin that provides an AI-powered chat interface using the Model Context Protocol (MCP) to interact with your WordPress site through natural language.

## Features

- 🤖 **AI-Powered Chat**: Uses OpenAI's GPT models with Cloudflare AI Gateway support
- 🔧 **MCP Integration**: Leverages the Model Context Protocol to interact with WordPress
- 📝 **WordPress Tools**: Create posts, manage users, access site information via natural language
- 🎨 **Modern UI**: Built with React, TypeScript, and shadcn/ui components
- ⚡ **Fast Development**: Vite build system with hot module replacement
- 🔒 **Secure**: WordPress capability-based access control and sanitized inputs

## Architecture

- **Frontend**: React + TypeScript + shadcn/ui + Tailwind CSS
- **Backend**: PHP (WordPress) + Node.js build process
- **MCP**: TypeScript SDK + WordPress MCP Adapter
- **AI**: OpenAI SDK → Cloudflare AI Gateway
- **Build**: Vite for modern React development

## Prerequisites

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Node.js 18.0 or higher
- Composer
- OpenAI API key
- (Optional) Cloudflare AI Gateway endpoint

## Installation

1. **Clone or download the plugin** to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone <repository-url> wp-mcp
   cd wp-mcp
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**:
   ```bash
   npm install
   ```

4. **Build the frontend assets**:
   ```bash
   # For development
   npm run dev
   
   # For production
   npm run build
   ```

5. **Activate the plugin** in your WordPress admin dashboard.

## Configuration

### 1. WordPress MCP Adapter

The plugin requires the WordPress MCP Adapter to be installed and configured. This should be automatically installed via Composer, but you may need to configure it separately.

### 2. Plugin Settings

1. Go to **MCP Chat → Settings** in your WordPress admin
2. Configure the following settings:

   - **OpenAI API Key**: Your OpenAI API key (required)
   - **Cloudflare AI Gateway URL**: Optional Cloudflare endpoint for API calls
   - **MCP Server URL**: Usually auto-configured to your WordPress REST API
   - **Chat History**: Enable/disable chat history storage
   - **Max Messages**: Maximum messages per chat session

### 3. API Configuration

The plugin supports two AI configuration options:

#### Option A: Direct OpenAI API
- **API Key**: Your OpenAI API key
- **Base URL**: Leave empty (uses https://api.openai.com/v1)

#### Option B: Cloudflare AI Gateway
- **API Key**: Your OpenAI API key
- **Base URL**: Your Cloudflare AI Gateway endpoint
  - Format: `https://gateway.ai.cloudflare.com/v1/{account_id}/{gateway_slug}/openai`

## Usage

### Accessing the Chat

1. Go to **MCP Chat** in your WordPress admin menu
2. The chat interface will initialize and connect to the MCP server
3. Start chatting with natural language commands

### Example Commands

- **Create Content**: "Create a new blog post about artificial intelligence"
- **Manage Posts**: "List my recent posts" or "Show me draft posts"
- **Site Information**: "What's my site information?" or "Show me the active theme"
- **User Management**: "List all users" or "Show user information"

### Available MCP Tools

The plugin automatically discovers and uses MCP tools from your WordPress installation:

- `wp-mcp/create-post`: Create new WordPress posts with title, content, status, and taxonomy terms
- `wp-mcp/list-posts`: List WordPress posts with filters (type, status, search, category, author)
- `wp-mcp/get-post`: Get detailed information about a specific post including content and metadata
- `wp-mcp/get-site-info`: Get WordPress site information including name, URL, version, theme, and statistics

## Development

### Development Mode

1. **Start the Vite dev server**:
   ```bash
   npm run dev
   ```

2. **Enable WordPress debug mode** in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   ```

3. The plugin will automatically load assets from the Vite dev server for hot reloading.

### Building for Production

```bash
npm run build
```

This creates optimized assets in the `dist/` directory that WordPress will automatically load.

### Code Structure

```
wp-mcp/
├── wp-mcp.php                 # Main plugin file
├── includes/                  # PHP classes
│   ├── Plugin.php            # Main plugin class
│   ├── Admin.php             # Admin interface
│   ├── RestApi.php           # REST API endpoints
│   └── Abilities.php         # MCP abilities registration
├── src/                      # TypeScript source
│   ├── main.tsx             # Entry point
│   ├── chat-app.tsx         # Main React component
│   ├── mcp-client.ts        # MCP client
│   ├── openai-client.ts     # OpenAI integration
│   └── types.ts             # TypeScript interfaces
├── components/               # React components
│   ├── ui/                  # shadcn/ui components
│   └── chat/                # Chat-specific components
└── styles/                  # CSS files
```

## Troubleshooting

### Common Issues

1. **"WordPress MCP Adapter not found"**
   - Run `composer install` to install the MCP adapter
   - Ensure your PHP version meets requirements

2. **"Production assets not found"**
   - Run `npm run build` to create production assets
   - Check that the `dist/` directory exists

3. **"OpenAI Configuration Required"**
   - Add your OpenAI API key in the plugin settings
   - Verify the API key is valid and has sufficient credits

4. **Chat not loading**
   - Check browser console for JavaScript errors
   - Ensure WordPress nonce and REST API are working
   - Verify the MCP server is accessible

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check WordPress logs and browser console for error details.

## Security

- All API calls are authenticated using WordPress nonces
- User capabilities are checked for admin access
- Input sanitization and validation on all endpoints
- OpenAI API keys are masked in frontend configuration
- MCP tool execution respects WordPress permissions

## REST API Endpoints

The plugin registers the following REST API endpoints under the `wp-mcp/v1` namespace:

| Endpoint | Method | Description | Permission |
|----------|--------|-------------|------------|
| `/chat` | POST | Save a chat message to history | `read` capability |
| `/chat/history` | GET | Retrieve chat history for a session | `read` capability |
| `/ai/chat/completions` | POST | Proxy requests to OpenAI/Cloudflare | `read` capability |
| `/settings` | GET | Get plugin settings | `manage_options` capability |

## Hooks & Filters

### Filters

**`wp_mcp_allowed_post_types`**
Filter the post types that can be created via MCP tools.
```php
add_filter( 'wp_mcp_allowed_post_types', function( $types ) {
    $types[] = 'custom_post_type';
    return $types;
});
```

**`wp_mcp_session_retention_days`**
Control how long chat sessions are retained before cleanup (default: 30 days).
```php
add_filter( 'wp_mcp_session_retention_days', function( $days ) {
    return 7; // Keep sessions for 7 days
});
```

**`wp_mcp_vite_dev_url`**
Override the Vite development server URL.
```php
add_filter( 'wp_mcp_vite_dev_url', function( $url ) {
    return 'http://localhost:3000';
});
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Support

For issues and feature requests, please use the GitHub issues page or contact the plugin author.