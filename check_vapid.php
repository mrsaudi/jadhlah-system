<?php
// check_vapid.php - فحص تطابق مفاتيح VAPID
header('Content-Type: application/json; charset=utf-8');

$results = [];

// 1. فحص landing.php
$landingFile = 'landing.php';
if (file_exists($landingFile)) {
    $landingContent = file_get_contents($landingFile);
    
    // استخراج المفتاح من JavaScript
    if (preg_match('/const vapidPublicKey = [\'"]([^\'"]+)[\'"]/', $landingContent, $matches)) {
        $results['landing_public_key'] = $matches[1];
        $results['landing_key_length'] = strlen($matches[1]);
    } else {
        $results['landing_public_key'] = 'NOT FOUND';
    }
} else {
    $results['landing_error'] = 'File not found';
}

// 2. فحص send_notifications.php
$sendFile = 'api/send_notifications.php';
if (file_exists($sendFile)) {
    $sendContent = file_get_contents($sendFile);
    
    // استخراج المفتاح العام
    if (preg_match('/\$vapidPublicKey = [\'"]([^\'"]+)[\'"]/', $sendContent, $matches)) {
        $results['send_public_key'] = $matches[1];
        $results['send_public_key_length'] = strlen($matches[1]);
    } else {
        $results['send_public_key'] = 'NOT FOUND';
    }
    
    // استخراج المفتاح الخاص
    if (preg_match('/\$vapidPrivateKey = [\'"]([^\'"]+)[\'"]/', $sendContent, $matches)) {
        $results['send_private_key'] = $matches[1];
        $results['send_private_key_length'] = strlen($matches[1]);
    } else {
        $results['send_private_key'] = 'NOT FOUND';
    }
} else {
    $results['send_error'] = 'File not found';
}

// 3. فحص test_subscribe.html
$testFile = 'test_subscribe.html';
if (file_exists($testFile)) {
    $testContent = file_get_contents($testFile);
    
    if (preg_match('/const vapidPublicKey = [\'"]([^\'"]+)[\'"]/', $testContent, $matches)) {
        $results['test_public_key'] = $matches[1];
        $results['test_key_length'] = strlen($matches[1]);
    } else {
        $results['test_public_key'] = 'NOT FOUND';
    }
}

// 4. التحقق من التطابق
$landingKey = $results['landing_public_key'] ?? '';
$sendKey = $results['send_public_key'] ?? '';
$testKey = $results['test_public_key'] ?? '';

$results['keys_match'] = ($landingKey === $sendKey && $landingKey === $testKey && $landingKey !== 'NOT FOUND');

if ($results['keys_match']) {
    $results['status'] = '✅ All keys match!';
    $results['verdict'] = 'KEYS ARE OK';
} else {
    $results['status'] = '❌ Keys DO NOT match!';
    $results['verdict'] = 'THIS IS THE PROBLEM!';
    
    $results['comparison'] = [
        'landing_vs_send' => $landingKey === $sendKey ? '✅ Match' : '❌ Different',
        'landing_vs_test' => $landingKey === $testKey ? '✅ Match' : '❌ Different',
        'send_vs_test' => $sendKey === $testKey ? '✅ Match' : '❌ Different'
    ];
}

// 5. معلومات إضافية
$results['expected_format'] = [
    'public_key_length' => 88,
    'private_key_length' => 43,
    'public_key_starts_with' => 'B',
    'private_key_chars' => 'Base64 URL-safe (A-Za-z0-9_-)'
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>