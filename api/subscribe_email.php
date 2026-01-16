<?php
// api/subscribe_email.php - تسجيل إيميل للإشعارات
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("فشل الاتصال بقاعدة البيانات");
    }
    $conn->set_charset("utf8mb4");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة غير مسموحة');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['groom_id']) || !isset($input['email'])) {
        throw new Exception('بيانات ناقصة');
    }
    
    $groomId = intval($input['groom_id']);
    $email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        throw new Exception('بريد إلكتروني غير صحيح');
    }
    
    // التحقق من وجود اشتراك مسبق
    $checkSub = $conn->prepare("SELECT id, is_notified FROM email_subscriptions WHERE groom_id = ? AND email = ?");
    $checkSub->bind_param("is", $groomId, $email);
    $checkSub->execute();
    $subResult = $checkSub->get_result();
    
    if ($subResult->num_rows > 0) {
        $existingSub = $subResult->fetch_assoc();
        
        if ($existingSub['is_notified'] == 1) {
            echo json_encode([
                'success' => true,
                'message' => 'تم إرسال الإشعار بالفعل لهذا البريد'
            ]);
            exit;
        } else {
            $updateStmt = $conn->prepare("UPDATE email_subscriptions SET is_active = 1, created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->bind_param("i", $existingSub['id']);
            $updateStmt->execute();
        }
    } else {
        $insertStmt = $conn->prepare("INSERT INTO email_subscriptions (groom_id, email, is_active) VALUES (?, ?, 1)");
        $insertStmt->bind_param("is", $groomId, $email);
        $insertStmt->execute();
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم التسجيل بنجاح! سنرسل لك إشعار عند جهوزية الصور'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>