<?php
/**
 * ============================================
 * WhatsApp Send API - جذلة
 * ============================================
 * 
 * الملف: api/whatsapp/send.php
 * الوظيفة: إرسال رسائل WhatsApp للحجوزات
 */

if (!defined('JADHLAH_APP')) {
    define('JADHLAH_APP', true);
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/whatsapp.php';
require_once __DIR__ . '/WhatsAppClient.php';

// التأكد من الاتصال
if (!isset($pdo)) {
    jsonResponse(['success' => false, 'error' => 'Database connection failed'], 500);
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

$bookingId = $input['booking_id'] ?? null;
$templateKey = $input['template_key'] ?? null;
$phone = $input['phone'] ?? null;
$employeeId = $input['employee_id'] ?? null;

if (!$bookingId && !$phone) {
    jsonResponse(['success' => false, 'error' => 'booking_id أو phone مطلوب'], 400);
}

if (!$templateKey) {
    jsonResponse(['success' => false, 'error' => 'template_key مطلوب'], 400);
}

// جلب بيانات الحجز
$booking = null;
if ($bookingId) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.name as package_name_db,
        (SELECT COALESCE(SUM(amount), 0) FROM booking_payments bp WHERE bp.booking_id = b.id AND bp.is_paid = 1) as total_paid
        FROM bookings b
        LEFT JOIN packages p ON b.package_id = p.id
        WHERE b.id = :id
    ");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        jsonResponse(['success' => false, 'error' => 'الحجز غير موجود'], 404);
    }
    
    $phone = $booking['phone'];
}

// جلب بيانات القالب
$stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE template_key = :key AND is_active = 1");
$stmt->execute([':key' => $templateKey]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    // محاولة البحث بالاسم
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_templates WHERE template_name = :name AND is_active = 1");
    $stmt->execute([':name' => $templateKey]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$template) {
    jsonResponse(['success' => false, 'error' => 'القالب غير موجود: ' . $templateKey], 404);
}

// إذا كان القالب للفريق، جلب بيانات الموظف
$employee = null;
if ($template['recipient_type'] === 'team' && $employeeId) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
    $stmt->execute([':id' => $employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($employee) {
        $phone = $employee['phone'];
    }
}

// تنسيق رقم الهاتف
$phone = formatPhone($phone);

// تجهيز المتغيرات
$variables = buildTemplateVariables($templateKey, $booking, $employee);

// إرسال الرسالة
$whatsapp = new WhatsAppClient();

$result = $whatsapp->sendTemplate(
    $phone,
    $template['template_name'],
    $template['template_language'] ?? 'ar',
    $variables
);

// تسجيل في السجل
logWhatsAppMessage($pdo, [
    'booking_id' => $bookingId,
    'template_id' => $template['id'],
    'phone' => $phone,
    'stage' => $template['stage'],
    'status' => $result['success'] ? 'sent' : 'failed',
    'message_id' => $result['message_id'] ?? null,
    'error' => $result['error'] ?? null
]);

// تحديث مرحلة الحجز إذا نجح الإرسال
if ($result['success'] && $booking && $template['stage']) {
    updateBookingStageIfNeeded($pdo, $bookingId, $template['stage']);
}

jsonResponse($result);

// ═══════════════════════════════════════════════════════════════════
// الدوال المساعدة
// ═══════════════════════════════════════════════════════════════════

function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '966' . substr($phone, 1);
    }
    if (substr($phone, 0, 3) !== '966' && strlen($phone) === 9) {
        $phone = '966' . $phone;
    }
    return $phone;
}

