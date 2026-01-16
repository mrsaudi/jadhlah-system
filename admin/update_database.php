<?php
// admin/update_database.php - تحديث هيكل قاعدة البيانات
session_start();

// التحقق من صلاحية المدير
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'manager') {
    die('غير مصرح لك بالوصول لهذه الصفحة');
}

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث قاعدة البيانات</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
</head>
<body>
<div class="container mt-4">
    <h1>🔄 تحديث قاعدة البيانات</h1>
    
    <?php
    $updates = [];
    $errors = [];
    
    if (isset($_POST['run_updates'])) {
        // تحديث 1: إضافة عمود updated_at
        try {
            $pdo->exec("ALTER TABLE grooms ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            $updates[] = "تم إضافة عمود updated_at";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                $errors[] = "خطأ في إضافة updated_at: " . $e->getMessage();
            } else {
                $updates[] = "عمود updated_at موجود مسبقاً";
            }
        }
        
        // تحديث 2: تحسين عمود ready_at
        try {
            $pdo->exec("ALTER TABLE grooms MODIFY COLUMN ready_at TIMESTAMP NULL DEFAULT NULL");
            $updates[] = "تم تحسين عمود ready_at";
        } catch (PDOException $e) {
            $errors[] = "خطأ في تحسين ready_at: " . $e->getMessage();
        }
        
        // تحديث 3: إضافة فهارس للأداء
        try {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_grooms_status ON grooms(is_active, is_blocked)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_grooms_ready ON grooms(ready)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_grooms_created ON grooms(created_at)");
            $updates[] = "تم إضافة الفهارس للأداء";
        } catch (PDOException $e) {
            $errors[] = "خطأ في إضافة الفهارس: " . $e->getMessage();
        }
        
        // تحديث 4: تنظيف البيانات المعطلة
        try {
            $result = $pdo->exec("UPDATE grooms SET ready_at = NULL WHERE ready = 0 AND ready_at IS NOT NULL");
            $updates[] = "تم تنظيف $result سجل من البيانات المتضاربة";
        } catch (PDOException $e) {
            $errors[] = "خطأ في تنظيف البيانات: " . $e->getMessage();
        }
        
        // تحديث 5: إضافة جدول لتتبع التحديثات
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_updates (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    update_name VARCHAR(100) NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('success', 'failed') DEFAULT 'success',
                    details TEXT
                )
            ");
            
            $pdo->prepare("INSERT INTO system_updates (update_name, details) VALUES (?, ?)")
                ->execute(['dashboard_fix_v1', 'تحديث نظام الداش بورد وإصلاح المشاكل']);
            
            $updates[] = "تم إنشاء جدول تتبع التحديثات";
        } catch (PDOException $e) {
            $errors[] = "خطأ في جدول التحديثات: " . $e->getMessage();
        }
        
        // تحديث 6: إضافة أعمدة للإحصائيات
        try {
            $pdo->exec("ALTER TABLE grooms ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL");
            $updates[] = "تم إضافة عمود last_activity";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                $errors[] = "خطأ في إضافة last_activity: " . $e->getMessage();
            }
        }
    }
    
    // عرض النتائج
    if (!empty($updates)) {
        echo '<div class="alert alert-success"><h5>✅ التحديثات المنجزة:</h5><ul>';
        foreach ($updates as $update) {
            echo "<li>$update</li>";
        }
        echo '</ul></div>';
    }
    
    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><h5>❌ الأخطاء:</h5><ul>';
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo '</ul></div>';
    }
    ?>
    
    <div class="card">
        <div class="card-header">
            <h5>تحديثات قاعدة البيانات المطلوبة</h5>
        </div>
        <div class="card-body">
            <ul>
                <li>إضافة عمود <code>updated_at</code> لتتبع آخر تحديث</li>
                <li>تحسين عمود <code>ready_at</code> للسماح بالقيم الفارغة</li>
                <li>إضافة فهارس للأداء</li>
                <li>تنظيف البيانات المتضاربة</li>
                <li>إنشاء جدول تتبع التحديثات</li>
                <li>إضافة أعمدة الإحصائيات</li>
            </ul>
            
            <form method="POST">
                <button type="submit" name="run_updates" class="btn btn-primary" 
                        onclick="return confirm('هل أنت متأكد من تنفيذ التحديثات؟')">
                    🚀 تنفيذ التحديثات
                </button>
            </form>
        </div>
    </div>
    
    <div class="mt-4">
        <h5>فحص الهيكل الحالي:</h5>
        
        <?php
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM grooms")->fetchAll();
            echo '<div class="table-responsive"><table class="table table-sm">';
            echo '<thead><tr><th>اسم العمود</th><th>النوع</th><th>فارغ؟</th><th>افتراضي</th></tr></thead><tbody>';
            
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><code>{$col['Field']}</code></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>" . ($col['Null'] === 'YES' ? '✅' : '❌') . "</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            
            echo '</tbody></table></div>';
            
            // فحص الفهارس
            $indexes = $pdo->query("SHOW INDEX FROM grooms")->fetchAll();
            echo '<h6>الفهارس:</h6><ul>';
            foreach ($indexes as $index) {
                echo "<li><code>{$index['Key_name']}</code> على العمود <code>{$index['Column_name']}</code></li>";
            }
            echo '</ul>';
            
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">خطأ في فحص الهيكل: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>
    
    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-secondary">العودة للداش بورد</a>
        <a href="diagnostic.php" class="btn btn-info">أداة التشخيص</a>
    </div>
</div>
</body>
</html>