<?php
/**
 * ============================================
 * إرسال إشعارات مجمعة للضيوف المسجلين
 * ============================================
 * 
 * الملف: api/whatsapp/send_bulk_notifications.php
 * الوظيفة: إرسال إشعار جاهزية الصور لكل الضيوف المسجلين لزفاف معين
 * 
 * POST /api/whatsapp/send_bulk_notifications.php
 * 
 * المعاملات:
 * - wedding_id: معرف الزفاف
 * - groom_name: اسم العريس والعروس
 * - page_url: رابط صفحة الصور
 * - api_key: مفتاح الأمان (اختياري)
 */

// إعداد الـ Headers
header('Content-Type: application/json; charset=utf-8');

// منع الوصول بغير POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

define('JADHLAH_APP', true);
require_once __DIR__ . '/WhatsAppClient.php';

// ============================================
// إعدادات قاعدة البيانات
// ============================================

$dbConfig = [
    'host' => 'localhost',
    'database' => 'jadhla_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// مفتاح الأمان (غيّره!)
$API_KEY = 'your_secret_api_key_here';

// ============================================
// قراءة البيانات
// ============================================

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

$weddingId = trim($input['wedding_id'] ?? '');
$groomName = trim($input['groom_name'] ?? '');
$pageUrl = trim($input['page_url'] ?? '');
$apiKey = trim($input['api_key'] ?? '');

// ============================================
// التحقق من الأمان (اختياري)
// ============================================

// أزل التعليق إذا أردت تفعيل التحقق من المفتاح
// if ($apiKey !== $API_KEY) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//     exit;
// }

// ============================================
// التحقق من البيانات
// ============================================

if (empty($weddingId) || empty($groomName) || empty($pageUrl)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'جميع الحقول مطلوبة: wedding_id, groom_name, page_url'
    ]);
    exit;
}

// ============================================
// الاتصال بقاعدة البيانات
// ============================================

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات']);
    exit;
}

// ============================================
// جلب الضيوف المسجلين الذين لم يُرسل لهم
// ============================================

$sql = "SELECT id, guest_name, phone_number 
        FROM whatsapp_subscriptions 
        WHERE wedding_id = ? 
          AND is_active = 1 
          AND notification_sent = 0";

$stmt = $pdo->prepare($sql);
$stmt->execute([$weddingId]);
$guests = $stmt->fetchAll();

if (empty($guests)) {
    echo json_encode([
        'success' => true,
        'message' => 'لا يوجد ضيوف جدد للإرسال',
        'total' => 0,
        'sent' => 0,
        'failed' => 0
    ]);
    exit;
}

// ============================================
// إرسال الإشعارات
// ============================================

$whatsapp = new WhatsAppClient();

$results = [
    'success' => true,
    'total' => count($guests),
    'sent' => 0,
    'failed' => 0,
    'details' => []
];

$updateSql = "UPDATE whatsapp_subscriptions 
              SET notification_sent = 1, 
                  notification_sent_at = NOW(),
                  message_id = ?
              WHERE id = ?";
$updateStmt = $pdo->prepare($updateSql);

foreach ($guests as $guest) {
    $response = $whatsapp->sendPhotosReadyNotification(
        $guest['phone_number'],
        $groomName,
        $pageUrl
    );
    
    if ($response['success']) {
        $results['sent']++;
        
        // تحديث حالة الإرسال
        $updateStmt->execute([
            $response['message_id'],
            $guest['id']
        ]);
        
        $results['details'][] = [
            'name' => $guest['guest_name'],
            'phone' => $guest['phone_number'],
            'status' => 'sent',
            'message_id' => $response['message_id']
        ];
        
        // تسجيل في جدول السجل
        logMessage($pdo, $guest, $groomName, $pageUrl, $response, $weddingId);
        
    } else {
        $results['failed']++;
        $results['details'][] = [
            'name' => $guest['guest_name'],
            'phone' => $guest['phone_number'],
            'status' => 'failed',
            'error' => $response['error']
        ];
    }
    
    // تأخير بين الرسائل
    usleep(200000); // 0.2 ثانية
}

$results['success'] = $results['failed'] === 0;

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ============================================
// دالة تسجيل الرسالة
// ============================================

function logMessage($pdo, $guest, $groomName, $pageUrl, $response, $weddingId) {
    try {
        $sql = "INSERT INTO whatsapp_messages_log 
                (message_type, template_name, recipient_phone, recipient_name, 
                 template_params, status, whatsapp_message_id, wedding_id, sent_at)
                VALUES ('template', 'grooms_ready', ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $guest['phone_number'],
            $guest['guest_name'],
            json_encode(['groom_name' => $groomName, 'page_url' => $pageUrl]),
            $response['success'] ? 'sent' : 'failed',
            $response['message_id'] ?? null,
            $weddingId
        ]);
    } catch (PDOException $e) {
        // تجاهل أخطاء التسجيل
    }
}
