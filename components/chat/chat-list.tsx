import { useRef, useEffect } from 'react'
import { ChatMessage, type Message } from './chat-message'

interface ChatListProps {
  messages: Message[]
  isLoading?: boolean
}

export function ChatList({ messages, isLoading }: ChatListProps) {
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  return (
    <div className="h-full overflow-y-auto">
      {messages.length > 0 ? (
        <div className="pb-4 px-4">
          {messages.map((message) => (
            <ChatMessage key={message.id} message={message} />
          ))}
          {isLoading && (
            <div className="flex justify-center py-4">
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <div className="h-2 w-2 animate-pulse rounded-full bg-current" />
                <div className="h-2 w-2 animate-pulse rounded-full bg-current [animation-delay:0.2s]" />
                <div className="h-2 w-2 animate-pulse rounded-full bg-current [animation-delay:0.4s]" />
                <span>AI is thinking...</span>
              </div>
            </div>
          )}
          <div ref={bottomRef} />
        </div>
      ) : (
        <div className="flex h-full items-center justify-center">
          <div className="text-center space-y-4">
            <div className="text-4xl">🤖</div>
            <div>
              <h2 className="text-xl font-semibold">WordPress MCP Chat</h2>
              <p className="text-muted-foreground mt-2">
                Start a conversation to interact with your WordPress site using AI and MCP tools.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}