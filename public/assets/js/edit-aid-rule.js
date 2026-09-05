document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('edit-aid-rule-form')
  const dialog = document.getElementById('edit-rule-confirm-dialog')
  if (!form || !dialog) return

  const itemInput = form.querySelector('[name="item_name"]')
  const itemName = dialog.querySelector('[data-confirm-item-name]')
  const closeButton = dialog.querySelector('.edit-rule-confirm-close')
  const cancelButton = dialog.querySelector('.edit-rule-dialog-cancel')
  const saveButton = dialog.querySelector('.edit-rule-dialog-save')
  let confirmed = false

  // Use one close path so Cancel, the close icon, and Escape always behave consistently.
  const closeDialog = () => {
    if (dialog.open) dialog.close()
  }

  const submitConfirmedChanges = () => {
    confirmed = true
    if (typeof form.requestSubmit === 'function') form.requestSubmit()
    else form.submit()
  }

  form.addEventListener('submit', (event) => {
    if (confirmed) return

    event.preventDefault()
    itemName.textContent = itemInput?.value.trim() || itemName.textContent

    // Native dialog support provides focus trapping; confirm is a safe fallback for older browsers.
    if (typeof dialog.showModal === 'function') {
      dialog.showModal()
      saveButton?.focus()
      return
    }

    if (window.confirm(dialog.textContent.trim())) {
      submitConfirmedChanges()
    }
  })

  closeButton?.addEventListener('click', closeDialog)
  cancelButton?.addEventListener('click', closeDialog)
  dialog.addEventListener('cancel', (event) => {
    event.preventDefault()
    closeDialog()
  })
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) closeDialog()
  })

  saveButton?.addEventListener('click', () => {
    closeDialog()
    submitConfirmedChanges()
  })
})
