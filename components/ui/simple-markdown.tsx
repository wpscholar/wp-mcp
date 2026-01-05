interface SimpleMarkdownProps {
  content: string
  className?: string
}

/**
 * Lightweight markdown parser that handles basic formatting
 * without heavy dependencies that conflict with MCP SDK
 */
export function SimpleMarkdown({ content, className }: SimpleMarkdownProps) {
  const parseInline = (text: string): React.ReactNode[] => {
    const nodes: React.ReactNode[] = []
    let remaining = text
    let key = 0

    while (remaining.length > 0) {
      // Bold: **text** or __text__
      const boldMatch = remaining.match(/^(\*\*|__)(.+?)\1/)
      if (boldMatch) {
        nodes.push(<strong key={key++}>{boldMatch[2]}</strong>)
        remaining = remaining.slice(boldMatch[0].length)
        continue
      }

      // Italic: *text* or _text_
      const italicMatch = remaining.match(/^(\*|_)([^*_]+?)\1/)
      if (italicMatch) {
        nodes.push(<em key={key++}>{italicMatch[2]}</em>)
        remaining = remaining.slice(italicMatch[0].length)
        continue
      }

      // Inline code: `code`
      const codeMatch = remaining.match(/^`([^`]+)`/)
      if (codeMatch) {
        nodes.push(
          <code key={key++} className="bg-muted px-1.5 py-0.5 rounded text-sm font-mono">
            {codeMatch[1]}
          </code>
        )
        remaining = remaining.slice(codeMatch[0].length)
        continue
      }

      // Links: [text](url)
      const linkMatch = remaining.match(/^\[([^\]]+)\]\(([^)]+)\)/)
      if (linkMatch) {
        nodes.push(
          <a
            key={key++}
            href={linkMatch[2]}
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary underline hover:no-underline"
          >
            {linkMatch[1]}
          </a>
        )
        remaining = remaining.slice(linkMatch[0].length)
        continue
      }

      // Regular character
      const nextSpecial = remaining.slice(1).search(/[\*_`\[]/)
      if (nextSpecial === -1) {
        nodes.push(remaining)
        break
      } else {
        nodes.push(remaining.slice(0, nextSpecial + 1))
        remaining = remaining.slice(nextSpecial + 1)
      }
    }

    return nodes
  }

  const parseBlock = (text: string): React.ReactNode[] => {
    const lines = text.split('\n')
    const nodes: React.ReactNode[] = []
    let key = 0
    let inList = false
    let listItems: React.ReactNode[] = []

    const flushList = () => {
      if (listItems.length > 0) {
        nodes.push(<ul key={key++} className="list-disc list-inside space-y-1 my-2">{listItems}</ul>)
        listItems = []
        inList = false
      }
    }

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i]

      // Headers: # ## ###
      const headerMatch = line.match(/^(#{1,3})\s+(.+)$/)
      if (headerMatch) {
        flushList()
        const level = headerMatch[1].length
        const content = parseInline(headerMatch[2])
        if (level === 1) {
          nodes.push(<h1 key={key++} className="text-xl font-bold mt-4 mb-2">{content}</h1>)
        } else if (level === 2) {
          nodes.push(<h2 key={key++} className="text-lg font-bold mt-3 mb-2">{content}</h2>)
        } else {
          nodes.push(<h3 key={key++} className="text-base font-bold mt-2 mb-1">{content}</h3>)
        }
        continue
      }

      // List items: - item or * item or • item
      const listMatch = line.match(/^[\-\*•]\s+(.+)$/)
      if (listMatch) {
        inList = true
        listItems.push(<li key={key++}>{parseInline(listMatch[1])}</li>)
        continue
      }

      // Numbered list: 1. item
      const numberedMatch = line.match(/^\d+\.\s+(.+)$/)
      if (numberedMatch) {
        flushList()
        if (!inList) {
          inList = true
        }
        listItems.push(<li key={key++}>{parseInline(numberedMatch[1])}</li>)
        continue
      }

      // Empty line
      if (line.trim() === '') {
        flushList()
        nodes.push(<br key={key++} />)
        continue
      }

      // Regular paragraph
      flushList()
      nodes.push(
        <p key={key++} className="my-1">
          {parseInline(line)}
        </p>
      )
    }

    flushList()
    return nodes
  }

  return (
    <div className={className}>
      {parseBlock(content)}
    </div>
  )
}
