document.addEventListener('DOMContentLoaded', () => {
  const dialog = document.getElementById('aid-request-delete-dialog')
  if (!dialog) return

  const closeButton = dialog.querySelector('.aid-request-delete-close')
  const cancelButton = dialog.querySelector('.aid-request-delete-cancel')
  const confirmButton = dialog.querySelector('.aid-request-delete-confirm')
  let pendingForm = null

  const closeDialog = () => {
    pendingForm = null
    if (dialog.open) dialog.close()
  }

  document.querySelectorAll('[data-request-delete-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault()
      pendingForm = form

      // Native dialogs keep focus inside the destructive-action confirmation.
      if (typeof dialog.showModal === 'function') dialog.showModal()
    })
  })

  confirmButton?.addEventListener('click', () => {
    if (pendingForm) pendingForm.submit()
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
})
