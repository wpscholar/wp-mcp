import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { 
  MCPClient, 
  MCPInitializeResult, 
  MCPTool, 
  MCPToolResult, 
  MCPResource, 
  MCPResourceContent,
  MCPError,
  MCPEvent,
  WordPressConfig
} from './types';

/**
 * WordPress MCP Client implementation using the official TypeScript SDK
 */
export class WordPressMCPClient implements MCPClient {
  private client: Client;
  private transport: StreamableHTTPClientTransport | null = null;
  private connected = false;
  private tools: MCPTool[] = [];
  private resources: MCPResource[] = [];
  private eventListeners: Map<string, Set<(event: MCPEvent) => void>> = new Map();

  constructor(
    private config: WordPressConfig
  ) {
    // Initialize the MCP Client using the official SDK
    this.client = new Client(
      {
        name: 'wp-mcp-chat-client',
        version: '1.0.0',
      },
      {
        capabilities: {}
      }
    );
  }

  /**
   * Add event listener
   */
  on(event: string, listener: (event: MCPEvent) => void): void {
    if (!this.eventListeners.has(event)) {
      this.eventListeners.set(event, new Set());
    }
    this.eventListeners.get(event)!.add(listener);
  }

  /**
   * Remove event listener
   */
  off(event: string, listener: (event: MCPEvent) => void): void {
    const listeners = this.eventListeners.get(event);
    if (listeners) {
      listeners.delete(listener);
    }
  }

  /**
   * Emit event
   */
  private emit(event: MCPEvent): void {
    const listeners = this.eventListeners.get(event.type);
    if (listeners) {
      listeners.forEach(listener => {
        try {
          listener(event);
        } catch (error) {
          console.error('Error in MCP event listener:', error);
        }
      });
    }
  }

  /**
   * Connect to the MCP server using official SDK StreamableHTTPClientTransport
   */
  async connect(_serverUrl: string): Promise<void> {
    try {
      // Use the direct MCP endpoint URL from WordPress configuration
      const mcpEndpoint = this.config.mcpUrl;
      console.log('Connecting to MCP endpoint:', mcpEndpoint);
      
      // Create HTTP transport with WordPress authentication headers
      this.transport = new StreamableHTTPClientTransport(new URL(mcpEndpoint), {
        requestInit: {
          headers: {
            'X-WP-Nonce': this.config.nonce,
            'Content-Type': 'application/json',
          },
        },
      });
      
      // Connect using the official SDK
      await this.client.connect(this.transport);

      this.connected = true;
      this.emit({ type: 'connected' });
      
      console.log('Connected to WordPress MCP Adapter via SDK');
    } catch (error) {
      const mcpError = error instanceof MCPError ? error : new MCPError(`Connection failed: ${error}`);
      this.emit({ type: 'error', data: mcpError });
      throw mcpError;
    }
  }

  /**
   * Initialize the MCP session - SDK handles this automatically after connect
   */
  async initialize(): Promise<MCPInitializeResult> {
    if (!this.connected) {
      throw new MCPError('Not connected to MCP server');
    }

    try {
      // The SDK has already handled initialization during connect()
      // Let's get the server info and load tools/resources
      console.log('MCP Client initialized via SDK');
      
      // Load initial tools and resources using SDK methods
      await Promise.all([
        this.loadTools(),
        this.loadResources(),
      ]);

      // Create a compatible result object
      const initResult: MCPInitializeResult = {
        protocolVersion: '2025-06-18',
        capabilities: {
          tools: {},
          resources: {},
          prompts: {},
        },
        serverInfo: {
          name: 'WordPress MCP Server',
          version: '1.0.0',
        },
      };
      
      this.emit({ type: 'initialized', data: initResult });
      
      return initResult;
    } catch (error) {
      const mcpError = error instanceof MCPError ? error : new MCPError(`Initialization failed: ${error}`);
      this.emit({ type: 'error', data: mcpError });
      throw mcpError;
    }
  }

  /**
   * List available tools
   */
  async listTools(): Promise<MCPTool[]> {
    if (!this.connected) {
      throw new MCPError('Not connected to MCP server');
    }

    return this.tools;
  }

