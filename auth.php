<?php
/**
 * CENDANA — Core Bootstrap File
 * Central Evaluasi Network, Direktori Akun, dan Navigasi Autentikasi
 * Loads configuration, database layer, authentication helpers, and utilities.
 */

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/config.sample.php')) {
    require_once __DIR__ . '/config.sample.php';
} else {
    die("Configuration file not found. Please copy config.sample.php to config.php and configure your database settings.");
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
