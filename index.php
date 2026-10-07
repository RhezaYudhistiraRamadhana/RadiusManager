<?php
require_once __DIR__ . '/auth.php';
requireLogin();
header('Location: welcome.php');
exit;
