<?php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'radius');          // FreeRADIUS database name
define('DB_USER',     'radius');          // MySQL username
define('DB_PASS',     'your_password');   // MySQL password
define('DB_PORT',     '3306');

// ─── App Configuration ────────────────────────────────────────────────────
define('APP_NAME',       'RadiusManager');
define('APP_VERSION',    '1.6.0');
define('APP_ADMIN',      'admin');        // Default admin username in config
define('APP_PASS',       password_hash('admin123', PASSWORD_DEFAULT)); // Default password hash
define('ROWS_PER_PAGE',  20);             // Default pagination count

// ─── Session lifetime (seconds) ──────────────────────────────────────────
define('SESSION_LIFETIME', 3600); // 1 hour

// ─── Load DB Connection ───────────────────────────────────────────────────
require_once __DIR__ . '/includes/db.php';
