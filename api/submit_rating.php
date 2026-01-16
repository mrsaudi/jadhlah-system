<?php
// api/submit_rating.php - نسخة محدثة
require_once '../config/database.php';

// الاتصال بقاعدة البيانات
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات']);
    exit;
}
$conn->set_charset("utf8mb4");

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة طلب غير صالحة']);
    exit;
}

// قراءة البيانات
$groomId = isset($_POST['groom_id']) ? intval($_POST['groom_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$name = isset($_POST['name']) && !empty(trim($_POST['name'])) ? trim($_POST['name']) : 'زائر';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// تنظيف البيانات
$name = $conn->real_escape_string($name);
$message = $conn->real_escape_string($message);

// التحقق من صحة البيانات
if ($groomId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'معرف العريس غير صالح']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'التقييم يجب أن يكون بين 1 و 5']);
    exit;
}

// التحقق من وجود العريس
$checkStmt = $conn->prepare("SELECT id FROM grooms WHERE id = ?");
$checkStmt->bind_param("i", $groomId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'العريس غير موجود']);
    exit;
}

// حفظ التقييم
$stmt = $conn->prepare("
    INSERT INTO groom_reviews (groom_id, name, rating, message, is_approved, created_at) 
    VALUES (?, ?, ?, ?, 0, NOW())
");

$stmt->bind_param("isis", $groomId, $name, $rating, $message);

if ($stmt->execute()) {
    $reviewId = $stmt->insert_id;
    
    // تسجيل في جدول التتبع (إذا كان موجوداً)
    session_start();
    $sessionId = session_id();
    
    $trackStmt = $conn->prepare("
        UPDATE visitor_rating_popups 
        SET rated = 1, rated_at = NOW() 
        WHERE session_id = ? AND groom_id = ?
        LIMIT 1
    ");
    
    if ($trackStmt) {
        $trackStmt->bind_param("si", $sessionId, $groomId);
        $trackStmt->execute();
    }
    
    // إرسال استجابة ناجحة
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'review_id' => $reviewId,
        'message' => 'تم استلام تقييمك بنجاح. شكراً لك!'
    ]);
    
} else {
    // خطأ في الإدراج
    error_log("Error inserting rating: " . $stmt->error);
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'فشل حفظ التقييم. يرجى المحاولة مرة أخرى'
    ]);
}

$stmt->close();
$conn->close();