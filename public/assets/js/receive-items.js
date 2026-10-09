document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#receipt-form label').forEach((label) => {
    if (label.querySelector('[required]') && !label.querySelector('.required-mark')) {
      const mark = document.createElement('span')
      mark.className = 'required-mark'
      mark.textContent = ' *'
      const firstControl = label.querySelector('input, select, textarea')
      label.insertBefore(mark, firstControl || null)
    }
  })
  const quantity = document.getElementById('quantity')
  const receiptForm = document.getElementById('receipt-form')
  const unitCost = document.getElementById('unit_cost')
  const bulkTotalCost = document.getElementById('bulk_total_cost')
  const totalCost = document.getElementById('total_cost')
  const totalCostField = document.getElementById('total-cost-field')
  const paymentStatus = document.getElementById('payment_status')
  const checkField = document.createElement('label')
  checkField.id = 'check-number-field'
  checkField.innerHTML = 'Check Number <span class="required-mark">*</span><input type="text" name="check_number" id="check_number" maxlength="100" placeholder="Enter check number"><small>Required for paid or partially paid receipts.</small>'
  paymentStatus.closest('label').after(checkField)
  const checkNumber = checkField.querySelector('input')
  checkField.hidden = paymentStatus.value === 'unpaid'
  const paidAmountField = document.getElementById('paid-amount-field')
  const paidAmount = document.getElementById('paid_amount')
  paidAmountField.hidden = paymentStatus.value !== 'partially-paid'
  const balanceAmount = document.getElementById('balance_amount')
  const balanceField = balanceAmount.closest('label')
  balanceField.id = 'balance-field'
  const supplier = document.getElementById('supplier_id')
  const item = document.getElementById('item_id')
  const destination = document.getElementById('stock_destination')
  const camp = document.getElementById('camp_id')
  const receivedDate = document.getElementById('received_date')
  const campField = document.getElementById('camp-field')
  const campDivisionField = document.getElementById('camp-division-field')
  const campDivision = document.getElementById('camp_division')
  const unitCostField = document.getElementById('unit-cost-field')
  const bulkCostField = document.getElementById('bulk-cost-field')
  const itemHelp = document.getElementById('item-help')
  const spectacleLinesField = document.getElementById('spectacle-lines-field')
  const spectacleLines = [...spectacleLinesField.querySelectorAll('.spectacle-receipt-line')]
  const spectacleTotal = document.getElementById('spectacle-receipt-total')
  const quantityLabel = document.getElementById('quantity-label')
  const quantityHelp = document.getElementById('quantity-help')
  function isSpectacles() {
    return item.selectedOptions[0]?.dataset.optical === 'spectacles'
  }

  let previousCampSelection
  function updateSpectacleFields() {
    const enabled = isSpectacles()
    const isCamp = destination.value === 'vision-camp'
    const campSelection = isCamp ? camp.value : ''
    if (previousCampSelection !== undefined && previousCampSelection !== campSelection) {
      spectacleLines.forEach(row => {
        row.querySelector('[type="checkbox"]').checked = false
        row.querySelector('[name$="[quantity]"]').value = ''
        row.querySelector('[name$="[unit_cost]"]').value = ''
      })
    }
    previousCampSelection = campSelection
    const campNeeds = isCamp && camp.value ? JSON.parse(camp.selectedOptions[0]?.dataset.needs || '{}') : {}
    spectacleLinesField.hidden = !enabled
    spectacleLines.forEach(row => {
      const checkbox = row.querySelector('[type="checkbox"]')
      const campSelected = row.querySelector('[data-camp-selected]')
      const count = row.querySelector('[name$="[quantity]"]')
      const price = row.querySelector('[name$="[unit_cost]"]')
      const needed = Number(campNeeds[checkbox.dataset.categoryId] || 0)
      if (isCamp) {
        checkbox.checked = needed > 0
        count.value = needed > 0 ? String(needed) : ''
      }
      const selected = enabled && checkbox.checked
      row.hidden = enabled && isCamp && needed === 0
      checkbox.disabled = !enabled || isCamp || checkbox.dataset.categoryStatus !== 'active'
      campSelected.disabled = !enabled || !isCamp || needed === 0
      count.disabled = !selected
      count.readOnly = isCamp
      price.disabled = !selected
      count.required = selected
      price.required = selected
      row.classList.toggle('is-selected', selected)
    })
    const firstType = spectacleLines[0]?.querySelector('[type="checkbox"]')
    firstType?.setCustomValidity(enabled && !spectacleLines.some(row => row.querySelector('[type="checkbox"]').checked)
      ? 'Select at least one spectacle type.' : '')
    quantity.readOnly = enabled
    totalCostField.hidden = enabled
    if (enabled) {
      quantityLabel.textContent = receiptForm.dataset.totalSpectacles
      quantityHelp.textContent = 'Calculated from the selected spectacle types.'
    } else {
      quantityLabel.textContent = 'Quantity'
      quantityHelp.textContent = 'Number of aid units received.'
    }
    unitCostField.hidden = enabled || isCamp
    unitCost.required = !enabled && !isCamp
    bulkCostField.hidden = enabled || !isCamp
    bulkTotalCost.required = !enabled && isCamp
    if (enabled) {
      quantity.value = spectacleLines.reduce((sum, row) =>
        sum + (row.querySelector('[type="checkbox"]').checked
          ? Math.max(0, Number(row.querySelector('[name$="[quantity]"]').value) || 0) : 0), 0)
    }
    calculate()
  }

  function updateDestination() {
    const isCamp = destination.value === 'vision-camp'
    campField.hidden = !isCamp
    campDivisionField.hidden = !isCamp
    camp.required = isCamp
    if (!isCamp) {
      camp.value = ''
      campDivision.value = ''
    } else {
      campDivision.value = camp.selectedOptions[0]?.dataset.division || ''
    }
    updateCampDate()
    filterAuthorizedItems()
    calculate()
  }

  function updateCampDate() {
    receivedDate.min = destination.value === 'vision-camp' ? camp.selectedOptions[0]?.dataset.completedDate || '' : ''
    if (receivedDate.min && receivedDate.value < receivedDate.min) receivedDate.value = receivedDate.min
  }

  function filterAuthorizedItems() {
    const supplierId = supplier.value
    const campItemId = destination.value === 'vision-camp' ? camp.selectedOptions[0]?.dataset.itemId || '' : ''
    let availableItems = 0
    Array.from(item.options).forEach((option, index) => {
      if (index === 0) return
      const isAuthorized =
        supplierId !== '' &&
        option.dataset.suppliers.includes(`,${supplierId},`) &&
        (destination.value !== 'vision-camp' || (campItemId !== '' && option.value === campItemId))
      option.hidden = !isAuthorized
      option.disabled = !isAuthorized
      if (isAuthorized) availableItems += 1
    })
    if (campItemId !== '' && Array.from(item.options).some(option => option.value === campItemId && !option.disabled)) item.value = campItemId
    if (
      !supplierId ||
      item.selectedOptions[0]?.disabled ||
      item.selectedOptions[0]?.hidden
    )
      item.value = ''
    item.disabled = supplierId === '' || availableItems === 0
    item.options[0].textContent =
      supplierId === ''
        ? 'Select a supplier first'
        : destination.value === 'vision-camp' && campItemId === ''
          ? 'Select a completed Vision Camp first'
        : availableItems > 0
          ? (campItemId ? 'Vision Camp item' : 'Select item and variety')
          : 'No authorized items for this supplier'
    itemHelp.textContent =
      supplierId === ''
        ? 'Choose a supplier to load its authorized items.'
        : destination.value === 'vision-camp' && campItemId === ''
          ? 'Choose a completed Vision Camp to identify its spectacles item.'
        : availableItems > 0
          ? (campItemId ? 'The built-in Spectacles item is selected for this camp.' : `${availableItems} authorized item${availableItems === 1 ? '' : 's'} available.`)
          : 'Allocate an item to this supplier in Supplier Configuration first.'
    updateSpectacleFields()
  }

  function calculate() {
    const total = isSpectacles()
      ? spectacleLines.reduce((sum, row) => {
        const selected = row.querySelector('[type="checkbox"]').checked
        const count = selected ? Math.max(0, Number(row.querySelector('[name$="[quantity]"]').value) || 0) : 0
        const price = selected ? Math.max(0, Number(row.querySelector('[name$="[unit_cost]"]').value) || 0) : 0
        row.querySelector('output').value = (count * price).toFixed(2)
        return sum + count * price
      }, 0)
      : destination.value === 'vision-camp'
      ? Math.max(0, Number(bulkTotalCost.value) || 0)
      : Math.max(0, Number(quantity.value) || 0) * Math.max(0, Number(unitCost.value) || 0)
    spectacleTotal.value = `Rs ${isSpectacles() ? total.toFixed(2) : '0.00'}`
    let paid = 0
    if (paymentStatus.value === 'fully-paid') paid = total
    if (paymentStatus.value === 'partially-paid')
      paid = Math.max(0, Number(paidAmount.value) || 0)
    totalCost.value = total.toFixed(2)
    balanceAmount.value = Math.max(0, total - paid).toFixed(2)
    balanceField.hidden = paymentStatus.value === 'fully-paid'
    paidAmountField.hidden = paymentStatus.value !== 'partially-paid'
    paidAmount.required = paymentStatus.value === 'partially-paid'
    checkField.hidden = paymentStatus.value === 'unpaid'
    checkNumber.required = paymentStatus.value !== 'unpaid'
  }

  ;[quantity, unitCost, bulkTotalCost, paymentStatus, paidAmount].forEach((field) =>
    field.addEventListener('input', calculate),
  )
  paymentStatus.addEventListener('change', calculate)
  supplier.addEventListener('change', filterAuthorizedItems)
  destination.addEventListener('change', updateDestination)
  camp.addEventListener('change', () => { campDivision.value = camp.selectedOptions[0]?.dataset.division || ''; updateCampDate(); filterAuthorizedItems() })
  item.addEventListener('change', updateSpectacleFields)
  spectacleLines.forEach(row => row.querySelectorAll('input').forEach(field =>
    field.addEventListener('input', updateSpectacleFields)))
  let receiptSubmitting = false
  const recordButton = receiptForm.querySelector('.record-receipt-button')
  receiptForm.addEventListener('submit', (event) => {
    if (receiptSubmitting) {
      event.preventDefault()
      return
    }
    receiptSubmitting = true
    recordButton.disabled = true
    recordButton.textContent = 'Recording...'
  })
  window.addEventListener('pageshow', () => {
    receiptSubmitting = false
    recordButton.disabled = false
    recordButton.textContent = 'Record'
  })
  updateDestination()
})
