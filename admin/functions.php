<?php
// functions.php - دوال النظام المحسنة

/**
 * إنشاء نسخة مصغرة من الصورة
 */
function createThumbnail($source, $destination, $maxSize) {
    try {
        // الحصول على معلومات الصورة
        $info = getimagesize($source);
        if (!$info) {
            throw new Exception("ليس ملف صورة صالح");
        }
        
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        
        // حساب الأبعاد الجديدة
        if ($width > $height) {
            $newWidth = $maxSize;
            $newHeight = intval($height * $maxSize / $width);
        } else {
            $newHeight = $maxSize;
            $newWidth = intval($width * $maxSize / $height);
        }
        
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
                throw new Exception("نوع الصورة غير مدعوم: $mime");
        }
        
        if (!$srcImage) {
            throw new Exception("فشل في قراءة الصورة");
        }
        
        // إنشاء صورة جديدة
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // الحفاظ على الشفافية
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            // خلفية بيضاء للصور الأخرى
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $white);
        }
        
        // نسخ وتغيير حجم الصورة
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );
        
        // حفظ الصورة
        $quality = defined('JPEG_QUALITY') ? JPEG_QUALITY : 85;
        $result = imagejpeg($dstImage, $destination, $quality);
        
        // تنظيف الذاكرة
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("خطأ في createThumbnail: " . $e->getMessage());
        return false;
    }
}

/**
 * تنظيف اسم الملف
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^\w\s\d\-_~,;\[\]\(\).]/', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    return $filename;
}

/**
 * الحصول على امتداد الملف الآمن
 */
function getFileExtension($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS) ? $ext : false;
}

/**
 * التحقق من نوع MIME للصورة
 */
function isValidImageMime($filepath) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    return in_array($mime, ALLOWED_MIME_TYPES);
}

/**
 * حذف مجلد بكل محتوياته
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    return rmdir($dir);
}

/**
 * الحصول على حجم الملف بتنسيق مقروء
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * إنشاء معرف فريد آمن
 */
function generateUniqueId($prefix = '') {
    return $prefix . bin2hex(random_bytes(16));
}

/**
 * تسجيل الدخول آمن
 */
function secureLogin($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username']
        ];
        return true;
    }
    return false;
}

/**
 * التحقق من تسجيل الدخول
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * إنشاء رمز CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من رمز CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}