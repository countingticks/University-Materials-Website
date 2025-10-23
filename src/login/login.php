<?php
// Config is already loaded by the router
// Login redirect logic is already handled by the router

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Add logging for debugging
    error_log("Login attempt: username=" . $username . ", timestamp=" . date('Y-m-d H:i:s'));
    
    if (empty($username) || empty($password)) {
        $error = 'Vă rugăm să completați toate câmpurile.';
        error_log("Login failed: empty fields");
    } else {
        try {
            if (login($username, $password)) {
                error_log("Login successful for user: " . $username);
        header('Location: /home');
        exit();
    } else {
        $error = 'Nume de utilizator sau parolă incorectă.';
                error_log("Login failed: invalid credentials for user " . $username);
            }
        } catch (Exception $e) {
            $error = 'Eroare la conectare. Încercați din nou.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

$pageTitle = "Conectare";
$pageDescription = "Conectează-te la contul tău";
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conectare - Grafică | UTCN</title>
    <meta name="description" content="Platformă de cursuri Grafică - Universitatea Tehnică din Cluj-Napoca">
    <link rel="stylesheet" href="/src/assets/global.css">
    <link rel="stylesheet" href="/src/login/login.css">
</head>
<body class="login-page">
    
    <div class="login-container">
        <div class="login-form">
            <h1>Grafică</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="/login">
                <div class="form-group">
                    <label for="username">Nume utilizator</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username"
                        required
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Parolă</label>
                    <div class="password-input-container">
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        required
                    >
                        <button type="button" class="password-toggle">
                            <span class="show-text"></span>
                            <span class="hide-text" style="display: none;"></span>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    Conectare
                </button>
            </form>
        </div>
    </div>
    
    <!-- Temporarily disabled to prevent form submission conflicts -->
    <!-- <script src="/src/assets/global.js"></script> -->
    <!-- <script src="/src/login/login.js"></script> -->

    <script>
        // Simple, non-interfering login form enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('.btn-login');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            if (!form || !submitBtn || !usernameInput || !passwordInput) {
                return;
            }

            // Simple form state reset on page load
            function resetFormState() {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.textContent = 'Conectare';
                usernameInput.disabled = false;
                passwordInput.disabled = false;
                }
                
            // Reset on page load (handles logout->login scenario)
            resetFormState();
            
            // Reset on page show (handles browser back/forward)
            window.addEventListener('pageshow', resetFormState);

            // Auto-focus username field if empty
            if (usernameInput && !usernameInput.value) {
                usernameInput.focus();
            }

            // Simple visual feedback on submit (non-blocking)
            form.addEventListener('submit', function() {
                submitBtn.textContent = 'Se conectează...';
                submitBtn.disabled = true;

                // Safety reset after 15 seconds (in case something goes wrong)
                setTimeout(resetFormState, 15000);
            });

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
            });

        // Password visibility toggle function - make it globally accessible
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
    </script>
</body>
</html> 