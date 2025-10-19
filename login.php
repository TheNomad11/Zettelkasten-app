<?php
session_start();

// ⚠️ IMPORTANT: Change these credentials before deploying!
define('USERNAME', 'admin');
// Generate new password hash by running: echo password_hash('your_password', PASSWORD_DEFAULT);
define('PASSWORD_HASH', 'yourhashedpassword'); // 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['username'] === USERNAME && password_verify($_POST['password'], PASSWORD_HASH)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = USERNAME;
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
// Add after failed login
$_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
$_SESSION['last_attempt'] = time();

if ($_SESSION['login_attempts'] > 5) {
    if (time() - $_SESSION['last_attempt'] < 900) { // 15 min lockout
        die("Too many failed attempts. Try again in 15 minutes.");
    } else {
        $_SESSION['login_attempts'] = 0;
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
        }
        .login-form button:hover {
            background: #229954;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        .password-hint {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            font-size: 0.9em;
            color: #856404;
        }
        .password-hint code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🗂️ Zettelkasten</h1>
        <h2>Sign in to continue</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <input type="text" name="username" placeholder="Username" required autofocus autocomplete="username">
            <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
            <button type="submit">Login</button>
        </form>
        
        
    </div>
</body>
</html>
