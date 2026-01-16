<?php
// ===================================
// admin/delete_all_photos_updated.php - حذف جميع صور العريس مع النسخ الثلاث
// ===================================

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

header('Content-Type: application/json');

try {
    $groomId = (int)($_GET['groom_id'] ?? 0);
    
    if (!$groomId) {
        throw new Exception('معرف العريس مطلوب');
    }
    
    // التحقق من وجود العريس
    $stmt = $pdo->prepare("SELECT id, groom_name FROM grooms WHERE id = ?");
    $stmt->execute([$groomId]);
    $groom = $stmt->fetch();
    
    if (!$groom) {
        throw new Exception('العريس غير موجود');
    }
    
    // جلب جميع صور العريس
    $stmt = $pdo->prepare("SELECT filename FROM groom_photos WHERE groom_id = ?");
    $stmt->execute([$groomId]);
    $photos = $stmt->fetchAll();
    
    $deletedFiles = 0;
    $failedFiles = [];
    $groomDir = GROOMS_BASE . '/' . $groomId;
    
    // حذف الملفات الفعلية (الثلاث نسخ لكل صورة)
    foreach ($photos as $photo) {
        $filename = $photo['filename'];
        $paths = [
            $groomDir . '/originals/' . $filename,      // الأصلية
            $groomDir . '/modal_thumb/' . $filename,    // المودال
            $groomDir . '/thumbs/' . $filename,         // الشبكي
            $groomDir . '/temp/' . $filename            // المؤقت
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                if (unlink($path)) {
                    $deletedFiles++;
                } else {
                    $failedFiles[] = basename($path);
                }
            }
        }
    }
    
    // حذف البنر إذا كان موجوداً (ثلاث نسخ أيضاً)
    if (!empty($groom['banner'])) {
        $bannerPaths = [
            $groomDir . '/banner.jpg',
            $groomDir . '/modal_thumb/banner.jpg',
            $groomDir . '/thumbs/banner.jpg'
        ];
        
        foreach ($bannerPaths as $path) {
            if (file_exists($path)) {
                if (unlink($path)) {
                    $deletedFiles++;
                } else {
                    $failedFiles[] = basename($path);
                }
            }
        }
    }
    
    // حذف السجلات من قاعدة البيانات
    $stmt = $pdo->prepare("DELETE FROM groom_photos WHERE groom_id = ?");
    $stmt->execute([$groomId]);
    $deletedRecords = $stmt->rowCount();
    
    // حذف من upload_queue أيضاً
    $stmt = $pdo->prepare("DELETE FROM upload_queue WHERE groom_id = ?");
    $stmt->execute([$groomId]);
    $deletedQueueRecords = $stmt->rowCount();
    
    // تسجيل العملية
    logError(
        "تم حذف جميع صور العريس #{$groomId} ({$groom['groom_name']}) - " .
        "سجلات: $deletedRecords، ملفات: $deletedFiles، قائمة انتظار: $deletedQueueRecords",
        'bulk_delete'
    );
    
    $response = [
        'success' => true,
        'message' => "تم حذف $deletedRecords صورة بجميع نسخها بنجاح",
        'deleted_files' => $deletedFiles,
        'deleted_records' => $deletedRecords,
        'deleted_queue_records' => $deletedQueueRecords
    ];
    
    if (!empty($failedFiles)) {
        $response['warning'] = 'بعض الملفات لم يتم حذفها';
        $response['failed_files'] = $failedFiles;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    logError("خطأ في حذف صور العريس: " . $e->getMessage(), 'errors');
}
?>
