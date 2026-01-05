import OpenAI from 'openai';
import { 
  OpenAIConfig,
  ChatCompletionRequest,
  ChatMessage,
  ToolCall,
  OpenAIError,
  MCPTool,
  WordPressConfig
} from './types';

/**
 * OpenAI client with Cloudflare AI Gateway support
 */
export class CloudflareOpenAIClient {
  private openai: OpenAI;
  private config: OpenAIConfig;

  constructor(
    config: OpenAIConfig,
    private wpConfig: WordPressConfig
  ) {
    this.config = config;
    
    // Use WordPress proxy endpoint to avoid CORS issues
    // The proxy will handle the Cloudflare AI Gateway authentication
    const clientConfig: any = {
      apiKey: 'proxy', // Using WordPress proxy, no direct API key needed
      baseURL: `${this.wpConfig.restUrl}ai`, // Use WordPress REST API as proxy
      dangerouslyAllowBrowser: true, // Required for browser usage
      defaultHeaders: {
        'X-WP-Nonce': this.wpConfig.nonce, // WordPress authentication
      },
    };

    this.openai = new OpenAI(clientConfig);
  }

  /**
   * Update configuration
   */
  updateConfig(config: Partial<OpenAIConfig>): void {
    this.config = { ...this.config, ...config };
    
    // Continue using WordPress proxy endpoint
    const clientConfig: any = {
      apiKey: 'proxy', // Using WordPress proxy
      baseURL: `${this.wpConfig.restUrl}ai`, // Use WordPress REST API as proxy
      dangerouslyAllowBrowser: true,
      defaultHeaders: {
        'X-WP-Nonce': this.wpConfig.nonce, // WordPress authentication
      },
    };

    this.openai = new OpenAI(clientConfig);
  }

  /**
   * Send a chat completion request
   */
  async createChatCompletion(request: ChatCompletionRequest): Promise<any> {
    try {
      const response = await this.openai.chat.completions.create({
        model: request.model || this.config.model || 'gpt-4',
        messages: request.messages as any,
        tools: request.tools,
        tool_choice: request.tool_choice,
        stream: request.stream,
        max_tokens: request.max_tokens,
        temperature: request.temperature,
      });

      return response;
    } catch (error: any) {
      throw new OpenAIError(
        error.message || 'OpenAI API request failed',
        error.status,
        error.code
      );
    }
  }

  /**
   * Create a streaming chat completion
   */
  async createStreamingCompletion(
    request: ChatCompletionRequest,
    onChunk: (chunk: any) => void,
    onComplete: (message: string) => void,
    onError: (error: Error) => void
  ): Promise<void> {
    try {
      const stream = await this.openai.chat.completions.create({
        ...request,
        messages: request.messages as any,
        stream: true,
      });

      let fullMessage = '';

      for await (const chunk of stream) {
        const delta = chunk.choices[0]?.delta;
        
        if (delta?.content) {
          fullMessage += delta.content;
          onChunk({
            type: 'content',
            content: delta.content,
          });
        }

        if (delta?.tool_calls) {
          onChunk({
            type: 'tool_calls',
            tool_calls: delta.tool_calls,
          });
        }

        if (chunk.choices[0]?.finish_reason) {
          onComplete(fullMessage);
          break;
        }
      }
    } catch (error: any) {
      onError(new OpenAIError(
        error.message || 'Streaming request failed',
        error.status,
        error.code
      ));
    }
  }

  /**
   * Convert chat messages to OpenAI format
   */
  convertMessagesToOpenAI(messages: ChatMessage[]): any[] {
    const openaiMessages: any[] = [];

    for (const message of messages) {
      if (message.role === 'system' || message.role === 'user') {
        openaiMessages.push({
          role: message.role,
          content: message.content,
        });
      } else if (message.role === 'assistant') {
        const assistantMessage: any = {
          role: 'assistant',
          content: message.content,
        };

        // Add tool calls if present
        if (message.toolCalls && message.toolCalls.length > 0) {
          assistantMessage.tool_calls = message.toolCalls.map(call => ({
            id: call.id,
            type: 'function',
            function: {
              name: call.name,
              arguments: JSON.stringify(call.arguments),
            },
          }));
        }

        openaiMessages.push(assistantMessage);

        // Add tool results as separate tool messages
        if (message.toolResults && message.toolResults.length > 0) {
          for (const result of message.toolResults) {
            openaiMessages.push({
              role: 'tool',
              content: result.error || JSON.stringify(result.result),
              tool_call_id: result.id,
            });
          }
        }
      }
    }

    return openaiMessages;
  }

