document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-camp-approval]').forEach(form => {
    const reason = form.elements.reason;
    form.querySelectorAll('button[name="decision"]').forEach(button => {
      button.addEventListener('click', () => {
        reason.required = button.value === 'rejected';
        reason.setCustomValidity('');
        if (reason.required && !reason.value.trim()) {
          reason.setCustomValidity(reason.dataset.requiredMessage);
        }
      });
    });
    reason.addEventListener('input', () => reason.setCustomValidity(''));
  });
});
