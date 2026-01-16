<?php
// admin/api/update_ready.php - تحديث حالة الجاهزية
session_start();
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

require_once dirname(__DIR__) . '/config.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$ready = isset($_POST['ready']) ? (int)$_POST['ready'] : 0;

if ($id <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'معرف غير صالح']));
}

try {
    if ($ready === 1) {
        // عند التفعيل: سجل التاريخ إذا لم يكن مسجلاً
        $stmt = $pdo->prepare("
            UPDATE grooms 
            SET ready = 1, 
                ready_at = CASE 
                    WHEN ready_at IS NULL THEN NOW() 
                    ELSE ready_at 
                END 
            WHERE id = ?
        ");
    } else {
        // عند الإلغاء: احتفظ بالتاريخ
        $stmt = $pdo->prepare("UPDATE grooms SET ready = 0 WHERE id = ?");
    }
    
    $success = $stmt->execute([$id]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الجاهزية']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث الحالة']);
    }
    
} catch (Exception $e) {
    error_log("Error updating ready status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في تحديث الحالة']);
}
?>