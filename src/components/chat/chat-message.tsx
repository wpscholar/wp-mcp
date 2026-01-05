import { cn } from '@/lib/utils'
import { SimpleMarkdown } from '../ui/simple-markdown'
import { ChatMessage as ChatMessageType } from '@/types'

interface ChatMessageProps {
  message: ChatMessageType
  className?: string
}

export function ChatMessage({ message, className }: ChatMessageProps) {
  return (
    <div
      className={cn(
        'group relative mb-4 flex items-start gap-4 px-4',
        className
      )}
    >
      <div
        className={cn(
          'flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-md border shadow',
          message.role === 'user'
            ? 'bg-background text-foreground'
            : 'bg-primary text-primary-foreground'
        )}
      >
        {message.role === 'user' ? (
          <UserIcon />
        ) : (
          <BotIcon />
        )}
      </div>
      <div className="flex-1 space-y-2 overflow-hidden">
        <SimpleMarkdown
          content={message.content}
          className="prose prose-sm max-w-none dark:prose-invert"
        />
        
        {message.toolCalls && message.toolCalls.length > 0 && (
          <div className="space-y-2">
            <div className="text-xs text-muted-foreground">Tool Calls:</div>
            {message.toolCalls.map((tool) => (
              <div
                key={tool.id}
                className="rounded-md border bg-muted p-3 text-sm"
              >
                <div className="font-medium text-foreground">
                  {tool.name}
                </div>
                <pre className="mt-1 text-xs text-muted-foreground">
                  {JSON.stringify(tool.arguments, null, 2)}
                </pre>
              </div>
            ))}
          </div>
        )}

        {message.toolResults && message.toolResults.length > 0 && (
          <div className="space-y-2">
            <div className="text-xs text-muted-foreground">Tool Results:</div>
            {message.toolResults.map((result) => (
              <div
                key={result.id}
                className={cn(
                  'rounded-md border p-3 text-sm',
                  result.error 
                    ? 'border-destructive bg-destructive/10' 
                    : 'bg-muted'
                )}
              >
                {result.error ? (
                  <div className="text-destructive">{result.error}</div>
                ) : (
                  <pre className="text-xs text-muted-foreground whitespace-pre-wrap">
                    {typeof result.result === 'string' 
                      ? result.result 
                      : JSON.stringify(result.result, null, 2)
                    }
                  </pre>
                )}
              </div>
            ))}
          </div>
        )}

        <div className="text-xs text-muted-foreground">
          {message.timestamp.toLocaleTimeString()}
        </div>
      </div>
    </div>
  )
}

function UserIcon() {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 256 256"
      fill="currentColor"
      className="h-4 w-4"
    >
      <path d="M230.92 212c-15.23-26.33-38.7-45.21-66.09-54.16a72 72 0 1 0-73.66 0c-27.39 8.94-50.86 27.82-66.09 54.16a8 8 0 1 0 13.85 8c18.84-32.56 52.14-52 89.07-52s70.23 19.44 89.07 52a8 8 0 1 0 13.85-8ZM72 96a56 56 0 1 1 56 56 56.06 56.06 0 0 1-56-56Z" />
    </svg>
  )
}

function BotIcon() {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 256 256"
      fill="currentColor"
      className="h-4 w-4"
    >
      <path d="M200 40H56a16 16 0 0 0-16 16v144a16 16 0 0 0 16 16h144a16 16 0 0 0 16-16V56a16 16 0 0 0-16-16ZM56 56h144v144H56Z" />
      <circle cx="108" cy="108" r="12" />
      <circle cx="148" cy="108" r="12" />
      <path d="M112 148h32a8 8 0 0 0 0-16h-32a8 8 0 0 0 0 16Z" />
    </svg>
  )
}