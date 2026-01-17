<?php
/**
 * ============================================
 * إرسال تلقائي لرسائل WhatsApp - Cron Job
 * ============================================
 * 
 * الملف: cron/whatsapp_scheduler.php
 * 
 * يجب تشغيله يومياً عبر Cron Job:
 * 0 9 * * * php /home/u709146392/domains/jadhlah.com/public_html/cron/whatsapp_scheduler.php
 * 
 * يقوم بـ:
 * 1. إرسال تذكير قبل يوم من الزفاف
 * 2. إرسال إشعار يوم الزفاف صباحاً
 * 3. إرسال طلب تقييم بعد 7 أيام من التسليم
 */

// تعريف التطبيق
define('JADHLAH_APP', true);

// إعدادات التنفيذ
set_time_limit(300);
ini_set('memory_limit', '256M');

// ملف السجل
$logFile = __DIR__ . '/../logs/whatsapp_cron_' . date('Y-m-d') . '.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo "[$timestamp] $message\n";
    }
}

writeLog("========================================");
writeLog("بدء تنفيذ Cron Job - WhatsApp Scheduler");
writeLog("========================================");

// الاتصال بقاعدة البيانات
$host = 'localhost';
$db   = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    writeLog("✓ تم الاتصال بقاعدة البيانات");
} catch (PDOException $e) {
    writeLog("✗ فشل الاتصال: " . $e->getMessage());
    exit(1);
}

// تحميل WhatsApp Client
require_once __DIR__ . '/../api/whatsapp/WhatsAppClient.php';
require_once __DIR__ . '/../config/whatsapp.php';

$whatsapp = new WhatsAppClient();
$stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

// ============================================
// 1. تذكير قبل يوم من الزفاف
// ============================================
writeLog("\n--- معالجة التذكيرات (قبل يوم) ---");

$tomorrow = date('Y-m-d', strtotime('+1 day'));

$stmt = $pdo->prepare("
    SELECT b.*, p.name as package_name_db
    FROM bookings b
    LEFT JOIN packages p ON b.package_id = p.id
    WHERE b.wedding_date = ?
    AND b.stage NOT IN ('delivered', 'review_requested', 'closed')
    AND b.id NOT IN (
        SELECT booking_id FROM whatsapp_messages_log 
        WHERE stage = 'reminder_sent' 
        AND DATE(sent_at) = CURRENT_DATE()
    )
");
$stmt->execute([$tomorrow]);
$reminderBookings = $stmt->fetchAll();

writeLog("وجدت " . count($reminderBookings) . " حجز للتذكير");

foreach ($reminderBookings as $booking) {
    try {
        // تجهيز الرسالة
        $paymentNote = '';
        if ($booking['payment_status'] !== 'paid') {
            $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM booking_payments WHERE booking_id = ? AND is_paid = 1");
            $stmt2->execute([$booking['id']]);
            $paid = $stmt2->fetchColumn();
            $remaining = $booking['total_price'] - $paid;
            if ($remaining > 0) {
                $paymentNote = "💰 المتبقي: " . number_format($remaining, 0) . " ريال";
            }
        }
        
        $variables = [
            $booking['groom_name'],
            $booking['venue'] ?? 'غير محدد',
            $paymentNote
        ];
        
        // إرسال الرسالة
        $result = $whatsapp->sendTemplate(
            $booking['phone'],
            'reminder_groom',
            'ar',
            $variables
        );
        
        // تسجيل النتيجة
        $pdo->prepare("
            INSERT INTO whatsapp_messages_log 
            (booking_id, recipient_phone, stage, status, whatsapp_message_id, sent_at)
            VALUES (?, ?, 'reminder_sent', ?, ?, NOW())
        ")->execute([
            $booking['id'],
            $booking['phone'],
            $result['success'] ? 'sent' : 'failed',
            $result['message_id'] ?? null
        ]);
        
        if ($result['success']) {
            // تحديث المرحلة
            $pdo->prepare("UPDATE bookings SET stage = 'reminder_sent', stage_updated_at = NOW() WHERE id = ? AND stage NOT IN ('delivered', 'closed')")
                ->execute([$booking['id']]);
            
            writeLog("✓ تذكير: {$booking['groom_name']} - {$booking['phone']}");
            $stats['sent']++;
        } else {
            writeLog("✗ فشل تذكير {$booking['groom_name']}: " . ($result['error'] ?? 'Unknown'));
            $stats['failed']++;
        }
        
        // تأخير بين الرسائل
        usleep(500000); // 0.5 ثانية
        
    } catch (Exception $e) {
        writeLog("✗ خطأ في تذكير {$booking['groom_name']}: " . $e->getMessage());
        $stats['failed']++;
    }
}

// ============================================
// 2. إشعار يوم الزفاف (صباحاً)
// ============================================
writeLog("\n--- معالجة إشعارات يوم الزفاف ---");

