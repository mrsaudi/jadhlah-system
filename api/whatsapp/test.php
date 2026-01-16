<?php
/**
 * ============================================
 * اختبار اتصال WhatsApp API - جذلة
 * ============================================
 * 
 * الملف: api/whatsapp/test.php
 * الوظيفة: اختبار إرسال رسالة تجريبية
 * 
 * ⚠️ احذف هذا الملف بعد الانتهاء من الاختبار!
 */

define('JADHLAH_APP', true);
require_once __DIR__ . '/WhatsAppClient.php';

header('Content-Type: application/json; charset=utf-8');

// ============================================
// إعدادات الاختبار
// ============================================

// ⚠️ غيّر هذا الرقم لرقمك للاختبار
$testPhone = '0552585043'; // رقمك

// بيانات الاختبار
$testGroomName = 'أحمد ومنى';
$testPageUrl = 'https://jadhla.com/wedding/test123';

// ============================================
// تنفيذ الاختبار
// ============================================

echo "<pre>";
echo "🔧 اختبار WhatsApp API\n";
echo "========================\n\n";

$whatsapp = new WhatsAppClient();

// اختبار 1: إرسال قالب إشعار الجاهزية
echo "📤 اختبار 1: إرسال قالب grooms_ready...\n";
$result = $whatsapp->sendPhotosReadyNotification($testPhone, $testGroomName, $testPageUrl);

if ($result['success']) {
    echo "✅ نجاح! Message ID: " . $result['message_id'] . "\n";
} else {
    echo "❌ فشل! الخطأ: " . $result['error'] . "\n";
    if (isset($result['error_code'])) {
        echo "   كود الخطأ: " . $result['error_code'] . "\n";
    }
}

echo "\n========================\n";
echo "🔍 تفاصيل الاستجابة:\n";
print_r($result);

echo "</pre>";
