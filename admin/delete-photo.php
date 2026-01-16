<?php
// admin/delete_photo.php - حذف صورة واحدة

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'غير مصرح']));
}

header('Content-Type: application/json');

try {
    $groomId = (int)($_POST['groom_id'] ?? 0);
    $filename = $_POST['filename'] ?? '';
    
    if (!$groomId || !$filename) {
        throw new Exception('معرف العريس واسم الملف مطلوبان');
    }
    
    // التحقق من وجود العريس
    $stmt = $pdo->prepare("SELECT id FROM grooms WHERE id = ?");
    $stmt->execute([$groomId]);
    if (!$stmt->fetch()) {
        throw new Exception('العريس غير موجود');
    }
    
    // حذف من قاعدة البيانات
    $stmt = $pdo->prepare("DELETE FROM groom_photos WHERE groom_id = ? AND filename = ?");
    $stmt->execute([$groomId, $filename]);
    
    // حذف من upload_queue إن وجد
    $stmt = $pdo->prepare("DELETE FROM upload_queue WHERE groom_id = ? AND filename = ?");
    $stmt->execute([$groomId, $filename]);
    
    // حذف الملفات الفعلية
    $paths = [
        sprintf(DIR_ORIGINALS, $groomId) . '/' . $filename,
        sprintf(DIR_MODAL_THUMB, $groomId) . '/' . $filename,
        sprintf(DIR_THUMBS, $groomId) . '/' . $filename,
        sprintf(DIR_UPLOAD_TEMP, $groomId) . '/' . $filename
    ];
    
    $deletedCount = 0;
    foreach ($paths as $path) {
        if (file_exists($path) && unlink($path)) {
            $deletedCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "تم حذف الصورة بنجاح",
        'deleted_files' => $deletedCount
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}