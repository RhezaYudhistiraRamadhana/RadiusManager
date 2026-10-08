<?php
require_once __DIR__ . '/auth.php';
requireLogin();
session_write_close();
header('Location: welcome.php');
exit;
