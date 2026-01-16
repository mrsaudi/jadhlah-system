<?php
// admin/diagnostic.php - أداة تشخيص المشاكل
session_start();

// التحقق من تسجيل الدخول (اختياري للتشخيص)
$isLoggedIn = isset($_SESSION['user']);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تشخيص النظام - جذلة</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <style>
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .diagnostic-section { 
            margin-bottom: 2rem; 
            padding: 1rem; 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
        }
        pre { 
            background: #f8f9fa; 
            padding: 1rem; 
            border-radius: 4px; 
            max-height: 200px; 
            overflow-y: auto; 
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <h1 class="mb-4">🔍 تشخيص النظام</h1>
    
    <?php
    $diagnostics = [];
    
    // 1. فحص ملفات PHP
    echo '<div class="diagnostic-section">';
    echo '<h3>📄 فحص الملفات</h3>';
    
    $requiredFiles = [
        'config.php',
        'update_page_status.php', 
        'dashboard_functions.js',
        'dashboard.php'
    ];
    
    foreach ($requiredFiles as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            echo "<p class='status-ok'>✅ $file موجود</p>";
            
            // فحص الأذونات
            if (is_readable($path)) {
                echo "<p class='status-ok'>📖 $file قابل للقراءة</p>";
            } else {
                echo "<p class='status-error'>❌ $file غير قابل للقراءة</p>";
            }
        } else {
            echo "<p class='status-error'>❌ $file مفقود</p>";
        }
    }
    echo '</div>';
    
    // 2. فحص قاعدة البيانات
    echo '<div class="diagnostic-section">';
    echo '<h3>🗄️ فحص قاعدة البيانات</h3>';
    
    try {
        require_once __DIR__ . '/config.php';
        echo "<p class='status-ok'>✅ الاتصال بقاعدة البيانات نجح</p>";
        
        // فحص الجداول المطلوبة
        $requiredTables = ['grooms', 'groom_photos', 'groom_reviews', 'users'];
        foreach ($requiredTables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<p class='status-ok'>✅ جدول $table موجود</p>";
                    
                    // فحص عدد السجلات
                    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                    echo "<p class='text-muted'>📊 عدد السجلات في $table: $count</p>";
                } else {
                    echo "<p class='status-error'>❌ جدول $table مفقود</p>";
                }
            } catch (Exception $e) {
                echo "<p class='status-error'>❌ خطأ في فحص جدول $table: " . $e->getMessage() . "</p>";
            }
        }
        
        // فحص أعمدة جدول grooms
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM grooms")->fetchAll(PDO::FETCH_COLUMN);
            $requiredColumns = ['id', 'groom_name', 'is_active', 'is_blocked', 'ready', 'ready_at'];
            
            echo "<h5>أعمدة جدول grooms:</h5>";
            foreach ($requiredColumns as $col) {
                if (in_array($col, $columns)) {
                    echo "<p class='status-ok'>✅ عمود $col موجود</p>";
                } else {
                    echo "<p class='status-error'>❌ عمود $col مفقود</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p class='status-error'>❌ خطأ في فحص أعمدة grooms: " . $e->getMessage() . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='status-error'>❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage() . "</p>";
    }
    echo '</div>';
    
    // 3. فحص المجلدات والأذونات
    echo '<div class="diagnostic-section">';
    echo '<h3>📁 فحص المجلدات والأذونات</h3>';
    
    $requiredDirs = [
        __DIR__ . '/logs',
        __DIR__ . '/temp_uploads',
        dirname(__DIR__) . '/grooms'
    ];
    
    foreach ($requiredDirs as $dir) {
        if (is_dir($dir)) {
            echo "<p class='status-ok'>✅ مجلد موجود: " . basename($dir) . "</p>";
            
            if (is_writable($dir)) {
                echo "<p class='status-ok'>✏️ قابل للكتابة: " . basename($dir) . "</p>";
            } else {
                echo "<p class='status-error'>❌ غير قابل للكتابة: " . basename($dir) . "</p>";
            }
        } else {
            echo "<p class='status-warning'>⚠️ مجلد مفقود: " . basename($dir) . "</p>";
            
            // محاولة إنشاء المجلد
            if (@mkdir($dir, 0755, true)) {
                echo "<p class='status-ok'>✅ تم إنشاء المجلد: " . basename($dir) . "</p>";
            } else {
                echo "<p class='status-error'>❌ فشل إنشاء المجلد: " . basename($dir) . "</p>";
            }
        }
    }
    echo '</div>';
    
    // 4. فحص إعدادات PHP
    echo '<div class="diagnostic-section">';
    echo '<h3>⚙️ إعدادات PHP</h3>';
    
    $phpSettings = [
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'max_file_uploads' => ini_get('max_file_uploads'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
        'log_errors' => ini_get('log_errors') ? 'On' : 'Off'
    ];
    
    foreach ($phpSettings as $setting => $value) {
        echo "<p><strong>$setting:</strong> $value</p>";
    }
    echo '</div>';
    
    // 5. فحص ملفات الأخطاء
    echo '<div class="diagnostic-section">';
    echo '<h3>📋 ملفات الأخطاء</h3>';
    
    $logFiles = [
        __DIR__ . '/logs/php_errors.log',
        __DIR__ . '/logs/update_status_errors.log',
        __DIR__ . '/logs/error.log'
    ];
    
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile)) {
            $size = filesize($logFile);
            echo "<p class='status-ok'>📝 " . basename($logFile) . " - حجم: " . number_format($size) . " بايت</p>";
            
            if ($size > 0) {
                $lastLines = array_slice(file($logFile), -5);
                echo "<h6>آخر 5 أسطر:</h6>";
                echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
            }
        } else {
            echo "<p class='status-warning'>⚠️ " . basename($logFile) . " غير موجود</p>";
        }
    }
    echo '</div>';
    
    // 6. اختبار AJAX
    echo '<div class="diagnostic-section">';
    echo '<h3>🔄 اختبار AJAX</h3>';
    echo '<button id="testAjax" class="btn btn-primary">اختبار الاتصال</button>';
    echo '<div id="ajaxResult" class="mt-3"></div>';
    echo '</div>';
    
    // 7. معلومات الجلسة
    if ($isLoggedIn) {
        echo '<div class="diagnostic-section">';
        echo '<h3>👤 معلومات الجلسة</h3>';
        echo "<p><strong>المستخدم:</strong> " . ($_SESSION['user'] ?? 'غير محدد') . "</p>";
        echo "<p><strong>الدور:</strong> " . ($_SESSION['role'] ?? 'غير محدد') . "</p>";
        echo "<p><strong>معرف الجلسة:</strong> " . session_id() . "</p>";
        echo '</div>';
    }
    ?>
    
    <div class="diagnostic-section">
        <h3>🛠️ إجراءات سريعة</h3>
        <button onclick="clearLogs()" class="btn btn-warning">مسح ملفات الأخطاء</button>
        <button onclick="testPermissions()" class="btn btn-info">اختبار الأذونات</button>
        <button onclick="location.reload()" class="btn btn-secondary">تحديث التشخيص</button>
    </div>
