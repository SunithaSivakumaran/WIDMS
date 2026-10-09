const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/spectacle-camps.js'), 'utf8');
const approvedSection = { hidden: false };
const rejectedSection = { hidden: false };
const reason = { required: false, disabled: false };
const categories = [{ required: false, disabled: false }, { required: false, disabled: false }];
const identityField = { value: '200183804299', addEventListener() {}, setCustomValidity() {} };
const form = {
  elements: { nic: identityField, elder_card_number: identityField },
  querySelector(selector) {
    return {
      'input[name="action"]': { value: 'save-participant' },
      '[data-sc-rejection-reason]': reason,
      '[data-sc-approved-section]': approvedSection,
      '[data-sc-rejected-section]': rejectedSection,
    }[selector];
  },
  querySelectorAll(selector) {
    assert.equal(selector, '[data-sc-approval-only]');
    return categories;
  },
};
const decision = {
  value: '',
  form,
  addEventListener(event, callback) {
    assert.equal(event, 'change');
    this.onChange = callback;
  },
};
const document = {
  querySelectorAll(selector) {
    if (selector === 'form') return [form];
    return selector === '[data-sc-decision]' ? [decision] : [];
  },
  getElementById() { return { textContent: 'Identification required' }; },
};

vm.runInNewContext(source, { document });
assert.equal(approvedSection.hidden, true);
assert.equal(rejectedSection.hidden, true);
assert.equal(reason.disabled, true);
assert.equal(categories.every(field => field.disabled), true);

decision.value = 'approved';
decision.onChange();
assert.equal(approvedSection.hidden, false);
assert.equal(rejectedSection.hidden, true);
assert.equal(reason.disabled, true);
assert.equal(categories.every(field => field.required && !field.disabled), true);

decision.value = 'rejected';
decision.onChange();
assert.equal(approvedSection.hidden, true);
assert.equal(rejectedSection.hidden, false);
assert.equal(reason.required, true);
assert.equal(reason.disabled, false);
assert.equal(categories.every(field => field.disabled && !field.required), true);

decision.value = '';
decision.onChange();
assert.equal(approvedSection.hidden, true);
assert.equal(rejectedSection.hidden, true);
assert.equal(reason.required, false);
assert.equal(reason.disabled, true);

console.log('PASS: participant decision reveals only the relevant fields.');
