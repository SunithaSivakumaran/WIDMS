// Let confirmation dialogs and validation handlers cancel a submit normally.
// Once a POST really proceeds, ignore further clicks until navigation completes.
document.addEventListener('submit', (event) => {
  const form = event.target
  if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return
  if (form.dataset.widmsSubmitting === 'true') {
    event.preventDefault()
    return
  }
  queueMicrotask(() => {
    if (event.defaultPrevented) return
    form.dataset.widmsSubmitting = 'true'
    form.setAttribute('aria-busy', 'true')
  })
}, true)

window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[data-widms-submitting="true"]').forEach((form) => {
    delete form.dataset.widmsSubmitting
    form.removeAttribute('aria-busy')
  })
})
