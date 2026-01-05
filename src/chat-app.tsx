import { useState, useEffect, useRef } from 'react';
import { Chat } from '@/components/chat/chat';
import { Button } from '@/components/ui/button';
import { 
  ChatMessage, 
  ToolResult, 
  WordPressConfig, 
  PluginSettings,
  MCPTool 
} from './types';
import { WordPressMCPClient } from './mcp-client';
import { CloudflareOpenAIClient } from './openai-client';
import { Settings, RefreshCw, Zap, Database } from 'lucide-react';

interface ChatAppProps {
  config: WordPressConfig;
}

export function ChatApp({ config }: ChatAppProps) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [settings, setSettings] = useState<PluginSettings | null>(null);
  const [mcpClient] = useState(() => new WordPressMCPClient(config));
  const [openaiClient, setOpenaiClient] = useState<CloudflareOpenAIClient | null>(null);
  const [connectionStatus, setConnectionStatus] = useState<'disconnected' | 'connecting' | 'connected'>('disconnected');
  const [tools, setTools] = useState<MCPTool[]>([]);
  const [sessionId] = useState(() => {
    // Try to restore session ID from localStorage, or create a new one
    const storedSessionId = localStorage.getItem('wp_mcp_session_id');
    if (storedSessionId) {
      return storedSessionId;
    }
    const newSessionId = crypto.randomUUID();
    localStorage.setItem('wp_mcp_session_id', newSessionId);
    return newSessionId;
  });
  const abortControllerRef = useRef<AbortController | null>(null);

  // Load settings and chat history on mount
  useEffect(() => {
    loadSettings();
    loadChatHistory();
  }, []);

  // Initialize OpenAI client when settings change
  useEffect(() => {
    if (!settings) return;

    // Check if AI is configured using masked settings values.
    // The actual API keys are never sent to the client for security.
    // All AI requests go through the server-side proxy which has the real keys.
    const hasOpenAIKey = settings.openai_api_key === '***';
    const hasCloudflareGateway = settings.cloudflare_gateway_url && settings.cloudflare_gateway_url.trim() !== '';

    if (hasOpenAIKey || hasCloudflareGateway) {
      const openaiConfig: any = {
        // Use a placeholder key - the real key is used server-side
        apiKey: 'server-side-proxy',
        model: hasCloudflareGateway ? 'openai/gpt-4o-mini' : 'gpt-4o-mini',
      };

      // All AI calls go through the WordPress proxy endpoint which handles authentication
      const client = new CloudflareOpenAIClient(openaiConfig, config);
      setOpenaiClient(client);
    } else {
      setOpenaiClient(null);
    }
  }, [settings, config]);

  // Initialize MCP client
  useEffect(() => {
    if (settings?.mcp_server_url) {
      initializeMCP();
    }
  }, [settings]);

  const loadSettings = async () => {
    try {
      const response = await fetch(`${config.restUrl}settings`, {
        headers: {
          'X-WP-Nonce': config.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setSettings(data.settings);
        }
      }
    } catch (error) {
      console.error('Failed to load settings:', error);
    }
  };

  const loadChatHistory = async () => {
    try {
      const response = await fetch(`${config.restUrl}chat/history?session_id=${sessionId}&limit=50`, {
        headers: {
          'X-WP-Nonce': config.nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success && data.history && data.history.length > 0) {
          const loadedMessages: ChatMessage[] = data.history.map((item: any) => ({
            id: item.id || `loaded-${Date.now()}-${Math.random()}`,
            role: item.role as 'user' | 'assistant',
            content: item.content,
            timestamp: new Date(item.timestamp),
          }));
          setMessages(loadedMessages);
        }
      }
    } catch (error) {
      console.error('Failed to load chat history:', error);
    }
  };

  const saveMessage = async (content: string, role: 'user' | 'assistant') => {
    try {
      await fetch(`${config.restUrl}chat`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': config.nonce,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          message: content,
          session_id: sessionId,
          role: role,
        }),
      });
    } catch (error) {
      console.error('Failed to save message:', error);
    }
  };

  const initializeMCP = async () => {
    if (!settings?.mcp_server_url) return;

    try {
      setConnectionStatus('connecting');
      
      await mcpClient.connect(settings.mcp_server_url);
      await mcpClient.initialize();
      
      const availableTools = await mcpClient.listTools();
      setTools(availableTools);
      
      setConnectionStatus('connected');
      
      // Add welcome message
      if (messages.length === 0) {
        const welcomeMessage: ChatMessage = {
          id: `system-${Date.now()}`,
          role: 'assistant',
          content: 'How can I help you?',
          timestamp: new Date(),
        };
        setMessages([welcomeMessage]);
      }
    } catch (error) {
      console.error('Failed to initialize MCP:', error);
      setConnectionStatus('disconnected');
      
      const errorMessage: ChatMessage = {
        id: `error-${Date.now()}`,
        role: 'assistant',
        content: `❌ **Connection Failed**

I couldn't connect to the WordPress MCP server. This might be due to:

• Missing or invalid API configuration
• Network connectivity issues
• Server-side MCP adapter not properly installed

Please check your settings and ensure the WordPress MCP Adapter is installed and configured.`,
        timestamp: new Date(),
      };
      setMessages([errorMessage]);
    }
  };

  const handleSendMessage = async (content: string) => {
    if (!openaiClient) {
      const errorMessage: ChatMessage = {
        id: `error-${Date.now()}`,
        role: 'assistant',
        content: '❌ **OpenAI Configuration Required**\n\nPlease configure your OpenAI API key in the plugin settings to start chatting.',
        timestamp: new Date(),
      };
      setMessages(prev => [...prev, errorMessage]);
      return;
    }

    const userMessage: ChatMessage = {
      id: `user-${Date.now()}`,
      role: 'user',
      content,
      timestamp: new Date(),
    };

    setMessages(prev => [...prev, userMessage]);
    saveMessage(content, 'user'); // Persist user message
    setIsLoading(true);

    // Create abort controller for this request
    abortControllerRef.current = new AbortController();

    try {
      // Get recent message context (last 10 messages)
      const recentMessages = [...messages, userMessage].slice(-10);
      
      // Send message to AI with available MCP tools
      const response = await openaiClient.sendMessage(
        content,
        recentMessages,
        tools
      );

      let assistantMessage: ChatMessage = {
        id: `assistant-${Date.now()}`,
        role: 'assistant',
        content: response.message,
        timestamp: new Date(),
        toolCalls: response.toolCalls,
      };

      // If there are tool calls, execute them
      if (response.toolCalls && response.toolCalls.length > 0) {
        const toolResults: ToolResult[] = [];

        for (const toolCall of response.toolCalls) {
          try {
            const result = await mcpClient.callTool(toolCall.name, toolCall.arguments);
            toolResults.push({
              id: toolCall.id,
              result: result.content,
            });
          } catch (error) {
            toolResults.push({
              id: toolCall.id,
              result: null,
              error: error instanceof Error ? error.message : String(error),
            });
          }
        }

        assistantMessage.toolResults = toolResults;

        // Send tool results back to AI to generate a natural language summary
        if (toolResults.some(r => !r.error)) {
          try {
            // Format tool results as a message the AI can understand
            const toolResultsSummary = toolResults.map(r => {
              if (r.error) {
                return `Tool ${r.id} failed: ${r.error}`;
              }
              const resultText = Array.isArray(r.result)
                ? r.result.map((item: any) => item.text || JSON.stringify(item)).join('\n')
                : JSON.stringify(r.result);
              return resultText;
            }).join('\n\n');

            const followUpResponse = await openaiClient.sendMessage(
              `Here are the results from the tool execution:\n\n${toolResultsSummary}\n\nPlease provide a helpful summary of these results for the user.`,
              recentMessages.slice(0, -1), // Context without tools
              [] // No tools needed for summary
            );

            if (followUpResponse.message.trim()) {
              assistantMessage.content = followUpResponse.message;
            }
          } catch (followUpError) {
            console.error('Follow-up response failed:', followUpError);
          }
        }
      }

      setMessages(prev => [...prev, assistantMessage]);
      saveMessage(assistantMessage.content, 'assistant'); // Persist assistant message
    } catch (error: any) {
      if (error.name === 'AbortError') {
        return; // Request was cancelled
      }

      console.error('Chat error:', error);
      
      const errorMessage: ChatMessage = {
        id: `error-${Date.now()}`,
        role: 'assistant',
        content: `❌ **Error**: ${error.message || 'Something went wrong. Please try again.'}`,
        timestamp: new Date(),
      };
      
      setMessages(prev => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
      abortControllerRef.current = null;
    }
  };

  const handleStop = () => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
  };

  const refreshConnection = async () => {
    await initializeMCP();
  };

  const getConnectionStatusIcon = () => {
    switch (connectionStatus) {
      case 'connected':
        return <Zap className="h-4 w-4 text-green-500" />;
      case 'connecting':
        return <RefreshCw className="h-4 w-4 text-yellow-500 animate-spin" />;
      case 'disconnected':
        return <Database className="h-4 w-4 text-red-500" />;
    }
  };

  const getConnectionStatusText = () => {
    switch (connectionStatus) {
      case 'connected':
        return `Connected • ${tools.length} tools available`;
      case 'connecting':
        return 'Connecting to MCP server...';
      case 'disconnected':
        return 'Disconnected from MCP server';
    }
  };

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b bg-background">
        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1">
            {getConnectionStatusIcon()}
            <span className="text-sm font-medium">MCP Chat</span>
          </div>
          <div className="text-xs text-muted-foreground">
            {getConnectionStatusText()}
          </div>
        </div>
        
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={refreshConnection}
            disabled={connectionStatus === 'connecting'}
            className="h-8"
          >
            <RefreshCw className={`h-3 w-3 ${connectionStatus === 'connecting' ? 'animate-spin' : ''}`} />
          </Button>
          
          <Button
            variant="outline"
            size="sm"
            onClick={() => window.open(`${config.ajaxUrl.replace('admin-ajax.php', '')}admin.php?page=wp-mcp-settings`, '_blank')}
            className="h-8"
          >
            <Settings className="h-3 w-3" />
          </Button>
        </div>
      </div>

      {/* Chat Interface */}
      <div className="flex-1 overflow-hidden">
        <Chat
          messages={messages}
          onSendMessage={handleSendMessage}
          isLoading={isLoading}
          onStop={handleStop}
          disabled={!openaiClient || connectionStatus !== 'connected'}
        />
      </div>

      {/* Status Bar */}
      <div className="px-4 py-2 border-t bg-muted/50 text-xs text-muted-foreground">
        <div className="flex items-center justify-between">
          <div>
            {settings?.openai_api_key !== '***' ? (
              <span className="text-green-600">✓ OpenAI configured</span>
            ) : (
              <span className="text-orange-600">⚠ OpenAI API key required</span>
            )}
          </div>
          <div>
            Messages: {messages.filter(m => m.role !== 'system').length}
            {settings?.chat_history_enabled && (
              <span className="ml-2">• History enabled</span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

export default ChatApp;