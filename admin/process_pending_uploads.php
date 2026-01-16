<?php
// ===================================
// admin/process_pending_uploads_updated.php - معالج الصور المحسن مع 3 أحجام
// ===================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

echo "🚀 بدء معالجة الصور بثلاث أحجام - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 60) . "\n";

// معالجة الملفات قيد الانتظار
$stmt = $pdo->prepare("
    SELECT * FROM upload_queue 
    WHERE status = 'pending' AND retry_count < 3
    ORDER BY created_at 
    LIMIT 10
");
$stmt->execute();
$pending = $stmt->fetchAll();

$processedCount = 0;
$failedCount = 0;

foreach ($pending as $row) {
    $queueId = $row['id'];
    $groomId = $row['groom_id'];
    $filename = $row['filename'];
    
    try {
        echo "📷 معالجة: $filename للعريس #$groomId\n";
        
        // تحديث الحالة إلى processing
        $pdo->prepare("
            UPDATE upload_queue 
            SET status = 'processing', retry_count = retry_count + 1 
            WHERE id = ?
        ")->execute([$queueId]);
        
        // تحديد المسارات (في جذر الموقع)
        $groomDir = GROOMS_BASE . '/' . $groomId;
        $tempDir = $groomDir . '/temp';
        $origDir = $groomDir . '/originals';
        $modalDir = $groomDir . '/modal_thumb';
        $thumbDir = $groomDir . '/thumbs';
        
        // إنشاء المجلدات إذا لم تكن موجودة
        foreach ([$origDir, $modalDir, $thumbDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new Exception("فشل في إنشاء المجلد: $dir");
            }
        }
        
        $tempPath = $tempDir . '/' . $filename;
        $origPath = $origDir . '/' . $filename;
        $modalPath = $modalDir . '/' . $filename;
        $thumbPath = $thumbDir . '/' . $filename;
        
        // التحقق من وجود الملف المؤقت
        if (!file_exists($tempPath)) {
            throw new Exception("الملف المؤقت غير موجود: $tempPath");
        }
        
        // 1. نقل الملف الأصلي (بدون معالجة)
        if (!rename($tempPath, $origPath)) {
            throw new Exception("فشل في نقل الملف الأصلي");
        }
        echo "   ✅ نقل الصورة الأصلية\n";
        
        // 2. إنشاء نسخة المودال (1500px)
        if (!createAdvancedThumbnail($origPath, $modalPath, 1500)) {
            throw new Exception("فشل في إنشاء نسخة المودال");
        }
        echo "   ✅ إنشاء نسخة المودال (1500px)\n";
        
        // 3. إنشاء النسخة المصغرة (300px)
        if (!createAdvancedThumbnail($origPath, $thumbPath, 300)) {
            throw new Exception("فشل في إنشاء النسخة المصغرة");
        }
        echo "   ✅ إنشاء النسخة المصغرة (300px)\n";
        
        // 4. التحقق من وجود السجل في groom_photos
        $checkStmt = $pdo->prepare("
            SELECT id FROM groom_photos 
            WHERE groom_id = ? AND filename = ?
        ");
        $checkStmt->execute([$groomId, $filename]);
        
        if (!$checkStmt->fetch()) {
            // إضافة السجل في جدول الصور
            $photoStmt = $pdo->prepare("
                INSERT INTO groom_photos 
                (groom_id, filename, is_featured, hidden, photo_order, created_at) 
                VALUES (?, ?, 0, 0, 0, NOW())
            ");
            $photoStmt->execute([$groomId, $filename]);
            echo "   ✅ إضافة سجل قاعدة البيانات\n";
        }
        
        // 5. تحديث الحالة إلى done
        $pdo->prepare("
            UPDATE upload_queue 
            SET status = 'done', error_message = NULL 
            WHERE id = ?
        ")->execute([$queueId]);
        
        $processedCount++;
        echo "✅ اكتملت معالجة: $filename\n\n";
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        
        // تحديث الحالة إلى failed
        $pdo->prepare("
            UPDATE upload_queue 
            SET status = 'failed', error_message = ? 
            WHERE id = ?
        ")->execute([$errorMsg, $queueId]);
        
        $failedCount++;
        echo "❌ فشل: $filename - $errorMsg\n\n";
        error_log("خطأ في معالجة الملف $filename: $errorMsg");
    }
}

/**
 * دالة إنشاء صورة مصغرة متقدمة مع حفظ الجودة
 */
function createAdvancedThumbnail($source, $destination, $maxSize) {
    try {
        $info = getimagesize($source);
        if (!$info) {
            throw new Exception("ليس ملف صورة صالح");
        }
        
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        
        // إذا كانت الصورة أصغر من الحجم المطلوب، انسخها كما هي
        if ($width <= $maxSize && $height <= $maxSize) {
            return copy($source, $destination);
        }
        
        // حساب الأبعاد الجديدة مع الحفاظ على النسبة
        $ratio = $width / $height;
        if ($width > $height) {
            $newWidth = $maxSize;
            $newHeight = intval($maxSize / $ratio);
        } else {
            $newHeight = $maxSize;
            $newWidth = intval($maxSize * $ratio);
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
        
        // إنشاء صورة جديدة مع تحسينات
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // تحسين جودة التغيير
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        
        // الحفاظ على الشفافية للـ PNG و GIF
        if ($mime == 'image/png' || $mime == 'image/gif') {
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            // خلفية بيضاء للصور الأخرى
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $white);
        }
        
        // نسخ وتغيير حجم الصورة مع تحسين الجودة
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );
        
        // حفظ الصورة بجودة عالية
        $quality = ($maxSize > 1000) ? 90 : 85; // جودة أعلى للصور الكبيرة
        $result = imagejpeg($dstImage, $destination, $quality);
        
        // تنظيف الذاكرة
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("خطأ في createAdvancedThumbnail: " . $e->getMessage());
        return false;
    }
}

// تنظيف المجلدات المؤقتة القديمة (مع الحفاظ على الأمان)
$cleanedDirs = 0;
$groomFolders = array_filter(scandir(GROOMS_BASE), fn($f) => is_dir(GROOMS_BASE . "/$f") && is_numeric($f));

foreach ($groomFolders as $groomId) {
    $tempPath = GROOMS_BASE . "/$groomId/temp";
    
    if (is_dir($tempPath)) {
        // التحقق من عدم وجود صور معلقة
        $pendingCheck = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM upload_queue 
            WHERE groom_id = ? AND status IN ('pending', 'processing')
        ");
        $pendingCheck->execute([$groomId]);
        $pendingCount = $pendingCheck->fetchColumn();
        
        if ($pendingCount == 0 && (time() - filemtime($tempPath)) > 86400) {
            $files = array_diff(scandir($tempPath), ['.', '..']);
            foreach ($files as $file) {
                $filePath = $tempPath . '/' . $file;
                if (is_file($filePath)) {
                    unlink($filePath);
                }
            }
            if (empty(array_diff(scandir($tempPath), ['.', '..']))) {
                rmdir($tempPath);
                $cleanedDirs++;
            }
        }
    }
}

if ($cleanedDirs > 0) {
    echo "🗑️ تم تنظيف $cleanedDirs مجلد temp قديم\n";
}

// تنظيف السجلات القديمة
$deletedRecords = $pdo->exec("
    DELETE FROM upload_queue 
    WHERE status IN ('done', 'failed') 
    AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
");

if ($deletedRecords > 0) {
    echo "🗄️ تم حذف $deletedRecords سجل قديم من upload_queue\n";
}

// إحصائيات النهائية
echo "\n" . str_repeat('=', 60) . "\n";
echo "📊 ملخص المعالجة:\n";
echo "   - صور تمت معالجتها: $processedCount\n";
echo "   - صور فشلت: $failedCount\n";
echo "   - مجلدات temp منظفة: $cleanedDirs\n";
echo "   - سجلات قديمة محذوفة: $deletedRecords\n";
echo "\n✅ اكتملت المعالجة المحسنة بنجاح!\n";

// تسجيل في ملف السجل
$logMessage = sprintf(
    "[%s] معالجة محسنة: %d نجح، %d فشل، %d مجلد منظف، %d سجل محذوف\n",
    date('Y-m-d H:i:s'), $processedCount, $failedCount, $cleanedDirs, $deletedRecords
);

$logFile = __DIR__ . '/logs/image_processing.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
?>

<?php