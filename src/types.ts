// MCP Types
export interface MCPClient {
  connect(serverUrl: string): Promise<void>;
  initialize(): Promise<MCPInitializeResult>;
  listTools(): Promise<MCPTool[]>;
  callTool(name: string, args?: Record<string, any>): Promise<MCPToolResult>;
  listResources(): Promise<MCPResource[]>;
  readResource(uri: string): Promise<MCPResourceContent>;
  disconnect(): Promise<void>;
}

export interface MCPInitializeResult {
  protocolVersion: string;
  capabilities: {
    tools?: {};
    resources?: {};
    prompts?: {};
  };
  serverInfo: {
    name: string;
    version: string;
  };
}

export interface MCPTool {
  name: string;
  description: string;
  inputSchema: {
    type: 'object';
    properties: Record<string, any>;
    required?: string[];
  };
}

export interface MCPToolResult {
  content: Array<{
    type: 'text' | 'image' | 'resource';
    text?: string;
    data?: string;
    mimeType?: string;
  }>;
  isError?: boolean;
  meta?: Record<string, any>;
}

export interface MCPResource {
  uri: string;
  name: string;
  description?: string;
  mimeType?: string;
}

export interface MCPResourceContent {
  contents: Array<{
    uri: string;
    mimeType?: string;
    text?: string;
    blob?: string;
  }>;
}

// Chat Types
export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  timestamp: Date;
  toolCalls?: ToolCall[];
  toolResults?: ToolResult[];
}

export interface ToolCall {
  id: string;
  name: string;
  arguments: Record<string, any>;
}

export interface ToolResult {
  id: string;
  result: any;
  error?: string;
}

// OpenAI Types
export interface ChatCompletionMessage {
  role: 'system' | 'user' | 'assistant' | 'tool';
  content: string;
  tool_calls?: Array<{
    id: string;
    type: 'function';
    function: {
      name: string;
      arguments: string;
    };
  }>;
  tool_call_id?: string;
}

export interface ChatCompletionRequest {
  model: string;
  messages: ChatCompletionMessage[];
  tools?: Array<{
    type: 'function';
    function: {
      name: string;
      description: string;
      parameters: Record<string, any>;
    };
  }>;
  tool_choice?: 'auto' | 'none' | { type: 'function'; function: { name: string } };
  stream?: boolean;
  max_tokens?: number;
  temperature?: number;
}

// WordPress Types
export interface WordPressConfig {
  restUrl: string;
  mcpUrl: string;
  nonce: string;
  currentUser: {
    ID: number;
    display_name: string;
    user_email: string;
  };
  ajaxUrl: string;
  pluginUrl: string;
  isDebug: boolean;
}

export interface WordPressAPIResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
}

// Plugin Settings
export interface PluginSettings {
  mcp_server_url: string;
  cloudflare_gateway_url: string;
  chat_history_enabled: boolean;
  max_messages_per_session: number;
  openai_api_key: string; // Will be masked as '***' in frontend
}

// Chat Session
export interface ChatSession {
  id: string;
  messages: ChatMessage[];
  createdAt: Date;
  updatedAt: Date;
}

// Error Types
export class MCPError extends Error {
  constructor(
    message: string,
    public code?: string,
    public details?: any
  ) {
    super(message);
    this.name = 'MCPError';
  }
}

export class OpenAIError extends Error {
  constructor(
    message: string,
    public status?: number,
    public code?: string
  ) {
    super(message);
    this.name = 'OpenAIError';
  }
}

// Event Types
export type ChatEvent = 
  | { type: 'message'; data: ChatMessage }
  | { type: 'typing_start' }
  | { type: 'typing_stop' }
  | { type: 'tool_call_start'; data: ToolCall }
  | { type: 'tool_call_end'; data: ToolResult }
  | { type: 'error'; data: Error };

export type MCPEvent =
  | { type: 'connected' }
  | { type: 'disconnected' }
  | { type: 'initialized'; data: MCPInitializeResult }
  | { type: 'tools_updated'; data: MCPTool[] }
  | { type: 'resources_updated'; data: MCPResource[] }
  | { type: 'error'; data: MCPError };