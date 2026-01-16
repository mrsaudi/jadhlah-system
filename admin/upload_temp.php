<?php
// ===================================
// admin/upload_temp_updated.php - رفع مؤقت محسن
// ===================================

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/config.php';

try {
    // التحقق من المعرف المؤقت
    $tempId = $_GET['temp_id'] ?? '';
    if (empty($tempId)) {
        throw new Exception('معرف الرفع المؤقت مطلوب');
    }
    
    // التحقق من الملف
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('لم يتم رفع أي ملف أو حدث خطأ في الرفع');
    }
    
    $file = $_FILES['file'];
    
    // التحقق من نوع الملف
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        throw new Exception('نوع الملف غير مدعوم. المسموح: JPG, PNG, GIF, WebP');
    }
    
    // التحقق من حجم الملف
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('حجم الملف كبير جداً. الحد الأقصى: 10 ميجابايت');
    }
    
    // إنشاء مجلد الرفع المؤقت في admin/temp_uploads
    $tempDir = TEMP_UPLOADS_BASE . '/' . $tempId;
    if (!is_dir($tempDir)) {
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception('فشل في إنشاء مجلد الرفع المؤقت');
        }
    }
    
    // إنشاء اسم ملف فريد
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        throw new Exception('امتداد الملف غير مدعوم');
    }
    
    $filename = uniqid('img_', true) . '.' . $ext;
    $filepath = $tempDir . '/' . $filename;
    
    // نقل الملف
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('فشل في حفظ الملف');
    }
    
    // تحسين الصورة قليلاً (بدون تدمير الجودة)
    optimizeImageLightly($filepath, $filepath);
    
    // إرجاع استجابة ناجحة
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'size' => filesize($filepath),
        'url' => "temp_uploads/{$tempId}/{$filename}",
        'message' => 'تم الرفع بنجاح - ستتم معالجة الصورة بـ3 أحجام بعد الحفظ'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log("خطأ في upload_temp_updated.php: " . $e->getMessage());
}

/**
 * تحسين خفيف للصورة بدون فقدان جودة كبير
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
        return true; // لا حاجة للتحسين
    }
    
    // تصغير طفيف للصور الكبيرة جداً فقط
    $ratio = min($maxDimension / $width, $maxDimension / $height);
    $newWidth = round($width * $ratio);
    $newHeight = round($height * $ratio);
    
    // إنشاء الصورة من المصدر
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
    
    // الحفاظ على الشفافية للـ PNG
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
        imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // نسخ وتغيير حجم الصورة
    imagecopyresampled(
        $dstImage, $srcImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $width, $height
    );
    
    // حفظ الصورة بجودة عالية
    $result = imagejpeg($dstImage, $destination, 95);
    
    imagedestroy($srcImage);
    imagedestroy($dstImage);
    
    return $result;
}
?>
