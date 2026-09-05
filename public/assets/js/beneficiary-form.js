const nic = document.querySelector('input[name="nic"]')

if (nic) {
  nic.required = false

  const label = nic.closest('label')

  if (label) label.childNodes[0].textContent = 'NIC Number '
}
document.addEventListener('DOMContentLoaded', () => {
  const nic = document.querySelector('input[name="nic"]')
  if (nic) {
    nic.required = false
    const label = nic.closest('label')
    if (label) label.childNodes[0].textContent = 'NIC Number '
  }
  const aidType = document.getElementById('item_id'),
    powerField = document.getElementById('power-field'),
    lensPower = document.getElementById('prescribed_power')
  const updateLensPower = () => {
    if (!aidType || !powerField || !lensPower) return
    const requiresPower =
      aidType.selectedOptions[0]?.dataset.requiresPower === '1'
    powerField.hidden = !requiresPower
    lensPower.required = requiresPower
    if (!requiresPower) lensPower.value = ''
  }
  aidType?.addEventListener('change', updateLensPower)
  updateLensPower()
  const district = document.getElementById('district_id'),
    ds = document.getElementById('ds_division_id'),
    gn = document.getElementById('gn_division_id'),
    gnRequiredIndicator = document.getElementById('gn-required-indicator'),
    serviceDivisionNotice = document.getElementById('service-division-gn-notice'),
    dob = document.getElementById('date_of_birth'),
    age = document.getElementById('beneficiary-age')
  if (!district || !ds || !gn) return
  const filter = (select, parent) => {
    Array.from(select.options).forEach((option, index) => {
      if (index === 0) return
      const visible = parent !== '' && option.dataset.parent === parent
      option.hidden = !visible
      option.disabled = !visible
    })
    if (select.selectedOptions[0]?.disabled) select.value = ''
    select.disabled = parent === ''
  }

  // Service-centre residents belong to the selected home, not to a GN Division.
  const updateGnDivision = () => {
    const isServiceDivision =
      ds.selectedOptions[0]?.dataset.serviceDivision === '1'

    gn.value = ''
    filter(gn, isServiceDivision ? '' : ds.value)
    gn.required = !isServiceDivision
    gnRequiredIndicator?.toggleAttribute('hidden', isServiceDivision)
    if (serviceDivisionNotice) serviceDivisionNotice.hidden = !isServiceDivision
  }

  district.addEventListener('change', () => {
    ds.value = ''
    gn.value = ''
    filter(ds, district.value)
    updateGnDivision()
  })
  ds.addEventListener('change', () => {
    updateGnDivision()
  })
  filter(ds, district.value)
  updateGnDivision()
  dob?.addEventListener('change', () => {
    if (!dob.value) {
      age.textContent = 'Age: —'
      return
    }
    const birth = new Date(dob.value),
      today = new Date()
    let years = today.getFullYear() - birth.getFullYear()
    if (
      today < new Date(today.getFullYear(), birth.getMonth(), birth.getDate())
    )
      years--
    age.textContent = `Age: ${Math.max(0, years)}`
  })
})
