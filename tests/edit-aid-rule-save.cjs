const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const page = fs.readFileSync(path.join(__dirname, '../modules/subject-officer/edit-aid-rule.php'), 'utf8');
const editor = fs.readFileSync(path.join(__dirname, '../public/assets/js/admin-dashboard.js'), 'utf8');
const guard = fs.readFileSync(path.join(__dirname, '../public/assets/js/form-submit-guard.js'), 'utf8');

assert.match(page, /<form method="post" class="edit-aid-rule-form"/);
assert.match(page, /<button class="admin-primary-action" type="submit">Save Changes<\/button>/);
assert.doesNotMatch(page, /edit-rule-confirm-dialog|edit-aid-rule\.js/);
assert.match(editor, /action\.value = 'save-rule-with-detail'/);
assert.match(editor, /name="beneficiary_fields_json"/);
assert.match(guard, /if \(form\.dataset\.widmsSubmitting === 'true'\)/);

console.log('Eligibility rule Save Changes posts directly with beneficiary fields and duplicate-click protection.');
