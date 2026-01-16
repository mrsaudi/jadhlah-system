<?php
// api/send_all_notifications.php - إرسال Push + Email معاً
$groomId = isset($_GET['groom_id']) ? intval($_GET['groom_id']) : 0;

if ($groomId <= 0) {
    die(json_encode(['success' => false, 'error' => 'Invalid groom_id']));
}

// 1. إرسال Push Notifications
$pushResult = @file_get_contents("https://jadhlah.com/api/send_notifications.php?groom_id={$groomId}");
$pushData = json_decode($pushResult, true);

// 2. إرسال Email Notifications
$emailResult = @file_get_contents("https://jadhlah.com/api/send_email_notifications.php?groom_id={$groomId}");
$emailData = json_decode($emailResult, true);

// نتيجة موحدة
echo json_encode([
    'success' => true,
    'push' => [
        'sent' => $pushData['sent'] ?? 0,
        'failed' => $pushData['failed'] ?? 0
    ],
    'email' => [
        'sent' => $emailData['sent'] ?? 0,
        'failed' => $emailData['failed'] ?? 0
    ],
    'total_sent' => ($pushData['sent'] ?? 0) + ($emailData['sent'] ?? 0)
]);
?>