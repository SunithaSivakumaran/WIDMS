(() => {
    const form = document.querySelector('.users-page .users-filters');
    const table = document.querySelector('.users-page .users-table');
    const error = document.querySelector('[data-user-filter-error]');
    if (!form || !table) return;

    let timer;
    let controller;
    let revision = 0;
    const search = form.elements.namedItem('search');

    const update = async (version) => {
        controller = new AbortController();
        const url = new URL(form.action, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        const language = new URL(window.location.href).searchParams.get('lang');
        if (language) url.searchParams.set('lang', language);
        try {
            const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', cache: 'no-store' });
            if (version !== revision) return;
            if (response.redirected) {
                window.location.assign(response.url);
                return;
            }
            if (!response.ok) throw new Error('User search failed.');
            const html = await response.text();
            if (version !== revision) return;
            const page = new DOMParser().parseFromString(html, 'text/html');
            const rows = page.querySelector('.users-page .users-table tbody');
            if (!rows) throw new Error('User results are unavailable.');
            table.querySelector('tbody').replaceWith(document.importNode(rows, true));
            table.closest('.users-table-wrap').scrollTop = 0;
            window.history.replaceState(null, '', url);
        } catch (exception) {
            if (exception.name !== 'AbortError' && version === revision) error.hidden = false;
        } finally {
            if (version === revision) table.removeAttribute('aria-busy');
        }
    };

    const schedule = (delay = 0) => {
        clearTimeout(timer);
        controller?.abort();
        const version = ++revision;
        error.hidden = true;
        table.setAttribute('aria-busy', 'true');
        timer = setTimeout(() => update(version), delay);
    };
    // No minimum search length: single letters, partial words and deletion all work.
    search.addEventListener('input', event => {
        if (!event.isComposing) schedule(180);
    });
    search.addEventListener('compositionend', () => schedule(180));
    form.querySelectorAll('select').forEach(select => select.addEventListener('change', () => schedule()));
    form.addEventListener('submit', event => { event.preventDefault(); schedule(); });
    form.querySelector('[data-clear-user-filters]').addEventListener('click', event => {
        event.preventDefault();
        search.value = '';
        form.elements.namedItem('status').value = '';
        form.elements.namedItem('role').value = '';
        schedule();
    });
})();
