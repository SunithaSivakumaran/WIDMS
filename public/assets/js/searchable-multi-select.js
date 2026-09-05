document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-searchable-multi-select]').forEach((selector) => {
    const dropdown = selector.querySelector('[data-multi-dropdown]')
    const search = selector.querySelector('[data-multi-search]')
    const selection = selector.querySelector('[data-multi-selection]')
    const count = selector.querySelector('[data-multi-count]')
    const noResults = selector.querySelector('[data-multi-no-results]')
    const options = [...selector.querySelectorAll('[data-multi-option]')]
    const removeLabel = selector.dataset.removeLabel || 'Remove'

    if (!search || !dropdown || !selection) return

    const closeDropdown = () => {
      dropdown.hidden = true
      search.setAttribute('aria-expanded', 'false')
    }

    const openDropdown = () => {
      dropdown.hidden = false
      search.setAttribute('aria-expanded', 'true')
    }

    // Reflect checked values as removable chips without changing submitted checkbox data.
    const renderSelection = () => {
      const checkedOptions = options.filter((option) => option.querySelector('input')?.checked)
      selection.replaceChildren()

      checkedOptions.forEach((option) => {
        const checkbox = option.querySelector('input')
        const label = option.querySelector('span')?.textContent.trim() || ''
        option.classList.add('selected')

        const chip = document.createElement('span')
        chip.className = 'multi-select-chip'
        chip.append(document.createTextNode(label))

        const remove = document.createElement('button')
        remove.type = 'button'
        remove.className = 'multi-select-chip-remove'
        remove.setAttribute('aria-label', `${removeLabel} ${label}`)
        remove.innerHTML = '&times;'
        remove.addEventListener('click', () => {
          checkbox.checked = false
          option.classList.remove('selected')
          renderSelection()
        })
        chip.appendChild(remove)
        selection.appendChild(chip)
      })

      options.forEach((option) => {
        if (!option.querySelector('input')?.checked) option.classList.remove('selected')
      })

      if (count) {
        count.textContent = String(checkedOptions.length)
        count.hidden = checkedOptions.length === 0
      }
    }

    const filterOptions = () => {
      const term = search?.value.trim().toLocaleLowerCase() || ''
      let visibleCount = 0
      options.forEach((option) => {
        const matches = option.textContent.toLocaleLowerCase().includes(term)
        option.hidden = !matches
        if (matches) visibleCount += 1
      })
      if (noResults) noResults.hidden = visibleCount !== 0
    }

    options.forEach((option) => {
      option.querySelector('input')?.addEventListener('change', () => {
        renderSelection()
        search.value = ''
        filterOptions()
        search.focus()
      })
    })
    search.addEventListener('focus', () => {
      filterOptions()
      openDropdown()
    })
    search.addEventListener('input', () => {
      filterOptions()
      openDropdown()
    })

    // Close predictably with Escape or an outside click on long forms.
    selector.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeDropdown()
        search.blur()
      }
    })
    document.addEventListener('click', (event) => {
      if (!selector.contains(event.target)) closeDropdown()
    })

    renderSelection()
  })
})
