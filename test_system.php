<?php
// test_system.php - سكريبت اختبار شامل
require_once 'config/database.php';
// 🔧 الترقيع
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
echo "<h1>🧪 اختبار المنظومة الكاملة</h1>";
echo "<hr>";

// 1. اختبار الاتصال بقاعدة البيانات
echo "<h2>1️⃣ قاعدة البيانات</h2>";
if ($conn) {
    echo "✅ الاتصال ناجح<br>";
    
    // التحقق من الجداول
    $tables = [
        'grooms', 'pending_grooms', 'groom_reviews',
        'rating_tokens', 'visitor_rating_popups',
        'notification_subscribers', 'notification_logs',
        'live_gallery_photos', 'active_events'
    ];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) as c FROM $table")->fetch_assoc()['c'];
            echo "✅ $table ($count سجل)<br>";
        } else {
            echo "❌ $table - غير موجود<br>";
        }
    }
} else {
    echo "❌ فشل الاتصال<br>";
}
echo "<hr>";

// 2. اختبار مجلدات FTP
echo "<h2>2️⃣ مجلدات FTP</h2>";
$ftpDirs = [
    '/home/u709146392/domains/jadhlah.com/ftp/live/',
    '/home/u709146392/domains/jadhlah.com/ftp/archive/',
    '/home/u709146392/domains/jadhlah.com/ftp/processed/'
];

foreach ($ftpDirs as $dir) {
    if (is_dir($dir)) {
        $files = count(glob($dir . '*'));
        echo "✅ $dir ($files ملف)<br>";
    } else {
        echo "❌ $dir - غير موجود<br>";
    }
}
echo "<hr>";

// 3. اختبار مجلدات الويب
echo "<h2>3️⃣ مجلدات الويب</h2>";
$webDirs = [
    '/home/u709146392/domains/jadhlah.com/public_html/uploads/live/',
    '/home/u709146392/domains/jadhlah.com/public_html/grooms/',
    '/home/u709146392/domains/jadhlah.com/public_html/assets/js/'
];

foreach ($webDirs as $dir) {
    if (is_dir($dir)) {
        $files = count(glob($dir . '*'));
        $writable = is_writable($dir) ? "قابل للكتابة" : "للقراءة فقط";
        echo "✅ $dir ($files ملف، $writable)<br>";
    } else {
        echo "❌ $dir - غير موجود<br>";
    }
}
echo "<hr>";

// 4. اختبار الملفات المطلوبة
echo "<h2>4️⃣ الملفات المطلوبة</h2>";
$requiredFiles = [
    'landing.php',
    'rate.php',
    'live-gallery.php',
    'admin/generate_rating_link.php',
    'api/subscribe_push.php',
    'api/subscribe_sms.php',
    'api/submit_rating.php',
    'scripts/ftp_watcher.php',
    'assets/js/rating-popup.js'
];

foreach ($requiredFiles as $file) {
    $fullPath = '/home/u709146392/domains/jadhlah.com/public_html/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        echo "✅ $file (" . number_format($size/1024, 2) . " KB)<br>";
    } else {
        echo "❌ $file - غير موجود<br>";
    }
}
echo "<hr>";

// 5. اختبار العرسان المنتظرين
echo "<h2>5️⃣ العرسان المنتظرين (اليوم + أمس)</h2>";
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stmt = $conn->prepare("
    SELECT COUNT(*) as c FROM pending_grooms 
    WHERE booking_date IN (?, ?) AND is_deleted = 0
");
$stmt->bind_param("ss", $yesterday, $today);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['c'];

if ($count > 0) {
    echo "✅ $count عريس منتظر<br>";
    
    // عرض التفاصيل
    $stmt = $conn->prepare("
        SELECT groom_name, booking_date, location 
        FROM pending_grooms 
        WHERE booking_date IN (?, ?) AND is_deleted = 0
    ");
    $stmt->bind_param("ss", $yesterday, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        echo "&nbsp;&nbsp;• {$row['groom_name']} - {$row['booking_date']} - {$row['location']}<br>";
    }
} else {
    echo "⚠️ لا يوجد عرسان منتظرين اليوم<br>";
}
echo "<hr>";

// 6. اختبار الصور الحية
echo "<h2>6️⃣ الصور الحية</h2>";
$livePhotos = $conn->query("
    SELECT COUNT(*) as c FROM live_gallery_photos 
    WHERE is_expired = 0 AND expires_at > NOW()
")->fetch_assoc()['c'];

echo $livePhotos > 0 ? "✅ $livePhotos صورة حية<br>" : "⚠️ لا توجد صور حية<br>";
echo "<hr>";

// 7. اختبار التقييمات
echo "<h2>7️⃣ التقييمات</h2>";
$reviews = $conn->query("SELECT COUNT(*) as c FROM groom_reviews")->fetch_assoc()['c'];
$tokens = $conn->query("SELECT COUNT(*) as c FROM rating_tokens")->fetch_assoc()['c'];
echo "✅ $reviews تقييم<br>";
echo "✅ $tokens رابط تقييم<br>";
echo "<hr>";

// 8. اختبار الإشعارات
echo "<h2>8️⃣ الإشعارات</h2>";
$subscribers = $conn->query("
    SELECT COUNT(*) as c FROM notification_subscribers WHERE is_active = 1
")->fetch_assoc()['c'];
echo $subscribers > 0 ? "✅ $subscribers مشترك<br>" : "⚠️ لا يوجد مشتركين<br>";
echo "<hr>";

// 9. معلومات السيرفر
echo "<h2>9️⃣ معلومات السيرفر</h2>";
echo "PHP: " . phpversion() . "<br>";
echo "MySQL: " . $conn->server_info . "<br>";
echo "الوقت الحالي: " . date('Y-m-d H:i:s') . "<br>";
echo "المنطقة الزمنية: " . date_default_timezone_get() . "<br>";

// إغلاق الاتصال
$conn->close();

echo "<hr>";
echo "<h2>✅ انتهى الاختبار</h2>";
?>