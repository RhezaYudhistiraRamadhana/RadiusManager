<?php
require_once __DIR__ . '/auth.php';

if (isPortalLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
