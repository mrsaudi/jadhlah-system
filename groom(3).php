<?php
// ===== بداية كود التتبع الجديد =====

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

// ===== نهاية كود التتبع الجديد =====

ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تضمين ملفات النظام
require_once __DIR__ . '/admin/session_tracker.php';
require_once __DIR__ . '/admin/config.php';

// التحقق من صحة معرف العريس
$groomId = filter_input(INPUT_GET, 'groom', FILTER_VALIDATE_INT);
if (!$groomId || $groomId <= 0) {
    http_response_code(400);
    die('❌ معرف العريس غير صحيح.');
}

require_once 'track_visitor.php';

// معالجة إرسال التقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    try {
        // التحقق من وجود البيانات المطلوبة
        $name = trim($_POST['review_name'] ?? '');
        $rating = filter_input(INPUT_POST, 'review_rating', FILTER_VALIDATE_INT);
        $message = trim($_POST['review_message'] ?? '');
        
        // التحقق من صحة البيانات
        if (empty($name) || !$rating || empty($message) || $rating < 1 || $rating > 5) {
            throw new Exception('جميع الحقول مطلوبة والتقييم يجب أن يكون من 1 إلى 5');
        }
        
        // حماية من التكرار (نفس الاسم خلال 10 دقائق)
        $duplicateCheck = $pdo->prepare("
            SELECT id FROM groom_reviews 
            WHERE groom_id = ? AND name = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $duplicateCheck->execute([$groomId, $name]);
        
        if ($duplicateCheck->fetch()) {
            throw new Exception('لقد قمت بإرسال تقييم مؤخراً. يرجى الانتظار قبل إرسال تقييم آخر.');
        }
        
        // إدراج التقييم
        $stmt = $pdo->prepare("
            INSERT INTO groom_reviews (groom_id, name, rating, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$groomId, $name, $rating, $message]);
        
        // إعادة توجيه لمنع إعادة الإرسال
        header("Location: groom.php?groom=$groomId&review=success");
        exit;
        
    } catch (Exception $e) {
        $reviewError = $e->getMessage();
    }
}

// جلب بيانات العريس مع التحقق من الوجود والتفعيل
try {
    $stmt = $pdo->prepare("
        SELECT * FROM grooms 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$groomId]);
    $groomData = $stmt->fetch();
    
    if (!$groomData) {
        http_response_code(404);
        die("❌ هذه الصفحة غير موجودة أو غير متاحة.");
    }
    
} catch (PDOException $e) {
    error_log("خطأ في قاعدة البيانات: " . $e->getMessage());
    http_response_code(500);
    die("❌ حدث خطأ في الخادم.");
}

// حساب عداد الأيام المتبقية
$startDate = !empty($groomData['ready_at']) 
    ? new DateTime($groomData['ready_at'])
    : new DateTime($groomData['created_at']);

$now = new DateTime();
$diff = $now->diff($startDate);
$daysElapsed = $diff->days;
$maxDays = 90;
$daysLeft = max(0, $maxDays - $daysElapsed);

// إذا انتهى العداد، إظهار رسالة وإيقاف التنفيذ
if ($daysLeft === 0) {
    http_response_code(410); // Gone
    include __DIR__ . '/templates/expired_page.php';
    exit;
}

// زيادة عداد الزيارات بشكل آمن
try {
    $pdo->prepare("UPDATE grooms SET page_views = page_views + 1 WHERE id = ?")->execute([$groomId]);
} catch (PDOException $e) {
    error_log("خطأ في تحديث عداد الزيارات: " . $e->getMessage());
}

// جلب الصور مع التحقق من الأخطاء
try {
    $stmt = $pdo->prepare("
        SELECT id, filename, likes, views, is_featured
        FROM groom_photos
        WHERE groom_id = ? AND hidden = 0
        ORDER BY is_featured DESC, likes DESC, id ASC
    ");
    $stmt->execute([$groomId]);
    $photos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في جلب الصور: " . $e->getMessage());
    $photos = [];
}

// جلب التقييمات المعتمدة
try {
    $stmt = $pdo->prepare("
        SELECT name, message, rating, created_at
        FROM groom_reviews
        WHERE groom_id = ? AND is_approved = 1
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$groomId]);
    $approvedReviews = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في جلب التقييمات: " . $e->getMessage());
    $approvedReviews = [];
}

// إعداد متغيرات العرض
$groomName = htmlspecialchars($groomData['groom_name']);
$eventDate = !empty($groomData['wedding_date']) ? htmlspecialchars($groomData['wedding_date']) : '';
$pageURL = "https://jadhlah.com/groom.php?groom=$groomId";
$bannerURL = !empty($groomData['banner']) 
    ? "https://jadhlah.com/grooms/$groomId/modal_thumb/banner.jpg"
    : "https://jadhlah.com/assets/default-banner.jpg";
$description = "شاهد أجمل لقطات زواج $groomName" . 
    ($eventDate ? " بتاريخ $eventDate" : '') . " من تصوير جذلة";

// إعداد قائمة الصور للتحميل
$photosList = [];
foreach ($photos as $photo) {
    $photosList[] = $photo['filename'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>حفل <?= $groomName ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
    <link rel="manifest" href="/assets/site.webmanifest">
    <meta name="theme-color" content="#ffc107">

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <meta name="keywords" content="زواج, حفل زواج, تصوير, جذلة, <?= $groomName ?>">
    <meta name="author" content="جذلة للتصوير">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="زواج <?= $groomName ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($bannerURL) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($pageURL) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="جذلة">
    
    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="زواج <?= $groomName ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($bannerURL) ?>">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Lateef&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">
    
    <!-- External Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <!-- File Download Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    
    <style>
    /* The Year of The Camel Font */
/* Light (100-300) */
@font-face {
    font-family: 'The Year of The Camel';
    font-weight: 100 200 300;
    src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtTGlnaHQub3RmMTcwNDYyMzYzODQwNQ==.otf');
}
/* Regular (400-600) */
@font-face {
    font-family: 'The Year of The Camel';
    font-weight: 400 500 600;
    src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtUmVndWxhci5vdGYxNzA0NjIzMjM0MzE5.otf');
}
/* Bold (700-900) */
@font-face {
    font-family: 'The Year of The Camel';
    font-weight: 700 800 900;
    src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtQm9sZC5vdGYxNzA0NjIzNjY3ODA1.otf');
}

        /* Base Styles */
        body { 
            margin: 0; 
            font-family: 'Tajawal', sans-serif; 
            background: #f7f7f7; 
            color: #222; 
        }
        html {
    scroll-behavior: smooth;
}

/* Smooth transitions */
* {
    transition: color 0.3s ease, background-color 0.3s ease;
}

        
      /* Hero Banner Enhanced */
.hero-banner-section {
    position: relative;
    width: 100%;
    overflow: hidden;
}

.banner-wrapper {
    position: relative;
    width: 100%;
    height: 70vh; /* 50% من ارتفاع الشاشة للكمبيوتر */
    overflow: hidden;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
}

.banner {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.banner-gradient {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #ffc107, #ff8c00);
    display: flex;
    align-items: center;
    justify-content: center;
}

.banner-gradient h2 {
    color: white;
    font-size: 48px;
    text-align: center;
}

/* Banner Overlay for better text visibility */
.banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, 
        rgba(0,0,0,0.3) 0%, 
        rgba(0,0,0,0.1) 50%, 
        rgba(0,0,0,0.6) 100%);
    pointer-events: none;
}

/* Logo on Top Right of Banner */
.banner-logo-container {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 10;
    animation: fadeInDown 1s ease;
}

.banner-top-logo {
    width: 120px;
    height: auto;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
}

/* Banner Content */
.banner-content {
    position: absolute;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    z-index: 5;
    animation: fadeInUp 1s ease;
}

.banner-title {
    font-size: 48px;
    font-family: 'The Year of The Camel', 'Lateef', cursive;
    font-weight: 600;
    color: white;
    margin: 0;
    text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
}

.banner-date {
    color: rgba(255,255,255,0.9);
    font-size: 18px;
    margin-top: 10px;
    text-shadow: 1px 1px 4px rgba(0,0,0,0.5);
}
/* Premium SVG Shape Divider - محافظة على الأصل */
.custom-shape-divider-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    overflow: hidden;
    line-height: 0;
    transform: rotate(180deg);
}

