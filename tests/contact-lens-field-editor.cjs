const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/admin-dashboard.js'), 'utf8');
const start = source.indexOf('  const mountBeneficiaryFieldEditor =');
const end = source.indexOf('  const aidConfigForm =', start);
assert.ok(start >= 0 && end > start, 'Beneficiary-field editor must be present');

const control = () => ({ value: '', hidden: true, textContent: '', listeners: {}, addEventListener(name, handler) { this.listeners[name] = handler; }, focus() {} });
const input = control();
const type = control();
const add = control();
const error = control();
const hidden = control();
const rows = [];
const body = { innerHTML: '', append(row) { rows.push(row); } };
const section = {
  querySelector(selector) {
    return {
      '[data-beneficiary-field-name]': input,
      '[data-beneficiary-field-type]': type,
      '[data-beneficiary-field-add]': add,
      'tbody': body,
      '.beneficiary-field-error': error,
      '[name="beneficiary_fields_json"]': hidden,
    }[selector];
  },
};
const document = {
  createElement(tag) {
    if (tag === 'section') return section;
    assert.equal(tag, 'tr');
    const badge = control();
    const edit = control();
    const remove = control();
    return {
      children: [control()],
      querySelector(selector) {
        return {
          '.beneficiary-field-type-badge': badge,
          '.beneficiary-field-edit': edit,
          '.beneficiary-field-delete': remove,
        }[selector] || null;
      },
    };
  },
};
const mount = new Function('document', `${source.slice(start, end)}; return mountBeneficiaryFieldEditor;`)(document);
const form = { dataset: { contactLens: '1' }, querySelector() { return null; } };
const editor = mount(form, { append(node) { assert.equal(node, section); } });

input.value = 'Power';
type.value = 'number';
add.listeners.click();
assert.deepEqual(JSON.parse(hidden.value), [{ label: 'Power', type: 'number', is_system: false }]);
assert.equal(error.hidden, true);

editor.setFields([{ label: 'Power', type: 'number', is_system: 0 }]);
assert.deepEqual(JSON.parse(hidden.value), [{ label: 'Power', type: 'number', is_system: false }]);
assert.ok(rows.length > 0);

console.log('Contact Lens Power can be added and reloaded as a normal beneficiary-information field.');
