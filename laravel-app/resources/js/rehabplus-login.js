// Show/hide the password. Wired up here because the Content-Security-Policy
// blocks inline onclick/onkeydown handlers.
const togglePassword = function () {
    const input = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');

    if (!input || !icon) {
        return;
    }

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
};

const passwordToggle = document.getElementById('passwordToggle');

if (passwordToggle) {
    passwordToggle.addEventListener('click', togglePassword);
    passwordToggle.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            togglePassword();
        }
    });
}
