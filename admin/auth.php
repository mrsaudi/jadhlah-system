<?php
// File: admin/auth.php
// تحميل صلاحيات المستخدم من الجلسة
session_start();
require __DIR__ . '/config.php';

function load_user_permissions() {
    global $pdo;
    if (empty($_SESSION['user']['id'])) return [];
    $stmt = $pdo->prepare("
        SELECT p.name
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        JOIN users u ON u.role_id = rp.role_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user']['id']]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function require_permission($perm) {
    $perms = load_user_permissions();
    if (!in_array($perm, $perms)) {
        header('HTTP/1.1 403 Forbidden');
        exit('غير مصرح بالدخول');
    }
}
?>
