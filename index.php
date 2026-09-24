<?php
require_once __DIR__ . '/auth.php';
requireLogin();
header('Location: dashboard.php');
exit;
