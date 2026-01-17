<?php
define('JADHLAH_APP', true);
require_once __DIR__ . '/WhatsAppClient.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre>";
echo "🔧 اختبار WhatsApp API\n";
echo "========================\n\n";

$whatsapp = new WhatsAppClient();

// إرسال رسالة نصية مباشرة
echo "📤 إرسال رسالة نصية لـ 966590656262...\n";

$result = $whatsapp->sendTextMessage(
    '966590656262',
    'مرحباً! 🎉 هذه رسالة تجريبية من نظام جذلة. التوكن يعمل بنجاح! ✅'
);

if ($result['success']) {
    echo "✅ نجاح! Message ID: " . $result['message_id'] . "\n";
    echo "\n🎊 تحقق من الواتساب الآن!\n";
} else {
    echo "❌ فشل! الخطأ: " . $result['error'] . "\n";
    echo "   كود الخطأ: " . ($result['error_code'] ?? 'N/A') . "\n";
}

echo "\n========================\n";
print_r($result);
echo "</pre>";