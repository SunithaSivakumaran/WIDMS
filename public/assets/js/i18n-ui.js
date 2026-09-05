document.addEventListener('DOMContentLoaded', () => {
  const translations = window.WIDMS_TRANSLATIONS || {}
  const entries = Object.entries(translations)
  if (entries.length === 0) return

  // Translate only visible interface text; user-entered values and application data stay intact.
  const translateText = (value) => {
    const trimmed = value.trim()
    const direct = translations[trimmed]
    if (direct) return value.replace(trimmed, direct)

    // Allow a decorative icon before an exact label, but never replace words inside data.
    for (const [english, translated] of entries) {
      if (!trimmed.endsWith(english)) continue
      const prefix = trimmed.slice(0, -english.length)
      if (/^[^\p{L}\p{N}]*$/u.test(prefix)) return value.replace(trimmed, `${prefix}${translated}`)
    }
    return value
  }

  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      const parent = node.parentElement
      if (!parent || ['SCRIPT', 'STYLE', 'TEXTAREA', 'OPTION'].includes(parent.tagName)) return NodeFilter.FILTER_REJECT
      return node.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT
    },
  })

  const textNodes = []
  while (walker.nextNode()) textNodes.push(walker.currentNode)
  textNodes.forEach((node) => {
    node.nodeValue = translateText(node.nodeValue)
  })

  // Placeholders, tooltips, accessibility labels, and options are visible UI copy too.
  document.querySelectorAll('[placeholder], [title], [aria-label], option').forEach((element) => {
    ;['placeholder', 'title', 'aria-label'].forEach((attribute) => {
      if (element.hasAttribute(attribute)) {
        element.setAttribute(attribute, translateText(element.getAttribute(attribute) || ''))
      }
    })
    if (element.tagName === 'OPTION') element.textContent = translateText(element.textContent)
  })
})
