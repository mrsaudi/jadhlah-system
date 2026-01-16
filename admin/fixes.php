<?php
// admin/fixes.php - إصلاحات سريعة للمشاكل الشائعة

session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'manager') {
    die('غير مصرح');
}

require_once __DIR__ . '/config.php';

// إصلاح 1: إنشاء المجلدات المطلوبة
function createRequiredDirectories() {
    $dirs = [
        __DIR__ . '/logs',
        __DIR__ . '/temp_uploads', 
        dirname(__DIR__) . '/grooms',
        dirname(__DIR__) . '/grooms/temp'
    ];
    
    $created = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0755, true)) {
                $created[] = $dir;
            }
        }
    }
    
    return $created;
}

// إصلاح 2: إصلاح أذونات الملفات
function fixFilePermissions() {
    $files = [
        __DIR__ . '/update_page_status.php',
        __DIR__ . '/dashboard_functions.js',
        __DIR__ . '/config.php'
    ];
    
    $fixed = [];
    foreach ($files as $file) {
        if (file_exists($file)) {
            if (@chmod($file, 0644)) {
                $fixed[] = $file;
            }
        }
    }
    
    return $fixed;
}

// إصلاح 3: تنظيف الجلسات المنتهية
function cleanExpiredSessions() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}

// إصلاح 4: إعادة تعيين العدادات
function resetCounters() {
    global $pdo;
    
    try {
        // إعادة حساب عدد الصور لكل عريس
        $pdo->exec("
            UPDATE grooms g 
            SET photo_count = (
                SELECT COUNT(*) 
                FROM groom_photos p 
                WHERE p.groom_id = g.id
            )
        ");
        
        // إعادة حساب الإعجابات
        $pdo->exec("
            UPDATE grooms g 
            SET total_likes = (
                SELECT COUNT(*) 
                FROM groom_likes l 
                WHERE l.groom_id = g.id
            ) + (
                SELECT COUNT(*) 
                FROM photo_likes pl 
                WHERE pl.groom_id = g.id
            )
        ");
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// إصلاح 5: تنظيف الملفات المؤقتة
function cleanTempFiles() {
    $tempDir = __DIR__ . '/temp_uploads';
    $cleaned = 0;
    
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < strtotime('-24 hours')) {
                if (@unlink($file)) {
                    $cleaned++;
                }
            }
        }
    }
    
    return $cleaned;
}

// تشغيل الإصلاحات
if (isset($_POST['run_fixes'])) {
    $results = [];
    
    // إنشاء المجلدات
    $created = createRequiredDirectories();
    $results[] = "تم إنشاء " . count($created) . " مجلد";
    
    // إصلاح الأذونات
    $fixed = fixFilePermissions();
    $results[] = "تم إصلاح أذونات " . count($fixed) . " ملف";
    
    // تنظيف الجلسات
    $cleaned_sessions = cleanExpiredSessions();
    $results[] = "تم تنظيف $cleaned_sessions جلسة منتهية";
    
    // إعادة تعيين العدادات
    if (resetCounters()) {
        $results[] = "تم إعادة تعيين العدادات";
    }
    
    // تنظيف الملفات المؤقتة
    $cleaned_files = cleanTempFiles();
    $results[] = "تم تنظيف $cleaned_files ملف مؤقت";
    
    echo '<div class="alert alert-success">';
    echo '<h5>✅ نتائج الإصلاح:</h5><ul>';
    foreach ($results as $result) {
        echo "<li>$result</li>";
    }
    echo '</ul></div>';
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إصلاحات سريعة</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
</head>
<body>
<div class="container mt-4">
    <h1>🔧 إصلاحات سريعة</h1>
    
    <div class="card">
        <div class="card-body">
            <h5>الإصلاحات المتاحة:</h5>
            <ul>
                <li>إنشاء المجلدات المطلوبة</li>
                <li>إصلاح أذونات الملفات</li>
                <li>تنظيف الجلسات المنتهية</li>
                <li>إعادة تعيين العدادات</li>
                <li>تنظيف الملفات المؤقتة</li>
            </ul>
            
            <form method="POST">
                <button type="submit" name="run_fixes" class="btn btn-warning">
                    🚀 تشغيل جميع الإصلاحات
                </button>
            </form>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-primary">العودة للداش بورد</a>
        <a href="diagnostic.php" class="btn btn-info">أداة التشخيص</a>
    </div>
</div>
</body>
</html>