.custom-shape-divider-bottom svg {
    position: relative;
    display: block;
    width: calc(100% + 1.3px);
    height: 100px;
}

.custom-shape-divider-bottom .shape-fill {
    fill: #FFFFFF;
}

/* Smart Navbar - تصميم زجاجي داكن */
.navbar {
    position: fixed;
    top: 0;
    width: 100%;
    background: rgba(20, 20, 20, 0.85); /* خلفية داكنة شفافة */
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow: 0 2px 15px rgba(0,0,0,0.3);
    z-index: 1000;
    transform: translateY(-100%);
    transition: transform 0.3s ease;
    /*border-bottom: 1px solid rgba(255,255,255,0.1);*/
}

.navbar.visible {
    transform: translateY(0);
}

.navbar-container {
    max-width: 100%;
    margin: 0 auto;
    padding: 15px 20px; /* حجم أكبر */
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.navbar .logo {
    height: 50px; /* حجم أكبر للشعار */
    width: auto;
}

.navbar .logo {
    height: 40px;
    width: auto;
    transition: filter 0.3s ease;
}

.nav-buttons {
    display: flex;
    gap: 25px;
    align-items: center;
}

.nav-btn {
    color: #ffffff; /* نص أبيض */
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 20px;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.nav-btn:hover {
    background: rgba(255,255,255,0.1);
    color: #ffc107;
}
.nav-btn:hover {
    background: rgba(255,191,0,0.15);
    color: #ff8c00;
}

/* Banner Logo - بدون خلفية */
.banner-logo-container {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 10;
    animation: fadeInDown 1s ease;
}

.banner-top-logo {
    width: 120px;
    height: auto;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.3));
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .navbar {
        background: #1a1a1a;
    }
    
    .navbar-container {
        padding: 12px 15px;
    }
    
    .navbar .logo {
        height: 40px;
    }
    
    .nav-buttons {
        gap: 15px;
    }
    
    .nav-btn {
        font-size: 14px;
        padding: 6px 12px;
    }
}


/* Animations */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .banner-wrapper {
        height: 80vh; /* 80% من ارتفاع الشاشة للجوال */
    }
    
    .banner-title {
        font-size: 36px;
    }
    
    .banner-top-logo {
        width: 80px;
    }
    
    .custom-shape-divider-bottom svg {
        height: 60px;
    }
}

        .info {
            padding: 30px 20px 20px;
            text-align: center;
            background: #fff;
        }
        
.groom-name {
    margin: 0;
    font-size: 42px;
    font-family: 'The Year of The Camel', 'Lateef', cursive;
    font-weight: 600;
            color: #333;
            line-height: 1.3;
        }
        .groom-name {
    background: linear-gradient(135deg, #FFD700, #FFA500, #FFD700);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: goldShine 3s ease infinite;
}

@keyframes goldShine {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
        
        .wedding-date {
            color: #888;
            font-size: 16px;
            margin: 8px 0;
        }
        
        .groom-notes {
            font-size: 15px;
            color: #555;
            margin-top: 10px;
            white-space: pre-line;
        }
        
 /* Gallery Mode Toggle - Fixed */
.gallery-controls {
    position: sticky;
    top: 0;
    z-index: 200;
    background: white;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.mode-toggle {
    display: flex;
    background: #f0f0f0;
    border-radius: 25px;
    padding: 4px;
    gap: 4px;
    max-width: 200px;
    margin: 0 auto;
}

.mode-btn {
    flex: 1;
    padding: 8px 16px;
    border: none;
    background: transparent;
    border-radius: 20px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    transition: all 0.3s ease;
}

.mode-btn.active {
    background: white;
    color: #333;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Gallery Container */
.instagram-gallery {
    position: relative;
    max-width: 1100px;
    margin: 0 auto;
    min-height: 500px;
}

/* Grid Mode */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    background: #fff;
}

.gallery-grid.hidden {
    display: none;
}

/* Single Mode Container - Instagram Feed Style */
.gallery-single {
    display: none;
    max-width: 600px;
    margin: 0 auto;
    padding: 0;
}

.gallery-single.active {
    display: block;
}

/* Remove navigation arrows */
.single-nav {
    display: none;
}

/* Single Photo Items */
.single-photo-item {
    margin-bottom: 20px;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.single-photo-item img {
    width: 100%;
    height: auto;
    display: block;
    max-height: 80vh;
    object-fit: contain;
    background: #f8f8f8;
}

.single-photo-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: white;
}

.action-buttons {
    display: flex;
    gap: 20px;
}

.action-btn {
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #666;
    transition: all 0.2s;
}

.action-btn:hover {
    transform: scale(1.1);
}

.action-btn.liked {
    color: #e91e63;
}

/* Photo Items in Grid */
.grid-photo-item {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    cursor: pointer;
    background: #f0f0f0;
}

.grid-photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.grid-photo-item:hover img {
    transform: scale(1.05);
}

.photo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    opacity: 0;
    transition: all 0.3s ease;
}

.grid-photo-item:hover .photo-overlay {
    background: rgba(0,0,0,0.3);
    opacity: 1;
}

/* Single Photo View */
.single-photo-container {
    max-width: 700px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.single-photo-container img {
    width: 100%;
    height: auto;
    max-height: 70vh;
    object-fit: contain;
    display: block;
    background: #f8f8f8;
}

.single-photo-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #f0f0f0;
}

.action-buttons {
    display: flex;
    gap: 25px;
}

.action-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #666;
    transition: all 0.2s;
}

.action-btn:hover {
    transform: scale(1.15);
}

.action-btn.liked {
    color: #e91e63;
}

/* Navigation arrows for single mode */
.single-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    margin-bottom: 10px;
}

.nav-arrow {
    background: #f0f0f0;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.nav-arrow:hover {
    background: #e0e0e0;
}

.nav-arrow:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.photo-counter {
    font-size: 14px;
    color: #666;
}

/* Swipe hint */
.swipe-hint {
    position: fixed;
    bottom: 100px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 14px;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
    z-index: 300;
}

.swipe-hint.show {
    opacity: 1;
}

@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1px;
    }
    
    .single-photo-container {
        border-radius: 0;
    }
}




/*جديد*/
.image-placeholder {
    width: 500px;
    max-width: 90%;
    aspect-ratio: 1;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 8px;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.heart-burst {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0);
    font-size: 120px;
    color: white;
    pointer-events: none;
    opacity: 0;
    filter: drop-shadow(0 0 10px rgba(255,255,255,0.5));
}

.heart-burst.active {
    animation: heartPop 0.8s cubic-bezier(0.28, 0.84, 0.42, 1);
}

