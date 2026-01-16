
// ========== admin/fix_permissions.php ==========
// <?php
session_start();

if ($_SESSION['role'] !== 'manager') {
    die('غير مصرح');
}

require_once __DIR__ . '/config.php';

$fixes = [];

// إصلاح الصلاحيات
try {
    // التأكد من وجود مجلد logs
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
        $fixes[] = "✅ تم إنشاء مجلد logs";
    } else {
        chmod($logsDir, 0755);
        $fixes[] = "✅ تم تحديث صلاحيات مجلد logs";
    }
    
    // التأكد من وجود جدول pending_grooms
    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_grooms (
        id INT PRIMARY KEY AUTO_INCREMENT,
        timestamp VARCHAR(255),
        groom_name VARCHAR(255),
        phone VARCHAR(50),
        booking_date VARCHAR(100),
        location VARCHAR(255),
        package VARCHAR(255),
        services TEXT,
        equipment TEXT,
        time_slot VARCHAR(100),
        delivery_method VARCHAR(100),
        paid_amount DECIMAL(10,2),
        remaining_amount DECIMAL(10,2),
        total_amount DECIMAL(10,2),
        employee_name VARCHAR(255),
        employee_email VARCHAR(255),
        invoice_number VARCHAR(100),
        invoice_date DATETIME,
        doc_id VARCHAR(255),
        doc_url TEXT,
        doc_view_url TEXT,
        doc_status VARCHAR(50),
        groom_id INT DEFAULT NULL,
        processed TINYINT DEFAULT 0,
        processed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_groom_id (groom_id),
        INDEX idx_timestamp (timestamp),
        INDEX idx_processed (processed)
    )");
    $fixes[] = "✅ جدول pending_grooms جاهز";
    
    // إضافة أعمدة مفقودة في grooms
    $columns = [
        'is_active' => "ALTER TABLE grooms ADD COLUMN is_active TINYINT DEFAULT 1",
        'is_blocked' => "ALTER TABLE grooms ADD COLUMN is_blocked TINYINT DEFAULT 0",
        'ready' => "ALTER TABLE grooms ADD COLUMN ready TINYINT DEFAULT 0",
        'ready_at' => "ALTER TABLE grooms ADD COLUMN ready_at DATETIME DEFAULT NULL"
    ];
    
    foreach ($columns as $column => $sql) {
        try {
            $pdo->exec($sql);
            $fixes[] = "✅ تم إضافة عمود $column";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                $fixes[] = "❌ خطأ في إضافة $column: " . $e->getMessage();
            } else {
                $fixes[] = "✔️ عمود $column موجود بالفعل";
            }
        }
    }
    
    echo "<h3>نتائج الإصلاح:</h3><ul>";
    foreach ($fixes as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul>";
    echo '<a href="dashboard.php" class="btn btn-primary">العودة للداشبورد</a>';
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage();
}
// ?>