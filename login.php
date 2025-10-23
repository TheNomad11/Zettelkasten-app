<?php
// Load configuration
require_once 'config.php';

// Sessions configuration
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Handle logout message
if (isset($_GET['logout'])) {
    $logout_message = "You have been logged out successfully.";
}

// Display timeout message if session expired
if (isset($_GET['timeout'])) {
    $timeout_message = "Your session expired due to inactivity. Please log in again.";
}

// Check rate limiting BEFORE processing login
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
    if (isset($_SESSION['last_attempt']) && (time() - $_SESSION['last_attempt']) < LOGIN_LOCKOUT_TIME) {
        $remaining_time = ceil((LOGIN_LOCKOUT_TIME - (time() - $_SESSION['last_attempt'])) / 60);
        $error = "Too many failed attempts. Try again in " . $remaining_time . " minutes.";
        $locked_out = true;
    } else {
        // Reset after lockout period expires
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['last_attempt']);
    }
}

// Process login only if not locked out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($locked_out)) {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        if ($_POST['username'] === USERNAME && password_verify($_POST['password'], PASSWORD_HASH)) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = USERNAME;
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();

            // Initialize CSRF token immediately on login
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();

            // Reset login attempts
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);

            // Log successful login
            error_log("Successful login for user: " . USERNAME);

            header('Location: index.php');
            exit;
        } else {
            // Increment failed attempts after failed login
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['last_attempt'] = time();

            // Log failed attempt
            error_log("Failed login attempt for username: " . ($_POST['username'] ?? 'none'));

            $error = "Invalid username or password";
        }
    } else {
        $error = "Please provide username and password";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Zettelkasten</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .login-container h1 {
            text-align: center;
            margin-bottom: 10px;
            font-size: 2em;
        }
        .login-container h2 {
            text-align: center;
            color: #7f8c8d;
            font-size: 1.1em;
            font-weight: 400;
            margin-bottom: 30px;
        }
        .login-form input {
            width: 100%;
            padding: 14px;
            margin: 10px 0;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .login-form input:focus {
            outline: none;
            border-color: #3498db;
        }
        .login-form button {
            width: 100%;
            padding: 14px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: background 0.3s;
            box-sizing: border-box;
        }
        .login-form button:hover {
            background: #229954;
        }
        .login-form button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success {
            background: #27ae60;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        .info {
            background: #3498db;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        .attempt-counter {
            text-align: center;
            color: #7f8c8d;
            font-size: 0.9em;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🗂️ Zettelkasten</h1>
        <h2>Sign in to continue</h2>

        <?php if (isset($timeout_message)): ?>
            <div class="info"><?= htmlspecialchars($timeout_message) ?></div>
        <?php endif; ?>

        <?php if (isset($logout_message)): ?>
            <div class="success"><?= htmlspecialchars($logout_message) ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <input type="text" name="username" placeholder="Username" required autofocus autocomplete="username" <?= isset($locked_out) ? 'disabled' : '' ?>>
            <input type="password" name="password" placeholder="Password" required autocomplete="current-password" <?= isset($locked_out) ? 'disabled' : '' ?>>
            <button type="submit" <?= isset($locked_out) ? 'disabled' : '' ?>>Login</button>
        </form>

        <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 0 && !isset($locked_out)): ?>
            <div class="attempt-counter">
                Failed attempts: <?= $_SESSION['login_attempts'] ?>/<?= MAX_LOGIN_ATTEMPTS ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
