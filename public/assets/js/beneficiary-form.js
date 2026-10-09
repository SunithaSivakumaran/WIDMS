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
  const district = document.getElementById('district_id'),
    ds = document.getElementById('ds_division_id'),
    gn = document.getElementById('gn_division_id'),
    gnRequiredIndicator = document.getElementById('gn-required-indicator'),
    serviceDivisionNotice = document.getElementById('service-division-gn-notice'),
    dob = document.getElementById('date_of_birth'),
    age = document.getElementById('beneficiary-age')

  const updateAge = () => {
    if (!age) return
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
  }
  dob?.addEventListener('change', updateAge)

  // Some workflows supply District and DS Division from the logged-in
  // officer. Their form contains only the already-filtered GN Division.
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
  const updateGnDivision = (resetSelection = false) => {
    const isServiceDivision =
      ds.selectedOptions[0]?.dataset.serviceDivision === '1'

    // Keep the beneficiary's saved GN Division when an edit form first loads.
    // Clear it only after the user changes the parent location, or when the
    // selected division is a service centre where GN Division is inapplicable.
    if (resetSelection || isServiceDivision) gn.value = ''
    filter(gn, isServiceDivision ? '' : ds.value)
    gn.required = !isServiceDivision
    gnRequiredIndicator?.toggleAttribute('hidden', isServiceDivision)
    if (serviceDivisionNotice) serviceDivisionNotice.hidden = !isServiceDivision
  }

  district.addEventListener('change', () => {
    ds.value = ''
    gn.value = ''
    filter(ds, district.value)
    updateGnDivision(true)
  })
  ds.addEventListener('change', () => {
    updateGnDivision(true)
  })
  filter(ds, district.value)
  updateGnDivision(false)
})
