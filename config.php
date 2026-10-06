<?php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'radius');          // FreeRADIUS database name
define('DB_USER',     'radius');          // MySQL username
define('DB_PASS',     'your_password');   // MySQL password
define('DB_PORT',     '3306');

// ─── App Configuration ────────────────────────────────────────────────────
define('APP_NAME',       'RadiusManager');
define('APP_VERSION',    '1.9.3');
define('APP_ADMIN',      'admin');        // Default admin username in config
define('APP_PASS',       password_hash('admin123', PASSWORD_DEFAULT)); // Default password hash
define('ROWS_PER_PAGE',  20);             // Default pagination count
define('EXPIRY_WARN_DAYS', 7);             // Days before expiry to trigger warning notice
define('API_KEY',        'radiusmanager_api_secret_key'); // REST API Bearer Authentication Key

// ─── Session lifetime (seconds) ──────────────────────────────────────────
define('SESSION_LIFETIME', 3600); // 1 hour

// ─── Environment & Error Logging ──────────────────────────────────────────
define('APP_ENV',  'production'); // 'development' | 'production'
define('LOG_FILE', __DIR__ . '/storage/logs/app.log');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_FILE);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ─── SMTP & Email Settings ────────────────────────────────────────────────
define('MAIL_FROM',       'noreply@your-domain.edu');
define('MAIL_FROM_NAME',  APP_NAME . ' Security');
define('SMTP_HOST',       ''); // e.g. smtp.office365.com or smtp.gmail.com (leave blank for standard mail())
define('SMTP_PORT',       587);
define('SMTP_USER',       '');
define('SMTP_PASS',       '');
define('SMTP_SECURE',     'tls'); // 'tls' or 'ssl'
define('DEV_MODE',        true);  // When true on localhost, displays helper code in testing

// ─── Load Helpers & DB Connection ─────────────────────────────────────────
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mail.php';
