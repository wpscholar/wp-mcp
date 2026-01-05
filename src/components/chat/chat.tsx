import { ChatList } from './chat-list'
import { ChatPanel } from './chat-panel'
import { cn } from '@/lib/utils'
import { ChatMessage } from '@/types'

interface ChatProps {
  className?: string
  messages: ChatMessage[]
  onSendMessage: (message: string) => void
  isLoading?: boolean
  onStop?: () => void
  disabled?: boolean
}

export function Chat({ 
  className, 
  messages, 
  onSendMessage, 
  isLoading = false, 
  onStop,
  disabled = false
}: ChatProps) {
  return (
    <div className={cn('flex h-full flex-col', className)}>
      <div className="flex-1 overflow-hidden">
        <ChatList messages={messages} isLoading={isLoading} />
      </div>
      <ChatPanel
        onSendMessage={onSendMessage}
        isLoading={isLoading}
        onStop={onStop}
        disabled={disabled}
      />
    </div>
  )
}