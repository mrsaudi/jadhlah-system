<?php
session_start();
$_SESSION['user'] = 'test';
$_SESSION['role'] = 'manager';

$_POST['action'] = 'toggle_ready';
$_POST['id'] = 1;
$_POST['ready'] = 1;

require __DIR__ . '/ajax_operations.php';
?>