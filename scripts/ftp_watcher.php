<?php
// scripts/ftp_watcher_fixed.php - نسخة محدثة تحافظ على الصور
require_once __DIR__ . '/../config/database.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

define('FTP_LIVE_DIR', '/home/u709146392/domains/jadhlah.com/ftp/live/');
define('FTP_ARCHIVE_DIR', '/home/u709146392/domains/jadhlah.com/ftp/archive/');
define('WEB_LIVE_DIR', '/home/u709146392/domains/jadhlah.com/public_html/uploads/live/');
define('WEB_ARCHIVE_DIR', '/home/u709146392/domains/jadhlah.com/public_html/uploads/archive/');
define('WEB_GROOMS_DIR', '/home/u709146392/domains/jadhlah.com/public_html/grooms/');

// لوق للتتبع
$logFile = __DIR__ . '/../logs/ftp_watcher_' . date('Y-m-d') . '.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    echo $logMessage;
    
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function processNewImages() {
    global $conn;
    
    // التأكد من وجود المجلد
    if (!is_dir(FTP_LIVE_DIR)) {
        logMessage("❌ مجلد FTP غير موجود: " . FTP_LIVE_DIR);
        return 0;
    }
    
    // البحث عن جميع امتدادات الصور
    $images = glob(FTP_LIVE_DIR . '*.{jpg,jpeg,JPG,JPEG,png,PNG,gif,GIF}', GLOB_BRACE);
    
    if (empty($images)) {
        logMessage("لا توجد صور جديدة");
        return 0;
    }
    
    logMessage("تم العثور على " . count($images) . " صورة جديدة");
    
    $processedCount = 0;
    foreach ($images as $imagePath) {
        try {
            if (processImage($imagePath)) {
                $processedCount++;
            }
        } catch (Exception $e) {
            logMessage("❌ خطأ في معالجة الصورة: " . $e->getMessage());
        }
    }
    
    return $processedCount;
}

