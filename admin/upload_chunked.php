<?php
// ===================================
// admin/upload_chunked_updated.php - نظام رفع موحد محسن
// ===================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    // 1) جلب معرف العريس
    $groomId = (int)($_GET['groom_id'] ?? 0);
    if (!$groomId) {
        throw new Exception('معرف العريس مطلوب');
    }

    // 2) التحقق من وجود العريس
    $stmt = $pdo->prepare("SELECT id FROM grooms WHERE id = ?");
    $stmt->execute([$groomId]);
    if (!$stmt->fetch()) {
        throw new Exception('العريس غير موجود');
    }

    // 3) التحقق من الملف
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('لم يتم رفع الملف بشكل صحيح');
    }

    $file = $_FILES['file'];
    
    // 4) التحقق من نوع الملف
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        throw new Exception('نوع الملف غير مدعوم');
    }

    // 5) التحقق من حجم الملف
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('حجم الملف كبير جداً. الحد الأقصى: 10 ميجابايت');
    }

    // 6) إنشاء مجلد temp للعريس
    $groomDir = GROOMS_BASE . '/' . $groomId;
    $tempDir = $groomDir . '/temp';
    
    if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
        throw new Exception('فشل في إنشاء مجلد الرفع');
    }

    // 7) إنشاء اسم ملف فريد
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        throw new Exception('امتداد الملف غير مدعوم');
    }
    
    $filename = uniqid('img_', true) . '.' . $ext;
    $destination = $tempDir . '/' . $filename;

    // 8) نقل الملف
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('فشل في حفظ الملف');
    }

    // 9) تحسين خفيف للصورة
    optimizeImageLightly($destination, $destination);

    // 10) إضافة إلى قائمة الانتظار للمعالجة الكاملة
    $stmt = $pdo->prepare("
        INSERT INTO upload_queue (groom_id, filename, status, created_at) 
        VALUES (?, ?, 'pending', NOW())
    ");
    $stmt->execute([$groomId, $filename]);

    // 11) الاستجابة
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'size' => filesize($destination),
        'message' => 'تم الرفع - ستتم معالجة الصورة بـ3 أحجام تلقائياً'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log("خطأ في upload_chunked_updated.php: " . $e->getMessage());
}

/**
 * تحسين خفيف للصورة (نفس الدالة من upload_temp_updated)
 */
function optimizeImageLightly($source, $destination) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // إذا كانت الصورة أقل من 3000px، لا تغير حجمها
    $maxDimension = 3000;
    if ($width <= $maxDimension && $height <= $maxDimension) {
        return true;
    }
    
    // تصغير طفيف للصور الكبيرة جداً فقط
    $ratio = min($maxDimension / $width, $maxDimension / $height);
    $newWidth = round($width * $ratio);
    $newHeight = round($height * $ratio);
    
    // باقي الكود مثل السابق...
    switch ($mime) {
        case 'image/jpeg':
            $srcImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $srcImage = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $srcImage = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $srcImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$srcImage) return false;
    
    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
        imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled(
        $dstImage, $srcImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $width, $height
    );
    
    $result = imagejpeg($dstImage, $destination, 95);
    
    imagedestroy($srcImage);
    imagedestroy($dstImage);
    
    return $result;
}
?>
