<?php
// admin/get_rating_token.php
session_start();

// التحقق من تسجيل الدخول
if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$groomId = intval($_GET['groom_id'] ?? 0);

if ($groomId <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف غير صالح']);
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
    
    // التحقق من وجود توكن أو إنشاء واحد جديد
    $stmt = $pdo->prepare("SELECT rating_token FROM grooms WHERE id = ?");
    $stmt->execute([$groomId]);
    $token = $stmt->fetchColumn();
    
    if (empty($token)) {
        // إنشاء توكن جديد
        $token = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("UPDATE grooms SET rating_token = ? WHERE id = ?");
        $stmt->execute([$token, $groomId]);
    }
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'groom_name' => $groom['groom_name']
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_rating_token.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'خطأ في قاعدة البيانات']);
}
?>