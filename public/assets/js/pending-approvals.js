document.addEventListener('DOMContentLoaded', () => {
  const tabs = document.querySelectorAll('.approval-tab')
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
