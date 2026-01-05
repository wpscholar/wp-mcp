import { useState, useRef, useEffect } from 'react'
import { Button } from '../ui/button'
import { Textarea } from '../ui/textarea'
import { cn } from '../../src/lib/utils'
import { Send, Square } from 'lucide-react'

interface ChatPanelProps {
  onSendMessage: (message: string) => void
  isLoading?: boolean
  onStop?: () => void
  disabled?: boolean
}

export function ChatPanel({ onSendMessage, isLoading, onStop, disabled = false }: ChatPanelProps) {
  const [input, setInput] = useState('')
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto'
      textareaRef.current.style.height = `${textareaRef.current.scrollHeight}px`
    }
  }, [input])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!input.trim() || isLoading || disabled) return
    
    onSendMessage(input.trim())
    setInput('')
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSubmit(e as any)
    }
  }

  return (
    <div className="border-t bg-background px-4 py-3">
      <form onSubmit={handleSubmit} className="flex gap-2">
        <div className="relative flex-1">
          <Textarea
            ref={textareaRef}
            placeholder="Type your message... (Shift+Enter for new line)"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            className={cn(
              'min-h-[60px] max-h-[200px] resize-none pr-12',
              'focus-visible:ring-1'
            )}
            disabled={isLoading || disabled}
            rows={1}
          />
        </div>
        
        {isLoading ? (
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={onStop}
            className="h-[60px] w-[60px] shrink-0"
          >
            <Square className="h-4 w-4" />
          </Button>
        ) : (
          <Button
            type="submit"
            disabled={!input.trim() || disabled}
            className="h-[60px] w-[60px] shrink-0"
          >
            <Send className="h-4 w-4" />
          </Button>
        )}
      </form>
      
      <div className="mt-2 text-xs text-muted-foreground text-center">
        This chat can execute WordPress actions via MCP tools. Use natural language to interact with your site.
      </div>
    </div>
  )
}