<?php
/**
 * Zettelkasten Configuration File
 * 
 * ⚠️ SECURITY WARNING: This file contains sensitive credentials.
 * Make sure to:
 * 1. Add config.php to .gitignore to prevent committing to version control
 * 2. Set proper file permissions (chmod 600 config.php)
 * 3. Keep this file outside your web root if possible
 */

// Session Configuration
define('SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 days in seconds
define('SESSION_TIMEOUT', 60 * 60 * 2); // 2 hours inactivity timeout

// Authentication Credentials
define('USERNAME', 'admin');

// Generate a new password hash by running in terminal:
// php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
define('PASSWORD_HASH', 'your-hashed-password');

// Security Settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// Application Settings
define('ZETTELS_DIR', 'zettels');
define('ZETTELS_PER_PAGE', 10);
define('RELATED_ZETTELS_LIMIT', 5);

// Debug Mode (set to false in production)
define('APP_DEBUG', false);

// CSRF Token Settings
define('CSRF_TOKEN_LIFETIME', 21600); // 6 hours in seconds