</div>

<script>
// اختبار AJAX
document.getElementById('testAjax').addEventListener('click', function() {
    const resultDiv = document.getElementById('ajaxResult');
    resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> جاري الاختبار...';
    
    fetch('update_page_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'id=0&action=test'
    })
    .then(response => {
        resultDiv.innerHTML = `
            <div class="alert alert-info">
                <strong>حالة HTTP:</strong> ${response.status}<br>
                <strong>نوع المحتوى:</strong> ${response.headers.get('content-type')}<br>
                <strong>الحالة:</strong> ${response.ok ? 'نجح' : 'فشل'}
            </div>
        `;
        return response.text();
    })
    .then(text => {
        resultDiv.innerHTML += `
            <div class="alert alert-secondary">
                <strong>الاستجابة:</strong><br>
                <pre>${text.substring(0, 500)}${text.length > 500 ? '...' : ''}</pre>
            </div>
        `;
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <strong>خطأ:</strong> ${error.message}
            </div>
        `;
    });
});

function clearLogs() {
    if (confirm('هل تريد مسح جميع ملفات الأخطاء؟')) {
        fetch('diagnostic.php?action=clear_logs', {method: 'POST'})
        .then(() => {
            alert('تم مسح ملفات الأخطاء');
            location.reload();
        });
    }
}

function testPermissions() {
    fetch('diagnostic.php?action=test_permissions', {method: 'POST'})
    .then(response => response.text())
    .then(result => {
        alert('نتيجة اختبار الأذونات: ' + result);
    });
}
</script>

<?php
// معالجة الإجراءات
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'clear_logs':
            $logFiles = glob(__DIR__ . '/logs/*.log');
            foreach ($logFiles as $file) {
                @unlink($file);
            }
            echo "تم مسح الملفات";
            exit;
            
        case 'test_permissions':
            $result = checkPermissions() ? 'الأذونات سليمة' : 'مشاكل في الأذونات';
            echo $result;
            exit;
    }
}
?>

</body>
</html>