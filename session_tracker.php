<?php
// session_tracker.php
// ملف تتبع الزوار - يتم تضمينه في بداية groom.php
// ضع هذا الكود في بداية ملف groom.php الموجود في جذر الموقع

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين ملف التكوين من مجلد admin
require_once __DIR__ . '/admin/config.php';

// جلب معرف العريس من الرابط
$groomId = isset($_GET['groom']) ? (int)$_GET['groom'] : null;

if ($groomId && $groomId > 0) {
    try {
        // معلومات الزائر
        $sessionId = session_id();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $currentPage = $_SERVER['REQUEST_URI'] ?? '/groom.php';
        
        // تحديد نوع الجهاز
        $deviceType = 'Desktop';
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
            $deviceType = 'Mobile';
        }
        
        // تحديد المتصفح
        $browser = 'Unknown';
        if (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'SamsungBrowser') !== false) {
            $browser = 'Samsung';
        } elseif (strpos($userAgent, 'Opera') !== false || strpos($userAgent, 'OPR') !== false) {
            $browser = 'Opera';
        } elseif (strpos($userAgent, 'Trident') !== false) {
            $browser = 'IE';
        } elseif (strpos($userAgent, 'Edge') !== false || strpos($userAgent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        }
        
        // تحديد النظام
        $platform = 'Unknown';
        if (preg_match('/linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $platform = 'Mac';
        } elseif (preg_match('/windows|win32/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/android/i', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $platform = 'iOS';
        }
        
        // تسجيل أو تحديث الجلسة
        $stmt = $pdo->prepare("
            INSERT INTO sessions (
                session_id, 
                groom_id, 
                page, 
                ip_address, 
                user_agent,
                device_type, 
                browser, 
                platform,
                page_views, 
                last_activity,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW()
            ) ON DUPLICATE KEY UPDATE
                groom_id = VALUES(groom_id),
                page = VALUES(page),
                page_views = page_views + 1,
                last_activity = NOW()
        ");
        
        $stmt->execute([
            $sessionId, 
            $groomId, 
            $currentPage,
            $ipAddress, 
            $userAgent, 
            $deviceType, 
            $browser,
            $platform
        ]);
        
        // تحديث عداد المشاهدات للعريس
        $stmt = $pdo->prepare("
            UPDATE grooms 
            SET page_views = page_views + 1 
            WHERE id = ?
        ");
        $stmt->execute([$groomId]);
        
        // حساب وتحديث الإحصائيات
        try {
            $pdo->exec("CALL calculate_groom_stats($groomId)");
        } catch (Exception $e) {
            // تجاهل خطأ الإحصائيات
        }
        
    } catch (Exception $e) {
        // تسجيل الخطأ دون إيقاف عرض الصفحة
        error_log("خطأ في تتبع الزائر: " . $e->getMessage());
    }
}

// متغير للتحقق من أن التتبع تم بنجاح
$trackingEnabled = true;
?>