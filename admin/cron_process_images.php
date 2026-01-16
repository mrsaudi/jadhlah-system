<?php
// admin/cron_process_images.php - معالج الصور المحدث لثلاث أحجام
// يتم تشغيله كل دقيقة عبر cron job

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// منع التشغيل من المتصفح (للأمان)
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_X_CRON_AUTH'])) {
    http_response_code(403);
    exit('Forbidden - This script can only be run via CLI or with proper authentication');
}

// ملف القفل لمنع التشغيل المتعدد
$lockFile = __DIR__ . '/process_images.lock';

// التحقق من القفل الحالي
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    $lockAge = time() - $lockTime;
    
    // إذا كان القفل أقدم من 10 دقائق، احذفه (قد يكون عالقاً)
    if ($lockAge > 600) {
        unlink($lockFile);
        echo "⚠️ تم حذف قفل عالق (عمره: " . round($lockAge/60) . " دقيقة)\n";
    } else {
        exit("⏳ عملية أخرى قيد التشغيل (بدأت منذ " . round($lockAge/60, 1) . " دقيقة)\n");
    }
}

// إنشاء القفل مع معلومات العملية
file_put_contents($lockFile, json_encode([
    'pid' => getmypid(),
    'started_at' => date('Y-m-d H:i:s'),
    'version' => 'enhanced_3_sizes'
]));

$startTime = microtime(true);
$processedCount = 0;
$failedCount = 0;
$cleanedDirs = 0;

