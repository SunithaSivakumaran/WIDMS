const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/spectacle-camps.js'), 'utf8');
const controls = {};
for (const name of ['camp', 'cancel', 'confirm', 'description']) {
  controls[name] = { textContent: '', listeners: {}, addEventListener(type, listener) { this.listeners[type] = listener; } };
}
const dialog = {
  open: false,
  listeners: {},
  showModal() { this.open = true; },
  close() { this.open = false; },
  addEventListener(type, listener) { this.listeners[type] = listener; },
  querySelector(selector) {
    return {
      '[data-sc-complete-camp]': controls.camp,
      '[data-sc-complete-cancel]': controls.cancel,
      '[data-sc-complete-confirm]': controls.confirm,
      '#sc-complete-description': controls.description,
    }[selector];
  },
};
class HTMLFormElement {
  submit() { this.submitCount++; }
}
const form = new HTMLFormElement();
form.dataset = { campReference: 'VC-4' };
form.listeners = {};
form.submitCount = 0;
form.addEventListener = function (type, listener) { this.listeners[type] = listener; };
form.requestSubmit = function () { throw new Error('Confirmation must bypass the intercepted submit event.'); };
const document = {
  querySelectorAll(selector) { return selector === '[data-sc-complete-form]' ? [form] : []; },
  getElementById(id) { return id === 'sc-complete-dialog' ? dialog : null; },
};
vm.runInNewContext(source, { document, HTMLFormElement });

let event = { prevented: false, preventDefault() { this.prevented = true; } };
form.listeners.submit(event);
assert.equal(event.prevented, true);
assert.equal(dialog.open, true);
assert.equal(controls.camp.textContent, 'VC-4');
assert.equal(form.submitCount, 0);
controls.cancel.listeners.click();
assert.equal(dialog.open, false);
assert.equal(form.submitCount, 0, 'cancel submitted the camp');

event = { prevented: false, preventDefault() { this.prevented = true; } };
form.listeners.submit(event);
assert.equal(dialog.open, true);
controls.confirm.listeners.click();
assert.equal(dialog.open, false);
assert.equal(form.submitCount, 1, 'confirmation did not submit exactly once');
console.log('PASS: camp completion requires confirmation; cancel does not submit.');
