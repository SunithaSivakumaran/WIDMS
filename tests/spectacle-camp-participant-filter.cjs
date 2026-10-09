const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/spectacle-camps.js'), 'utf8');
const rows = [
  { textContent: 'SCP-1 Sunitha 200183804299 Reading Only Selected for spectacles', dataset: { scStatus: 'approved' }, hidden: false },
  { textContent: 'SCP-2 Nimal 200183804298 Rejected', dataset: { scStatus: 'rejected' }, hidden: false },
  { textContent: 'SCP-3 Maya 200183804297 Bifocal Distributed', dataset: { scStatus: 'distributed' }, hidden: false },
];
const empty = { hidden: true };
const statusFilter = { value: '', addEventListener(event, callback) { assert.equal(event, 'change'); this.onChange = callback; } };
const section = {
  querySelectorAll(selector) { assert.equal(selector, '[data-sc-row]'); return rows; },
  querySelector(selector) { return selector === '[data-sc-status-filter]' ? statusFilter : selector === '[data-sc-empty]' ? empty : null; },
};
const search = {
  value: '',
  closest(selector) { assert.equal(selector, '.sc-table-section'); return section; },
  addEventListener(event, callback) { assert.equal(event, 'input'); this.onInput = callback; },
};
const document = { querySelectorAll(selector) { return selector === '[data-sc-search]' ? [search] : []; } };
vm.runInNewContext(source, { document });

search.value = 'reading only';
search.onInput();
assert.deepEqual(rows.map(row => row.hidden), [false, true, true]);

search.value = '';
statusFilter.value = 'rejected';
statusFilter.onChange();
assert.deepEqual(rows.map(row => row.hidden), [true, false, true]);

statusFilter.value = 'distributed';
statusFilter.onChange();
assert.deepEqual(rows.map(row => row.hidden), [true, true, false]);

search.value = 'SCP-1';
search.onInput();
assert.deepEqual(rows.map(row => row.hidden), [true, true, true]);
assert.equal(empty.hidden, false);

console.log('PASS: participant search and status filter work together.');

const camps = [
  { textContent: 'VC-1 Hikkaduwa Approved Camp Conducted Pending Admin approval', dataset: { scCampStage: 'conducted', scCampDistrict: '1', scStockStatus: 'pending', scDistributionStatus: 'none' }, hidden: false },
  { textContent: 'VC-2 Bentota Completed Released', dataset: { scCampStage: 'completed', scCampDistrict: '2', scStockStatus: 'approved released', scDistributionStatus: 'in-distribution' }, hidden: false },
];
const campEmpty = { hidden: true };
const campFilter = { value: '', addEventListener(event, callback) { assert.equal(event, 'change'); this.onChange = callback; } };
const districtFilter = { value: '', addEventListener(event, callback) { assert.equal(event, 'change'); this.onChange = callback; } };
const stockFilter = { value: '', addEventListener(event, callback) { assert.equal(event, 'change'); this.onChange = callback; } };
const distributionFilter = { value: '', addEventListener(event, callback) { assert.equal(event, 'change'); this.onChange = callback; } };
const campSection = {
  querySelectorAll(selector) { return selector === '[data-sc-row]' ? camps : []; },
  querySelector(selector) { return selector === '[data-sc-camp-status-filter]' ? campFilter : selector === '[data-sc-camp-district-filter]' ? districtFilter : selector === '[data-sc-stock-status-filter]' ? stockFilter : selector === '[data-sc-distribution-status-filter]' ? distributionFilter : selector === '[data-sc-empty]' ? campEmpty : null; },
};
const campSearch = {
  value: '',
  closest() { return campSection; },
  addEventListener(event, callback) { assert.equal(event, 'input'); this.onInput = callback; },
};
vm.runInNewContext(source, { document: { querySelectorAll(selector) { return selector === '[data-sc-search]' ? [campSearch] : []; } } });
campFilter.value = 'completed';
campFilter.onChange();
assert.deepEqual(camps.map(row => row.hidden), [true, false]);
campSearch.value = 'Hikkaduwa';
campSearch.onInput();
assert.deepEqual(camps.map(row => row.hidden), [true, true]);
assert.equal(campEmpty.hidden, false);
campFilter.value = '';
campSearch.value = '';
districtFilter.value = '1';
districtFilter.onChange();
assert.deepEqual(camps.map(row => row.hidden), [false, true]);
districtFilter.value = '';
stockFilter.value = 'released';
stockFilter.onChange();
assert.deepEqual(camps.map(row => row.hidden), [true, false]);
stockFilter.value = '';
distributionFilter.value = 'in-distribution';
distributionFilter.onChange();
assert.deepEqual(camps.map(row => row.hidden), [true, false]);
distributionFilter.value = 'distributed';
distributionFilter.onChange();
assert.deepEqual(camps.map(row => row.hidden), [true, true]);
console.log('PASS: camp search, camp status, stock status, distribution status and district filters work together.');

const approvalCards = [
  { textContent: 'VC-1 Katuwana Subject Officer', hidden: false },
  { textContent: 'SCB-123 Reading Only Distance Only', hidden: false },
];
const approvalEmpty = { hidden: true };
const approvalSection = {
  querySelectorAll(selector) { assert.equal(selector, '[data-sc-approval-card]'); return approvalCards; },
  querySelector(selector) { assert.equal(selector, '[data-sc-approval-empty]'); return approvalEmpty; },
};
const approvalSearch = {
  value: '',
  closest(selector) { assert.equal(selector, '.sc-approval-list'); return approvalSection; },
  addEventListener(event, callback) { assert.equal(event, 'input'); this.onInput = callback; },
};
vm.runInNewContext(source, { document: { querySelectorAll(selector) { return selector === '[data-sc-approval-search]' ? [approvalSearch] : []; } } });
approvalSearch.value = 'distance only';
approvalSearch.onInput();
assert.deepEqual(approvalCards.map(card => card.hidden), [true, false]);
approvalSearch.value = 'no such request';
approvalSearch.onInput();
assert.deepEqual(approvalCards.map(card => card.hidden), [true, true]);
assert.equal(approvalEmpty.hidden, false);
console.log('PASS: approval-card search filters camps and stock batches.');
