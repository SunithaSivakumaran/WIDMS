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
  const unitCost = document.getElementById('unit_cost')
  const totalCost = document.getElementById('total_cost')
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
  const itemHelp = document.getElementById('item-help')
  const powerField = document.getElementById('power-field')
  const powerValue = document.getElementById('power_value')
  const quantityLabel = document.getElementById('quantity-label')
  const quantityHelp = document.getElementById('quantity-help')
  const powerControls = document.createElement('div')
  powerControls.className = 'power-controls'
  powerField.insertBefore(powerControls, document.getElementById('power_sign'))
  powerControls.append(document.getElementById('power_sign'), powerValue)
  const powerCount = document.createElement('input')
  powerCount.type = 'number'; powerCount.min = '1'; powerCount.id = 'power_count'; powerCount.name = 'power_count'; powerCount.placeholder = 'Lens count for this power'; powerCount.hidden = true
  const powerCountLabel = document.createElement('span')
  powerCountLabel.textContent = 'Lens count for this power'
  powerControls.append(powerCount)
  const powerEntriesInput = document.createElement('input'); powerEntriesInput.type = 'hidden'; powerEntriesInput.name = 'power_entries'; powerField.append(powerEntriesInput)
  const powerEntries = []
  const powerTable = document.createElement('div')
  powerTable.className = 'power-entry-summary'
  powerTable.innerHTML = '<button type="button" class="add-power-entry" data-add-power>Add</button><strong>Power entries</strong><table><thead><tr><th>Power</th><th>Count</th><th></th></tr></thead><tbody></tbody></table><small class="power-total-hint">Add each power and its quantity. Counts cannot exceed the total.</small>'
  powerField.append(powerTable)
  const powerBody = powerTable.querySelector('tbody')
  function renderPowerEntries() {
    powerBody.innerHTML = powerEntries.map((entry, index) => `<tr><td>${entry.sign}${Number(entry.power).toFixed(2)}</td><td>${entry.count}</td><td><button type="button" data-edit="${index}">Edit</button></td></tr>`).join('')
    powerEntriesInput.value = JSON.stringify(powerEntries)
    powerBody.querySelectorAll('[data-edit]').forEach((button) => button.addEventListener('click', () => { const index = Number(button.dataset.edit); const entry = powerEntries[index]; powerEntries.splice(index, 1); document.getElementById('power_sign').value = entry.sign; powerValue.value = entry.power; powerCount.value = entry.count; renderPowerEntries(); updatePowerField() }))
  }
  powerTable.querySelector('[data-add-power]').addEventListener('click', () => {
    const count = Number(powerCount.value || 0); const total = Number(quantity.value || 0); const used = powerEntries.reduce((sum, entry) => sum + Number(entry.count), 0)
    if (!powerValue.value || count < 1 || used + count > total) { powerCount.setCustomValidity('Power counts cannot exceed the total quantity.'); powerCount.reportValidity(); return }
    powerCount.setCustomValidity(''); powerEntries.push({ sign: document.getElementById('power_sign').value, power: Number(powerValue.value), count }); renderPowerEntries(); powerValue.value = ''; powerCount.value = ''
  })

  function isPowerItem() {
    const text = item.selectedOptions[0]?.textContent || ''
    return /contact\s*lens|spectacles?|\bspecs\b/i.test(text)
  }

  function updatePowerField() {
    const enabled = isPowerItem()
    powerField.hidden = !enabled
    if (powerField.firstChild && powerField.firstChild.nodeType === Node.TEXT_NODE)
      powerField.firstChild.textContent = enabled ? 'Power (Number) ' : 'Power (Number) '
    // Power rows are validated by the Add button/server; cleared editor fields
    // must not block submission after a row has already been added.
    powerValue.required = false
    powerCount.hidden = !enabled
    powerCount.required = false
    powerCountLabel.hidden = !enabled
    quantityLabel.textContent = enabled ? `Total Number of ${/contact\s*lens/i.test(item.selectedOptions[0]?.textContent || '') ? 'Contact Lenses' : 'Spectacles'}` : 'Quantity'
    powerCountLabel.textContent = enabled ? `${/contact\s*lens/i.test(item.selectedOptions[0]?.textContent || '') ? 'Lens' : 'Spectacle'} count for this power` : 'Count for this power'
    powerCount.placeholder = powerCountLabel.textContent
    quantityHelp.textContent = enabled ? 'Number received for the selected power (used for total-cost calculation).' : 'Number of aid units received.'
    powerTable.hidden = !enabled
    const sign = document.getElementById('power_sign').value
    powerTable.querySelector('[data-power]').textContent = powerValue.value === '' ? '—' : `${sign}${Number(powerValue.value).toFixed(2)}`
    powerTable.querySelector('[data-count]').textContent = powerCount.value || '—'
    renderPowerEntries()
  }

  function filterAuthorizedItems() {
    const supplierId = supplier.value
    let availableItems = 0
    Array.from(item.options).forEach((option, index) => {
      if (index === 0) return
      const isAuthorized =
        supplierId !== '' &&
        option.dataset.suppliers.includes(`,${supplierId},`)
      option.hidden = !isAuthorized
      option.disabled = !isAuthorized
      if (isAuthorized) availableItems += 1
    })
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
        : availableItems > 0
          ? 'Select item and variety'
          : 'No authorized items for this supplier'
    itemHelp.textContent =
      supplierId === ''
        ? 'Choose a supplier to load its authorized items.'
        : availableItems > 0
          ? `${availableItems} authorized item${availableItems === 1 ? '' : 's'} available.`
          : 'Allocate an item to this supplier in Supplier Configuration first.'
    updatePowerField()
  }

  function calculate() {
    const total =
      Math.max(0, Number(quantity.value) || 0) *
      Math.max(0, Number(unitCost.value) || 0)
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

  ;[quantity, unitCost, paymentStatus, paidAmount].forEach((field) =>
    field.addEventListener('input', calculate),
  )
  paymentStatus.addEventListener('change', calculate)
  supplier.addEventListener('change', filterAuthorizedItems)
  item.addEventListener('change', updatePowerField)
  powerValue.addEventListener('input', updatePowerField)
  powerCount.addEventListener('input', updatePowerField)
  powerCount.addEventListener('input', () => powerCount.setCustomValidity(''))
  document.getElementById('power_sign').addEventListener('change', updatePowerField)
  document.getElementById('receipt-form').addEventListener('submit', () => {
    powerValue.required = false
    powerCount.required = false
    powerEntriesInput.value = JSON.stringify(powerEntries)
  })
  filterAuthorizedItems()
  calculate()
})
