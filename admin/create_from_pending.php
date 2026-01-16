<?php
// admin/create_from_pending_enhanced.php
// إنشاء عريس من البيانات المستوردة مع فصل الملاحظات

session_start();
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// استلام الطابع الزمني
$timestamp = $_GET['timestamp'] ?? '';
if (!$timestamp) {
    $_SESSION['error'] = 'الطابع الزمني مفقود';
    header('Location: dashboard.php');
    exit;
}

try {
    // جلب بيانات من pending_grooms
    $stmt = $pdo->prepare("SELECT * FROM pending_grooms WHERE timestamp = ? AND groom_id IS NULL");
    $stmt->execute([$timestamp]);
    $data = $stmt->fetch();
    
    if (!$data) {
        throw new Exception('لا يوجد حجز مطابق أو تم معالجته مسبقاً');
    }
    
    // الملاحظة الافتراضية (التهنئة)
    $defaultNote = "بارك الله لهما وبارك عليهما وجمع بينهما في خير";
    
    // ملاحظات النظام (معلومات الاستيراد)
    $systemNotes = [];
    
    // إضافة المعلومات المتوفرة فقط
    $systemNotes[] = "📌 مصدر البيانات: Google Sheets";
    $systemNotes[] = "👤 تم الإنشاء بواسطة: " . $_SESSION['user'];
    $systemNotes[] = "📅 تاريخ الإنشاء: " . date('Y-m-d H:i:s');
    
    if (!empty($data['phone'])) {
        $systemNotes[] = "📱 رقم الهاتف: " . $data['phone'];
    }
    
    if (!empty($data['package'])) {
        $systemNotes[] = "📦 الباقة: " . $data['package'];
    }
    
    if (!empty($data['paid_amount']) && $data['paid_amount'] != '0') {
        $systemNotes[] = "💰 المدفوع: " . $data['paid_amount'] . " ريال";
    }
    
    if (!empty($data['remaining_amount']) && $data['remaining_amount'] != '0') {
        $systemNotes[] = "💸 المتبقي: " . $data['remaining_amount'] . " ريال";
    }
    
    if (!empty($data['total_amount']) && $data['total_amount'] != '0') {
        $systemNotes[] = "💵 الإجمالي: " . $data['total_amount'] . " ريال";
    }
    
    if (!empty($data['invoice_number'])) {
        $systemNotes[] = "📄 رقم الفاتورة: " . $data['invoice_number'];
    }
    
    if (!empty($data['employee_name'])) {
        $systemNotes[] = "👨‍💼 الموظف المسؤول: " . $data['employee_name'];
    }
    
    if (!empty($data['services'])) {
        $systemNotes[] = "🎥 الخدمات: " . $data['services'];
    }
    
    if (!empty($data['equipment'])) {
        $systemNotes[] = "📷 المعدات: " . $data['equipment'];
    }
    
    if (!empty($data['time_slot'])) {
        $systemNotes[] = "⏰ الوقت: " . $data['time_slot'];
    }
    
    if (!empty($data['delivery_method'])) {
        $systemNotes[] = "📦 طريقة التسليم: " . $data['delivery_method'];
    }
    
    $systemNotesText = implode("\n", $systemNotes);
    
    // إنشاء اسم مجلد فريد
    $folderName = 'groom_' . time() . '_' . rand(1000, 9999);
    
    // إنشاء سجل العريس
    $stmt = $pdo->prepare("
        INSERT INTO grooms (
            folder_name,
            groom_name, 
            wedding_date, 
            hall_name, 
            notes, 
            system_notes,
            import_source, 
            import_date, 
            import_by,
            created_at, 
            is_active,
            is_blocked,
            ready
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), 1, 0, 0
        )
    ");
    
    $stmt->execute([
        $folderName,
        $data['groom_name'],
        $data['booking_date'],
        $data['location'],
        $defaultNote,
        $systemNotesText,
        'Google Sheets',
        $_SESSION['user']
    ]);
    
    $groomId = $pdo->lastInsertId();
    
    // تحديث pending_grooms
    $stmt = $pdo->prepare("
        UPDATE pending_grooms 
        SET groom_id = ?, 
            processed = 1,
            processed_at = NOW(),
            updated_at = NOW() 
        WHERE timestamp = ?
    ");
    $stmt->execute([$groomId, $timestamp]);
    
    // إنشاء المجلدات المطلوبة (في جذر الموقع)
    $groomBaseDir = dirname(__DIR__) . '/grooms/' . $groomId;
    $dirs = [
        $groomBaseDir,
        $groomBaseDir . '/originals',
        $groomBaseDir . '/modal_thumb',
        $groomBaseDir . '/thumbs',
        $groomBaseDir . '/temp'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("فشل في إنشاء المجلد: $dir");
            }
        }
    }
    
    // تسجيل النشاط
    try {
        $pdo->exec("CALL log_activity(
            '{$_SESSION['user']}',
            'create_from_pending',
            'groom',
            $groomId,
            'إنشاء صفحة عريس من البيانات المستوردة',
            '{$_SERVER['REMOTE_ADDR']}'
        )");
    } catch (Exception $e) {
        // تجاهل خطأ تسجيل النشاط
    }
    
    // حساب الإحصائيات الأولية
    try {
        $pdo->exec("CALL calculate_groom_stats($groomId)");
    } catch (Exception $e) {
        // تجاهل خطأ الإحصائيات
    }
    
    $_SESSION['flash'] = 'تم إنشاء صفحة العريس بنجاح من البيانات المستوردة';
    header("Location: edit_groom.php?id=" . $groomId);
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    error_log("خطأ في create_from_pending: " . $e->getMessage());
    header('Location: dashboard.php');
}
exit;
?>