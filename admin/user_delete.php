<?php
session_start();
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'manager') {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // التحقق من عدم حذف المستخدم الحالي
        $currentUserId = $_SESSION['user_data']['id'] ?? 0;
        if ($id == $currentUserId) {
            $_SESSION['error'] = 'لا يمكنك حذف حسابك الحالي';
        } else {
            // حذف المستخدم
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['success'] = 'تم حذف المستخدم بنجاح';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'فشل في حذف المستخدم';
    }
}

header('Location: users_list.php');
exit;
?>