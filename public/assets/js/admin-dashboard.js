document.addEventListener('DOMContentLoaded', () => {
  // Make every shared success and error alert dismissible, including alerts added by future pages.
  const dismissAlert = (notification) => {
    notification.classList.add('notification-hiding')
    window.setTimeout(() => notification.remove(), 300)
  }

  document.querySelectorAll('.alert-success, .alert-danger').forEach((notification) => {
    if (notification.querySelector('.notification-close')) return

    notification.classList.add('widms-dismissible-alert')
    const closeButton = document.createElement('button')
    closeButton.className = 'notification-close'
    closeButton.type = 'button'
    closeButton.setAttribute('aria-label', 'Close')
    closeButton.innerHTML = '&times;'
    closeButton.addEventListener('click', () => dismissAlert(notification))
    notification.appendChild(closeButton)
  })

  // Avoid repeating the role when it is also being used as the profile display name.
  document.querySelectorAll('.admin-profile').forEach((profile) => {
    const name = profile.querySelector('strong')
    const role = profile.querySelector('small')
    if (name && role && name.textContent.trim().toLocaleLowerCase() === role.textContent.trim().toLocaleLowerCase()) {
      role.hidden = true
    }
  })

  document.querySelectorAll('.topbar').forEach((topbar) => {
    // Every role receives the same compact identity badge in the shared top bar.
    const headingGroup = topbar.firstElementChild
    const roleName = document.querySelector('.admin-profile small')?.textContent.trim()
    if (headingGroup && roleName && !headingGroup.querySelector('.topbar-role')) {
      // Separate the title and role into a compact, readable heading group.
      headingGroup.classList.add('topbar-heading')
      const roleBadge = document.createElement('span')
      roleBadge.className = 'topbar-role'
      roleBadge.textContent = roleName
      headingGroup.appendChild(roleBadge)
    }

    let actions = topbar.querySelector('.topbar-actions')
    if (!actions) {
      actions = document.createElement('div')
      actions.className = 'topbar-actions'
      topbar.appendChild(actions)
    }
    actions.innerHTML = `
            <label class="search-box">
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path></svg>
                <input type="search" placeholder="Search anything..." aria-label="Search this page">
            </label>
            <button class="notification-button" type="button" aria-label="Notifications">
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
            </button>`

    // Mark the active role consistently without changing its permissions or routes.
    const normalizedRole = (roleName || '').toLowerCase()
    const roleClass = normalizedRole.includes('subject') || normalizedRole.includes('විෂය') || normalizedRole.includes('விடய')
      ? 'role-subject'
      : normalizedRole.includes('store') || normalizedRole.includes('ගබඩා') || normalizedRole.includes('களஞ்சிய')
        ? 'role-store'
        : normalizedRole.includes('social') || normalizedRole.includes('සමාජ') || normalizedRole.includes('சமூக')
          ? 'role-social'
          : 'role-admin'
    document.body.classList.add('widms-unified-ui', roleClass)

    const search = actions.querySelector('input[type="search"]')
    search?.addEventListener('input', () => {
      const term = search.value.trim().toLowerCase()
      topbar
        .closest('.admin-shell')
        ?.querySelectorAll('table tbody tr')
        .forEach((row) => {
          if (row.querySelector('[colspan]')) return
          row.hidden =
            term !== '' && !row.textContent.toLowerCase().includes(term)
        })
    })
  })

  // Add the item-specific beneficiary field controls only on the Subject Officer rule-builder form.
  const aidConfigForm = document.querySelector('form.aid-config-form')
  if (aidConfigForm) {
    const action = aidConfigForm.querySelector('input[name="action"]')
    const fields = aidConfigForm.querySelector('.aid-config-fields')
    if (action && fields && !aidConfigForm.querySelector('[name="beneficiary_detail_required"]')) {
      action.value = 'save-item-with-detail'
      const detailControls = document.createElement('div')
      detailControls.className = 'item-beneficiary-detail-config'
      detailControls.innerHTML = `
        <label><span>Beneficiary-specific information?</span>
          <select name="beneficiary_detail_required"><option value="0">No</option><option value="1">Yes, require information</option></select>
        </label>
        <label hidden data-beneficiary-detail-label><span>Information Field Name <b class="required-mark" aria-hidden="true">*</b></span><input name="beneficiary_detail_label" maxlength="100" placeholder="e.g. Prescription Power"></label>
        <label hidden data-beneficiary-detail-type><span>Information Type <b class="required-mark" aria-hidden="true">*</b></span><select name="beneficiary_detail_type"><option value="text">Text</option><option value="number">Number</option></select></label>`
      fields.append(detailControls)
      const requirement = detailControls.querySelector('[name="beneficiary_detail_required"]')
      const labelField = detailControls.querySelector('[data-beneficiary-detail-label]')
      const typeField = detailControls.querySelector('[data-beneficiary-detail-type]')
      const labelInput = detailControls.querySelector('[name="beneficiary_detail_label"]')
      const toggleDetailFields = () => {
        const needed = requirement.value === '1'
        labelField.hidden = !needed
        typeField.hidden = !needed
        labelInput.required = needed
      }
      requirement.addEventListener('change', toggleDetailFields)
      toggleDetailFields()
    }
  }

  // Existing rules load their saved item-information definition before enabling the enhanced edit submission.
  const editRuleForm = document.querySelector('form.edit-aid-rule-form')
  if (editRuleForm && !editRuleForm.querySelector('[name="beneficiary_detail_required"]')) {
    const actions = editRuleForm.querySelector('.edit-rule-actions')
    const ruleId = editRuleForm.querySelector('[name="rule_id"]')?.value
    if (actions && ruleId) {
      const section = document.createElement('section')
      section.className = 'edit-rule-beneficiary-section'
      section.innerHTML = `
        <h3>Beneficiary Information</h3>
        <p>Choose whether this aid item must collect a specific value from the beneficiary.</p>
        <div class="edit-rule-grid compact">
          <label>Beneficiary-specific information?<select name="beneficiary_detail_required"><option value="0">No</option><option value="1">Yes, require information</option></select></label>
          <label hidden data-beneficiary-detail-label>Information Field Name<input name="beneficiary_detail_label" maxlength="100" placeholder="e.g. Prescription Power"></label>
          <label hidden data-beneficiary-detail-type>Information Type<select name="beneficiary_detail_type"><option value="text">Text</option><option value="number">Number</option></select></label>
        </div>`
      actions.before(section)
      const requirement = section.querySelector('[name="beneficiary_detail_required"]')
      const labelField = section.querySelector('[data-beneficiary-detail-label]')
      const typeField = section.querySelector('[data-beneficiary-detail-type]')
      const labelInput = section.querySelector('[name="beneficiary_detail_label"]')
      const typeInput = section.querySelector('[name="beneficiary_detail_type"]')
      const toggle = () => {
        const needed = requirement.value === '1'
        labelField.hidden = !needed
        typeField.hidden = !needed
        labelInput.required = needed
      }
      requirement.addEventListener('change', toggle)
      fetch(`item-beneficiary-field.php?rule_id=${encodeURIComponent(ruleId)}`)
        .then((response) => response.ok ? response.json() : Promise.reject())
        .then((field) => {
          if (field.beneficiary_field_label) {
            requirement.value = '1'
            labelInput.value = field.beneficiary_field_label
            typeInput.value = field.beneficiary_field_type === 'number' ? 'number' : 'text'
          }
          toggle()
          const action = document.createElement('input')
          action.type = 'hidden'
          action.name = 'action'
          action.value = 'save-rule-with-detail'
          editRuleForm.append(action)
        })
        .catch(() => toggle())
    }
  }

  const sidebar = document.getElementById('admin-sidebar')
  const overlay = document.getElementById('sidebar-overlay')
  const menuButton = document.getElementById('menu-button')
  const closeButton = document.getElementById('sidebar-close')
  const sidebarNav = sidebar?.querySelector('.sidebar-nav')
  const sidebarScrollKey = 'widms-sidebar-scroll'

  if (!sidebar || !overlay || !menuButton || !closeButton) return

  if (sidebar.classList.contains('management-role-sidebar')) {
    document.body.classList.add('admin-ui')
  }

  if (sidebarNav) {
    const savedScroll = Number(sessionStorage.getItem(sidebarScrollKey))
    if (Number.isFinite(savedScroll) && savedScroll > 0) {
      sidebarNav.scrollTop = savedScroll
    } else {
      sidebarNav
        .querySelector('.nav-link.active')
        ?.scrollIntoView({ block: 'nearest' })
    }

    const rememberSidebarPosition = () =>
      sessionStorage.setItem(sidebarScrollKey, String(sidebarNav.scrollTop))
    sidebarNav.addEventListener('scroll', rememberSidebarPosition, {
      passive: true,
    })
    sidebarNav
      .querySelectorAll('.nav-link')
      .forEach((link) =>
        link.addEventListener('click', rememberSidebarPosition),
      )
    window.addEventListener('pagehide', rememberSidebarPosition)
  }

  const setSidebar = (open) => {
    sidebar.classList.toggle('open', open)
    overlay.classList.toggle('show', open)
    document.body.classList.toggle('nav-open', open)
  }

  menuButton.addEventListener('click', () => setSidebar(true))
  closeButton.addEventListener('click', () => setSidebar(false))
  overlay.addEventListener('click', () => setSidebar(false))

  window.addEventListener('resize', () => {
    if (window.innerWidth > 991) setSidebar(false)
  })

  document.querySelectorAll('.alert-success').forEach((notification) => {
    window.setTimeout(() => {
      dismissAlert(notification)
    }, 3500)
  })
})
