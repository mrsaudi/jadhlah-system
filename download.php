<?php
// ===================================
// ملف: download.php - تحميل الصور الأصلية (في جذر الموقع)
// ===================================

require_once __DIR__ . '/admin/config.php';

// التحقق من المعاملات
$groomId = (int)($_GET['groom'] ?? 0);
$filename = $_GET['file'] ?? '';

if (!$groomId || !$filename) {
    http_response_code(400);
    exit('معاملات غير صحيحة');
}

// التحقق من وجود العريس
$stmt = $pdo->prepare("SELECT id, groom_name FROM grooms WHERE id = ? AND is_active = 1");
$stmt->execute([$groomId]);
$groom = $stmt->fetch();

if (!$groom) {
    http_response_code(404);
    exit('العريس غير موجود');
}

// التحقق من وجود الصورة في قاعدة البيانات
$stmt = $pdo->prepare("
    SELECT filename FROM groom_photos 
    WHERE groom_id = ? AND filename = ? AND hidden = 0
");
$stmt->execute([$groomId, $filename]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    exit('الصورة غير موجودة');
}

// مسار الصورة الأصلية
$originalPath = GROOMS_BASE . '/' . $groomId . '/originals/' . $filename;

if (!file_exists($originalPath)) {
    http_response_code(404);
    exit('ملف الصورة غير موجود');
}

// تحديد نوع المحتوى
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $originalPath);
finfo_close($finfo);

// إعداد headers للتحميل
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($originalPath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// إرسال الملف
readfile($originalPath);
exit;
?>