try {
    echo "🚀 بدء معالجة الصور المحسنة - " . date('Y-m-d H:i:s') . "\n";
    echo "🔧 إصدار: معالجة ثلاث أحجام (أصلية + مودال + شبكي)\n";
    echo str_repeat('-', 60) . "\n";
    
    // 1. معالجة الملفات قيد الانتظار
    echo "📋 فحص قائمة الانتظار...\n";
    
    $stmt = $pdo->prepare("
        SELECT * FROM upload_queue 
        WHERE status = 'pending' AND retry_count < 3
        ORDER BY created_at 
        LIMIT 15
    ");
    $stmt->execute();
    $pending = $stmt->fetchAll();
    
    echo "📦 وجد " . count($pending) . " ملف قيد الانتظار\n\n";
    
    foreach ($pending as $row) {
        $queueId = $row['id'];
        $groomId = $row['groom_id'];
        $filename = $row['filename'];
        
        try {
            echo "🖼️ معالجة: $filename (عريس #$groomId)\n";
            
            // تحديث الحالة إلى processing
            $pdo->prepare("
                UPDATE upload_queue 
                SET status = 'processing', retry_count = retry_count + 1 
                WHERE id = ?
            ")->execute([$queueId]);
            
            // تحديد المسارات
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
            
            // 1. نقل الصورة الأصلية (بدون معالجة)
            if (!rename($tempPath, $origPath)) {
                throw new Exception("فشل في نقل الصورة الأصلية");
            }
            echo "   ✅ حفظ النسخة الأصلية\n";
            
            // 2. إنشاء نسخة المودال (1500px)
            if (!createEnhancedThumbnail($origPath, $modalPath, 1500, 90)) {
                throw new Exception("فشل في إنشاء نسخة المودال");
            }
            echo "   ✅ إنشاء نسخة المودال (1500px)\n";
            
            // 3. إنشاء النسخة المصغرة للشبكة (300px)
            if (!createEnhancedThumbnail($origPath, $thumbPath, 300, 85)) {
                throw new Exception("فشل في إنشاء النسخة المصغرة");
            }
            echo "   ✅ إنشاء النسخة الشبكية (300px)\n";
            
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
            echo "   🎉 اكتملت معالجة: $filename\n\n";
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            
            // تحديث الحالة إلى failed مع رسالة الخطأ
            $pdo->prepare("
                UPDATE upload_queue 
                SET status = 'failed', error_message = ? 
                WHERE id = ?
            ")->execute([$errorMsg, $queueId]);
            
            $failedCount++;
            echo "   ❌ فشل: $filename - $errorMsg\n\n";
            error_log("خطأ في معالجة الملف $filename: $errorMsg");
        }
    }
    
    // 2. تنظيف المجلدات المؤقتة القديمة
    echo "🧹 تنظيف المجلدات المؤقتة...\n";
    
    // تنظيف مجلد admin/temp_uploads
    $tempBase = TEMP_UPLOADS_BASE;
    if (is_dir($tempBase)) {
        $dirs = array_diff(scandir($tempBase), ['.', '..']);
        foreach ($dirs as $dir) {
            $dirPath = $tempBase . '/' . $dir;
            if (!is_dir($dirPath)) continue;
            
            $modTime = filemtime($dirPath);
            $age = time() - $modTime;
            
            // حذف المجلدات الأقدم من 24 ساعة
            if ($age > 86400) {
                if (deleteDirectory($dirPath)) {
                    echo "   🗑️ حذف مجلد temp قديم: $dir (عمره: " . round($age/3600, 1) . " ساعة)\n";
                    $cleanedDirs++;
                }
            }
        }
    }
    
    // تنظيف مجلدات temp داخل مجلدات العرسان
    $groomFolders = array_filter(
        scandir(GROOMS_BASE), 
        fn($f) => is_dir(GROOMS_BASE . "/$f") && is_numeric($f)
    );
    
    foreach ($groomFolders as $groomId) {
        $tempPath = GROOMS_BASE . "/$groomId/temp";
        
        if (is_dir($tempPath)) {
            // التحقق من عدم وجود صور معلقة للمعالجة
            $pendingCheck = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM upload_queue 
                WHERE groom_id = ? AND status IN ('pending', 'processing')
            ");
            $pendingCheck->execute([$groomId]);
            $pendingCount = $pendingCheck->fetchColumn();
            
            $age = time() - filemtime($tempPath);
            
            // حذف فقط إذا لم تكن هناك صور معلقة وكان عمر المجلد أكثر من 24 ساعة
            if ($pendingCount == 0 && $age > 86400) {
                $files = array_diff(scandir($tempPath), ['.', '..']);
                $fileCount = 0;
                
                foreach ($files as $file) {
                    $filePath = $tempPath . '/' . $file;
                    if (is_file($filePath)) {
                        unlink($filePath);
                        $fileCount++;
                    }
                }
                
                // حذف المجلد إذا كان فارغاً
                $remainingFiles = array_diff(scandir($tempPath), ['.', '..']);
                if (empty($remainingFiles)) {
                    rmdir($tempPath);
                    echo "   🗑️ حذف مجلد temp للعريس #$groomId ($fileCount ملف)\n";
                    $cleanedDirs++;
                }
            }
        }
    }
    
    // 3. إحصائيات مفصلة
    echo "\n📊 إحصائيات آخر 24 ساعة:\n";
    
    $stats = $pdo->query("
        SELECT 
            status, 
            COUNT(*) as count,
            AVG(retry_count) as avg_retries,
            MIN(created_at) as oldest,
            MAX(created_at) as newest
        FROM upload_queue 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status
        ORDER BY 
            CASE status 
                WHEN 'pending' THEN 1 
                WHEN 'processing' THEN 2 
                WHEN 'done' THEN 3 
                WHEN 'failed' THEN 4 
            END
    ")->fetchAll();
    
    foreach ($stats as $stat) {
        $avgRetries = round($stat['avg_retries'], 1);
        echo "   📈 {$stat['status']}: {$stat['count']} ملف (متوسط المحاولات: $avgRetries)\n";
    }
    
    // 4. تنبيهات الأداء والأخطاء
    echo "\n🔍 فحص الأداء:\n";
    
    // فحص الملفات العالقة في المعالجة
    $stuckProcessing = $pdo->query("
        SELECT COUNT(*) as count 
        FROM upload_queue 
        WHERE status = 'processing' 
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ")->fetchColumn();
    
    if ($stuckProcessing > 0) {
        echo "   ⚠️ تحذير: $stuckProcessing ملف عالق في المعالجة لأكثر من 30 دقيقة!\n";
        
        // إعادة تعيين الملفات العالقة إلى pending
        $pdo->exec("
            UPDATE upload_queue 
            SET status = 'pending', retry_count = retry_count + 1 
            WHERE status = 'processing' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            AND retry_count < 3
        ");
        echo "   🔄 تم إعادة تعيين الملفات العالقة إلى pending\n";
    }
    
    // فحص معدل الفشل
    $failureStats = $pdo->query("
        SELECT 
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
            COUNT(*) as total
        FROM upload_queue 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetch();
    
    if ($failureStats['total'] > 0) {
        $failureRate = ($failureStats['failed'] / $failureStats['total']) * 100;
        if ($failureRate > 20) {
            echo "   ⚠️ تحذير: معدل فشل عالي " . round($failureRate, 1) . "% في الساعة الماضية!\n";
        }
    }
    
    // فحص استخدام مساحة التخزين
    $totalSize = 0;
    $groomCount = 0;
    
    if (is_dir(GROOMS_BASE)) {
        foreach ($groomFolders as $groomId) {
            $groomPath = GROOMS_BASE . '/' . $groomId;
            if (is_dir($groomPath)) {
                $groomCount++;
                $totalSize += getDirSize($groomPath);
            }
        }
    }
    
    $totalSizeMB = round($totalSize / (1024 * 1024), 2);
    echo "   💾 مساحة التخزين: {$totalSizeMB} MB لـ $groomCount عريس\n";
    
    // 5. تنظيف السجلات القديمة
    echo "\n🗄️ تنظيف قاعدة البيانات:\n";
    
    $deletedRecords = $pdo->exec("
        DELETE FROM upload_queue 
        WHERE status IN ('done', 'failed') 
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    if ($deletedRecords > 0) {
        echo "   🗑️ تم حذف $deletedRecords سجل قديم من upload_queue\n";
    }
    
    // حساب وقت التنفيذ
    $executionTime = round(microtime(true) - $startTime, 2);
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "🎯 ملخص المعالجة:\n";
    echo "   ✅ صور تمت معالجتها: $processedCount\n";
    echo "   ❌ صور فشلت: $failedCount\n";
    echo "   🗑️ مجلدات temp منظفة: $cleanedDirs\n";
    echo "   🗄️ سجلات قديمة محذوفة: $deletedRecords\n";
    echo "   ⏱️ وقت التنفيذ: {$executionTime} ثانية\n";
    echo "   💾 استخدام الذاكرة: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
    echo "\n✅ اكتملت المعالجة المحسنة بنجاح!\n";
    
} catch (Exception $e) {
    echo "\n💥 خطأ عام: " . $e->getMessage() . "\n";
    error_log("خطأ في cron_process_images: " . $e->getMessage());
    
    // إرسال تنبيه في حالة الخطأ الحرج
    $errorDetails = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'processed' => $processedCount,
        'failed' => $failedCount
    ];
    
    error_log("CRON ERROR DETAILS: " . json_encode($errorDetails));
    
} finally {
    // حذف القفل
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
    
    // تسجيل ملخص في السجل
    $logMessage = sprintf(
        "[%s] معالجة: %d نجح، %d فشل، %d مجلد منظف، %.2f ثانية\n",
        date('Y-m-d H:i:s'), $processedCount, $failedCount, $cleanedDirs, $executionTime ?? 0
    );
    
    $logFile = __DIR__ . '/logs/cron_processing.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // تدوير ملف السجل إذا كان كبيراً
    if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) { // 10MB
        $backupLog = $logFile . '.' . date('Y-m-d');
        rename($logFile, $backupLog);
        echo "📝 تم أرشفة ملف السجل الكبير\n";
    }
}

/**
 * دالة إنشاء صورة مصغرة محسنة مع جودة عالية
 */
function createEnhancedThumbnail($source, $destination, $maxSize, $quality = 85) {
    try {
        $info = getimagesize($source);
        if (!$info) {
            throw new Exception("ليس ملف صورة صالح");
        }
        
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        
        // إذا كانت الصورة أصغر من الحجم المطلوب، انسخها مع ضغط خفيف
        if ($width <= $maxSize && $height <= $maxSize) {
            return copy($source, $destination);
        }
        
        // حساب الأبعاد الجديدة مع الحفاظ على النسبة
        $ratio = min($maxSize / $width, $maxSize / $height);
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
                throw new Exception("نوع الصورة غير مدعوم: $mime");
        }
        
        if (!$srcImage) {
            throw new Exception("فشل في قراءة الصورة");
        }
        
        // إنشاء صورة جديدة مع تحسينات الجودة
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // تحسين خوارزمية إعادة التشكيل
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
        
        // نسخ وتغيير حجم الصورة مع أفضل جودة
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );
        
        // حفظ الصورة مع الجودة المحددة
        $result = imagejpeg($dstImage, $destination, $quality);
        
        // تنظيف الذاكرة
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("خطأ في createEnhancedThumbnail: " . $e->getMessage());
        return false;
    }
}

/**
 * حساب حجم المجلد
 */
function getDirSize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}

/**
 * حذف مجلد وجميع محتوياته
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;
    
    try {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    } catch (Exception $e) {
        error_log("خطأ في حذف المجلد $dir: " . $e->getMessage());
        return false;
    }
}
?>