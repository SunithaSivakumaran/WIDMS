document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('spectacle-category-form')
  const name = form?.querySelector('[name="spectacle_category_name"]')
  if (form && name) {
    form.querySelectorAll('[data-spectacle-option-name]').forEach(option => {
      option.addEventListener('change', () => {
        if (option.checked) name.value = option.dataset.spectacleOptionName || ''
      })
    })
  }

  const dialog = document.getElementById('spectacle-category-remove-dialog')
  if (!dialog) return
  const categoryName = dialog.querySelector('[data-spectacle-remove-name]')
  const confirmButton = dialog.querySelector('[data-spectacle-remove-confirm]')
  const cancelButton = dialog.querySelector('[data-spectacle-remove-cancel]')
  let pendingButton = null

  document.querySelectorAll('[data-spectacle-remove]').forEach(button => {
    button.addEventListener('click', event => {
      if (button.dataset.confirmed === 'true') return
      event.preventDefault()
      if (typeof dialog.showModal !== 'function') {
        if (window.confirm('Remove this unused spectacle type?')) {
          button.dataset.confirmed = 'true'
          button.form.requestSubmit(button)
        }
        return
      }
      pendingButton = button
      categoryName.textContent = button.dataset.categoryName || ''
      dialog.showModal()
    })
  })

  cancelButton.addEventListener('click', () => dialog.close())
  dialog.addEventListener('close', () => { pendingButton = null })
  confirmButton.addEventListener('click', () => {
    if (!pendingButton) return
    const button = pendingButton
    button.dataset.confirmed = 'true'
    dialog.close()
    button.form.requestSubmit(button)
  })
})
