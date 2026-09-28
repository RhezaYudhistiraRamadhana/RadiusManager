<?php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'radius');          // FreeRADIUS database name
define('DB_USER',     'radius');          // MySQL username
define('DB_PASS',     'your_password');   // MySQL password
define('DB_PORT',     '3306');

// ─── App Configuration ────────────────────────────────────────────────────
define('APP_NAME',       'RadiusManager');
define('APP_VERSION',    '1.9.0');
define('APP_ADMIN',      'admin');        // Default admin username in config
define('APP_PASS',       password_hash('admin123', PASSWORD_DEFAULT)); // Default password hash
define('ROWS_PER_PAGE',  20);             // Default pagination count
define('EXPIRY_WARN_DAYS', 7);             // Days before expiry to trigger warning notice
define('API_KEY',        'radiusmanager_api_secret_key'); // REST API Bearer Authentication Key

// ─── Session lifetime (seconds) ──────────────────────────────────────────
define('SESSION_LIFETIME', 3600); // 1 hour

// ─── SMTP & Email Settings (Production Mail Relay) ────────────────────────
define('MAIL_FROM',       'noreply@your-domain.edu');
define('MAIL_FROM_NAME',  APP_NAME . ' Security');
define('SMTP_HOST',       ''); // e.g. smtp.office365.com, smtp.gmail.com, or leave blank for PHP mail()
define('SMTP_PORT',       587);
define('SMTP_USER',       '');
define('SMTP_PASS',       '');
define('SMTP_SECURE',     'tls'); // 'tls' or 'ssl'
define('DEV_MODE',        false); // Set to false in production to disable test OTP displays

// ─── Load DB Connection & Mail Helpers ────────────────────────────────────
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mail.php';

