<?php
/**
 * سكريبت الإصلاح - تشغيله مرة واحدة فقط
 * يصلح مشكلة التمييز التلقائي للصور
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("يجب تسجيل الدخول أولاً");
}

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("خطأ في الاتصال: " . $e->getMessage());
}

$log = [];

// الخطوة 1: التأكد من وجود جميع الإعدادات
$log[] = "🔍 فحص الإعدادات...";

$settingsToAdd = [
    'auto_feature_photos' => '0',
    'auto_show_grooms' => '0', 
    'auto_show_videos' => '0'
];

foreach ($settingsToAdd as $key => $defaultValue) {
    $stmt = $pdo->prepare("SELECT setting_value FROM gallery_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->prepare("INSERT INTO gallery_settings (setting_key, setting_value) VALUES (?, ?)")
            ->execute([$key, $defaultValue]);
        $log[] = "✅ تم إضافة إعداد: $key = $defaultValue";
    } else {
        $log[] = "ℹ️ إعداد موجود: $key = {$exists['setting_value']}";
    }
}

// الخطوة 2: جلب قيمة الإعداد الحالي
$stmt = $pdo->query("SELECT setting_value FROM gallery_settings WHERE setting_key = 'auto_feature_photos'");
$autoFeature = $stmt->fetch();
$autoFeatureEnabled = ($autoFeature['setting_value'] ?? '0') == '1';

$log[] = "";
$log[] = "⚙️ حالة التمييز التلقائي: " . ($autoFeatureEnabled ? "مفعّل ✅" : "معطّل ❌");
$log[] = "";

// الخطوة 3: إذا كان التمييز التلقائي معطّل، نلغي تمييز الصور الجديدة
if (!$autoFeatureEnabled) {
    $log[] = "🔧 إلغاء تمييز الصور التلقائي حسب الإعدادات...";
    
    // حساب عدد الصور المميزة حالياً
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM groom_photos WHERE is_featured = 1 AND hidden = 0");
    $currentCount = $countStmt->fetch()['total'];
    
    $log[] = "📊 عدد الصور المميزة حالياً: $currentCount";
    
    // خيار 1: إلغاء تمييز جميع الصور (لإعادة الاختيار من الصفر)
    if (isset($_GET['reset_all'])) {
        $pdo->exec("UPDATE groom_photos SET is_featured = 0");
        $log[] = "✅ تم إلغاء تمييز جميع الصور ($currentCount صورة)";
        $log[] = "👉 يمكنك الآن اختيار الصور المميزة يدوياً من قسم 'تمييز صور'";
    } else {
        $log[] = "ℹ️ الصور المميزة الحالية ستبقى كما هي";
        $log[] = "ℹ️ الصور الجديدة التي يتم تمييزها في صفحات العرسان ستظهر في قسم 'تمييز صور' وتحتاج موافقتك";
        $log[] = "";
        $log[] = "⚠️ إذا أردت إعادة اختيار جميع الصور من الصفر:";
        $log[] = "   <a href='?reset_all=1' style='color: #FFD700; text-decoration: underline;'>اضغط هنا لإلغاء تمييز جميع الصور</a>";
    }
} else {
    $log[] = "✅ التمييز التلقائي مفعّل - الصور تُميّز تلقائياً";
}

$log[] = "";
$log[] = "✅ انتهى الإصلاح بنجاح!";
$log[] = "🔙 <a href='gallery_admin_complete.php' style='color: #FFD700; text-decoration: underline;'>العودة للوحة التحكم</a>";

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إصلاح نظام التمييز التلقائي</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, 'Tajawal', sans-serif;
            background: #000;
            color: white;
            padding: 40px 20px;
            line-height: 1.8;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #1A1A1A;
            padding: 40px;
            border-radius: 15px;
            border: 2px solid #FFD700;
        }
        h1 {
            color: #FFD700;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }
        .log {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 215, 0, 0.2);
        }
        .log-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .log-item:last-child {
            border-bottom: none;
        }
        a {
            color: #FFD700;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .success { color: #10B981; }
        .warning { color: #F59E0B; }
        .info { color: #3B82F6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 سكريبت إصلاح نظام التمييز التلقائي</h1>
        <div class="log">
            <?php foreach ($log as $item): ?>
            <div class="log-item"><?= $item ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>