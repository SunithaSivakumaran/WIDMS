document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('aid-request-form')

  const useNic = document.getElementById('use_nic')

  const useEldersCard = document.getElementById('use_elders_card')

  const nicField = document.getElementById('nic-identification-field')

  const eldersField = document.getElementById(
    'elders-card-identification-field',
  )

  const nic = document.getElementById('nic')

  const eldersCard = document.getElementById('elders_card_number')

  const error = document.getElementById('identification-error')

  const preview = document.getElementById('eligibility-preview')

  const item = document.getElementById('item_id')

  /*
  |--------------------------------------------------------------------------
  | Make sure all required HTML elements exist
  |--------------------------------------------------------------------------
  */

  if (
    !form ||
    !useNic ||
    !useEldersCard ||
    !nicField ||
    !eldersField ||
    !nic ||
    !eldersCard ||
    !error
  ) {
    return
  }

  /*
  |--------------------------------------------------------------------------
  | Show / Hide Identification Fields
  |--------------------------------------------------------------------------
  */

  function updateIdentification() {
    /*
     * Show NIC field only when NIC is selected.
     */
    nicField.hidden = !useNic.checked

    nic.required = useNic.checked

    /*
     * Show Elders' Identity Card field
     * only when it is selected.
     */
    eldersField.hidden = !useEldersCard.checked

    eldersCard.required = useEldersCard.checked

    /*
     * If at least one identification method is selected,
     * hide the previous error.
     *
     * IMPORTANT:
     * Do not show the error here.
     */
    if (useNic.checked || useEldersCard.checked) {
      error.hidden = true
    }

    /*
     * Clear NIC validation when NIC is unchecked.
     */
    if (!useNic.checked) {
      nic.setCustomValidity('')
    }

    /*
     * Clear Elders' Identity Card validation
     * when the option is unchecked.
     */
    if (!useEldersCard.checked) {
      eldersCard.setCustomValidity('')
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Checkbox Change Events
  |--------------------------------------------------------------------------
  */

  useNic.addEventListener('change', updateIdentification)

  useEldersCard.addEventListener('change', updateIdentification)

  /*
  |--------------------------------------------------------------------------
  | Clear old error while user edits NIC
  |--------------------------------------------------------------------------
  */

  nic.addEventListener('input', () => {
    nic.setCustomValidity('')
  })

  /*
  |--------------------------------------------------------------------------
  | Clear old error while user edits Elders' Identity Card
  |--------------------------------------------------------------------------
  */

  eldersCard.addEventListener('input', () => {
    eldersCard.setCustomValidity('')
  })

  // Show previous devices and the current probation decision before submission.
  const escapeHtml = (value) => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character])

  async function checkEligibility() {
    if (!preview || (!nic.value.trim() && !eldersCard.value.trim())) return
    const query = new URLSearchParams({ nic: nic.value, elders_card: eldersCard.value })
    if (item?.value) query.set('item_id', item.value)
    try {
      const response = await fetch(`beneficiary-eligibility.php?${query}`)
      const result = await response.json()
      if (!result.found) { preview.hidden = true; return }
      const history = (result.history || []).map(entry => `${escapeHtml(entry.item_name)}${entry.variety ? ` / ${escapeHtml(entry.variety)}` : ''} — ${escapeHtml(entry.distributed_at.slice(0, 10))}`).join('<br>')
      const decision = result.eligibility ? `<strong>${escapeHtml(result.eligibility.reason)}</strong>` : ''
      preview.classList.toggle('ineligible', result.eligibility?.eligible === false)
      preview.innerHTML = `<b>${escapeHtml(result.beneficiary)}</b>${history ? `<span>${escapeHtml(result.history_label)}:<br>${history}</span>` : ''}${decision}`
      preview.hidden = false
    } catch { preview.hidden = true }
  }

  nic.addEventListener('blur', checkEligibility)
  eldersCard.addEventListener('blur', checkEligibility)
  item?.addEventListener('change', checkEligibility)

  /*
  |--------------------------------------------------------------------------
  | Validate Form
  |--------------------------------------------------------------------------
  */

  form.addEventListener('submit', (event) => {
    /*
    |--------------------------------------------------------------------------
    | At least one identification method is required
    |--------------------------------------------------------------------------
    */

    if (!useNic.checked && !useEldersCard.checked) {
      event.preventDefault()

      error.textContent = form.dataset.identificationRequired || "Please select NIC or Elders' Identity Card."

      error.hidden = false

      return
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Sri Lankan NIC
    |--------------------------------------------------------------------------
    |
    | Old format:
    | 726712395V
    | 726712395X
    |
    | New format:
    | 199012345678
    |--------------------------------------------------------------------------
    */

    if (useNic.checked) {
      const nicValue = nic.value.replace(/\s+/g, '').toUpperCase()

      const nicPattern = /^(?:[0-9]{9}[VX]|[0-9]{12})$/

      if (!nicPattern.test(nicValue)) {
        event.preventDefault()

        nic.setCustomValidity(form.dataset.invalidNic || 'Enter a valid Sri Lankan NIC.')

        nic.reportValidity()

        return
      }

      /*
       * Store cleaned NIC value.
       */
      nic.value = nicValue

      nic.setCustomValidity('')
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Elders' Identity Card
    |--------------------------------------------------------------------------
    */

    if (useEldersCard.checked) {
      const cardValue = eldersCard.value.trim().toUpperCase()

      const cardPattern = /^[A-Z0-9/-]{4,30}$/

      if (!cardPattern.test(cardValue)) {
        event.preventDefault()

        eldersCard.setCustomValidity(
          form.dataset.invalidEldersCard || "Enter a valid Elders' Identity Card number.",
        )

        eldersCard.reportValidity()

        return
      }

      /*
       * Store cleaned card value.
       */
      eldersCard.value = cardValue

      eldersCard.setCustomValidity('')
    }
  })

  /*
  |--------------------------------------------------------------------------
  | Initial Page State
  |--------------------------------------------------------------------------
  |
  | Error MUST NOT appear when page first loads.
  |--------------------------------------------------------------------------
  */

  error.hidden = true

  updateIdentification()
})
