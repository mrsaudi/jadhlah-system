<?php
// track_visitor.php - تتبع الزوار في الموقع
session_start();

// تضمين ملف التكوين
require_once __DIR__ . '/admin/config.php';

// الحصول على معلومات الزائر
$sessionId = session_id();
$page = $_SERVER['REQUEST_URI'] ?? '/';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// تحديد معرف العريس إن وجد
$groomId = null;
if (isset($_GET['groom']) && is_numeric($_GET['groom'])) {
    $groomId = (int)$_GET['groom'];
}

// تحديد نوع الجهاز
function getDeviceType($userAgent) {
    if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/i', $userAgent)) {
        return 'Mobile';
    } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
        return 'Tablet';
    }
    return 'Desktop';
}

// تحديد المتصفح
function getBrowser($userAgent) {
    if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
    if (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edge/i', $userAgent)) return 'Chrome';
    if (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) return 'Safari';
    if (preg_match('/Edge/i', $userAgent)) return 'Edge';
    if (preg_match('/Opera|OPR/i', $userAgent)) return 'Opera';
    return 'Other';
}

// الحصول على معلومات الموقع الجغرافي (اختياري)
function getLocationInfo($ip) {
    // يمكنك استخدام خدمة GeoIP هنا
    // مؤقتاً نعيد بيانات افتراضية
    return [
        'country' => 'السعودية',
        'city' => 'جدة'
    ];
}

try {
    $deviceType = getDeviceType($userAgent);
    $browser = getBrowser($userAgent);
    $location = getLocationInfo($ipAddress);
    
    // حفظ أو تحديث الجلسة
    $stmt = $pdo->prepare("
        INSERT INTO sessions (session_id, page, groom_id, last_activity, ip_address, user_agent, country, city, device_type, browser)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            page = VALUES(page),
            groom_id = VALUES(groom_id),
            last_activity = NOW(),
            device_type = VALUES(device_type),
            browser = VALUES(browser)
    ");
    
    $stmt->execute([
        $sessionId,
        $page,
        $groomId,
        $ipAddress,
        $userAgent,
        $location['country'],
        $location['city'],
        $deviceType,
        $browser
    ]);
    
    // إرجاع حالة النجاح
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
    
} catch (Exception $e) {
    error_log("Error tracking visitor: " . $e->getMessage());
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
    }
}
?>