function buildTemplateVariables($templateKey, $booking, $employee = null) {
    if (!$booking) return [];
    
    $groomName = $booking['groom_name'];
    $weddingDate = formatArabicDate($booking['wedding_date']);
    $packageName = $booking['package_name'] ?? $booking['package_name_db'] ?? 'غير محدد';
    $totalPrice = number_format($booking['total_price'], 0);
    $venue = $booking['venue'] ?? 'غير محدد';
    $city = $booking['city'] ?? '';
    $weddingTime = $booking['wedding_time'] ? date('g:i A', strtotime($booking['wedding_time'])) : '';
    $fullVenue = $venue . ($city ? ' - ' . $city : '');
    
    switch ($templateKey) {
        case 'booking_confirmation':
            return [$groomName, $weddingDate, $packageName, $totalPrice];
            
        case 'coordination_request':
            $link = "https://jadhlah.com/coordination/{$booking['id']}";
            return [$groomName, $link];
            
        case 'team_assignment':
            $employeeName = $employee['name'] ?? 'الفريق';
            return [$employeeName, $groomName, $weddingDate, $fullVenue, $weddingTime];
            
        case 'photo_guidelines':
            $guidelines = "✅ تأكد من إضاءة جيدة\n✅ جهّز ملابس الاستقبال\n✅ كن جاهزاً قبل 30 دقيقة";
            return [$groomName, $guidelines];
            
        case 'reminder_groom':
            $paymentNote = '';
            if ($booking['payment_status'] !== 'paid') {
                $remaining = $booking['total_price'] - ($booking['total_paid'] ?? 0);
                if ($remaining > 0) {
                    $paymentNote = "💰 المتبقي: " . number_format($remaining, 0) . " ريال";
                }
            }
            return [$groomName, $fullVenue, $paymentNote];
            
        case 'reminder_team':
            return [$groomName, $fullVenue, $weddingTime];
            
        case 'wedding_today':
            $arrivalTime = $booking['wedding_time'] 
                ? date('g:i A', strtotime($booking['wedding_time'] . ' -30 minutes'))
                : '7:30 PM';
            return [$groomName, $fullVenue, $arrivalTime];
            
        case 'processing_start':
            $deliveryDate = $booking['expected_delivery_date'] 
                ? formatArabicDate($booking['expected_delivery_date'])
                : formatArabicDate(date('Y-m-d', strtotime($booking['wedding_date'] . ' +14 days')));
            return [$groomName, $deliveryDate];
            
        case 'grooms_ready':
            $link = $booking['delivery_link'] ?? "https://jadhlah.com/groom/{$booking['groom_id']}";
            return [$groomName, $link];
            
        case 'review_request':
            $reviewLink = "https://jadhlah.com/rate?booking={$booking['id']}";
            return [$groomName, $reviewLink];
            
        case 'thank_you':
            return [$groomName];
            
        default:
            return [$groomName];
    }
}

function formatArabicDate($date) {
    if (!$date) return '';
    $months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',
               7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
    $ts = strtotime($date);
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function logWhatsAppMessage($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_messages_log 
            (booking_id, template_id, recipient_phone, stage, status, whatsapp_message_id, error_message, sent_at)
            VALUES (:booking_id, :template_id, :phone, :stage, :status, :message_id, :error, NOW())
        ");
        $stmt->execute([
            ':booking_id' => $data['booking_id'],
            ':template_id' => $data['template_id'],
            ':phone' => $data['phone'],
            ':stage' => $data['stage'],
            ':status' => $data['status'],
            ':message_id' => $data['message_id'],
            ':error' => $data['error']
        ]);
    } catch (Exception $e) {
        error_log("WhatsApp Log Error: " . $e->getMessage());
    }
}

function updateBookingStageIfNeeded($pdo, $bookingId, $templateStage) {
    $stageOrder = [
        'new_booking' => 1, 'coordination' => 2, 'team_assigned' => 3,
        'guidelines_sent' => 4, 'reminder_sent' => 5, 'wedding_day' => 6,
        'processing' => 7, 'delivered' => 8, 'review_requested' => 9, 'closed' => 10
    ];
    
    $stmt = $pdo->prepare("SELECT stage FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $bookingId]);
    $current = $stmt->fetchColumn();
    
    $currentOrder = $stageOrder[$current] ?? 0;
    $newOrder = $stageOrder[$templateStage] ?? 0;
    
    if ($newOrder > $currentOrder) {
        $pdo->prepare("UPDATE bookings SET stage = :stage, stage_updated_at = NOW() WHERE id = :id")
            ->execute([':stage' => $templateStage, ':id' => $bookingId]);
            
        // تسجيل في سجل المراحل
        $pdo->prepare("
            INSERT INTO booking_stage_log (booking_id, from_stage, to_stage, changed_by, change_type, whatsapp_sent)
            VALUES (:id, :from, :to, 'system', 'auto', 1)
        ")->execute([':id' => $bookingId, ':from' => $current, ':to' => $templateStage]);
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
