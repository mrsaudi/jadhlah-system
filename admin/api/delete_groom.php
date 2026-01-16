<?php
// admin/api/delete_groom.php - حذف العريس
session_start();
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

// التحقق من الصلاحية (المدير فقط)
if ($_SESSION['role'] !== 'manager') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'غير مصرح لك بهذا الإجراء']));
}

require_once dirname(__DIR__) . '/config.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'معرف غير صالح']));
}

try {
    $pdo->beginTransaction();
    
    // حذف البيانات المرتبطة
    $tables = [
        'groom_photos',
        'groom_reviews',
        'groom_likes',
        'photo_likes',
        'photo_views',
        'upload_queue',
        'sessions'
    ];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE groom_id = ?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            // تجاهل إذا كان الجدول غير موجود
        }
    }
    
    // حذف العريس
    $stmt = $pdo->prepare("DELETE FROM grooms WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    
    // حذف المجلد
    $groomDir = dirname(dirname(__DIR__)) . '/grooms/' . $id;
    if (is_dir($groomDir)) {
        deleteDirectory($groomDir);
    }
    
    echo json_encode(['success' => true, 'message' => 'تم حذف الصفحة بنجاح']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting groom: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في حذف الصفحة']);
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : @unlink($path);
    }
    
    return @rmdir($dir);
}
?>