<?php
// admin/logout.php - تسجيل خروج الإداريين
session_start();

// إلغاء جميع متغيرات الجلسة
$_SESSION = array();

// حذف ملف تعريف الارتباط للجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه لصفحة تسجيل الدخول
header("Location: login.php?logout=1");
exit;
?>