  /**
   * Convert MCP tools to OpenAI tools format
   */
  convertMCPToolsToOpenAI(mcpTools: MCPTool[]): any[] {
    return mcpTools.map(tool => ({
      type: 'function',
      function: {
        name: tool.name,
        description: tool.description,
        parameters: tool.inputSchema,
      },
    }));
  }

  /**
   * Process tool calls from OpenAI response
   */
  processToolCalls(toolCalls: any[]): ToolCall[] {
    return toolCalls.map(call => ({
      id: call.id,
      name: call.function.name,
      arguments: JSON.parse(call.function.arguments || '{}'),
    }));
  }

  /**
   * Send a simple chat message
   */
  async sendMessage(
    message: string,
    context: ChatMessage[] = [],
    tools: MCPTool[] = []
  ): Promise<{
    message: string;
    toolCalls?: ToolCall[];
  }> {
    const messages = this.convertMessagesToOpenAI([
      ...context,
      {
        id: `user-${Date.now()}`,
        role: 'user',
        content: message,
        timestamp: new Date(),
      },
    ]);

    const request: ChatCompletionRequest = {
      model: this.config.model || 'gpt-4',
      messages,
      tools: tools.length > 0 ? this.convertMCPToolsToOpenAI(tools) : undefined,
      tool_choice: tools.length > 0 ? 'auto' : undefined,
      temperature: 0.7,
      max_tokens: 2000,
    };

    try {
      const response = await this.createChatCompletion(request);
      const choice = response.choices[0];
      
      if (!choice) {
        throw new OpenAIError('No response from OpenAI');
      }

      const result: {
        message: string;
        toolCalls?: ToolCall[];
      } = {
        message: choice.message.content || '',
      };

      if (choice.message.tool_calls) {
        result.toolCalls = this.processToolCalls(choice.message.tool_calls);
      }

      return result;
    } catch (error) {
      if (error instanceof OpenAIError) {
        throw error;
      }
      throw new OpenAIError(`Failed to send message: ${error}`);
    }
  }

  /**
   * Get available models (if supported by the endpoint)
   */
  async getAvailableModels(): Promise<string[]> {
    try {
      const response = await this.openai.models.list();
      return response.data.map(model => model.id);
    } catch (error) {
      // If models endpoint is not available (e.g., on Cloudflare Gateway),
      // return common OpenAI models
      return [
        'gpt-4',
        'gpt-4-turbo',
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-3.5-turbo',
      ];
    }
  }

  /**
   * Test the connection
   */
  async testConnection(): Promise<boolean> {
    try {
      const response = await this.sendMessage('Hello, this is a connection test.');
      return !!response.message;
    } catch (error) {
      console.error('Connection test failed:', error);
      return false;
    }
  }

  /**
   * Get usage statistics (if available)
   */
  async getUsage(): Promise<any> {
    // This would depend on the specific Cloudflare Gateway or OpenAI API features
    // For now, return a placeholder
    return {
      message: 'Usage statistics not available in browser environment',
    };
  }

  /**
   * Create a system message for WordPress context
   */
  createWordPressSystemMessage(): any {
    return {
      role: 'system',
      content: `You are a helpful AI assistant integrated into a WordPress site. You can interact with WordPress through MCP (Model Context Protocol) tools.

Available capabilities:
- Create, read, update, and manage WordPress posts and pages
- Manage users and user roles
- Access site information and settings
- Interact with the WordPress database through safe, predefined tools

Guidelines:
- Always be helpful and provide accurate information
- When performing WordPress actions, explain what you're doing
- Ask for confirmation before making significant changes
- Respect user permissions and WordPress security
- Provide clear, actionable responses

Site Information:
- Site URL: ${this.wpConfig.restUrl.replace('/wp-json/', '')}
- Current User: ${this.wpConfig.currentUser.display_name}
- WordPress REST API: ${this.wpConfig.restUrl}

You should use the available MCP tools to interact with WordPress when users request actions like creating posts, managing content, or retrieving site information.`,
    };
  }
}