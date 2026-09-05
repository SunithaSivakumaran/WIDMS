/*
 * Add one consistent, accessible visibility control to every password field.
 * Building the control here avoids duplicating toggle logic across forms.
 */
document.querySelectorAll('input[type="password"]').forEach((input) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-toggle';
    toggle.setAttribute('aria-label', 'Show password');
    toggle.setAttribute('aria-pressed', 'false');
    toggle.innerHTML = `
        <svg class="eye-icon eye-open" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>
        <svg class="eye-icon eye-closed is-hidden" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 3l18 18"></path>
            <path d="M10.7 6.2A10.7 10.7 0 0 1 12 6c6.5 0 10 6 10 6a17.8 17.8 0 0 1-2.1 2.8"></path>
            <path d="M6.6 6.6C3.6 8.4 2 12 2 12s3.5 6 10 6a10 10 0 0 0 4.7-1.2"></path>
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
        </svg>`;
    wrapper.appendChild(toggle);

    const openEye = toggle.querySelector('.eye-open');
    const closedEye = toggle.querySelector('.eye-closed');

    toggle.addEventListener('click', () => {
        const willShowPassword = input.type === 'password';
        input.type = willShowPassword ? 'text' : 'password';

        // The crossed eye communicates that clicking again will hide visible text.
        openEye.classList.toggle('is-hidden', willShowPassword);
        closedEye.classList.toggle('is-hidden', !willShowPassword);
        toggle.setAttribute('aria-pressed', String(willShowPassword));
        toggle.setAttribute('aria-label', willShowPassword ? 'Hide password' : 'Show password');
        input.focus();
    });
});
