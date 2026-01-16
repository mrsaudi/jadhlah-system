<?php
/**
 * ===========================================
 * سكريبت تحويل الصفحات المنتهية إلى خاملة
 * ===========================================
 * 
 * الاستخدام:
 * 1. يدوي: افتح الملف في المتصفح
 * 2. Cron: أضف في cPanel:
 *    */15 * * * * /usr/bin/php /path/to/check_expired_grooms.php
 * 
 * يتم التشغيل كل 15 دقيقة
 */

// تعطيل عرض الأخطاء في الإنتاج
ini_set('display_errors', 0);
error_reporting(E_ALL);

// تضمين ملف التكوين
require_once __DIR__ . '/admin/config.php';

// التحقق من طريقة الوصول
$isCLI = php_sapi_name() === 'cli';
$isAuthorized = $isCLI || (isset($_GET['key']) && $_GET['key'] === 'jadhlah2025');

if (!$isAuthorized) {
    http_response_code(403);
    die('Unauthorized');
}

// بدء التسجيل
$startTime = microtime(true);
$logFile = __DIR__ . '/logs/expired_grooms_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    
    if (!php_sapi_name() === 'cli') {
        echo "<p>[$timestamp] $message</p>";
    }
}

logMessage("=== بدء فحص الصفحات المنتهية ===");

try {
    // 1. جلب الصفحات النشطة المنتهية
    $stmt = $pdo->query("
        SELECT 
            id,
            groom_name,
            created_at,
            ready_at,
            IFNULL(expiry_days, 90) as expiry_days,
            DATEDIFF(NOW(), IFNULL(ready_at, created_at)) as days_elapsed
        FROM grooms
        WHERE is_active = 1 
        AND is_blocked = 0
        AND IFNULL(expiry_days, 90) > 0
        AND DATEDIFF(NOW(), IFNULL(ready_at, created_at)) >= IFNULL(expiry_days, 90)
    ");
    
    $expiredGrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($expiredGrooms);
    
    logMessage("تم العثور على $count صفحة منتهية");
    
    if ($count === 0) {
        logMessage("لا توجد صفحات منتهية للتحديث");
        logMessage("=== انتهى الفحص بنجاح ===");
        exit(0);
    }
    
    // 2. تحويل الصفحات المنتهية إلى خاملة
    $updateStmt = $pdo->prepare("
        UPDATE grooms 
        SET is_active = 0 
        WHERE id = ?
    ");
    
    $updated = 0;
    $failed = 0;
    
    foreach ($expiredGrooms as $groom) {
        try {
            $updateStmt->execute([$groom['id']]);
            
            if ($updateStmt->rowCount() > 0) {
                $updated++;
                logMessage(sprintf(
                    "✅ تم تحويل الصفحة #%d (%s) إلى خاملة - مضى %d يوم من %d يوم",
                    $groom['id'],
                    $groom['groom_name'],
                    $groom['days_elapsed'],
                    $groom['expiry_days']
                ));
            }
            
        } catch (PDOException $e) {
            $failed++;
            logMessage(sprintf(
                "❌ فشل تحويل الصفحة #%d (%s): %s",
                $groom['id'],
                $groom['groom_name'],
                $e->getMessage()
            ));
        }
    }
    
    // 3. إحصائيات النتائج
    $duration = round(microtime(true) - $startTime, 2);
    
    logMessage("=== ملخص النتائج ===");
    logMessage("✅ تم التحويل: $updated صفحة");
    logMessage("❌ فشل: $failed صفحة");
    logMessage("⏱️ المدة: {$duration} ثانية");
    logMessage("=== انتهى الفحص ===");
    
    // 4. إرسال تقرير عبر البريد (اختياري)
    if ($updated > 0) {
        try {
            $adminEmail = 'admin@jadhlah.com'; // غيّر هذا
            $subject = "تقرير الصفحات المنتهية - " . date('Y-m-d');
            $message = "تم تحويل $updated صفحة إلى خاملة\n\n" . file_get_contents($logFile);
            
            // استخدم mail() أو PHPMailer هنا إذا أردت
            // mail($adminEmail, $subject, $message);
            
        } catch (Exception $e) {
            logMessage("⚠️ فشل إرسال التقرير عبر البريد: " . $e->getMessage());
        }
    }
    
    // 5. عرض النتائج في المتصفح
    if (!$isCLI) {
        echo "<hr>";
        echo "<h3>النتائج:</h3>";
        echo "<ul>";
        echo "<li>✅ تم التحويل: <strong>$updated</strong> صفحة</li>";
        echo "<li>❌ فشل: <strong>$failed</strong> صفحة</li>";
        echo "<li>⏱️ المدة: <strong>{$duration}</strong> ثانية</li>";
        echo "</ul>";
        echo "<p><a href='admin/dashboard.php'>العودة للوحة التحكم</a></p>";
    }
    
    exit(0);
    
} catch (Exception $e) {
    logMessage("💥 خطأ فادح: " . $e->getMessage());
    error_log("Check expired grooms error: " . $e->getMessage());
    exit(1);
}

/**
 * ===========================================
 * تعليمات الإعداد في cPanel
 * ===========================================
 * 
 * 1. اذهب إلى: cPanel > Advanced > Cron Jobs
 * 
 * 2. أضف Cron Job جديد:
 *    الوقت: */15 * * * * (كل 15 دقيقة)
 *    الأمر: /usr/bin/php /home/username/public_html/check_expired_grooms.php
 * 
 * 3. أو يمكنك استخدام wget:
 *    الأمر: wget -q -O - https://jadhlah.com/check_expired_grooms.php?key=jadhlah2025
 * 
 * 4. تحقق من اللوقات في: /logs/expired_grooms_YYYY-MM-DD.log
 */
