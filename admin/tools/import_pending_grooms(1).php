<?php
// admin/tools/import_pending_grooms.php
// نسخة محسنة تستورد جميع السجلات التي groom_id فيها فارغ

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 دقائق للاستيراد الكبير
ini_set('memory_limit', '256M');

session_start();

// التحقق من الصلاحيات
if (empty($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

require_once dirname(__DIR__) . '/config.php';

// إعدادات Google Sheets
$SHEET_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQwZKxSN1xw1hjbn6MY2eB-7cmG-S_AS11MswrhqkOoq8ALcECXf3EKb2ejFMRwQ80-7ds4_IPK90C8/pub?output=csv';

// دالة تسجيل النشاط
function logImportActivity($message, $type = 'info') {
    $logFile = dirname(__DIR__) . '/logs/import_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['user'] ?? 'System';
    $logEntry = "[$timestamp] [$type] [$user] $message\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// دالة تحويل التاريخ العربي
function convertArabicDate($dateStr) {
    if (empty($dateStr)) return null;
    
    // تحويل ص و م إلى AM/PM
    $dateStr = str_replace(['ص', 'م'], ['AM', 'PM'], $dateStr);
    
    // محاولة تحويل التاريخ
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    return null;
}

// دالة تنظيف النص
function cleanText($text) {
    return trim(preg_replace('/\s+/', ' ', $text));
}

try {
    logImportActivity("========================================");
    logImportActivity("بدء عملية الاستيراد الشامل من Google Sheets");
    
    // جلب البيانات من Google Sheets
    $csvContent = @file_get_contents($SHEET_URL);
    if (!$csvContent) {
        throw new Exception("فشل في الاتصال بـ Google Sheets");
    }
    
    // تحليل CSV
    $rows = array_map('str_getcsv', explode("\n", $csvContent));
    
    // التحقق من وجود بيانات
    if (count($rows) < 2) {
        throw new Exception("لا توجد بيانات للاستيراد");
    }
    
    // السطر الأول كعناوين
    $headers = array_map('trim', $rows[0]);
    unset($rows[0]); // حذف السطر الأول
    
    logImportActivity("عدد الصفوف المكتشفة: " . count($rows));
    
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $processedNames = []; // لتتبع الأسماء المعالجة
    
    foreach ($rows as $rowIndex => $row) {
        try {
            // تنظيف البيانات
            $data = array_map('cleanText', array_pad($row, 22, ''));
            
            // الحصول على البيانات بالترتيب المتوقع
            list(
    $timestamp_ar,      // 0: الطابع الزمني
    $groom_name,       // 1: اسم العريس (الصحيح!)
    $phone,            // 2: رقم الجوال
    $booking_date_ar,  // 3: تاريخ الحجز
    $location,         // 4: الموقع/القاعة
    $package,          // 5: الباقة
    $services,         // 6: الخدمات
    $equipment,        // 7: المعدات
    $time_slot,        // 8: الوقت
    $delivery_method,  // 9: طريقة التسليم
    $paid_amount,      // 10: المبلغ المدفوع
    $remaining_amount, // 11: المبلغ المتبقي
    $total_amount,     // 12: المبلغ الإجمالي
    $employee_name,    // 13: اسم الموظف
    $employee_email,   // 14: بريد الموظف
    $invoice_number,   // 15: رقم الفاتورة
    $invoice_date_ar,  // 16: تاريخ الفاتورة
    $doc_id,          // 17: معرف المستند
    $doc_url,         // 18: رابط المستند
    $doc_view_url,    // 19: رابط عرض المستند
    $doc_status,      // 20: حالة المستند
    $groom_id         // 21: معرف العريس (نقل للآخر)
) = $data;
            // التحقق من الشروط الأساسية
            if (empty($groom_name)) {
                logImportActivity("السطر $rowIndex: تخطي - لا يوجد اسم عريس", 'skip');
                $skipped++;
                continue;
            }
            
            // تخطي إذا كان groom_id موجود (تم معالجته مسبقاً)
            if (!empty($groom_id) && $groom_id != '0' && $groom_id != 'NULL') {
                logImportActivity("السطر $rowIndex: تخطي - $groom_name له معرف ($groom_id)", 'skip');
                $skipped++;
                continue;
            }
            
            // التحقق من عدم معالجة نفس الاسم مرتين في نفس الجلسة
            $nameKey = $groom_name . '_' . $phone;
            if (in_array($nameKey, $processedNames)) {
                logImportActivity("السطر $rowIndex: تخطي - $groom_name تم معالجته في هذه الجلسة", 'skip');
                $skipped++;
                continue;
            }
            
            // التحقق من عدم وجود السجل في قاعدة البيانات
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM pending_grooms 
                WHERE groom_name = ? 
                AND (phone = ? OR (phone IS NULL AND ? IS NULL))
            ");
            $checkStmt->execute([$groom_name, $phone, $phone]);
            
            if ($checkStmt->fetchColumn() > 0) {
                logImportActivity("السطر $rowIndex: تخطي - $groom_name موجود في قاعدة البيانات", 'skip');
                $skipped++;
                continue;
            }
            
            // تحويل التواريخ
            $timestamp = convertArabicDate($timestamp_ar) ?: date('Y-m-d H:i:s');
            $booking_date = convertArabicDate($booking_date_ar);
            $invoice_date = convertArabicDate($invoice_date_ar);
            
            // تنظيف المبالغ المالية
            $paid_amount = preg_replace('/[^0-9.]/', '', $paid_amount) ?: '0';
            $remaining_amount = preg_replace('/[^0-9.]/', '', $remaining_amount) ?: '0';
            $total_amount = preg_replace('/[^0-9.]/', '', $total_amount) ?: '0';
            
            // إدراج في قاعدة البيانات
            $insertStmt = $pdo->prepare("
                INSERT INTO pending_grooms (
                    timestamp, groom_name, phone, booking_date, location, package,
                    services, equipment, time_slot, delivery_method,
                    paid_amount, remaining_amount, total_amount,
                    employee_name, employee_email, invoice_number, invoice_date,
                    doc_id, doc_url, doc_view_url, doc_status,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $insertStmt->execute([
                $timestamp,
                $groom_name,
                $phone ?: null,
                $booking_date,
                $location ?: null,
                $package ?: null,
                $services ?: null,
                $equipment ?: null,
                $time_slot ?: null,
                $delivery_method ?: null,
                $paid_amount,
                $remaining_amount,
                $total_amount,
                $employee_name ?: null,
                $employee_email ?: null,
                $invoice_number ?: null,
                $invoice_date,
                $doc_id ?: null,
                $doc_url ?: null,
                $doc_view_url ?: null,
                $doc_status ?: null
            ]);
            
            $imported++;
            $processedNames[] = $nameKey;
            logImportActivity("✅ السطر $rowIndex: استيراد ناجح - $groom_name", 'success');
            
        } catch (Exception $rowError) {
            $errorMsg = "السطر $rowIndex: " . $rowError->getMessage();
            $errors[] = $errorMsg;
            logImportActivity("❌ " . $errorMsg, 'error');
            
            // الاستمرار في معالجة باقي الصفوف
            continue;
        }
    }
    
    // النتيجة النهائية
    $result = [
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => array_slice($errors, 0, 10), // أول 10 أخطاء فقط
        'total_errors' => count($errors),
        'message' => "✅ تم استيراد $imported صفحة جديدة، تخطي $skipped صفحة"
    ];
    
    if (count($errors) > 0) {
        $result['message'] .= "\n⚠️ مع " . count($errors) . " خطأ";
    }
    
    logImportActivity("========================================");
    logImportActivity("النتيجة النهائية: " . $result['message'], 'complete');
    logImportActivity("========================================");
    
    // إرجاع النتيجة
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
    
} catch (Exception $e) {
    $error = "❌ خطأ عام: " . $e->getMessage();
    logImportActivity($error, 'critical');
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $error,
        'imported' => 0,
        'skipped' => 0
    ]);
}
?>