@keyframes heartPop {
    0% {
        transform: translate(-50%, -50%) scale(0) rotate(-45deg);
        opacity: 0;
    }
    15% {
        transform: translate(-50%, -50%) scale(1.3) rotate(-45deg);
        opacity: 0.9;
    }
    30% {
        transform: translate(-50%, -50%) scale(0.95) rotate(-45deg);
        opacity: 1;
    }
    45% {
        transform: translate(-50%, -50%) scale(1.05) rotate(-45deg);
        opacity: 1;
    }
    80% {
        transform: translate(-50%, -50%) scale(1) rotate(-45deg);
        opacity: 1;
    }
    100% {
        transform: translate(-50%, -50%) scale(1) rotate(-45deg);
        opacity: 0;
    }
}

.instagram-modal-actions {
    position: absolute;
    bottom: -35px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
    padding: 15px 30px;
    border-radius: 30px;
    color: white;
    display: flex;
    align-items: center;
    gap: 25px;
}

.instagram-modal-action {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 16px;
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    transition: transform 0.2s ease;
    text-decoration: none;
}

.instagram-modal-action:hover {
    transform: scale(1.1);
}

@media (max-width: 768px) {
    .instagram-modal-item {
        padding: 60px 10px;
    }
    
    .instagram-modal-image {
        /*max-height: 60vh;*/
    }
}
        /* YouTube Section */
        .youtube-wrapper {
            display: flex;
            flex-direction: column;
            gap: 30px;
            padding: 20px 0;
            align-items: center;
        }
        
        .youtube-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            width: 75%;
            transition: transform 0.3s ease;
        }
        
        .youtube-card:hover {
            transform: translateY(-4px);
        }
        
        .youtube-card iframe {
            width: 100%;
            height: 315px;
            border: none;
            display: block;
        }
        
        /* Stats Section */
        .info-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 40px 0;
            flex-wrap: wrap;
        }
        
        .stat-box {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    padding: 20px 30px;
    text-align: center;
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
    transition: transform 0.3s ease;
    min-width: 140px;
}
        
        .stat-box:hover {
            transform: translateY(-4px);
        }
        
        .stat-label {
            font-size: 14px;
            color: #888;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }
        
        /* Review Section */
        .review-form-wrapper {
            max-width: 70%;
            margin: 40px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
        }
        
        .review-title {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            font-size: 30px;
            cursor: pointer;
            margin: 10px 0;
        }
        
        .star-rating span {
            color: #ccc;
            transition: color 0.2s;
        }
        
        .star-rating span:hover,
        .star-rating span:hover ~ span {
            color: #f5b301;
        }
        
        .star-rating .selected,
        .star-rating .selected ~ span {
            color: #f5b301;
        }
        
        .review-form label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #555;
            font-size: 15px;
        }
        
        .review-form input[type="text"],
        .review-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .review-form button {
            margin-top: 20px;
            padding: 12px 25px;
            background: #ffbf00;
            color: #000;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            font-weight: bold;
        }
        
        .review-form button:hover {
            background: #e6ac00;
        }
        
        .review-success {
            background: #e0ffe0;
            color: #226622;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .review-error {
            background: #ffe0e0;
            color: #662222;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        /* Download Section */
        .download-section {
            background: linear-gradient(135deg, #f9fafb 0%, #f0f0f0 100%);
            padding: 60px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .download-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            max-width: 450px;
            margin: 0 auto;
            padding: 40px 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,191,0,0.2);
        }
        
        .download-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ffbf00 0%, #ff8c00 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: white;
            box-shadow: 0 10px 30px rgba(255,191,0,0.3);
        }
        
        .btn-gradient {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: #fff;
            font-size: 18px;
            font-weight: 600;
            padding: 16px 32px;
            border: none;
            border-radius: 50px;
            box-shadow: 0 8px 25px rgba(255,140,0,0.3);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .btn-gradient:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(255,140,0,0.4);
        }
/* Ripple Effect */
.btn-gradient {
    position: relative;
    overflow: hidden;
}

