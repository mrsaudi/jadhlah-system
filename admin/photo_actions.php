<?php
// ===================================
// admin/photo_actions_updated.php - معالج إجراءات الصور المحدث
// ===================================

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$action = $_GET['action'] ?? '';
$photoId = (int)($_GET['id'] ?? 0);

if (!$photoId || !in_array($action, ['hide', 'unhide', 'delete', 'feature'])) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    // جلب معلومات الصورة
    $stmt = $pdo->prepare("SELECT * FROM groom_photos WHERE id = ?");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();
    
    if (!$photo) {
        throw new Exception('الصورة غير موجودة');
    }
    
    $groomId = $photo['groom_id'];
    $filename = $photo['filename'];
    $groomDir = GROOMS_BASE . '/' . $groomId;
    
    switch ($action) {
        case 'hide':
            // تبديل حالة الإخفاء
            $newHidden = $photo['hidden'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE groom_photos SET hidden = ? WHERE id = ?");
            $stmt->execute([$newHidden, $photoId]);
            break;
            
        case 'unhide':
            $stmt = $pdo->prepare("UPDATE groom_photos SET hidden = 0 WHERE id = ?");
            $stmt->execute([$photoId]);
            break;
            
        case 'feature':
            // تبديل حالة التمييز
            $newFeatured = $photo['is_featured'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE groom_photos SET is_featured = ? WHERE id = ?");
            $stmt->execute([$newFeatured, $photoId]);
            break;
            
        case 'delete':
            // حذف من قاعدة البيانات
            $stmt = $pdo->prepare("DELETE FROM groom_photos WHERE id = ?");
            $stmt->execute([$photoId]);
            
            // حذف الملفات الفعلية (الثلاث نسخ)
            $paths = [
                $groomDir . '/originals/' . $filename,
                $groomDir . '/modal_thumb/' . $filename,
                $groomDir . '/thumbs/' . $filename,
                $groomDir . '/temp/' . $filename
            ];
            
            $deletedFiles = 0;
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    if (unlink($path)) {
                        $deletedFiles++;
                    }
                }
            }
            
            // حذف من قائمة الانتظار إن وجد
            $stmt = $pdo->prepare("DELETE FROM upload_queue WHERE groom_id = ? AND filename = ?");
            $stmt->execute([$groomId, $filename]);
            
            logError("تم حذف الصورة: $filename مع $deletedFiles ملف للعريس #$groomId", 'photo_actions');
            break;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
