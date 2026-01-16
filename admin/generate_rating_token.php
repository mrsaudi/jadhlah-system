<?php
// admin/generate_rating_token.php
session_start();

// التحقق من تسجيل الدخول
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$groomId = intval($_GET['groom_id'] ?? 0);

if ($groomId <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف العريس غير صالح']);
    exit;
}

try {
    // التحقق من وجود العريس
    $stmt = $pdo->prepare("SELECT id, groom_name FROM grooms WHERE id = ?");
    $stmt->execute([$groomId]);
    $groom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$groom) {
        echo json_encode(['success' => false, 'error' => 'العريس غير موجود']);
        exit;
    }
    
    // توليد token جديد
    $token = bin2hex(random_bytes(32));
    
    // صلاحية 30 يوم
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // حفظ في قاعدة البيانات
    $stmt = $pdo->prepare("
        INSERT INTO rating_tokens (groom_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$groomId, $token, $expiresAt]);
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'groom_name' => $groom['groom_name'],
        'groom_id' => $groom['id']
    ]);
    
} catch (PDOException $e) {
    error_log("Error in generate_rating_token.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'خطأ في قاعدة البيانات']);
}