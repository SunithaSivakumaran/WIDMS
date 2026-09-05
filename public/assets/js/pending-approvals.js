document.addEventListener('DOMContentLoaded', () => {
  // Only the registration control switches an in-page panel; the other controls open full workflow pages.
  const tabs = document.querySelectorAll('.approval-tab[data-tab]')
  const panels = document.querySelectorAll('.approval-tab-panel')

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((item) => {
        const active = item === tab
        item.classList.toggle('active', active)
        item.setAttribute('aria-selected', String(active))
      })

      panels.forEach((panel) => {
        panel.classList.toggle(
          'active',
          panel.dataset.panel === tab.dataset.tab,
        )
      })
    })
  })
})