.btn-gradient::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-gradient:active::before {
    width: 300px;
    height: 300px;
}
        /* Promo Section */
        .promo-section {
            text-align: center;
            padding: 60px 20px;
            background: #fafafa;
        }

        .promo-logo {
            width: 200px;
            margin-bottom: 20px;
        }

        .promo-text {
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
            font-weight: 500;
            font-family: 'The Year of The Camel', 'Tajawal', sans-serif;
            line-height: 1.4;
        }

        .promo-btn {
            display: inline-block;
            background: #25D366;
            color: white;
            padding: 12px 24px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            transition: background 0.3s, transform 0.3s;
        }

        .promo-btn:hover {
            background: #1ebc59;
            transform: translateY(-2px);
            color: white;
        }
        
        /* Social Section */
        .social-section {
            padding: 60px 20px;
            text-align: center;
            background: #f7f7f7;
        }

        .social-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 25px;
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .social-link {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #eaeaea;
            transition: background 0.3s, transform 0.3s;
            text-decoration: none;
        }

        .social-link img {
            width: 24px;
            height: 24px;
            filter: brightness(0.3);
            transition: filter 0.3s;
        }

        .social-link:hover {
            transform: scale(1.1);
        }

        .social-link:hover img {
            filter: brightness(0) saturate(100%) sepia(100%) hue-rotate(20deg) brightness(1.1);
        }

        .social-link.tiktok:hover    { background: #000; }
        .social-link.instagram:hover { background: #E1306C; }
        .social-link.snapchat:hover  { background: #FFFC00; }
        .social-link.x:hover         { background: #1DA1F2; }
        
        /* Instagram Section */
        .instagram-section {
            padding: 60px 20px;
            background: #fff;
            text-align: center;
        }
        
        .instagram-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 30px;
        }
        
        /* Copy Section */
        .copy-section {
            text-align: center;
            margin: 50px 0 20px;
            padding: 20px;
        }

        .copy-btn {
            padding: 10px 22px;
            background: #ffbf00;
            border: none;
            border-radius: 999px;
            font-size: 14px;
            font-weight: bold;
            color: #000;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .copy-btn:hover {
            background: #e0a900;
            transform: translateY(-1px);
        }

        .copy-msg {
            margin-top: 10px;
            font-size: 14px;
            color: green;
            display: none;
            font-weight: bold;
        }
        
        /* Site Footer */
        .site-footer {
            background: #1e1e1e;
            color: #ccc;
            padding: 40px 20px;
            text-align: center;
        }

        .footer-logo {
            width: 120px;
            margin-bottom: 15px;
            filter: brightness(0.8);
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .footer-link {
            color: #ccc;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            padding: 5px 10px;
        }

        .footer-link:hover {
            color: #ffbf00;
        }

        .footer-copy {
            font-size: 13px;
            color: #888;
            margin: 0;
        }
        
        /* Floating Elements */
        .whatsapp-float {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 46px;
            height: 46px;
            background: #25D366;
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            z-index: 999;
            transition: background 0.3s, transform 0.3s;
        }
        
        .whatsapp-float:hover {
            background: #1ebe5d;
            transform: scale(1.08);
        }
        
        .floating-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 50%;
            background: rgba(50, 50, 50, 0.6);
            backdrop-filter: blur(4px);
            color: white;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            transition: background 0.3s ease, transform 0.3s ease;
        }
        
        .floating-btn:hover {
            background: rgba(70, 70, 70, 0.8);
            transform: scale(1.08);
        }
        
        /* Toast */
        .toast {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 16px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 10000;
            font-weight: bold;
        }
        
        .toast.show {
            opacity: 1;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .instagram-gallery {
                gap: 1px;
            }
            
            .instagram-modal-nav {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .instagram-modal-nav.prev {
                left: 10px;
            }
            
            .instagram-modal-nav.next {
                right: 10px;
            }
            
            .instagram-modal-info {
                bottom: 10px;
                padding: 10px 20px;
                gap: 15px;
            }
            
            .youtube-card {
                width: 95%;
            }
            
            .review-form-wrapper {
                max-width: 95%;
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .instagram-photo-item .photo-overlay {
                opacity: 1;
                background: none;
            }
            
            .photo-overlay-stat {
                display: none;
            }
            
            .groom-name {
                font-size: 28px;
            }
        }
          /* Welcome Modal - محسّن */
        .welcome-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 5000;
            backdrop-filter: blur(10px);
        }
        .welcome-content {
            background: linear-gradient(135deg, #fff 0%, #f5f5f5 100%);
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            position: relative;
            max-width: 350px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease-out;
        }
        .welcome-close {
            position: absolute;
            top: 12px; right: 12px;
            background: rgba(0,0,0,0.1);
            border: none;
            font-size: 18px;
            cursor: pointer;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            transition: all 0.3s;
        }
        .welcome-close:hover {
            background: rgba(0,0,0,0.2);
            transform: rotate(90deg);
        }
        .welcome-logo {
            width: 120px;
            margin-bottom: 20px;
        }
        .welcome-text {
            font-size: 18px;
            color: #333;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        /* Loading Progress - جديد */
        .loading-progress {
            width: 100%;
            margin-top: 20px;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(0,0,0,0.1);
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }
       .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ffbf00, #ff8c00);
    border-radius: 3px;
    width: 0%;
    transition: width 0.3s ease;
    box-shadow: 0 0 10px rgba(255,191,0,0.5);
    position: relative;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
            color: #666;
        }
/* Banner Logo Enhancement */
.banner-logo-container {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 10;
    animation: fadeInDown 1s ease;
    /*background: rgba(255,255,255,0.1);*/
    padding: 10px;
    border-radius: 10px;
    backdrop-filter: blur(5px);
}

@media (max-width: 768px) {
    .banner-logo-container {
        top: 10px;
        right: 10px;
        padding: 5px;
    }
}
.info .groom-name {
    margin: 30px 0 10px;
    font-size: 48px;
    text-align: center;
}

@media (max-width: 768px) {
    .info .groom-name {
        font-size: 36px;
    }
}

.custom-shape-divider-bottom svg {
    pointer-events: none;
}

/* إزالة الخط الأسود بعد الـ shape divider */
.navbar {
    border-bottom: none !important; /* إزالة الحد تماماً */
}

/* أو إذا كنت تريد الإبقاء عليه مع الـ navbar الظاهر فقط */
.navbar:not(.visible) {
    border-bottom-color: transparent !important;
}

/* تأكد من عدم وجود فراغ بين الـ divider والمحتوى */
.custom-shape-divider-bottom {
    bottom: -1px; /* تحريك pixel واحد للأسفل */
}

.info {
    margin-top: -2px; /* سحب المحتوى للأعلى قليلاً */
    position: relative;
    z-index: 1;
}
.instagram-gallery.single-mode {
    padding: 0 20px;
    max-width: 700px;
}


    </style>
</head>
<body>
<!-- Navigation -->
<header class="navbar">
    <div class="navbar-container">
        <a href="/index.php" aria-label="الصفحة الرئيسية">
            <img src="/assets/black_logo_jadhlah_t.svg" alt="شعار جذلة" class="logo">
        </a>
        <nav class="nav-buttons" role="navigation">
            <a href="/index.php" class="nav-btn">الرئيسية</a>
            <a href="https://wa.me/966544705859" target="_blank" class="nav-btn" rel="noopener">تواصل معنا</a>
        </nav>
    </div>
</header>

  <!-- Hero Banner Section -->
<div class="hero-banner-section">
    <div class="banner-wrapper">
<!-- Logo Overlay -->
<div class="banner-logo-container">
    <img src="/assets/black_logo_jadhlah_t.svg" alt="شعار جذلة" class="banner-top-logo">
</div>
        
        <?php if (!empty($groomData['banner'])): ?>
            <img src="/grooms/<?= $groomId ?>/modal_thumb/banner.jpg?v=<?= time() ?>" 
                 class="banner" alt="بنر حفل <?= $groomName ?>" loading="eager">
            <div class="banner-overlay"></div>
        <?php else: ?>
            <div class="banner-gradient">
                <h2><?= $groomName ?></h2>
            </div>
        <?php endif; ?>
        

    </div>
    
    <!-- Premium SVG Wave Divider -->
    <div class="custom-shape-divider-bottom">
        <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" class="shape-fill"></path>
            <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" class="shape-fill"></path>
            <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" class="shape-fill"></path>
        </svg>
    </div>
</div>
    <!-- Main Content -->
    <main>
</div>
<!-- اسم العريس بعد البانر -->
<section class="info">
    <h1 class="groom-name"><?= $groomName ?></h1>
    <?php if ($eventDate): ?>
        <p class="wedding-date"><?= $eventDate ?></p>
    <?php endif; ?>
</section>
<!-- Event Notes Only -->
<?php if (!empty($groomData['notes'])): ?>
<section class="info fade-in-up">
    <p class="groom-notes"><?= nl2br(htmlspecialchars($groomData['notes'])) ?></p>
</section>
<?php endif; ?>

        <!-- YouTube Videos -->
        <?php if (!empty(array_filter([$groomData['youtube1'], $groomData['youtube2'], $groomData['youtube3'], $groomData['youtube4'], $groomData['youtube5'], $groomData['youtube6'], $groomData['youtube7']]))): ?>
            <section class="youtube-wrapper" aria-label="فيديوهات الحفل">
                <?php
                for ($i = 1; $i <= 7; $i++) {
                    $field = "youtube$i";
                    $link = $groomData[$field] ?? '';
                    if (!empty($link)) {
                        $videoId = '';
                        if (preg_match('/(?:v=|youtu\.be\/|embed\/|shorts\/|v\/)([A-Za-z0-9_-]+)/', $link, $matches)) {
                            $videoId = $matches[1];
                        }

                        if ($videoId) {
                            echo "
                            <div class='youtube-card fade-in-up'>
                                <iframe src='https://www.youtube.com/embed/$videoId'
                                        title='فيديو الحفل $i'
                                        allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                                        allowfullscreen loading='lazy'></iframe>
                            </div>";
                        }
                    }
                }
                ?>
            </section>
            
        <?php endif; ?>

<style>
.skeleton-loader {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    max-width: 1100px;
    margin: 0 auto;
}
.skeleton-item {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    aspect-ratio: 1;
}
@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
<div class="skeleton-loader" id="skeletonLoader" style="display:none;">
    <div class="skeleton-item"></div>
    <div class="skeleton-item"></div>
    <div class="skeleton-item"></div>
    <div class="skeleton-item"></div>
    <div class="skeleton-item"></div>
    <div class="skeleton-item"></div>
</div>

        <!-- Instagram Style Photo Gallery -->
<!-- Instagram Style Photo Gallery -->
<?php if (!empty($photos)): ?>
    <!-- Gallery Controls -->
    <div class="gallery-controls">
        <div class="mode-toggle">
            <button class="mode-btn active" data-mode="grid">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                </svg>
            </button>
            <button class="mode-btn" data-mode="single">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18"/>
                </svg>
            </button>
        </div>
    </div>
    
    <section class="instagram-gallery" id="photoGallery">
        <!-- Grid View -->
        <div class="gallery-grid" id="gridView">
            <?php foreach ($photos as $index => $photo): ?>
                <div class="grid-photo-item" data-index="<?= $index ?>">
                    <img src="/grooms/<?= $groomId ?>/thumbs/<?= htmlspecialchars($photo['filename']) ?>" 
                         alt="صورة <?= $index + 1 ?>" loading="lazy">
                    <div class="photo-overlay">
                        <span>❤️ <?= $photo['likes'] ?></span>
                        <span>👁️ <?= $photo['views'] ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Single View -->
        <div class="gallery-single" id="singleView">
            <div class="single-nav">
                <button class="nav-arrow" id="prevPhoto">◀</button>
                <span class="photo-counter">
                    <span id="currentNum">1</span> / <?= count($photos) ?>
                </span>
                <button class="nav-arrow" id="nextPhoto">▶</button>
            </div>
            <div class="single-photo-container">
                <div id="singlePhotoWrapper"></div>
            </div>
        </div>
    </section>
    
    <div class="swipe-hint" id="swipeHint">
        ← Swipe للتنقل →
    </div>
<?php endif; ?>


        <!-- Promo Section -->
        <section class="promo-section">
            <img src="/assets/whiti_logo_jadhlah_t.svg" alt="شعار جذلة" class="promo-logo">
            <h2 class="promo-text">لأن القلب يعيش هذه الليلة ألف مرة بعدستنا</h2>
            <a href="https://wa.me/966544705859" target="_blank" class="promo-btn" rel="noopener">تواصل معنا عبر واتساب</a>
        </section>

        <!-- Social Media Section -->
        <section class="social-section">
            <h3 class="social-title">تابعنا على السوشال ميديا</h3>
            <div class="social-icons">
                <a href="https://www.tiktok.com/@jadhlah" target="_blank" class="social-link tiktok" aria-label="TikTok" rel="noopener">
                    <img src="/assets/icons/tiktok.svg" alt="TikTok">
                </a>
                <a href="https://www.instagram.com/jadhlah" target="_blank" class="social-link instagram" aria-label="Instagram" rel="noopener">
                    <img src="/assets/icons/instagram.svg" alt="Instagram">
                </a>
                <a href="https://www.snapchat.com/add/vmp.pro" target="_blank" class="social-link snapchat" aria-label="Snapchat" rel="noopener">
                    <img src="/assets/icons/snapchat.svg" alt="Snapchat">
                </a>
                <a href="https://x.com/jadhlah" target="_blank" class="social-link x" aria-label="X" rel="noopener">
                    <img src="/assets/icons/x.svg" alt="X">
                </a>
            </div>
        </section>
        
        <!-- Statistics -->
        <section class="info-stats">
            <div class="stat-box">
                <div class="stat-icon">
                    <svg width="28" height="28" fill="#444" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M1.293 12.707a1 1 0 0 1 0-1.414C3.84 8.746 7.655 6 12 6s8.16 2.746 10.707 5.293a1 1 0 0 1 0 1.414C20.16 15.254 16.345 18 12 18s-8.16-2.746-10.707-5.293Zm10.707 3.293a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
                    </svg>
                </div>
                <div class="stat-label">زيارات الصفحة</div>
                <div class="stat-value"><?= number_format($groomData['page_views']) ?></div>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <svg width="28" height="28" fill="#444" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M6 2a1 1 0 1 0 0 2h.243A6.97 6.97 0 0 0 9 10c0 1.726-.628 3.3-1.657 4.5A6.97 6.97 0 0 0 6.243 20H6a1 1 0 1 0 0 2h12a1 1 0 1 0 0-2h-.243a6.97 6.97 0 0 0-1.1-5.5A6.97 6.97 0 0 0 15 10c0-1.726.628-3.3 1.657-4.5A6.97 6.97 0 0 0 17.757 4H18a1 1 0 1 0 0-2H6Z"/>
                    </svg>
                </div>
                <div class="stat-label">يبقى لإغلاق الصفحة</div>
                <div class="stat-value"><?= $daysLeft ?> يوم<?= $daysLeft !== 1 ? 'اً' : '' ?></div>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <svg width="28" height="28" fill="#444" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="stat-label">عدد الصور</div>
                <div class="stat-value"><?= count($photos) ?></div>
            </div>
        </section>

        <!-- Download Section -->
        <section class="download-section">
            <div class="download-card">
                <div class="download-icon">📦</div>
                <h5 class="card-title">أرشف صور حفل <?= htmlspecialchars($groomData['groom_name']) ?></h5>
                <p class="card-text">احصل على جميع الصور الأصلية بجودة عالية في ملف واحد</p>
                <button id="downloadAllBtn" class="btn-gradient">
                    <span>⬇️</span>
                    تحميل جميع الصور
                </button>
            </div>
        </section>

        <!-- Reviews Display -->
        <?php if (!empty($approvedReviews)): ?>
            <section style="max-width:800px;margin:40px auto;padding:20px;" aria-label="تقييمات العملاء">
                <h3 style="text-align:center;color:#444;">قالوا عن جذلة</h3>
                
                <div class="swiper mySwiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($approvedReviews as $review): ?>
                            <div class="swiper-slide" style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);max-width:300px;">
                                <div style="font-size:16px;color:#f5b301;margin-bottom:8px;" aria-label="تقييم <?= $review['rating'] ?> من 5">
                                    <?= str_repeat("⭐", $review['rating']) ?>
                                </div>
                                <div style="color:#333;margin-bottom:10px;"><?= nl2br(htmlspecialchars($review['message'])) ?></div>
                                <div style="text-align:right;font-size:14px;color:#888;">— <?= htmlspecialchars($review['name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-button-next" aria-label="التقييم التالي"></div>
                    <div class="swiper-button-prev" aria-label="التقييم السابق"></div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Review Form -->
        <section class="review-form-wrapper">
            <h3 class="review-title">💬 اترك تقييمك</h3>

            <?php if (isset($_GET['review']) && $_GET['review'] === 'success'): ?>
                <div class="review-success">✅ شكرًا على تقييمك! سيتم مراجعته من قبل الإدارة.</div>
            <?php endif; ?>

            <?php if (isset($reviewError)): ?>
                <div class="review-error">❌ <?= htmlspecialchars($reviewError) ?></div>
            <?php endif; ?>

            <form method="POST" class="review-form" novalidate>
                <label for="review_name">اسمك *</label>
                <input type="text" id="review_name" name="review_name" required maxlength="100"
                       value="<?= htmlspecialchars($_POST['review_name'] ?? '') ?>">

                <label>عدد النجوم *</label>
                <div class="star-rating" role="radiogroup" aria-label="تقييم الخدمة">
                    <span data-value="5" role="radio" tabindex="0" aria-label="5 نجوم">★</span>
                    <span data-value="4" role="radio" tabindex="0" aria-label="4 نجوم">★</span>
                    <span data-value="3" role="radio" tabindex="0" aria-label="3 نجوم">★</span>
                    <span data-value="2" role="radio" tabindex="0" aria-label="نجمتان">★</span>
                    <span data-value="1" role="radio" tabindex="0" aria-label="نجمة واحدة">★</span>
                </div>
                <input type="hidden" id="review_rating" name="review_rating" required>

                <label for="review_message">رسالتك *</label>
                <textarea id="review_message" name="review_message" rows="4" required maxlength="500"><?= htmlspecialchars($_POST['review_message'] ?? '') ?></textarea>

                <button type="submit" name="submit_review">إرسال التقييم</button>
            </form>
        </section>

        <!-- Copy Page Link Section -->
        <section class="copy-section">
            <button onclick="copyPageLink()" class="copy-btn" aria-label="نسخ رابط هذه الصفحة">📎 نسخ رابط هذه الصفحة</button>
            <div id="copy-msg" class="copy-msg">✅ تم نسخ الرابط</div>
        </section>
        
        <!-- Instagram Feed Section -->
        <section class="instagram-section">
            <h3 class="instagram-title">تابعنا على انستغرام</h3>
            <div style="text-align: center; margin-bottom: 20px;">
                <p style="color: #666; font-size: 16px;">شاهد أحدث أعمالنا وكواليس التصوير</p>
            </div>
            <!-- Instagram Embed -->
            <div style="max-width: 600px; margin: 0 auto;">
                <blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/jadhlah/" data-instgrm-version="14" style="background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:540px; min-width:326px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);">
                </blockquote>
                <script async src="//www.instagram.com/embed.js"></script>
            </div>
            <a href="https://www.instagram.com/jadhlah" target="_blank" style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #833AB4, #C13584, #F77737); color: white; padding: 12px 28px; border-radius: 30px; text-decoration: none; font-size: 16px; font-weight: 600; margin-top: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s;">
                <img src="/assets/icons/instagram.svg" alt="Instagram" style="width: 20px; height: 20px; filter: brightness(0) invert(1);">
                زيارة حسابنا على انستغرام
            </a>
        </section>
    </main>
    
    <!-- Site Footer -->
    <footer class="site-footer">
        <div class="footer-content">
            <img src="/assets/black_logo_jadhlah_t.svg" alt="شعار جذلة" class="footer-logo">
            <nav class="footer-links" role="navigation">
                <a href="/index.php" class="footer-link">الرئيسية</a>
                <a href="/about.php" class="footer-link">من نحن</a>
                <a href="https://wa.me/966544705859" target="_blank" class="footer-link" rel="noopener">تواصل معنا</a>
            </nav>
            <p class="footer-copy">© <?= date('Y') ?> جذلة. جميع الحقوق محفوظة.</p>
        </div>
    </footer>
    
    
 <!-- Welcome Modal with Loading -->
    <div id="welcomeModal" class="welcome-modal">
        <div class="welcome-content">
            <button id="welcomeClose" class="welcome-close">✖</button>
            <img src="/assets/whiti_logo_jadhlah_t.svg" alt="شعار جذلة" class="welcome-logo">
            <p class="welcome-text">لقطاتنا تعيش أطول من لحظاتها</p>
            <div class="loading-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text" id="progressText">جاري التحميل... 0%</div>
            </div>
        </div>
    </div>
    
    

    <!-- Instagram Style Modal -->


    <!-- Floating Action Buttons -->
    <a href="https://wa.me/966544705859" target="_blank" class="whatsapp-float" 
       title="تواصل معنا عبر واتساب" rel="noopener" aria-label="واتساب">
        <img src="/assets/icons/whatsapp.png" alt="واتساب" width="22" height="22">
    </a>

    <button id="scrollToggleBtn" class="floating-btn" title="انتقال" aria-label="انتقال سريع">
        <svg id="scrollIcon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 4a1 1 0 0 1 .7.3l6 6a1 1 0 1 1-1.4 1.4L12 6.4 6.7 11.7a1 1 0 0 1-1.4-1.4l6-6A1 1 0 0 1 12 4Z"/>
        </svg>
    </button>

    <!-- Toast Notification -->
    <div id="toast" class="toast" aria-live="polite" aria-atomic="true"></div>

    <!-- JavaScript -->
    <script>
        // Instagram Style Gallery JavaScript
        const groomId = <?= json_encode($groomId) ?>;
        const photosData = <?= json_encode($photos) ?>;
        const photosList = <?= json_encode($photosList) ?>;
        let currentPhotoIndex = 0;
        let likedPhotos = JSON.parse(localStorage.getItem('likedPhotos') || '{}');
        let doubleClickTimer = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Smart Navbar
// Smart Navbar
// Smart Navbar - محسّن للظهور بعد البانر
function initSmartNavbar() {
    const navbar = document.querySelector('.navbar');
    const banner = document.querySelector('.banner-wrapper');
    
    if (!navbar || !banner) return; // تحقق من وجود العناصر
    
    let lastScroll = 0;
    let isNavbarVisible = false;
    
    // إخفاء الـ navbar عند البداية
    navbar.classList.remove('visible');
    navbar.style.transform = 'translateY(-100%)';
    
    function updateNavbar() {
        const currentScroll = window.pageYOffset;
        const bannerHeight = banner.offsetHeight;
        
        // يظهر الـ navbar فقط بعد تجاوز البانر بـ 50px
        if (currentScroll > bannerHeight + 50) {
            if (!isNavbarVisible) {
                navbar.classList.add('visible');
                navbar.style.transform = 'translateY(0)';
                isNavbarVisible = true;
            }
            
            // منطق الإخفاء والإظهار عند التمرير
            if (currentScroll > lastScroll && currentScroll > bannerHeight + 100) {
                // التمرير للأسفل - إخفاء
                navbar.style.transform = 'translateY(-100%)';
            } else {
                // التمرير للأعلى - إظهار
                navbar.style.transform = 'translateY(0)';
            }
        } else {
            // إخفاء تماماً عندما نكون في منطقة البانر
            if (isNavbarVisible) {
                navbar.classList.remove('visible');
                navbar.style.transform = 'translateY(-100%)';
                isNavbarVisible = false;
            }
        }
        
        lastScroll = currentScroll;
    }
    
    // استخدام throttle لتحسين الأداء
    let ticking = false;
    function requestTick() {
        if (!ticking) {
            window.requestAnimationFrame(updateNavbar);
            ticking = true;
            setTimeout(() => { 
                ticking = false; 
            }, 100);
        }
    }
    
    // تشغيل مرة واحدة عند البداية
    updateNavbar();
    
    // إضافة مستمع الحدث للتمرير
    window.addEventListener('scroll', requestTick);
    
    // إضافة مستمع لتغيير حجم النافذة
    window.addEventListener('resize', () => {
        if (!ticking) {
            updateNavbar();
        }
    });
}

// تشغيل الـ function عند تحميل الصفحة
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSmartNavbar);
} else {
    initSmartNavbar();
}



// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSmartNavbar);
} else {
    initSmartNavbar();
}

initSmartNavbar();

// Parallax effect for banner
window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset;
    const banner = document.querySelector('.banner');
    if (banner) {
        banner.style.transform = `translateY(${scrolled * 0.5}px)`;
    }
});
            // Lazy Loading with Intersection Observer
