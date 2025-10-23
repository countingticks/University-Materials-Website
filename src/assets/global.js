// Global JavaScript functionality

// DOM Ready function
function ready(fn) {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

// Utility functions
const Utils = {
    // Show notification - DISABLED to remove top right notifications
    showNotification: function(message, type = 'info') {
        // Notifications disabled - do nothing
        console.log(`Notification (${type}): ${message}`);
        return;
        
        // Original notification code commented out:
        /*
        const notification = document.createElement('div');
        notification.className = `alert alert-${type}`;
        notification.textContent = message;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.maxWidth = '300px';
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
        */
    },

    // Format date
    formatDate: function(date) {
        return new Intl.DateTimeFormat('ro-RO').format(date);
    },

    // Format time
    formatTime: function(date) {
        return new Intl.DateTimeFormat('ro-RO', {
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    },

    // Smooth scroll to element
    scrollTo: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    },

    // Debounce function
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Form validation helpers
const FormValidator = {
    // Validate email
    isValidEmail: function(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    // Validate required field
    isRequired: function(value) {
        return value !== null && value !== undefined && value.trim() !== '';
    },

    // Validate minimum length
    minLength: function(value, min) {
        return value && value.length >= min;
    },

    // Add error styling to field
    addError: function(field, message) {
        field.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        errorDiv.style.color = '#e53e3e';
        errorDiv.style.fontSize = '0.85rem';
        errorDiv.style.marginTop = '0.25rem';

        // Place the error under the whole form-group, not next to the input
        const container = field.closest('.form-group') || field.parentNode;

        const existingError = container.querySelector(':scope > .error-message');
        if (existingError) {
            existingError.remove();
        }

        container.appendChild(errorDiv);
    },

    // Remove error styling
    removeError: function(field) {
        field.classList.remove('error');
        const container = field.closest('.form-group') || field.parentNode;
        const errorMsg = container.querySelector(':scope > .error-message');
        if (errorMsg) {
            errorMsg.remove();
        }
    }
};

// Loading states
const LoadingStates = {
    show: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.add('loading');
            element.style.opacity = '0.6';
            element.style.pointerEvents = 'none';
        }
    },

    hide: function(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.remove('loading');
            element.style.opacity = '';
            element.style.pointerEvents = '';
        }
    }
};

// Initialize global functionality
ready(function() {
    // Add smooth scrolling to all anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            Utils.scrollTo(this.getAttribute('href'));
        });
    });

    // Add loading states to forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            LoadingStates.show(this);
        });
    });

    // Auto-hide alerts after 10 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 10000);
    });
});

// Password functionality - make it globally accessible
window.togglePasswordVisibility = function(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) {
        console.error('Field not found:', fieldId);
        return;
    }
    
    const button = field.parentNode.querySelector('.password-toggle');
    if (!button) {
        console.error('Toggle button not found for field:', fieldId);
        return;
    }
    
    const showText = button.querySelector('.show-text');
    const hideText = button.querySelector('.hide-text');
    
    if (!showText || !hideText) {
        console.error('Show/hide text elements not found for field:', fieldId);
        return;
    }
    
    if (field.type === 'password') {
        field.type = 'text';
        showText.style.display = 'none';
        hideText.style.display = 'inline-block';
    } else {
        field.type = 'password';
        showText.style.display = 'inline-block';
        hideText.style.display = 'none';
    }
}

// Password confirmation validation
function validatePasswordConfirmation(passwordId, confirmId, messageId) {
    const password = document.getElementById(passwordId);
    const confirm = document.getElementById(confirmId);
    const message = document.getElementById(messageId);
    
    if (!password || !confirm || !message) return;
    
    function checkMatch() {
        if (confirm.value === '') {
            message.style.display = 'none';
            return;
        }
        
        if (password.value === confirm.value) {
            message.style.display = 'block';
            message.style.color = '#059669';
            message.textContent = '✓ Parolele se potrivesc';
        } else {
            message.style.display = 'block';
            message.style.color = '#dc2626';
            message.textContent = '✗ Parolele nu se potrivesc';
        }
    }
    
    password.addEventListener('input', checkMatch);
    confirm.addEventListener('input', checkMatch);
    
    return checkMatch;
}

// Initialize password functionality
ready(function() {
    // Set up password toggle event listeners
    document.addEventListener('click', function(e) {
        if (e.target.closest('.password-toggle')) {
            e.preventDefault();
            const button = e.target.closest('.password-toggle');
            const container = button.closest('.password-input-container');
            const input = container.querySelector('input[type="password"], input[type="text"]');
            
            if (input) {
                window.togglePasswordVisibility(input.id);
            }
        }
    });
    
    // For add user modal
    if (document.getElementById('password') && document.getElementById('password_confirm')) {
        validatePasswordConfirmation('password', 'password_confirm', 'add-password-match-message');
    }
    
    // For edit user modal
    if (document.getElementById('edit_password') && document.getElementById('edit_password_confirm')) {
        validatePasswordConfirmation('edit_password', 'edit_password_confirm', 'password-match-message');
    }
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Utils, FormValidator, LoadingStates, togglePasswordVisibility, validatePasswordConfirmation };
} 