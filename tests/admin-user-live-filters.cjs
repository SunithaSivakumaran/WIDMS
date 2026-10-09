// Isolated event/race checks: no browser session, database writes or network sends.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const control = value => ({ value, events: {}, addEventListener(name, handler) { this.events[name] = handler; } });
const fields = { page: control('users'), search: control(''), status: control(''), role: control('') };
const clear = control('');
const error = { hidden: true };
const wrapper = { scrollTop: 50 };
let displayed = 'initial';
let busy = false;
let timer;
let location;
const requests = [];
const form = {
    action: 'https://widms.test/dashboard.php', events: {},
    elements: { namedItem: name => fields[name] },
    querySelectorAll: () => [fields.status, fields.role],
    querySelector: () => clear,
    addEventListener(name, handler) { this.events[name] = handler; },
};
const table = {
    querySelector: () => ({ replaceWith: rows => { displayed = rows; } }),
    closest: () => wrapper,
    setAttribute: () => { busy = true; },
    removeAttribute: () => { busy = false; },
};
const context = {
    URL, URLSearchParams, AbortController,
    setTimeout: fn => { timer = fn; return 1; }, clearTimeout: () => { timer = null; },
    FormData: class { *[Symbol.iterator]() { for (const [key, field] of Object.entries(fields)) yield [key, field.value]; } },
    DOMParser: class { parseFromString(html) { return { querySelector: () => html }; } },
    document: { querySelector: selector => selector.includes('users-filters') ? form : selector.includes('users-table') ? table : error, importNode: rows => rows },
    window: { location: { href: 'https://widms.test/dashboard.php?page=users&lang=ta', assign: url => { location = url; } }, history: { replaceState: (_state, _title, url) => { location = url; } } },
    fetch: (url, options) => new Promise(resolve => requests.push({ url, options, resolve })),
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/assets/js/admin-user-filters.js'), 'utf8'), context);
const finish = async (request, result) => {
    request.resolve({ ok: true, redirected: false, text: async () => result });
    await new Promise(resolve => setImmediate(resolve));
};

(async () => {
    fields.search.value = 'a'; fields.search.events.input({}); timer();
    assert.equal(requests[0].url.searchParams.get('search'), 'a');
    assert.equal(requests[0].url.searchParams.get('lang'), 'ta');
    fields.search.value = 'al'; fields.search.events.input({}); timer();
    assert.equal(requests[0].options.signal.aborted, true);
    await finish(requests[1], 'two-letter-results');
    await finish(requests[0], 'stale-single-letter-results');
    assert.equal(displayed, 'two-letter-results');
    assert.equal(busy, false);
    assert.equal(wrapper.scrollTop, 0);
    fields.search.value = 'alice'; fields.search.events.input({}); timer();
    await finish(requests[2], 'whole-word-results');
    assert.equal(displayed, 'whole-word-results');
    fields.status.value = 'inactive'; fields.status.events.change(); timer();
    assert.equal(requests[3].url.searchParams.get('status'), 'inactive');
    fields.role.value = 'store-keeper'; fields.role.events.change(); timer();
    assert.equal(requests[4].url.searchParams.get('role'), 'store-keeper');
    assert.equal(requests[4].url.searchParams.get('search'), 'alice');
    await finish(requests[4], 'combined-results');
    clear.events.click({ preventDefault() {} }); timer();
    for (const key of ['search','status','role']) assert.equal(requests[5].url.searchParams.get(key), '');
    await finish(requests[5], 'all-users');
    assert.equal(displayed, 'all-users');
    assert.equal(location.searchParams.get('page'), 'users');
    console.log('Admin live filters passed: single letters, partial/full words, status/role changes, clear and stale-response protection.');
})().catch(error => { console.error(error); process.exitCode = 1; });
