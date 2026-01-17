<?php
define('JADHLAH_APP', true);

// اختبار إرسال واتساب
$data = [
    'booking_id' => 1, // رقم الحجز
    'template_key' => 'booking_confirmation'
];

$ch = curl_init('https://jadhlah.com/api/whatsapp/send.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($result, true));
echo "</pre>";