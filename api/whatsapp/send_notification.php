<?php
/**
 * ============================================
 * إرسال إشعار جاهزية الصور - جذلة
 * ============================================
 * 
 * الملف: api/whatsapp/send_notification.php
 * الوظيفة: إرسال إشعار للضيوف عند جاهزية صور الزفاف
 * 
 * الاستخدام:
 * POST /api/whatsapp/send_notification.php
 * 
 * المعاملات:
 * - phone: رقم الهاتف
 * - groom_name: اسم العريس
 * - page_url: رابط صفحة الصور
 * 
 * أو للإرسال المجمع:
 * - phones: مصفوفة من الأرقام
 * - groom_name: اسم العريس
 * - page_url: رابط صفحة الصور
 */

// منع الوصول المباشر بدون POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// تعريف الثابت للسماح بتضمين ملف الإعدادات
define('JADHLAH_APP', true);

// تضمين الملفات المطلوبة
require_once __DIR__ . '/WhatsAppClient.php';

// إعداد الـ Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// قراءة البيانات المرسلة
$input = json_decode(file_get_contents('php://input'), true);

// إذا لم تكن JSON، جرب POST العادي
if (empty($input)) {
    $input = $_POST;
}

// التحقق من البيانات المطلوبة
$groomName = $input['groom_name'] ?? '';
$pageUrl = $input['page_url'] ?? '';
$singlePhone = $input['phone'] ?? '';
$multiplePhones = $input['phones'] ?? [];

// التحقق من الحقول الإلزامية
if (empty($groomName)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'اسم العريس مطلوب (groom_name)'
    ]);
    exit;
}

if (empty($pageUrl)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'رابط الصفحة مطلوب (page_url)'
    ]);
    exit;
}

if (empty($singlePhone) && empty($multiplePhones)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'رقم الهاتف مطلوب (phone أو phones)'
    ]);
    exit;
}

// إنشاء عميل WhatsApp
$whatsapp = new WhatsAppClient();

// تحديد الأرقام للإرسال
$phones = !empty($singlePhone) ? [$singlePhone] : $multiplePhones;

// نتائج الإرسال
$results = [
    'success' => true,
    'total' => count($phones),
    'sent' => 0,
    'failed' => 0,
    'details' => []
];

// إرسال لكل رقم
foreach ($phones as $phone) {
    $phone = trim($phone);
    
    if (empty($phone)) {
        continue;
    }
    
    $response = $whatsapp->sendPhotosReadyNotification($phone, $groomName, $pageUrl);
    
    if ($response['success']) {
        $results['sent']++;
        $results['details'][] = [
            'phone' => $phone,
            'status' => 'sent',
            'message_id' => $response['message_id']
        ];
    } else {
        $results['failed']++;
        $results['details'][] = [
            'phone' => $phone,
            'status' => 'failed',
            'error' => $response['error']
        ];
    }
    
    // تأخير بسيط بين الرسائل لتجنب Rate Limiting
    if (count($phones) > 1) {
        usleep(100000); // 0.1 ثانية
    }
}

// تحديد نجاح العملية الكلي
$results['success'] = $results['failed'] === 0;

// إرجاع النتيجة
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
