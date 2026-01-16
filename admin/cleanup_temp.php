<?php
// admin/cleanup_temp.php
// ملف تنظيف الملفات المؤقتة والبيانات القديمة

ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(300);

require_once __DIR__ . '/config.php';

// دالة تسجيل النشاط
function logCleanup($message) {
    $logFile = __DIR__ . '/logs/cleanup_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // طباعة للـ CLI
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

try {
    logCleanup("========================================");
    logCleanup("بدء عملية التنظيف الدورية");
    
    $totalCleaned = 0;
    
    // 1. استدعاء Stored Procedure للتنظيف
    try {
        $pdo->exec("CALL cleanup_old_sessions()");
        logCleanup("تم تنظيف الجلسات القديمة");
    } catch (Exception $e) {
        logCleanup("خطأ في تنظيف الجلسات: " . $e->getMessage());
    }
    
    // 2. تنظيف المجلدات المؤقتة في admin/temp_uploads
    $tempDir = __DIR__ . '/temp_uploads';
    $filesCleaned = 0;
    
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            // حذف الملفات الأقدم من 24 ساعة
            if (is_file($file) && (time() - filemtime($file)) > 86400) {
                if (@unlink($file)) {
                    $filesCleaned++;
                }
            }
            // حذف المجلدات الفارغة
            if (is_dir($file)) {
                $subFiles = glob($file . '/*');
                if (empty($subFiles)) {
                    @rmdir($file);
                }
            }
        }
    }
    logCleanup("تم حذف $filesCleaned ملف مؤقت من temp_uploads");
    $totalCleaned += $filesCleaned;
    
    // 3. تنظيف مجلدات temp للعرسان (في جذر الموقع)
    $groomBaseDir = dirname(__DIR__) . '/grooms';
    $tempDirsCleaned = 0;
    
    if (is_dir($groomBaseDir)) {
        $groomDirs = glob($groomBaseDir . '/*/temp');
        
        foreach ($groomDirs as $tempDir) {
            if (is_dir($tempDir)) {
                $groomId = basename(dirname($tempDir));
                
                // التحقق من عدم وجود معالجات معلقة
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM upload_queue 
                    WHERE groom_id = ? AND status IN ('pending', 'processing')
                ");
                $stmt->execute([$groomId]);
                
                if ($stmt->fetchColumn() == 0) {
                    // حذف الملفات القديمة
                    $files = glob($tempDir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file) && (time() - filemtime($file)) > 86400) {
                            if (@unlink($file)) {
                                $tempDirsCleaned++;
                            }
                        }
                    }
                    
                    // حذف المجلد إذا كان فارغاً
                    $remainingFiles = glob($tempDir . '/*');
                    if (empty($remainingFiles)) {
                        @rmdir($tempDir);
                    }
                }
            }
        }
    }
    logCleanup("تم تنظيف $tempDirsCleaned ملف من مجلدات temp العرسان");
    $totalCleaned += $tempDirsCleaned;
    
    // 4. تنظيف ملفات السجلات القديمة (أكثر من 30 يوم)
    $logFiles = glob(__DIR__ . '/logs/*.log');
    $logsDeleted = 0;
    
    foreach ($logFiles as $logFile) {
        if ((time() - filemtime($logFile)) > 2592000) { // 30 يوم
            if (@unlink($logFile)) {
                $logsDeleted++;
            }
        }
    }
    logCleanup("تم حذف $logsDeleted ملف سجل قديم");
    $totalCleaned += $logsDeleted;
    
    // 5. تحديث إحصائيات العرسان
    try {
        $grooms = $pdo->query("SELECT id FROM grooms")->fetchAll(PDO::FETCH_ASSOC);
        $updatedCount = 0;
        
        foreach ($grooms as $groom) {
            try {
                $pdo->exec("CALL calculate_groom_stats({$groom['id']})");
                $updatedCount++;
            } catch (Exception $e) {
                // تجاهل الأخطاء الفردية
            }
        }
        logCleanup("تم تحديث إحصائيات $updatedCount عريس");
    } catch (Exception $e) {
        logCleanup("خطأ في تحديث الإحصائيات: " . $e->getMessage());
    }
    
    // 6. تحسين الجداول
    $tables = ['grooms', 'groom_photos', 'sessions', 'upload_queue', 'pending_grooms'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE $table");
            logCleanup("تم تحسين جدول $table");
        } catch (Exception $e) {
            logCleanup("فشل تحسين جدول $table: " . $e->getMessage());
        }
    }
    
    // 7. تحديث الإحصائيات المخزنة مؤقتاً
    try {
        $pdo->exec("DELETE FROM statistics_cache WHERE expires_at < NOW()");
        logCleanup("تم تحديث الإحصائيات المخزنة");
    } catch (Exception $e) {
        // تجاهل إذا لم يكن الجدول موجوداً
    }
    
    // النتيجة النهائية
    logCleanup("========================================");
    logCleanup("✅ اكتملت عملية التنظيف بنجاح");
    logCleanup("📊 إجمالي العناصر المحذوفة: $totalCleaned");
    logCleanup("========================================");
    
    // إرسال النتيجة إذا كان من خلال HTTP
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => "تم التنظيف بنجاح",
            'details' => [
                'temp_files' => $filesCleaned,
                'groom_temps' => $tempDirsCleaned,
                'log_files' => $logsDeleted,
                'total' => $totalCleaned
            ]
        ]);
    }
    
} catch (Exception $e) {
    $error = "❌ خطأ في التنظيف: " . $e->getMessage();
    logCleanup($error);
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $error
        ]);
    }
    
    exit(1);
}

exit(0);
?>