<?php
/**
 * CENDANA — Core Bootstrap File
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Loads configuration, database layer, authentication helpers, and utilities.
 */

// Enforce Asia/Jakarta (WIB) timezone synchronization across PHP
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Jakarta');
}
date_default_timezone_set(APP_TIMEZONE);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/config.sample.php')) {
    require_once __DIR__ . '/config.sample.php';
} else {
    die("Configuration file not found. Please copy config.sample.php to config.php and configure your database settings.");
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Safe self-healing indexer for FreeRADIUS tables (throttled to run at most once per 24h)
if (isLoggedIn()) {
    ensurePerformanceIndexes();
}