function processImage($imagePath) {
    global $conn;
    
    $filename = basename($imagePath);
    logMessage("معالجة: $filename");
    
    // التأكد من أنها صورة صحيحة
    $imageInfo = @getimagesize($imagePath);
    if ($imageInfo === false) {
        logMessage("  ❌ ملف تالف أو غير صالح: $filename");
        return false;
    }
    logMessage("  ✓ الصورة صحيحة: {$imageInfo[0]}x{$imageInfo[1]}");
    
    // اسم ملف جديد فريد
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $newFilename = uniqid('live_') . '_' . time() . '.' . $extension;
    logMessage("  ← الاسم الجديد: $newFilename");
    
    // 1. نسخ الصورة إلى uploads/live (ستبقى هنا دائماً)
    $webLivePath = WEB_LIVE_DIR . $newFilename;
    
    // التأكد من وجود مجلد الوجهة
    if (!is_dir(WEB_LIVE_DIR)) {
        logMessage("  ⚠️ مجلد الوجهة غير موجود، جاري إنشاؤه...");
        if (!@mkdir(WEB_LIVE_DIR, 0755, true)) {
            logMessage("  ❌ فشل إنشاء مجلد الوجهة");
            return false;
        }
    }
    
    if (!@copy($imagePath, $webLivePath)) {
        logMessage("  ❌ فشل نسخ الصورة");
        return false;
    }
    logMessage("  ✓ تم نسخ الصورة: " . filesize($webLivePath) . " بايت");
    
    // 2. إنشاء thumbnail
    if (createThumbnail($webLivePath)) {
        logMessage("  ✓ تم إنشاء thumbnail");
    }
    
    // 3. الحصول على معلومات الصورة
    list($width, $height) = $imageInfo;
    $filesize = filesize($webLivePath);
    
    // 4. إضافة للقاعدة - مع تاريخ انتهاء بعد 24 ساعة
    logMessage("  ← حفظ في القاعدة...");
    
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $conn->prepare("
        INSERT INTO live_gallery_photos 
        (filename, original_filename, width, height, filesize, uploaded_at, expires_at, is_processed, is_hidden, is_expired) 
        VALUES (?, ?, ?, ?, ?, NOW(), ?, 1, 0, 0)
    ");
    
    if (!$stmt) {
        logMessage("  ❌ خطأ في prepare: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ssiiss", $newFilename, $filename, $width, $height, $filesize, $expiresAt);
    
    if (!$stmt->execute()) {
        logMessage("  ❌ فشل الحفظ في القاعدة: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $photoId = $conn->insert_id;
    logMessage("  ✓ تم الحفظ في القاعدة (ID: $photoId) - تنتهي: $expiresAt");
    $stmt->close();
    
    // 5. ربط بالعريس النشط (إذا وُجد)
    $activeGroom = getActiveGroom();
    if ($activeGroom) {
        logMessage("  ← عريس نشط: " . $activeGroom['groom_name']);
        copyToGroomFolder($webLivePath, $activeGroom);
        
        $conn->query("
            UPDATE live_gallery_photos 
            SET groom_id = {$activeGroom['groom_id']} 
            WHERE id = $photoId
        ");
        logMessage("  ✓ تم ربط الصورة بالعريس");
    }
    
    // 6. نقل الصورة الأصلية من FTP للأرشيف
    $archiveDate = date('Y-m-d');
    $archiveDir = FTP_ARCHIVE_DIR . $archiveDate . '/';
    
    logMessage("  ← نقل للأرشيف: $archiveDir");
    
    if (!is_dir($archiveDir)) {
        if (!@mkdir($archiveDir, 0755, true)) {
            logMessage("  ⚠️ فشل إنشاء مجلد الأرشيف");
            @unlink($imagePath);
            return true;
        }
    }
    
    if (@rename($imagePath, $archiveDir . $filename)) {
        logMessage("  ✓ تم نقل الصورة للأرشيف");
    } else {
        @unlink($imagePath);
        logMessage("  ✓ تم حذف الصورة من FTP");
    }
    
    logMessage("✅ انتهت معالجة: $filename");
    return true;
}

function createThumbnail($imagePath) {
    $thumbWidth = 400;
    $thumbHeight = 300;
    
    try {
        $thumbPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '_thumb.$1', $imagePath);
        
        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($origWidth, $origHeight) = $imageInfo;
        
        // قراءة الصورة حسب النوع
        $source = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $source = @imagecreatefromgif($imagePath);
                break;
        }
        
        if (!$source) return false;
        
        // حساب الأبعاد
        $ratio = min($thumbWidth / $origWidth, $thumbHeight / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);
        
        // إنشاء الصورة المصغرة
        $dest = imagecreatetruecolor($newWidth, $newHeight);
        
        // خلفية بيضاء للشفافية
        $white = imagecolorallocate($dest, 255, 255, 255);
        imagefill($dest, 0, 0, $white);
        
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        // حفظ كـ JPEG
        $result = imagejpeg($dest, $thumbPath, 85);
        
        imagedestroy($source);
        imagedestroy($dest);
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

function getActiveGroom() {
    global $conn;
    
    // جلب آخر عريس نشط
    $result = $conn->query("
        SELECT g.id as groom_id, g.groom_name, g.folder_name
        FROM grooms g
        WHERE g.id = (
            SELECT MAX(id) FROM grooms 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        )
        LIMIT 1
    ");
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    // إذا لم يوجد عريس حديث، جلب آخر عريس
    $result = $conn->query("
        SELECT id as groom_id, groom_name, folder_name
        FROM grooms 
        ORDER BY id DESC 
        LIMIT 1
    ");
    
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
}

function copyToGroomFolder($imagePath, $groom) {
    if (!$groom || !isset($groom['folder_name'])) {
        return false;
    }
    
    $groomFolder = WEB_GROOMS_DIR . $groom['folder_name'] . '/';
    
    if (!is_dir($groomFolder)) {
        @mkdir($groomFolder, 0755, true);
    }
    
    $filename = basename($imagePath);
    $groomImagePath = $groomFolder . $filename;
    
    if (@copy($imagePath, $groomImagePath)) {
        logMessage("  ✓ تم النسخ لمجلد العريس: {$groom['folder_name']}");
        return true;
    }
    return false;
}

function updateExpiredPhotos() {
    global $conn;
    
    // تحديث حالة الصور المنتهية (لكن لا نحذف الملفات)
    $result = $conn->query("
        UPDATE live_gallery_photos 
        SET is_expired = 1
        WHERE expires_at < NOW() 
        AND (is_expired = 0 OR is_expired IS NULL)
        LIMIT 100
    ");
    
    if ($result) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            logMessage("📋 تم تحديث حالة $affected صورة منتهية (الملفات ما زالت موجودة)");
        }
    }
    
    return $conn->affected_rows;
}

// نسخ الصور المؤرشفة إلى مجلد الأرشيف (اختياري)
function copyArchivedPhotos() {
    global $conn;
    
    // إنشاء مجلد الأرشيف إذا لم يكن موجوداً
    if (!is_dir(WEB_ARCHIVE_DIR)) {
        @mkdir(WEB_ARCHIVE_DIR, 0755, true);
    }
    
    // جلب الصور المنتهية التي لم تُنسخ للأرشيف بعد
    $result = $conn->query("
        SELECT * FROM live_gallery_photos 
        WHERE is_expired = 1 
        AND (archived_copied = 0 OR archived_copied IS NULL)
        LIMIT 50
    ");
    
    if ($result) {
        while ($photo = $result->fetch_assoc()) {
            $sourcePath = WEB_LIVE_DIR . $photo['filename'];
            $destPath = WEB_ARCHIVE_DIR . $photo['filename'];
            
            if (file_exists($sourcePath) && !file_exists($destPath)) {
                if (@copy($sourcePath, $destPath)) {
                    // تحديث حالة النسخ
                    $conn->query("
                        UPDATE live_gallery_photos 
                        SET archived_copied = 1 
                        WHERE id = " . $photo['id']
                    );
                    logMessage("  ← نسخ للأرشيف: " . $photo['filename']);
                }
            }
        }
    }
}

// ==========================================
// البرنامج الرئيسي
// ==========================================

logMessage("========================================");
logMessage("بدء معالجة الصور - النسخة المصححة");
logMessage("المجلد: " . FTP_LIVE_DIR);
logMessage("========================================");

// 1. معالجة الصور الجديدة
$processed = processNewImages();

// 2. تحديث حالة الصور المنتهية (بدون حذف الملفات)
$expired = updateExpiredPhotos();

// 3. نسخ الصور المؤرشفة (اختياري - يمكن تفعيله لاحقاً)
// copyArchivedPhotos();

logMessage("========================================");
logMessage("انتهى: معالجة $processed صورة، تحديث $expired صورة منتهية");
logMessage("الصور تبقى في: " . WEB_LIVE_DIR);
logMessage("========================================");
?>