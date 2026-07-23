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

        messageBox.textContent = 'Creating your account…';

        const fullNameField = document.getElementById('fullName');
        const emailField = document.getElementById('email');
        const phoneField = document.getElementById('phone');
        const roleField = document.getElementById('role');

        const payload = new URLSearchParams({
            full_name: fullNameField ? fullNameField.value.trim() : '',
            email: emailField ? emailField.value.trim() : '',
            phone_number: phoneField ? phoneField.value.trim() : '',
            password: password,
        });

        // The role dropdown's "Administrator" option maps to the backend's
        // 'zicta' role — the visible label matches the page as designed,
        // only the submitted value is translated.
        if (roleField) {
            const role = roleField.value === 'administrator' ? 'zicta' : roleField.value;
            payload.set('role', role);
        }

        fetch('auth/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: payload,
            credentials: 'same-origin',
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (result.ok && result.data.success) {
                    messageBox.classList.remove('error');
                    messageBox.textContent = 'Account created! Redirecting to login…';
                    form.reset();
                    setTimeout(function () { window.location.href = 'login.html'; }, 1200);
                } else {
                    messageBox.classList.add('error');
                    messageBox.textContent = result.data.error || 'Registration failed. Please try again.';
                }
            })
            .catch(function () {
                messageBox.classList.add('error');
                messageBox.textContent = 'Network error — please try again.';
            });
    });
});