  /**
   * Load tools using the official MCP SDK
   */
  private async loadTools(): Promise<void> {
    try {
      // Use the SDK's listTools method - it handles all the protocol details
      const result = await this.client.listTools();
      console.log('Tools loaded via SDK:', result);

      // Convert SDK tools format to our internal format
      this.tools = result.tools.map(tool => ({
        name: tool.name,
        description: tool.description || '',
        inputSchema: {
          type: 'object' as const,
          properties: tool.inputSchema?.properties || {},
          required: tool.inputSchema?.required || [],
        },
      }));

      this.emit({ type: 'tools_updated', data: this.tools });
      console.log(`Loaded ${this.tools.length} tools via MCP SDK`);
    } catch (error) {
      console.error('Failed to load tools via SDK:', error);
      this.tools = [];
    }
  }

  /**
   * Call a tool using the official MCP SDK
   */
  async callTool(name: string, args: Record<string, any> = {}): Promise<MCPToolResult> {
    if (!this.connected) {
      throw new MCPError('Not connected to MCP server');
    }

    try {
      console.log(`Calling tool "${name}" with args:`, args);
      
      // Use the SDK's callTool method - it handles all the protocol details
      const result = await this.client.callTool({ name, arguments: args });
      console.log(`Tool "${name}" result:`, result);

      // Convert SDK result format to our internal format
      const toolResult: MCPToolResult = {
        content: Array.isArray(result.content) ? result.content : [],
        isError: Boolean(result.isError),
        meta: result.meta || {},
      };

      return toolResult;
    } catch (error) {
      console.error(`Tool "${name}" call failed:`, error);
      const mcpError = error instanceof MCPError ? error : new MCPError(`Tool call failed: ${error}`);
      this.emit({ type: 'error', data: mcpError });
      throw mcpError;
    }
  }

  /**
   * List available resources
   */
  async listResources(): Promise<MCPResource[]> {
    if (!this.connected) {
      throw new MCPError('Not connected to MCP server');
    }

    return this.resources;
  }

  /**
   * Load resources using the official MCP SDK
   */
  private async loadResources(): Promise<void> {
    try {
      // Use the SDK's listResources method - it handles all the protocol details
      const result = await this.client.listResources();
      console.log('Resources loaded via SDK:', result);

      // Convert SDK resources format to our internal format
      this.resources = result.resources.map(resource => ({
        uri: resource.uri,
        name: resource.name || '',
        description: resource.description,
        mimeType: resource.mimeType,
      }));

      this.emit({ type: 'resources_updated', data: this.resources });
      console.log(`Loaded ${this.resources.length} resources via MCP SDK`);
    } catch (error) {
      console.error('Failed to load resources via SDK:', error);
      this.resources = [];
    }
  }

  /**
   * Read a resource using the official MCP SDK
   */
  async readResource(uri: string): Promise<MCPResourceContent> {
    if (!this.connected) {
      throw new MCPError('Not connected to MCP server');
    }

    try {
      console.log(`Reading resource: ${uri}`);
      
      // Use the SDK's readResource method - it handles all the protocol details
      const result = await this.client.readResource({ uri });
      console.log(`Resource "${uri}" content:`, result);

      return result;
    } catch (error) {
      console.error(`Resource "${uri}" read failed:`, error);
      const mcpError = error instanceof MCPError ? error : new MCPError(`Resource read failed: ${error}`);
      this.emit({ type: 'error', data: mcpError });
      throw mcpError;
    }
  }

  /**
   * Disconnect from the MCP server using the official SDK
   */
  async disconnect(): Promise<void> {
    try {
      // Use the SDK's disconnect method
      if (this.transport) {
        await this.client.close();
        this.transport = null;
      }
      
      this.connected = false;
      this.tools = [];
      this.resources = [];
      this.emit({ type: 'disconnected' });
      console.log('Disconnected from MCP server via SDK');
    } catch (error) {
      console.error('Error during SDK disconnect:', error);
    }
  }

  /**
   * Get connection status
   */
  isConnected(): boolean {
    return this.connected;
  }

  /**
   * Get available tools (cached)
   */
  getTools(): MCPTool[] {
    return [...this.tools];
  }

  /**
   * Get available resources (cached)
   */
  getResources(): MCPResource[] {
    return [...this.resources];
  }

  /**
   * Convert MCP tool to OpenAI function format
   */
  toolToOpenAIFunction(tool: MCPTool) {
    return {
      type: 'function' as const,
      function: {
        name: tool.name,
        description: tool.description,
        parameters: tool.inputSchema,
      },
    };
  }

  /**
   * Convert all tools to OpenAI functions format
   */
  getToolsForOpenAI() {
    return this.tools.map(tool => this.toolToOpenAIFunction(tool));
  }
}