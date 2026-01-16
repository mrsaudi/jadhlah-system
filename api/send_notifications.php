<?php
// api/send_notifications.php - نسخة نهائية مع روابط صحيحة
header('Content-Type: application/json');
error_reporting(0);

require '../config/database.php';
require '../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// Get groom_id
$groomId = 0;
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $groomId = isset($input['groom_id']) ? intval($input['groom_id']) : 0;
} else {
    $groomId = isset($_GET['groom_id']) ? intval($_GET['groom_id']) : 0;
}

if($groomId <= 0) {
    die(json_encode(['success'=>false,'error'=>'Invalid groom_id']));
}

// Database
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

// Get groom
$stmt = $conn->prepare("SELECT groom_name, folder_name FROM grooms WHERE id = ?");
$stmt->bind_param("i", $groomId);
$stmt->execute();
$groom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$groom) {
    die(json_encode(['success'=>false,'error'=>'Groom not found']));
}

// Get subscriptions
$stmt = $conn->prepare("SELECT id, endpoint, auth, p256dh FROM push_subscriptions WHERE groom_id = ? AND is_active = 1");
$stmt->bind_param("i", $groomId);
$stmt->execute();
$subs = $stmt->get_result();
$stmt->close();

if($subs->num_rows === 0) {
    $conn->close();
    die(json_encode(['success'=>true,'message'=>'No subscribers','sent'=>0]));
}

// WebPush setup
$webPush = new WebPush([
    'VAPID' => [
        'subject' => 'mailto:info@jadhlah.com',
        'publicKey' => 'BIxYJhtuWzU00qHiGLpXE7RXbsdkapV4870OniWKAWedC1iCfxVMbiXLU7-CIngtuTM8IYcQ9j4PbVBFOiMOyhw',
        'privateKey' => 'kB88wO9mZ67jB6nIhzhvxc0EDtK7Rc13Fc9VGmOuAos'
    ]
]);

// تحديد الرابط الصحيح
$pageUrl = '/groom.php?groom=' . $groomId;

// إذا folder_name موجود، استخدمه (يحتاج .htaccess)
// if(!empty($groom['folder_name'])) {
//     $pageUrl = '/grooms/' . $groom['folder_name'];
// }

// Notification payload
$payload = json_encode([
    'title' => '🎉 الصور جاهزة!',
    'body' => 'صور زواج ' . $groom['groom_name'] . ' متاحة الآن للمشاهدة',
    'icon' => '/images/logo.png',
    'badge' => '/images/badge.png',
    'data' => [
        'url' => $pageUrl,
        'groom_id' => $groomId
    ]
]);

$sent = 0;
$failed = 0;
$expired = [];

// Send to all subscribers
while($sub = $subs->fetch_assoc()) {
    try {
        $subscription = Subscription::create([
            'endpoint' => $sub['endpoint'],
            'keys' => [
                'auth' => $sub['auth'],
                'p256dh' => $sub['p256dh']
            ]
        ]);
        
        $result = $webPush->sendOneNotification($subscription, $payload);
        
        if($result->isSuccess()) {
            $sent++;
        } else {
            $failed++;
            $statusCode = $result->getResponse() ? $result->getResponse()->getStatusCode() : 0;
            if($statusCode == 410 || $statusCode == 404) {
                $expired[] = $sub['id'];
            }
        }
    } catch(Exception $e) {
        $failed++;
    }
}

// Deactivate expired subscriptions
if(!empty($expired)) {
    $ids = implode(',', array_map('intval', $expired));
    $conn->query("UPDATE push_subscriptions SET is_active = 0 WHERE id IN ($ids)");
}

$conn->close();

echo json_encode([
    'success' => true,
    'groom_name' => $groom['groom_name'],
    'groom_id' => $groomId,
    'total_subscribers' => $sent + $failed,
    'sent' => $sent,
    'failed' => $failed,
    'expired_removed' => count($expired)
]);
?>