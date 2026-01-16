<?php
// admin/setup_deleted_tracking.php
// ملف إنشاء جدول تتبع المحذوفين
session_start();

// التحقق من الصلاحيات
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'manager') {
    die('غير مصرح - يجب أن تكون مدير للوصول لهذه الصفحة');
}

// الاتصال بقاعدة البيانات
$host = 'localhost';
$dbname = 'u709146392_jadhlah_db';
$username = 'u709146392_jad_admin';
$password = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "<!DOCTYPE html>";
    echo "<html lang='ar' dir='rtl'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>إعداد نظام تتبع المحذوفين</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css' rel='stylesheet'>";
    echo "</head>";
    echo "<body>";
    echo "<div class='container mt-5'>";
    echo "<div class='card'>";
    echo "<div class='card-header bg-primary text-white'>";
    echo "<h3>إعداد نظام تتبع المحذوفين</h3>";
    echo "</div>";
    echo "<div class='card-body'>";
    
    // إنشاء جدول تتبع المحذوفين
    $sql = "CREATE TABLE IF NOT EXISTS deleted_pending_grooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        groom_name VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        booking_date VARCHAR(100),
        location VARCHAR(255),
        deleted_by VARCHAR(100),
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reason VARCHAR(500),
        original_data JSON,
        INDEX idx_groom_name (groom_name),
        INDEX idx_phone (phone),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "<div class='alert alert-success'>✅ تم إنشاء جدول deleted_pending_grooms بنجاح</div>";
    
    // إضافة عمود is_deleted في pending_grooms إذا لم يكن موجوداً
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM pending_grooms LIKE 'is_deleted'");
        if ($checkColumn->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pending_grooms ADD COLUMN is_deleted TINYINT DEFAULT 0");
            echo "<div class='alert alert-success'>✅ تم إضافة عمود is_deleted</div>";
        } else {
            echo "<div class='alert alert-info'>✔️ عمود is_deleted موجود بالفعل</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-warning'>⚠️ تحذير: " . $e->getMessage() . "</div>";
    }
    
    // إضافة عمود deletion_token لمنع الاستيراد
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM pending_grooms LIKE 'deletion_token'");
        if ($checkColumn->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pending_grooms ADD COLUMN deletion_token VARCHAR(100)");
            echo "<div class='alert alert-success'>✅ تم إضافة عمود deletion_token</div>";
        } else {
            echo "<div class='alert alert-info'>✔️ عمود deletion_token موجود بالفعل</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-warning'>⚠️ تحذير: " . $e->getMessage() . "</div>";
    }
    
    // إضافة فهرس للبحث السريع
    try {
        // التحقق من وجود الفهرس أولاً
        $checkIndex = $pdo->query("SHOW INDEX FROM pending_grooms WHERE Key_name = 'idx_deletion'");
        if ($checkIndex->rowCount() == 0) {
            $pdo->exec("CREATE INDEX idx_deletion ON pending_grooms(is_deleted, deletion_token)");
            echo "<div class='alert alert-success'>✅ تم إضافة فهرس البحث</div>";
        } else {
            echo "<div class='alert alert-info'>✔️ الفهرس موجود بالفعل</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='alert alert-warning'>⚠️ تحذير: " . $e->getMessage() . "</div>";
    }
    
    // عرض إحصائيات الجداول
    echo "<hr>";
    echo "<h5>إحصائيات النظام:</h5>";
    
    // عدد العرسان المنتظرين
    $countPending = $pdo->query("SELECT COUNT(*) as total FROM pending_grooms WHERE (is_deleted IS NULL OR is_deleted = 0)")->fetch();
    echo "<p>📊 عدد العرسان المنتظرين النشطين: <strong>" . $countPending['total'] . "</strong></p>";
    
    // عدد المحذوفين soft delete
    $countSoftDeleted = $pdo->query("SELECT COUNT(*) as total FROM pending_grooms WHERE is_deleted = 1")->fetch();
    echo "<p>🗑️ عدد المحذوفين (soft delete): <strong>" . $countSoftDeleted['total'] . "</strong></p>";
    
    // عدد المحذوفين في سلة المحذوفات
    $countDeleted = $pdo->query("SELECT COUNT(*) as total FROM deleted_pending_grooms")->fetch();
    echo "<p>🗑️ عدد المحذوفين في سلة المحذوفات: <strong>" . $countDeleted['total'] . "</strong></p>";
    
    echo "<hr>";
    echo "<div class='alert alert-success'>";
    echo "<h5>✅ <strong>النظام جاهز للعمل!</strong></h5>";
    echo "<p>تم إعداد نظام تتبع المحذوفين بنجاح. يمكنك الآن:</p>";
    echo "<ul>";
    echo "<li>حذف العرسان مع الاحتفاظ بسجل في سلة المحذوفات</li>";
    echo "<li>استرجاع العرسان المحذوفين عند الحاجة</li>";
    echo "<li>منع استيراد العرسان المحذوفين مسبقاً</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</div>"; // card-body
    echo "<div class='card-footer'>";
    echo '<a href="dashboard.php" class="btn btn-primary">العودة للداشبورد</a> ';
    echo '<a href="tools/manage_deleted.php" class="btn btn-success">إدارة المحذوفين</a>';
    echo "</div>";
    echo "</div>"; // card
    echo "</div>"; // container
    echo "</body>";
    echo "</html>";
    
} catch (PDOException $e) {
    echo "<!DOCTYPE html>";
    echo "<html lang='ar' dir='rtl'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<title>خطأ</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css' rel='stylesheet'>";
    echo "</head>";
    echo "<body>";
    echo "<div class='container mt-5'>";
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ خطأ في الاتصال بقاعدة البيانات</h4>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<hr>";
    echo "<p>تأكد من:</p>";
    echo "<ul>";
    echo "<li>صحة بيانات الاتصال بقاعدة البيانات</li>";
    echo "<li>وجود صلاحيات CREATE TABLE</li>";
    echo "<li>تسجيل الدخول كمدير</li>";
    echo "</ul>";
    echo '<a href="../index.php" class="btn btn-secondary">العودة</a>';
    echo "</div>";
    echo "</div>";
    echo "</body>";
    echo "</html>";
}
?>