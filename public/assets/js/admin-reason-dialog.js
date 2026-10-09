/*
 * Destructive admin actions ask for a reason only after the action is chosen,
 * keeping tables compact while preserving mandatory server-side validation.
 */
const reasonDialog = document.createElement('dialog');
reasonDialog.className = 'admin-reason-dialog';
reasonDialog.innerHTML = `
    <form method="dialog" class="reason-dialog-card">
        <div class="reason-dialog-heading">
            <div><span class="reason-dialog-icon">!</span><h2></h2></div>
            <button class="reason-dialog-close" type="button" aria-label="Close">&times;</button>
        </div>
        <label><span class="reason-dialog-label">Reason</span>
            <textarea rows="4" maxlength="500" required></textarea>
        </label>
        <p class="reason-dialog-error" hidden>A reason is required.</p>
        <div class="reason-dialog-actions">
            <button class="reason-dialog-cancel" type="button">Cancel</button>
            <button class="reason-dialog-confirm" type="button"></button>
        </div>
    </form>`;
document.body.appendChild(reasonDialog);

let activeTrigger = null;
const reasonInput = reasonDialog.querySelector('textarea');
const reasonError = reasonDialog.querySelector('.reason-dialog-error');
const reasonLabel = reasonDialog.querySelector('.reason-dialog-label');
const cancelButton = reasonDialog.querySelector('.reason-dialog-cancel');
const confirmButton = reasonDialog.querySelector('.reason-dialog-confirm');

// Close explicitly because dynamically created dialog forms behave differently across browsers.
function closeReasonDialog() {
    reasonInput.value = '';
    reasonError.hidden = true;
    activeTrigger = null;
    reasonDialog.close();
}

reasonDialog.querySelector('.reason-dialog-cancel').addEventListener('click', closeReasonDialog);
reasonDialog.querySelector('.reason-dialog-close').addEventListener('click', closeReasonDialog);
reasonDialog.addEventListener('cancel', (event) => {
    event.preventDefault();
    closeReasonDialog();
});
reasonDialog.addEventListener('click', (event) => {
    if (event.target === reasonDialog) closeReasonDialog();
});

// Delegation also covers action buttons inserted by live-filtered tables.
document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-reason-trigger]');
        if (!trigger || trigger.disabled) return;
        activeTrigger = trigger;
        reasonDialog.querySelector('h2').textContent = trigger.dataset.dialogTitle;
        confirmButton.textContent = trigger.dataset.dialogConfirm;
        reasonLabel.textContent = trigger.dataset.reasonLabel || 'Reason';
        reasonError.textContent = trigger.dataset.reasonRequired || 'A reason is required.';
        cancelButton.textContent = trigger.dataset.cancelLabel || 'Cancel';
        reasonInput.value = '';
        reasonError.hidden = true;
        reasonDialog.showModal();
        reasonInput.focus();
});

confirmButton.addEventListener('click', () => {
    const reason = reasonInput.value.trim();
    if (reason === '') {
        reasonError.hidden = false;
        reasonInput.focus();
        return;
    }

    const form = activeTrigger.closest('form');
    form.elements[activeTrigger.dataset.reasonField].value = reason;

    // A hidden field carries the destructive action because its visible trigger is not a submit button.
    const action = document.createElement('input');
    action.type = 'hidden';
    action.name = activeTrigger.dataset.submitName;
    action.value = activeTrigger.dataset.submitValue;
    form.appendChild(action);
    reasonDialog.close();
    form.requestSubmit();
});
