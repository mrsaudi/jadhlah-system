<?php
define('JADHLAH_APP', true);
require_once __DIR__ . '/WhatsAppClient.php';

header('Content-Type: text/html; charset=utf-8');

echo "<pre>";
echo "🔧 اختبار WhatsApp API\n";
echo "========================\n\n";

$whatsapp = new WhatsAppClient();

// اختبار بقالب hello_world الافتراضي
echo "📤 اختبار: إرسال قالب hello_world...\n";

$result = $whatsapp->sendTemplate(
    '966552585043',  // رقمك
    'hello_world',   // قالب افتراضي من Meta
    'en_US'          // اللغة الإنجليزية
);

if ($result['success']) {
    echo "✅ نجاح! Message ID: " . $result['message_id'] . "\n";
} else {
    echo "❌ فشل! الخطأ: " . $result['error'] . "\n";
    if (isset($result['error_code'])) {
        echo "   كود الخطأ: " . $result['error_code'] . "\n";
    }
}

echo "\n========================\n";
print_r($result);
echo "</pre>";