$today = date('Y-m-d');
$currentHour = (int)date('H');

// فقط بين 8-10 صباحاً
if ($currentHour >= 8 && $currentHour <= 10) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.name as package_name_db
        FROM bookings b
        LEFT JOIN packages p ON b.package_id = p.id
        WHERE b.wedding_date = ?
        AND b.stage NOT IN ('delivered', 'review_requested', 'closed')
        AND b.id NOT IN (
            SELECT booking_id FROM whatsapp_messages_log 
            WHERE stage = 'wedding_day' 
            AND DATE(sent_at) = CURRENT_DATE()
        )
    ");
    $stmt->execute([$today]);
    $todayBookings = $stmt->fetchAll();
    
    writeLog("وجدت " . count($todayBookings) . " حجز اليوم");
    
    foreach ($todayBookings as $booking) {
        try {
            $arrivalTime = $booking['wedding_time'] 
                ? date('g:i A', strtotime($booking['wedding_time'] . ' -30 minutes'))
                : '7:00 PM';
            
            $variables = [
                $booking['groom_name'],
                $booking['venue'] ?? 'غير محدد',
                $arrivalTime
            ];
            
            $result = $whatsapp->sendTemplate(
                $booking['phone'],
                'wedding_today',
                'ar',
                $variables
            );
            
            $pdo->prepare("
                INSERT INTO whatsapp_messages_log 
                (booking_id, recipient_phone, stage, status, whatsapp_message_id, sent_at)
                VALUES (?, ?, 'wedding_day', ?, ?, NOW())
            ")->execute([
                $booking['id'],
                $booking['phone'],
                $result['success'] ? 'sent' : 'failed',
                $result['message_id'] ?? null
            ]);
            
            if ($result['success']) {
                $pdo->prepare("UPDATE bookings SET stage = 'wedding_day', stage_updated_at = NOW() WHERE id = ?")
                    ->execute([$booking['id']]);
                writeLog("✓ يوم الزفاف: {$booking['groom_name']}");
                $stats['sent']++;
            } else {
                writeLog("✗ فشل: {$booking['groom_name']}");
                $stats['failed']++;
            }
            
            usleep(500000);
            
        } catch (Exception $e) {
            writeLog("✗ خطأ: " . $e->getMessage());
            $stats['failed']++;
        }
    }
} else {
    writeLog("تخطي إشعارات يوم الزفاف (الوقت: $currentHour - يجب 8-10 صباحاً)");
}

// ============================================
// 3. طلب تقييم بعد التسليم بـ 3 أيام
// ============================================
writeLog("\n--- معالجة طلبات التقييم ---");

$reviewDate = date('Y-m-d', strtotime('-3 days'));

$stmt = $pdo->prepare("
    SELECT b.*
    FROM bookings b
    WHERE b.stage = 'delivered'
    AND DATE(b.stage_updated_at) <= ?
    AND b.id NOT IN (
        SELECT booking_id FROM whatsapp_messages_log 
        WHERE stage = 'review_requested'
    )
    LIMIT 20
");
$stmt->execute([$reviewDate]);
$reviewBookings = $stmt->fetchAll();

writeLog("وجدت " . count($reviewBookings) . " حجز لطلب التقييم");

foreach ($reviewBookings as $booking) {
    try {
        $reviewLink = "https://jadhlah.com/rate?booking={$booking['id']}";
        
        $variables = [
            $booking['groom_name'],
            $reviewLink
        ];
        
        $result = $whatsapp->sendTemplate(
            $booking['phone'],
            'review_request',
            'ar',
            $variables
        );
        
        $pdo->prepare("
            INSERT INTO whatsapp_messages_log 
            (booking_id, recipient_phone, stage, status, whatsapp_message_id, sent_at)
            VALUES (?, ?, 'review_requested', ?, ?, NOW())
        ")->execute([
            $booking['id'],
            $booking['phone'],
            $result['success'] ? 'sent' : 'failed',
            $result['message_id'] ?? null
        ]);
        
        if ($result['success']) {
            $pdo->prepare("UPDATE bookings SET stage = 'review_requested', stage_updated_at = NOW() WHERE id = ?")
                ->execute([$booking['id']]);
            writeLog("✓ طلب تقييم: {$booking['groom_name']}");
            $stats['sent']++;
        } else {
            writeLog("✗ فشل: {$booking['groom_name']}");
            $stats['failed']++;
        }
        
        usleep(500000);
        
    } catch (Exception $e) {
        writeLog("✗ خطأ: " . $e->getMessage());
        $stats['failed']++;
    }
}

// ============================================
// النتائج النهائية
// ============================================
writeLog("\n========================================");
writeLog("النتائج النهائية:");
writeLog("  - تم الإرسال: {$stats['sent']}");
writeLog("  - فشل: {$stats['failed']}");
writeLog("  - تخطي: {$stats['skipped']}");
writeLog("========================================");
writeLog("انتهى التنفيذ\n");