const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src && !img.src.includes('modal_thumb')) {
                img.src = img.dataset.src;
                img.classList.add('fade-in');
                imageObserver.unobserve(img);
            }
        }
    });
}, {
    rootMargin: '50px'
});

// Apply to gallery images
document.querySelectorAll('.instagram-photo-item img').forEach(img => {
    if (!img.complete) {
        img.style.opacity = '0';
        img.addEventListener('load', function() {
            this.style.opacity = '1';
            this.style.transition = 'opacity 0.3s ease';
        });
    }
});

            initializeInstagramGallery();
            initializeDownload();
            initializeDeepLink();
            initializeReviews();
            initializeScrollButton();
            initializeSwiper();
            copyPageLink = copyPageLinkFunc; // Global function
        });
        
function initializeInstagramGallery() {
    let currentMode = 'grid';
    let currentPhotoIndex = 0;
    const gridView = document.getElementById('gridView');
    const singleView = document.getElementById('singleView');
    const singleWrapper = document.getElementById('singlePhotoWrapper');
    
    if (!gridView || !singleView) return;
    
    // Mode toggle
    document.querySelectorAll('.mode-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.dataset.mode;
            if (mode === currentMode) return;
            
            document.querySelectorAll('.mode-btn').forEach(b => {
                b.classList.toggle('active');
            });
            
            if (mode === 'grid') {
                showGridMode();
            } else {
                showSingleMode(currentPhotoIndex);
            }
        });
    });
    
    // Grid photo clicks
    document.querySelectorAll('.grid-photo-item').forEach((item, index) => {
        item.addEventListener('click', () => {
            showSingleMode(index);
        });
    });
    
    // Navigation arrows
    const prevBtn = document.getElementById('prevPhoto');
    const nextBtn = document.getElementById('nextPhoto');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPhotoIndex > 0) {
                showSinglePhoto(currentPhotoIndex - 1);
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentPhotoIndex < photosData.length - 1) {
                showSinglePhoto(currentPhotoIndex + 1);
            }
        });
    }
    
    // Touch/swipe support
    let touchStartX = 0;
    singleView.addEventListener('touchstart', e => {
        touchStartX = e.touches[0].clientX;
    });
    
    singleView.addEventListener('touchend', e => {
        const touchEndX = e.changedTouches[0].clientX;
        const diff = touchStartX - touchEndX;
        
        if (Math.abs(diff) > 50) {
            if (diff > 0 && currentPhotoIndex < photosData.length - 1) {
                showSinglePhoto(currentPhotoIndex + 1);
            } else if (diff < 0 && currentPhotoIndex > 0) {
                showSinglePhoto(currentPhotoIndex - 1);
            }
        }
    });
    
    function showGridMode() {
        currentMode = 'grid';
        gridView.classList.remove('hidden');
        singleView.classList.remove('active');
        document.querySelector('.mode-btn[data-mode="grid"]').classList.add('active');
        document.querySelector('.mode-btn[data-mode="single"]').classList.remove('active');
    }
    
    function showSingleMode(index) {
        currentMode = 'single';
        gridView.classList.add('hidden');
        singleView.classList.add('active');
        document.querySelector('.mode-btn[data-mode="single"]').classList.add('active');
        document.querySelector('.mode-btn[data-mode="grid"]').classList.remove('active');
        showSinglePhoto(index);
        
        // Show swipe hint for mobile
        if (window.innerWidth <= 768) {
            const hint = document.getElementById('swipeHint');
            if (hint) {
                hint.classList.add('show');
                setTimeout(() => hint.classList.remove('show'), 2000);
            }
        }
    }
    
    function showSinglePhoto(index) {
        currentPhotoIndex = index;
        const photo = photosData[index];
        
        // Update counter
        document.getElementById('currentNum').textContent = index + 1;
        
        // Update navigation buttons
        if (prevBtn) prevBtn.disabled = index === 0;
        if (nextBtn) nextBtn.disabled = index === photosData.length - 1;
        
        // Create photo HTML
        singleWrapper.innerHTML = `
            <div class="heart-burst" id="heartBurst">❤️</div>
            <img src="/grooms/${groomId}/modal_thumb/${photo.filename}" 
                 alt="صورة ${index + 1}">
            <div class="single-photo-actions">
                <div class="action-buttons">
                    <button class="action-btn like-btn ${likedPhotos[photo.id] ? 'liked' : ''}" 
                            data-id="${photo.id}">
                        <span>${likedPhotos[photo.id] ? '❤️' : '🤍'}</span>
                        <span>${photo.likes}</span>
                    </button>
                    <span class="action-btn">
                        👁️ <span>${photo.views}</span>
                    </span>
                    <a class="action-btn" 
                       href="/download.php?groom=${groomId}&file=${photo.filename}" 
                       download>⬇️</a>
                </div>
            </div>
        `;
        
        // Setup like button
        const likeBtn = singleWrapper.querySelector('.like-btn');
        if (likeBtn) {
            likeBtn.addEventListener('click', handleLike);
        }
        
        // Record view
        if (typeof recordView === 'function') {
            recordView(photo.id);
        }
    }
    
    function handleLike(e) {
        const btn = e.currentTarget;
        const photoId = btn.dataset.id;
        
        if (!likedPhotos[photoId]) {
            likedPhotos[photoId] = true;
            btn.classList.add('liked');
            btn.querySelector('span:first-child').textContent = '❤️';
            
            const count = btn.querySelector('span:last-child');
            count.textContent = parseInt(count.textContent) + 1;
            
            localStorage.setItem('likedPhotos', JSON.stringify(likedPhotos));
            
            if (typeof recordLike === 'function') {
                recordLike(photoId);
            }
            
            // Animate heart
            const heart = document.getElementById('heartBurst');
            if (heart) {
                heart.classList.add('active');
                setTimeout(() => heart.classList.remove('active'), 800);
            }
        }
    }
}

        
        // Scroll toggle button
        function initializeScrollButton() {
            const scrollBtn = document.getElementById('scrollToggleBtn');
            if (!scrollBtn) return;

            function updateScrollButton() {
                const scrollY = window.scrollY;
                const maxScroll = document.documentElement.scrollHeight - window.innerHeight;

                if (scrollY < maxScroll / 2) {
                    scrollBtn.innerHTML = `
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 20a1 1 0 0 1-.7-.3l-6-6a1 1 0 1 1 1.4-1.4L12 17.6l5.3-5.3a1 1 0 1 1 1.4 1.4l-6 6a1 1 0 0 1-.7.3Z"/>
                        </svg>
                    `;
                    scrollBtn.onclick = () => window.scrollTo({ top: maxScroll, behavior: 'smooth' });
                    scrollBtn.setAttribute('aria-label', 'التمرير للأسفل');
                } else {
                    scrollBtn.innerHTML = `
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 4a1 1 0 0 1 .7.3l6 6a1 1 0 1 1-1.4 1.4L12 6.4 6.7 11.7a1 1 0 0 1-1.4-1.4l6-6A1 1 0 0 1 12 4Z"/>
                        </svg>
                    `;
                    scrollBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
                    scrollBtn.setAttribute('aria-label', 'التمرير للأعلى');
                }
            }

            window.addEventListener('scroll', updateScrollButton);
            updateScrollButton();
        }
        
        // Reviews system
        function initializeReviews() {
            document.querySelectorAll('.star-rating span').forEach(star => {
                star.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    document.getElementById('review_rating').value = value;
                    
                    document.querySelectorAll('.star-rating span').forEach(s => s.classList.remove('selected'));
                    
                    this.classList.add('selected');
                    let next = this.nextElementSibling;
                    while (next) {
                        next.classList.add('selected');
                        next = next.nextElementSibling;
                    }
                });
            });
            // Auto-save review form
