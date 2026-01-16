
// ========== admin/tools/export_pending.php ==========
// <?php
session_start();

if (!isset($_SESSION['user'])) {
    die('غير مصرح');
}

require_once dirname(__DIR__) . '/config.php';

try {
    $stmt = $pdo->query("
        SELECT 
            groom_name as 'اسم العريس',
            phone as 'الهاتف',
            booking_date as 'تاريخ الحجز',
            location as 'الموقع',
            package as 'الباقة',
            services as 'الخدمات',
            paid_amount as 'المدفوع',
            remaining_amount as 'المتبقي',
            total_amount as 'الإجمالي',
            employee_name as 'الموظف',
            CASE 
                WHEN groom_id IS NOT NULL THEN 'معالج'
                ELSE 'منتظر'
            END as 'الحالة',
            created_at as 'تاريخ الإضافة'
        FROM pending_grooms
        ORDER BY created_at DESC
    ");
    
    $data = $stmt->fetchAll();
    
    // إنشاء ملف CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pending_grooms_' . date('Y-m-d') . '.csv"');
    
    // إضافة BOM لدعم العربية في Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // كتابة العناوين
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // كتابة البيانات
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    die('خطأ في التصدير: ' . $e->getMessage());
}
// ?>