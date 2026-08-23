document.addEventListener('DOMContentLoaded', function() {
    // Show/Hide Password
    const togglePasswords = document.querySelectorAll('.toggle-password');
    togglePasswords.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });

    // Password Strength Meter
    const passwordInput = document.getElementById('passwordInput');
    if (passwordInput) {
        const meter = document.getElementById('strengthMeter');
        const text = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            let strength = 0;

            if (val.length > 0) {
                if (val.length >= 8) strength += 1;
                if (val.match(/[a-z]+/)) strength += 1;
                if (val.match(/[A-Z]+/)) strength += 1;
                if (val.match(/[0-9]+/)) strength += 1;
                if (val.match(/[$@#&!]+/)) strength += 1;
            }

            // Update UI based on strength
            meter.className = 'strength-meter'; // reset classes

            if (val.length === 0) {
                meter.style.width = '0%';
                text.textContent = '';
            } else if (strength <= 2) {
                meter.style.width = '25%';
                meter.classList.add('bg-danger');
                text.textContent = 'Weak';
                text.className = 'strength-text text-danger';
            } else if (strength === 3 || strength === 4) {
                meter.style.width = '60%';
                meter.classList.add('bg-warning');
                text.textContent = 'Good';
                text.className = 'strength-text text-warning';
            } else {
                meter.style.width = '100%';
                meter.classList.add('bg-success');
                text.textContent = 'Strong';
                text.className = 'strength-text text-success';
            }
        });
    }

    // Loading State for Forms
    const authForms = document.querySelectorAll('.auth-form');
    authForms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.setAttribute('data-original-text', originalText);
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Please wait...';
            }
        });
    });
});
