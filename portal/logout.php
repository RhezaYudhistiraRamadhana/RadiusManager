<?php
require_once __DIR__ . '/auth.php';
portalLogout();
header("Location: login.php?logged_out=1");
exit;
