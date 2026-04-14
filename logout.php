<?php
// logout.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';
Auth::logout();
header('Location: /login.php?msg=logged_out');
exit();