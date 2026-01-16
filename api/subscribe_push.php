<?php
// api/subscribe_push.php
ob_start();

try {
    error_reporting(0);
    ini_set('display_errors', 0);
    
    require_once '../config/database.php';
    
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    $conn->set_charset("utf8mb4");
    
    ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($input['groom_id']) || !isset($input['subscription'])) {
        throw new Exception('Missing required fields');
    }
    
    $groomId = intval($input['groom_id']);
    $subscription = $input['subscription'];
    $endpoint = isset($subscription['endpoint']) ? $subscription['endpoint'] : '';
    
    // استخراج auth و p256dh بشكل منفصل
    $auth = isset($subscription['keys']['auth']) ? $subscription['keys']['auth'] : '';
    $p256dh = isset($subscription['keys']['p256dh']) ? $subscription['keys']['p256dh'] : '';
    
    // التحقق من صحة البيانات
if (strlen($auth) < 16 || strlen($p256dh) < 50) {
    throw new Exception('Invalid subscription keys format');
}

// التحقق من Base64
if (!preg_match('/^[A-Za-z0-9_-]+$/', $auth) || !preg_match('/^[A-Za-z0-9_-]+$/', $p256dh)) {
    throw new Exception('Invalid Base64 format in keys');
}
    
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    
    if (empty($endpoint) || empty($auth) || empty($p256dh)) {
        throw new Exception('Invalid subscription data');
    }
    
    // التحقق من وجود اشتراك مسبق
    $checkStmt = $conn->prepare("
        SELECT id FROM push_subscriptions 
        WHERE groom_id = ? AND endpoint = ?
    ");
    
    if (!$checkStmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $checkStmt->bind_param("is", $groomId, $endpoint);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // تحديث موجود
        $updateStmt = $conn->prepare("
            UPDATE push_subscriptions 
            SET auth = ?, 
                p256dh = ?,
                user_agent = ?, 
                ip_address = ?,
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE groom_id = ? AND endpoint = ?
        ");
        
        if (!$updateStmt) {
            throw new Exception('Update prepare failed');
        }
        
        $updateStmt->bind_param("ssssis", $auth, $p256dh, $userAgent, $ipAddress, $groomId, $endpoint);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Update failed: ' . $updateStmt->error);
        }
        
        $updateStmt->close();
        $message = 'تم تحديث الاشتراك بنجاح';
        
    } else {
        // إدراج جديد
        $insertStmt = $conn->prepare("
            INSERT INTO push_subscriptions
            (groom_id, endpoint, auth, p256dh, user_agent, ip_address, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        
        if (!$insertStmt) {
            throw new Exception('Insert prepare failed: ' . $conn->error);
        }
        
        $insertStmt->bind_param("isssss", $groomId, $endpoint, $auth, $p256dh, $userAgent, $ipAddress);
        
        if (!$insertStmt->execute()) {
            throw new Exception('Insert failed: ' . $insertStmt->error);
        }
        
        $insertStmt->close();
        $message = 'تم التسجيل بنجاح';
    }
    
    $checkStmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'debug' => [
            'groom_id' => $groomId,
            'endpoint_length' => strlen($endpoint)
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    error_log("Subscribe Push Error: " . $e->getMessage());
}

ob_end_flush();
?>