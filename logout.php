<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$auth = new Auth();
$auth->logout();

header('Location: ' . APP_URL . '/index.php');
exit;