const reviewForm = document.querySelector('.review-form');
if (reviewForm) {
    // Save on input
    reviewForm.addEventListener('input', function() {
        const formData = {
            name: document.getElementById('review_name').value,
            rating: document.getElementById('review_rating').value,
            message: document.getElementById('review_message').value
        };
        localStorage.setItem('reviewDraft', JSON.stringify(formData));
    });
    
    // Restore saved data
    const savedData = localStorage.getItem('reviewDraft');
    if (savedData) {
        const data = JSON.parse(savedData);
        document.getElementById('review_name').value = data.name || '';
        document.getElementById('review_message').value = data.message || '';
        if (data.rating) {
            document.getElementById('review_rating').value = data.rating;
            // Update stars visual
            document.querySelectorAll('.star-rating span').forEach(star => {
                if (star.getAttribute('data-value') <= data.rating) {
                    star.classList.add('selected');
                }
            });
        }
    }
    
    // Clear on successful submit
    if (window.location.search.includes('review=success')) {
        localStorage.removeItem('reviewDraft');
    }
}

        }
        
        // Swiper initialization
        function initializeSwiper() {
            if (typeof Swiper !== 'undefined' && document.querySelector('.mySwiper')) {
                new Swiper(".mySwiper", {
                    slidesPerView: 1.2,
                    spaceBetween: 20,
                    navigation: {
                        nextEl: ".swiper-button-next",
                        prevEl: ".swiper-button-prev"
                    },
                    breakpoints: {
                        640: { slidesPerView: 2.2 },
                        1024: { slidesPerView: 3 }
                    }
                });
            }
        }
        
        // Deep linking
        function updateDeepLink() {
            const photo = photosData[currentPhotoIndex];
            const url = new URL(window.location.href);
            url.searchParams.set('photo', photo.id);
            window.history.replaceState({}, '', url.toString());
        }
        
        function clearDeepLink() {
            const url = new URL(window.location.href);
            url.searchParams.delete('photo');
            window.history.replaceState({}, '', url.toString());
        }
        
        function initializeDeepLink() {
            const params = new URLSearchParams(window.location.search);
            const photoId = params.get('photo');
            
            if (photoId) {
                const index = photosData.findIndex(p => p.id == photoId);
                if (index >= 0) {
                    document.querySelectorAll('.instagram-photo-item')[index].click();
                }
            }
        }
        
        // API functions
       function recordView(photoId) {
    // Check if element exists before updating
    const viewElement = document.getElementById('viewCount');
    
    fetch('/reaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `photo_id=${photoId}&action=view`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const photo = photosData.find(p => p.id == photoId);
            if (photo) {
                photo.views = data.count;
                if (viewElement) {
                    viewElement.textContent = data.count;
                }
            }
        }
    })
    .catch(error => console.error('Error recording view:', error));
}

        function recordLike(photoId) {
            fetch('/reaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `photo_id=${photoId}&action=like`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const photo = photosData.find(p => p.id == photoId);
                    if (photo) {
                        photo.likes = data.count;
                    }
                }
            })
            .catch(error => console.error('Error recording like:', error));
        }
        
        // Download functionality
        function initializeDownload() {
            const downloadBtn = document.getElementById('downloadAllBtn');
            if (!downloadBtn || !photosList.length) return;

            downloadBtn.addEventListener('click', async function() {
                if (typeof JSZip === 'undefined' || typeof saveAs === 'undefined') {
                    alert('مكتبات التحميل غير متوفرة. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
                    return;
                }

                this.disabled = true;
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner"></span> جاري التحضير...';

                try {
                    const zip = new JSZip();
                    let processedCount = 0;

                    for (const filename of photosList) {
                        try {
                            const imageUrl = `/grooms/${groomId}/originals/${filename}`;
                            const response = await fetch(imageUrl);
                            
                            if (!response.ok) {
                                console.warn('فشل تحميل:', filename);
                                continue;
                            }

                            const blob = await response.blob();
                            zip.file(filename, blob);
                            processedCount++;

                            const progress = Math.round((processedCount / photosList.length) * 100);
                            this.innerHTML = `<span class="spinner"></span> ${progress}%`;
                        } catch (error) {
                            console.error('خطأ في تحميل الصورة:', filename, error);
                        }
                    }

                    if (processedCount === 0) {
                        throw new Error('لم يتم تحميل أي صور');
                    }

                    this.innerHTML = '<span class="spinner"></span> إنشاء الأرشيف...';
                    const content = await zip.generateAsync(
                        { type: 'blob', compression: 'STORE' },
                        function(metadata) {
                            const progress = Math.round(metadata.percent);
                            downloadBtn.innerHTML = `<span class="spinner"></span> ضغط ${progress}%`;
                        }
                    );

                    const fileName = `groom_${groomId}_photos.zip`;
                    saveAs(content, fileName);

                    this.innerHTML = '✅ تم التحميل!';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 3000);

                } catch (error) {
                    console.error('خطأ في التحميل:', error);
                    this.innerHTML = '❌ فشل التحميل';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 3000);
                }
            });
        }
        
       // Welcome Modal with Page Loading Progress
        document.addEventListener('DOMContentLoaded', () => {
            const welcomeModal = document.getElementById('welcomeModal');
            const closeBtn = document.getElementById('welcomeClose');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            
            // Show modal
            welcomeModal.style.display = 'flex';
            
            // Track loading progress
            let loadedResources = 0;
            const totalResources = document.images.length + document.querySelectorAll('iframe').length;
            
            function updateProgress() {
                loadedResources++;
                const percentage = Math.min(Math.round((loadedResources / totalResources) * 100), 100);
                progressFill.style.width = percentage + '%';
                progressText.textContent = `جاري التحميل... ${percentage}%`;
                
                if (percentage >= 100) {
                    setTimeout(() => {
                        welcomeModal.style.opacity = '0';
                        setTimeout(() => welcomeModal.style.display = 'none', 500);
                    }, 500);
                }
            }
            
            // Track image loading
            Array.from(document.images).forEach(img => {
                if (img.complete) {
                    updateProgress();
                } else {
                    img.addEventListener('load', updateProgress);
                    img.addEventListener('error', updateProgress);
                }
            });
            
            // Track iframe loading
            document.querySelectorAll('iframe').forEach(iframe => {
                iframe.addEventListener('load', updateProgress);
            });
            
            // Manual close
            closeBtn.addEventListener('click', () => {
                welcomeModal.style.opacity = '0';
                setTimeout(() => welcomeModal.style.display = 'none', 500);
            });
            
            // Auto-hide after max time
            setTimeout(() => {
                if (welcomeModal.style.display !== 'none') {
                    welcomeModal.style.opacity = '0';
                    setTimeout(() => welcomeModal.style.display = 'none', 500);
                }
            }, 5000);
        });



        // Copy link function
        function copyPageLinkFunc() {
            const url = window.location.href;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url)
                    .then(() => {
                        showCopyMessage();
                        showToast('✅ تم نسخ الرابط');
                    })
                    .catch(() => fallbackCopyToClipboard(url));
            } else {
                fallbackCopyToClipboard(url);
            }
        }
        
        function fallbackCopyToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopyMessage();
                showToast('✅ تم نسخ الرابط');
            } catch (err) {
                showToast('فشل في نسخ الرابط');
            } finally {
                document.body.removeChild(textArea);
            }
        }
        
        function showCopyMessage() {
            const copyMsg = document.getElementById('copy-msg');
            if (copyMsg) {
                copyMsg.style.display = 'block';
                setTimeout(() => {
                    copyMsg.style.display = 'none';
                }, 2000);
            }
        }
        
        // Show toast message
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
}, 3000);
        }
    </script>
</body>
</html>