import React from 'react'
import ReactDOM from 'react-dom/client'
import ChatApp from './chat-app'
import { WordPressConfig } from './types'
import '../styles/globals.css'

// Ensure WordPress global is available
declare global {
  var wpMcp: WordPressConfig;
}

/**
 * Initialize the MCP Chat application
 */
function initializeMCPChat() {
  const container = document.getElementById('wp-mcp-chat-app');
  
  if (!container) {
    console.error('WP MCP: Chat container not found');
    return;
  }

  // Check if WordPress config is available
  if (!window.wpMcp) {
    console.error('WP MCP: WordPress configuration not found');
    container.innerHTML = `
      <div style="padding: 20px; text-align: center; color: #666;">
        <h3>Configuration Error</h3>
        <p>WordPress configuration data is not available.</p>
        <p>Please refresh the page or contact your administrator.</p>
      </div>
    `;
    return;
  }

  const config = window.wpMcp;

  // Validate required configuration
  const requiredFields = ['restUrl', 'nonce', 'currentUser'];
  const missingFields = requiredFields.filter(field => !config[field as keyof WordPressConfig]);
  
  if (missingFields.length > 0) {
    console.error('WP MCP: Missing required configuration fields:', missingFields);
    container.innerHTML = `
      <div style="padding: 20px; text-align: center; color: #666;">
        <h3>Configuration Error</h3>
        <p>Required configuration fields are missing: ${missingFields.join(', ')}</p>
        <p>Please check your WordPress installation and plugin configuration.</p>
      </div>
    `;
    return;
  }

  try {
    // Create React root and render the application
    const root = ReactDOM.createRoot(container);
    
    root.render(
      <React.StrictMode>
        <ChatApp config={config} />
      </React.StrictMode>
    );
  } catch (error) {
    console.error('WP MCP: Failed to initialize chat application:', error);
    
    container.innerHTML = `
      <div style="padding: 20px; text-align: center; color: #666;">
        <h3>Initialization Error</h3>
        <p>Failed to start the chat application.</p>
        <p>Error: ${error instanceof Error ? error.message : 'Unknown error'}</p>
        <p style="font-size: 12px; margin-top: 10px;">
          Check the browser console for more details.
        </p>
      </div>
    `;
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeMCPChat);
} else {
  initializeMCPChat();
}

// Also export the initialization function for manual use
export { initializeMCPChat };