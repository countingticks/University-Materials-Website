// Login page specific functionality

ready(function() {
    const loginForm = document.querySelector('form');
    const usernameField = document.getElementById('username');
    const passwordField = document.getElementById('password');
    const submitButton = loginForm.querySelector('button[type="submit"]');

    // Form validation
    function validateLoginForm() {
        let isValid = true;

        // Clear previous errors
        FormValidator.removeError(usernameField);
        FormValidator.removeError(passwordField);

        // Validate username
        if (!FormValidator.isRequired(usernameField.value)) {
            FormValidator.addError(usernameField, 'Numele de utilizator este obligatoriu');
            isValid = false;
        }

        // Validate password
        if (!FormValidator.isRequired(passwordField.value)) {
            FormValidator.addError(passwordField, 'Parola este obligatorie');
            isValid = false;
        }

        return isValid;
    }

    // Real-time validation
    usernameField.addEventListener('blur', function() {
        if (!FormValidator.isRequired(this.value)) {
            FormValidator.addError(this, 'Numele de utilizator este obligatoriu');
        } else {
            FormValidator.removeError(this);
        }
    });

    passwordField.addEventListener('blur', function() {
        if (!FormValidator.isRequired(this.value)) {
            FormValidator.addError(this, 'Parola este obligatorie');
        } else {
            FormValidator.removeError(this);
        }
    });

    // Clear errors on input
    usernameField.addEventListener('input', function() {
        FormValidator.removeError(this);
    });

    passwordField.addEventListener('input', function() {
        FormValidator.removeError(this);
    });

    // Handle form submission
    loginForm.addEventListener('submit', function(e) {
        if (!validateLoginForm()) {
            e.preventDefault();
            Utils.showNotification('Te rog să corectezi erorile din formular', 'error');
            return false;
        }

        // Add loading state
        submitButton.textContent = 'Se conectează...';
        submitButton.disabled = true;
        LoadingStates.show(loginForm);
    });

    // Add Enter key support
    [usernameField, passwordField].forEach(field => {
        field.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loginForm.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    });

    // Auto-focus username field
    if (usernameField && !usernameField.value) {
        usernameField.focus();
    }

    // Remember username (optional)
    if (localStorage.getItem('rememberedUsername')) {
        usernameField.value = localStorage.getItem('rememberedUsername');
        passwordField.focus();
    }

    // Save username on successful form submission (before page redirect)
    loginForm.addEventListener('submit', function() {
        if (usernameField.value.trim()) {
            localStorage.setItem('rememberedUsername', usernameField.value.trim());
        }
    });

    // Add visual feedback for form fields
    [usernameField, passwordField].forEach(field => {
        field.addEventListener('focus', function() {
            this.parentNode.style.transform = 'scale(1.02)';
            this.parentNode.style.transition = 'transform 0.2s ease';
        });

        field.addEventListener('blur', function() {
            this.parentNode.style.transform = '';
        });
    });



    // Show/hide password functionality (if needed in future)
    function addPasswordToggle() {
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.innerHTML = 'Show';
        toggleBtn.style.position = 'absolute';
        toggleBtn.style.right = '10px';
        toggleBtn.style.top = '50%';
        toggleBtn.style.transform = 'translateY(-50%)';
        toggleBtn.style.border = 'none';
        toggleBtn.style.background = 'none';
        toggleBtn.style.cursor = 'pointer';
        
        passwordField.parentNode.style.position = 'relative';
        passwordField.style.paddingRight = '40px';
        passwordField.parentNode.appendChild(toggleBtn);

        toggleBtn.addEventListener('click', function() {
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                this.innerHTML = 'Hide';
            } else {
                passwordField.type = 'password';
                this.innerHTML = 'Show';
            }
        });
    }

    // Uncomment to enable password toggle
    // addPasswordToggle();
}); 