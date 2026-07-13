document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.auth-form');
    const messageBox = document.querySelector('.form-message');

    if (!form || !messageBox) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const requiredFields = form.querySelectorAll('[required]');
        let allFilled = true;

        requiredFields.forEach(function (field) {
            if (!field.value.trim()) {
                allFilled = false;
                field.classList.add('field-error');
            } else {
                field.classList.remove('field-error');
            }
        });

        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const termsChecked = document.getElementById('terms').checked;

        messageBox.classList.remove('error');
        messageBox.textContent = '';

        if (!allFilled) {
            messageBox.classList.add('error');
            messageBox.textContent = 'Please fill in all required fields.';
            return;
        }

        if (!termsChecked) {
            messageBox.classList.add('error');
            messageBox.textContent = 'Please accept the terms and privacy policy to continue.';
            return;
        }

        if (password.length < 8) {
            messageBox.classList.add('error');
            messageBox.textContent = 'Password must be at least 8 characters long.';
            return;
        }

        if (password !== confirmPassword) {
            messageBox.classList.add('error');
            messageBox.textContent = 'Passwords do not match. Please try again.';
            return;
        }

        messageBox.textContent = 'Account details look good. Your registration request is ready to be submitted.';
        form.reset();
    });
});
