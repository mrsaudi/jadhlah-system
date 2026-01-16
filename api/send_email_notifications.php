<?php
// api/send_email_notifications_simple.php - بدون PHPMailer
error_reporting(E_ALL);
ini_set('display_errors', 0);

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

$groomId = isset($_GET['groom_id']) ? intval($_GET['groom_id']) : 0;

if ($groomId <= 0) {
    die(json_encode(['success' => false, 'error' => 'معرف غير صحيح']));
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'خطأ في قاعدة البيانات']));
}

// جلب بيانات العريس
$stmt = $pdo->prepare("SELECT groom_name FROM grooms WHERE id = ?");
$stmt->execute([$groomId]);
$groom = $stmt->fetch();

if (!$groom) {
    die(json_encode(['success' => false, 'error' => 'العريس غير موجود']));
}

// جلب المشتركين
$stmt = $pdo->prepare("
    SELECT id, email 
    FROM email_subscriptions 
    WHERE groom_id = ? AND is_active = 1 AND is_notified = 0
");
$stmt->execute([$groomId]);
$subscribers = $stmt->fetchAll();

if (empty($subscribers)) {
    die(json_encode(['success' => true, 'message' => 'لا يوجد مشتركين', 'sent' => 0]));
}

$sent = 0;
$failed = 0;
$pageUrl = 'https://jadhlah.com/groom.php?groom=' . $groomId;

foreach ($subscribers as $sub) {
    $to = $sub['email'];
    $subject = '🎉 الصور جاهزة - ' . $groom['groom_name'];
    
    $message = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #FFD700, #FFA500); padding: 40px 20px; text-align: center; }
            .header h1 { color: white; margin: 0; font-size: 32px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
            .content { padding: 40px 30px; text-align: right; }
            .content h2 { color: #333; margin-bottom: 20px; }
            .content p { color: #666; line-height: 1.8; margin-bottom: 15px; }
            .groom-name { color: #FFD700; font-weight: bold; }
            .button { display: inline-block; background: linear-gradient(135deg, #FFD700, #FFA500); color: white !important; padding: 18px 50px; text-decoration: none; border-radius: 50px; margin: 20px 0; font-weight: bold; font-size: 18px; }
            .footer { background: #f9f9f9; padding: 30px; text-align: center; border-top: 3px solid #FFD700; }
            .footer a { color: #FFD700; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 الصور جاهزة!</h1>
            </div>
            <div class='content'>
                <h2>مرحباً،</h2>
                <p>يسعدنا إعلامك بأن صور حفل زواج <span class='groom-name'>{$groom['groom_name']}</span> أصبحت جاهزة الآن!</p>
                <p style='text-align: center;'>
                    <a href='{$pageUrl}' class='button'>مشاهدة الصور الآن</a>
                </p>
                <p style='color: #999; font-size: 14px; margin-top: 30px;'>
                    أو انسخ الرابط: <a href='{$pageUrl}' style='color: #FFD700;'>{$pageUrl}</a>
                </p>
            </div>
            <div class='footer'>
                <p><strong>جذلة للتصوير الفوتوغرافي</strong></p>
                <p><a href='https://instagram.com/jadhlah'>Instagram</a> | <a href='https://wa.me/966544705859'>WhatsApp</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: جذلة <noreply@jadhlah.com>\r\n";
    $headers .= "Reply-To: info@jadhlah.com\r\n";
    
    if (@mail($to, $subject, $message, $headers)) {
        $sent++;
        $pdo->prepare("UPDATE email_subscriptions SET is_notified = 1, notified_at = NOW() WHERE id = ?")->execute([$sub['id']]);
    } else {
        $failed++;
    }
    
    usleep(500000); // 0.5 ثانية بين كل إيميل
}

echo json_encode([
    'success' => true,
    'groom_name' => $groom['groom_name'],
    'sent' => $sent,
    'failed' => $failed,
    'total' => count($subscribers)
], JSON_UNESCAPED_UNICODE);
?>