const companyCombobox = document.querySelector('[data-company-combobox]');

if (companyCombobox) {
    const input = companyCombobox.querySelector('input[role="combobox"]');
    const toggle = companyCombobox.querySelector('.supplier-company-toggle');
    const list = companyCombobox.querySelector('.supplier-company-options');
    const options = Array.from(companyCombobox.querySelectorAll('[data-company-option]'));
    const emptyMessage = companyCombobox.querySelector('[data-company-empty]');
    let activeIndex = -1;

    const setExpanded = (expanded) => {
        list.hidden = !expanded;
        input.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-expanded', String(expanded));
        companyCombobox.classList.toggle('is-open', expanded);
        if (!expanded) {
            activeIndex = -1;
            options.forEach((option) => option.classList.remove('is-active'));
        }
    };

    const visibleOptions = () => options.filter((option) => !option.hidden);

    const filterOptions = () => {
        const query = input.value.trim().toLocaleLowerCase();
        options.forEach((option) => {
            const companyName = option.dataset.companyName.toLocaleLowerCase();
            option.hidden = query !== '' && !companyName.includes(query);
        });
        emptyMessage.hidden = visibleOptions().length !== 0;
        activeIndex = -1;
        options.forEach((option) => option.classList.remove('is-active'));
    };

    const openList = () => {
        filterOptions();
        setExpanded(true);
    };

    const chooseOption = (option) => {
        input.value = option.dataset.companyName;
        setExpanded(false);
        input.focus();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    input.addEventListener('focus', openList);
    input.addEventListener('input', openList);
    toggle.addEventListener('click', () => {
        if (list.hidden) {
            openList();
            input.focus();
        } else {
            setExpanded(false);
        }
    });

    options.forEach((option) => option.addEventListener('click', () => chooseOption(option)));

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setExpanded(false);
            return;
        }
        if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;

        let available = visibleOptions();
        if (list.hidden) openList();
        available = visibleOptions();
        if (event.key === 'Enter') {
            if (activeIndex >= 0 && available[activeIndex]) {
                event.preventDefault();
                chooseOption(available[activeIndex]);
            }
            return;
        }
        if (available.length === 0) return;

        event.preventDefault();
        activeIndex = event.key === 'ArrowDown'
            ? (activeIndex + 1) % available.length
            : (activeIndex <= 0 ? available.length - 1 : activeIndex - 1);
        options.forEach((option) => option.classList.remove('is-active'));
        available[activeIndex].classList.add('is-active');
        available[activeIndex].scrollIntoView({ block: 'nearest' });
    });

    document.addEventListener('click', (event) => {
        if (!companyCombobox.contains(event.target)) setExpanded(false);
    });
}
