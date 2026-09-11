<?php
declare(strict_types=1);

if (!function_exists('renderCorrectionRequestForm')) {
    /**
     * Render the shared correction-request form used by operational roles.
     *
     * Keeping the fields and layout here prevents each role from drifting
     * away from the Store Keeper correction workflow.
     */
    function renderCorrectionRequestForm(
        array $values,
        array $errorTypes,
        string $action = 'dashboard.php?page=correction-requests'
    ): void {
        $value = static function (string $field) use ($values): string {
            return htmlspecialchars((string) ($values[$field] ?? ''), ENT_QUOTES, 'UTF-8');
        };
        $safeAction = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        ?>
        <form method="post" action="<?= $safeAction ?>" class="correction-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="correction-grid">
                <div class="correction-field-card correction-reference-field">
                    <span class="correction-field-label">Inventory Record Reference</span>
                    <small class="correction-reference-help">Corrections are available only for batches and payments dated within the last 7 days.</small>
                    <div class="correction-reference-control">
                        <select name="reference_type" id="correction-reference-type" aria-label="Inventory reference type" required>
                            <option value="batch" <?= ($values['reference_type'] ?? 'batch') === 'batch' ? 'selected' : '' ?>>Batch Number</option>
                            <option value="payment" <?= ($values['reference_type'] ?? '') === 'payment' ? 'selected' : '' ?>>Payment Number</option>
                        </select>
                        <input name="record_reference" id="correction-record-reference" maxlength="100" aria-label="Inventory record reference" placeholder="e.g. BAT-0001" value="<?= $value('record_reference') ?>" required>
                    </div>
                </div>
                <label>
                    Field to Correct
                    <select name="error_type" id="correction-error-type" required>
                        <?php foreach ($errorTypes as $errorValue => $label): ?>
                            <?php $referenceTypes = in_array($errorValue, ['wrong-payment-amount', 'wrong-check-number'], true) ? 'batch payment' : 'batch'; ?>
                            <option value="<?= htmlspecialchars((string) $errorValue, ENT_QUOTES, 'UTF-8') ?>" data-reference-types="<?= $referenceTypes ?>" <?= ($values['error_type'] ?? '') === $errorValue ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="full-width">
                    Current (incorrect) Value
                    <input name="current_value" id="correction-current-value" placeholder="Select a valid reference and field" value="<?= $value('current_value') ?>" readonly aria-readonly="true" required>
                    <small class="correction-current-value-status" id="correction-current-value-status" aria-live="polite">Loaded automatically from the current database record.</small>
                </label>
                <label class="full-width">
                    Proposed Correction
                    <input name="proposed_correction" id="correction-proposed-value" placeholder="What it should be changed to" value="<?= $value('proposed_correction') ?>" required>
                </label>
                <label class="full-width">
                    Reason
                    <textarea name="request_reason" rows="3" placeholder="Explain why this correction is needed..." required><?= $value('request_reason') ?></textarea>
                </label>
            </div>
            <button class="correction-submit-button" type="submit">Submit Correction Request</button>
        </form>
        <script>
        (() => {
            const type = document.getElementById('correction-reference-type');
            const reference = document.getElementById('correction-record-reference');
            const errorType = document.getElementById('correction-error-type');
            const currentValue = document.getElementById('correction-current-value');
            const proposedValue = document.getElementById('correction-proposed-value');
            const status = document.getElementById('correction-current-value-status');
            if (!type || !reference || !errorType || !currentValue || !proposedValue || !status) return;
            let requestSequence = 0;
            let lookupTimer;
            const updatePlaceholder = () => {
                const placeholders = { batch: 'e.g. BAT-0001', payment: 'e.g. PAY-0001' };
                reference.placeholder = placeholders[type.value] || placeholders.batch;
            };
            const updateErrorTypes = () => {
                const options = Array.from(errorType.options);
                options.forEach(option => {
                    const allowed = (option.dataset.referenceTypes || '').split(' ').includes(type.value);
                    option.disabled = !allowed;
                    option.hidden = !allowed;
                });
                if (errorType.selectedOptions[0]?.disabled) {
                    const firstAllowed = options.find(option => !option.disabled);
                    if (firstAllowed) errorType.value = firstAllowed.value;
                }
            };
            const showStatus = (message, isError = false) => {
                status.textContent = message;
                status.classList.toggle('is-error', isError);
            };
            const updateProposedField = () => {
                proposedValue.type = 'text';
                proposedValue.removeAttribute('min');
                proposedValue.removeAttribute('max');
                proposedValue.removeAttribute('step');
                proposedValue.maxLength = 100;
                proposedValue.placeholder = 'Enter the corrected value';
                if (errorType.value === 'wrong-quantity') {
                    proposedValue.type = 'number';
                    proposedValue.min = '1';
                    proposedValue.step = '1';
                    proposedValue.placeholder = 'Enter the corrected quantity';
                } else if (['wrong-unit-cost', 'wrong-payment-amount'].includes(errorType.value)) {
                    proposedValue.type = 'number';
                    proposedValue.min = '0';
                    proposedValue.step = '0.01';
                    proposedValue.placeholder = errorType.value === 'wrong-unit-cost' ? 'Enter the corrected unit cost' : 'Enter the corrected payment amount';
                } else if (errorType.value === 'wrong-date') {
                    proposedValue.type = 'date';
                    proposedValue.max = new Date().toISOString().slice(0, 10);
                } else if (errorType.value === 'wrong-bill-number') {
                    proposedValue.placeholder = 'Enter the corrected bill / invoice number';
                } else if (errorType.value === 'wrong-check-number') {
                    proposedValue.placeholder = 'Enter the corrected check number';
                }
            };
            const loadCurrentValue = async () => {
                window.clearTimeout(lookupTimer);
                const enteredReference = reference.value.trim();
                const sequence = ++requestSequence;
                currentValue.value = '';
                if (!enteredReference) {
                    showStatus('Enter a reference to load its current value.');
                    return;
                }
                showStatus('Loading current value...');
                const query = new URLSearchParams({
                    reference_type: type.value,
                    record_reference: enteredReference,
                    error_type: errorType.value
                });
                try {
                    const response = await fetch(`dashboard.php?page=correction-reference-value&${query.toString()}`, { headers: { Accept: 'application/json' } });
                    const result = await response.json();
                    if (sequence !== requestSequence) return;
                    if (!response.ok || !result.ok) throw new Error(result.message || 'Unable to load the current value.');
                    reference.value = result.record_reference;
                    currentValue.value = result.current_value;
                    showStatus('Current value loaded from the database.');
                } catch (error) {
                    if (sequence !== requestSequence) return;
                    showStatus(error.message || 'Unable to load the current value.', true);
                }
            };
            const scheduleLookup = () => {
                window.clearTimeout(lookupTimer);
                lookupTimer = window.setTimeout(loadCurrentValue, 350);
            };
            type.addEventListener('change', () => {
                updatePlaceholder();
                updateErrorTypes();
                updateProposedField();
                reference.value = '';
                currentValue.value = '';
                proposedValue.value = '';
                showStatus('Enter a reference to load its current value.');
            });
            reference.addEventListener('input', scheduleLookup);
            reference.addEventListener('blur', loadCurrentValue);
            errorType.addEventListener('change', () => {
                proposedValue.value = '';
                updateProposedField();
                loadCurrentValue();
            });
            updatePlaceholder();
            updateErrorTypes();
            updateProposedField();
            if (reference.value.trim()) loadCurrentValue();
        })();
        </script>
        <?php
    }
}
