// Use delegation as a safety net: alerts may be rendered by a page fragment
// or the dashboard script may be loaded more than once.
document.addEventListener('click', (event) => {
  const closeButton = event.target.closest?.('.notification-close')
  if (!closeButton) return
  const notification = closeButton.closest('.alert-success, .alert-danger')
  if (!notification) return
  event.preventDefault()
  event.stopPropagation()
  notification.hidden = true
  notification.style.display = 'none'
  if (notification.parentNode) notification.parentNode.removeChild(notification)
}, true)

const initializeWidmsDashboard = () => {
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
    closeButton.addEventListener('click', (event) => {
      event.preventDefault()
      event.stopPropagation()
      notification.hidden = true
      notification.style.display = 'none'
      if (notification.parentNode) notification.parentNode.removeChild(notification)
    })
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

  // Each aid item can request several beneficiary fields. Keep the field list
  // in a small editable table and submit it as one validated JSON definition.
  const mountBeneficiaryFieldEditor = (form, target, insertBefore = false) => {
    if (!form || form.querySelector('[name="beneficiary_fields_json"]')) return null
    const section = document.createElement('section')
    section.className = 'beneficiary-fields-editor'
    section.innerHTML = `
      <div class="beneficiary-fields-heading"><div><h3>Beneficiary Information</h3><p>Add every detail this aid item must collect. Image or PDF fields are suitable for a Doctor Report and other supporting documents.</p></div></div>
      <div class="beneficiary-field-add-row">
        <label>Information Field Name<input type="text" maxlength="100" data-beneficiary-field-name placeholder="e.g. Doctor Report"></label>
        <label>Information Type<select data-beneficiary-field-type><option value="text">Text</option><option value="number">Number</option><option value="date">Date</option><option value="image">Image file</option><option value="pdf">PDF document</option></select></label>
        <button type="button" class="beneficiary-field-add" data-beneficiary-field-add>Add Field</button>
      </div>
      <p class="beneficiary-field-error" hidden></p>
      <div class="beneficiary-fields-table-wrap"><table class="beneficiary-fields-table"><thead><tr><th>Field Name</th><th>Type</th><th>Action</th></tr></thead><tbody></tbody></table></div>
      <input type="hidden" name="beneficiary_fields_json" value="[]">`
    if (insertBefore) target.before(section); else target.append(section)
    const input = section.querySelector('[data-beneficiary-field-name]')
    const type = section.querySelector('[data-beneficiary-field-type]')
    const add = section.querySelector('[data-beneficiary-field-add]')
    const body = section.querySelector('tbody')
    const error = section.querySelector('.beneficiary-field-error')
    const hidden = section.querySelector('[name="beneficiary_fields_json"]')
    let fields = []
    let editingIndex = -1
    const typeName = value => ({ text: 'Text', number: 'Number', date: 'Date', image: 'Image file', pdf: 'PDF document' }[value] || 'Text')
    const render = () => {
      hidden.value = JSON.stringify(fields)
      body.innerHTML = ''
      if (!fields.length) body.innerHTML = '<tr><td colspan="3" class="beneficiary-fields-empty">No beneficiary information fields added.</td></tr>'
      fields.forEach((field, index) => {
        const row = document.createElement('tr')
        // Power is a permanent field for the built-in vision items. Treat it
        // as protected even when an older cached response omits is_system.
        const isProtected = field.is_system || field.label.trim().toLocaleLowerCase() === 'power'
        row.innerHTML = isProtected
          ? `<td></td><td><span class="beneficiary-field-type-badge"></span></td><td class="beneficiary-fields-actions"><span class="beneficiary-field-system-badge"></span></td>`
          : `<td></td><td><span class="beneficiary-field-type-badge"></span></td><td class="beneficiary-fields-actions"><button type="button" class="beneficiary-field-edit">Edit</button><button type="button" class="beneficiary-field-delete">Delete</button></td>`
        row.children[0].textContent = field.label
        row.querySelector('.beneficiary-field-type-badge').textContent = typeName(field.type)
        if (isProtected) row.querySelector('.beneficiary-field-system-badge').textContent = form.dataset.builtInLabel || 'Built-in'
        row.querySelector('.beneficiary-field-edit')?.addEventListener('click', () => { input.value = field.label; type.value = field.type; editingIndex = index; add.textContent = 'Update Field'; input.focus() })
        row.querySelector('.beneficiary-field-delete')?.addEventListener('click', () => { fields.splice(index, 1); if (editingIndex === index) { editingIndex = -1; input.value = ''; type.value = 'text'; add.textContent = 'Add Field' } render() })
        body.append(row)
      })
    }
    add.addEventListener('click', () => {
      const label = input.value.trim().replace(/\s+/g, ' ')
      error.hidden = true
      if (label.length < 2) { error.textContent = 'Enter an information field name.'; error.hidden = false; input.focus(); return }
      if (fields.some((field, index) => index !== editingIndex && field.label.toLocaleLowerCase() === label.toLocaleLowerCase())) { error.textContent = 'Each information field needs a different name.'; error.hidden = false; return }
      const field = { label, type: type.value, is_system: false }
      if (editingIndex >= 0) fields[editingIndex] = field; else if (fields.length < 10) fields.push(field); else { error.textContent = 'You can add up to 10 information fields.'; error.hidden = false; return }
      editingIndex = -1; input.value = ''; type.value = 'text'; add.textContent = 'Add Field'; render()
    })
    render()
    return { setFields: values => { fields = Array.isArray(values) ? values.filter(field => field && typeof field.label === 'string' && ['text', 'number', 'date', 'image', 'pdf'].includes(field.type)).map(field => ({ label: field.label, type: field.type, is_system: Number(field.is_system) === 1 })) : []; render() } }
  }

  const aidConfigForm = document.querySelector('form.aid-config-form')
  if (aidConfigForm) {
    const action = aidConfigForm.querySelector('input[name="action"]')
    const fields = aidConfigForm.querySelector('.aid-config-fields')
    if (action && fields) { action.value = 'save-item-with-detail'; mountBeneficiaryFieldEditor(aidConfigForm, fields) }
  }

  const editRuleForm = document.querySelector('form.edit-aid-rule-form')
  if (editRuleForm) {
    const actions = editRuleForm.querySelector('.edit-rule-actions')
    const ruleId = editRuleForm.querySelector('[name="rule_id"]')?.value
    if (actions && ruleId) {
      const action = document.createElement('input')
      action.type = 'hidden'; action.name = 'action'; action.value = 'save-rule-with-detail'; editRuleForm.append(action)
      const editor = mountBeneficiaryFieldEditor(editRuleForm, actions, true)
      fetch(`item-beneficiary-field.php?rule_id=${encodeURIComponent(ruleId)}`)
        .then(response => response.ok ? response.json() : Promise.reject())
        .then(data => editor?.setFields(data.fields))
        .catch(() => {})
    }
  }

  // Request tables stay compact while a shared dialog presents every configured
  // beneficiary value, image, and PDF document in a readable layout.
  const extraInfoButtons = [...document.querySelectorAll('.request-extra-info-button')]
  if (extraInfoButtons.length) {
    const dialog = document.createElement('dialog')
    dialog.className = 'request-extra-info-dialog'
    dialog.innerHTML = `
      <div class="request-extra-info-card">
        <header><div><span class="request-extra-info-kicker">Aid Request</span><h2></h2></div><button type="button" class="request-extra-info-x" aria-label="Close">&times;</button></header>
        <div class="request-extra-info-body"></div>
        <footer><button type="button" class="request-extra-info-close">Close</button></footer>
      </div>`
    document.body.append(dialog)
    const title = dialog.querySelector('h2')
    const body = dialog.querySelector('.request-extra-info-body')
    const closeButton = dialog.querySelector('.request-extra-info-close')
    const closeIcon = dialog.querySelector('.request-extra-info-x')
    const close = () => dialog.open && dialog.close()
    closeButton.addEventListener('click', close)
    closeIcon.addEventListener('click', close)
    dialog.addEventListener('click', event => { if (event.target === dialog) close() })
    const typeNames = { text: 'Text', number: 'Number', date: 'Date', image: 'Image file', pdf: 'PDF document' }
    extraInfoButtons.forEach(button => button.addEventListener('click', () => {
      let details = []
      try { details = JSON.parse(button.dataset.requestExtraInfo || '[]') } catch (_) { details = [] }
      title.textContent = button.dataset.dialogTitle || 'Beneficiary Details'
      const closeLabel = button.dataset.closeLabel || 'Close'
      closeButton.textContent = closeLabel
      closeIcon.setAttribute('aria-label', closeLabel)
      body.innerHTML = ''
      details.forEach(detail => {
        const row = document.createElement('section')
        row.className = 'request-extra-info-row'
        const heading = document.createElement('div')
        heading.className = 'request-extra-info-row-heading'
        const label = document.createElement('strong')
        label.textContent = detail.label || 'Information'
        const type = document.createElement('span')
        type.textContent = typeNames[detail.type] || 'Text'
        heading.append(label, type)
        row.append(heading)
        const storedPath = typeof detail.value === 'string' && /^uploads\/aid-documents\/[a-zA-Z0-9.-]+$/.test(detail.value) ? detail.value : ''
        if (detail.type === 'image' && storedPath) {
          const link = document.createElement('a'); link.href = storedPath; link.target = '_blank'; link.rel = 'noopener'; link.className = 'request-extra-info-image-link'
          const image = document.createElement('img'); image.src = storedPath; image.alt = detail.label || 'Uploaded image'; link.append(image); row.append(link)
        } else if (detail.type === 'pdf' && storedPath) {
          const link = document.createElement('a'); link.href = storedPath; link.target = '_blank'; link.rel = 'noopener'; link.className = 'request-extra-info-document'; link.textContent = detail.display_value || 'Open PDF document'; row.append(link)
        } else {
          const value = document.createElement('p')
          const rawValue = detail.display_value ?? detail.value ?? '—'
          const isPower = detail.type === 'number' && String(detail.label || '').trim().toLocaleLowerCase() === 'power'
          value.textContent = isPower && rawValue !== '' && Number.isFinite(Number(rawValue))
            ? `${Number(rawValue) >= 0 ? '+' : ''}${Number(rawValue).toFixed(2)}`
            : rawValue
          row.append(value)
        }
        body.append(row)
      })
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '')
    }))
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
}

// The script can also be injected after DOMContentLoaded by dashboard pages.
// Initialize immediately in that case so alert close controls always work.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeWidmsDashboard, { once: true })
} else {
  initializeWidmsDashboard()
}
