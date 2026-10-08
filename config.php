<?php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'radius');          // FreeRADIUS database name
define('DB_USER',     'radius');          // MySQL username
define('DB_PASS',     'your_password');   // MySQL password
define('DB_PORT',     '3306');

// ─── App Configuration ────────────────────────────────────────────────────
define('APP_NAME',       'CENDANA');
define('APP_FULL_NAME',  'Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi');
define('APP_TAGLINE',    'FreeRADIUS Management & Access Control System');
define('APP_VERSION',    '1.9.5');
define('APP_ADMIN',      'admin');        // Default admin username in config
define('APP_PASS',       '$2y$10$SMwoxw3ADrdqCtu2SQkAtO54NbtxHfp4pGo23dHI0Xaoc30WmOYAi'); // Precomputed bcrypt for 'admin123'
define('ROWS_PER_PAGE',  20);             // Default pagination count
define('EXPIRY_WARN_DAYS', 7);             // Days before expiry to trigger warning notice
define('API_KEY',        'cendana_api_secret_key'); // REST API Bearer Authentication Key

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
