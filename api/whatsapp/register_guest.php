<?php
/**
 * ============================================
 * تسجيل الضيوف لإشعارات الواتساب
 * ============================================
 * 
 * الملف: api/whatsapp/register_guest.php
 * الوظيفة: حفظ بيانات الضيوف في قاعدة البيانات
 * 
 * POST /api/whatsapp/register_guest.php
 * 
 * المعاملات:
 * - wedding_id: معرف الزفاف
 * - guest_name: اسم الضيف
 * - phone_number: رقم الهاتف (مع كود الدولة)
 */

// إعداد الـ Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// التعامل مع طلبات OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// منع الوصول بغير POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ============================================
// إعدادات قاعدة البيانات
// ============================================
// ⚠️ عدّل هذه الإعدادات حسب موقعك
require_once '../config/database.php';

// $dbConfig = [
//     'host' => 'localhost',
//     'database' => 'jadhla_db', // غيّر لاسم قاعدة بياناتك
//     'username' => 'root',       // غيّر لاسم المستخدم
//     'password' => '',           // غيّر لكلمة المرور
//     'charset' => 'utf8mb4'
// ];

// ============================================
// قراءة البيانات
// ============================================

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input)) {
    $input = $_POST;
}

$weddingId = trim($input['wedding_id'] ?? '');
$guestName = trim($input['guest_name'] ?? '');
$phoneNumber = trim($input['phone_number'] ?? '');

// ============================================
// التحقق من البيانات
// ============================================

$errors = [];

if (empty($weddingId)) {
    $errors[] = 'معرف الزفاف مطلوب';
}

if (empty($guestName)) {
    $errors[] = 'الاسم مطلوب';
}

if (strlen($guestName) < 2) {
    $errors[] = 'الاسم قصير جداً';
}

if (empty($phoneNumber)) {
    $errors[] = 'رقم الهاتف مطلوب';
}

// التحقق من صحة الرقم السعودي
$phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
if (!preg_match('/^966[5][0-9]{8}$/', $phoneNumber)) {
    $errors[] = 'رقم الهاتف غير صحيح';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => implode('، ', $errors)
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الاتصال بقاعدة البيانات'
    ]);
    // تسجيل الخطأ (لا تظهره للمستخدم)
    error_log('Database connection error: ' . $e->getMessage());
    exit;
}

// ============================================
// التحقق من عدم التكرار
// ============================================

try {
    $checkSql = "SELECT id FROM whatsapp_subscriptions 
                 WHERE wedding_id = ? AND phone_number = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$weddingId, $phoneNumber]);
    
    if ($checkStmt->fetch()) {
        // الرقم مسجل مسبقاً - نعتبرها نجاح
        echo json_encode([
            'success' => true,
            'message' => 'رقمك مسجل مسبقاً، سنرسل لك إشعار عند جاهزية الصور'
        ]);
        exit;
    }
} catch (PDOException $e) {
    // نتجاهل الخطأ ونحاول الإدراج
}

// ============================================
// إدراج البيانات
// ============================================

try {
    $sql = "INSERT INTO whatsapp_subscriptions 
            (wedding_id, guest_name, phone_number, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $weddingId,
        $guestName,
        $phoneNumber,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم التسجيل بنجاح! سنرسل لك إشعار عند جاهزية الصور',
            'subscription_id' => $pdo->lastInsertId()
        ]);
    } else {
        throw new Exception('فشل الإدراج');
    }
    
} catch (PDOException $e) {
    // التحقق من خطأ التكرار
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode([
            'success' => true,
            'message' => 'رقمك مسجل مسبقاً، سنرسل لك إشعار عند جاهزية الصور'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'حدث خطأ أثناء التسجيل، يرجى المحاولة لاحقاً'
        ]);
        error_log('Insert error: ' . $e->getMessage());
    }
}
