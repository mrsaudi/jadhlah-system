<?php
// generate_vapid.php - ضعه في المجلد الرئيسي وشغّله مرة واحدة
require_once 'vendor/autoload.php';

use Minishlink\WebPush\VAPID;

echo "🔑 Generating VAPID Keys...\n\n";

$keys = VAPID::createVapidKeys();

echo "✅ Keys Generated Successfully!\n\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PUBLIC KEY (use in landing.php):\n";
echo $keys['publicKey'] . "\n\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PRIVATE KEY (use in send_notifications.php - KEEP SECRET!):\n";
echo $keys['privateKey'] . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "⚠️  IMPORTANT:\n";
echo "1. Copy the PUBLIC KEY to landing.php (line with vapidPublicKey)\n";
echo "2. Copy the PRIVATE KEY to send_notifications.php (line with vapidPrivateKey)\n";
echo "3. Delete this file after copying the keys for security!\n";
?>