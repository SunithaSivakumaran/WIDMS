document.addEventListener('DOMContentLoaded', () => {
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
      notification.classList.add('notification-hiding')
      window.setTimeout(() => notification.remove(), 300)
    }, 3500)
  })
})
