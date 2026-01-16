<?php
// api/send_email_notifications_simple.php - إرسال تلقائي محسّن
error_reporting(0);

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
    
    // عنوان احترافي بدون إيموجي كثيرة
    $subject = 'صور حفل زواج ' . $groom['groom_name'] . ' - جذلة للتصوير';
    
    // نسخة نصية بسيطة (مهمة جداً لتجنب Spam)
    $plainText = "مرحباً،\n\n";
    $plainText .= "نود إعلامك بأن صور حفل زواج {$groom['groom_name']} أصبحت متاحة الآن.\n\n";
    $plainText .= "للاطلاع على الصور، يرجى زيارة:\n";
    $plainText .= "$pageUrl\n\n";
    $plainText .= "مع تحيات فريق جذلة للتصوير الفوتوغرافي\n";
    $plainText .= "Instagram: @jadhlah\n";
    $plainText .= "WhatsApp: 0544705859\n";
    
    // محتوى HTML أنيق ومهني
    $htmlMessage = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Arial, sans-serif; 
                background: #fafafa; 
                padding: 20px 10px;
                line-height: 1.6;
            }
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff; 
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
            
            /* Header بشعار أنيق */
            .header { 
                background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
                padding: 30px 20px;
                text-align: center;
                border-bottom: 3px solid #d4af37;
            }
            .logo {
                font-size: 36px;
                font-weight: bold;
                color: #d4af37;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                letter-spacing: 2px;
                margin-bottom: 8px;
            }
            .tagline {
                color: #e0e0e0;
                font-size: 13px;
                letter-spacing: 1px;
            }
            
            /* المحتوى */
            .content { 
                padding: 35px 30px;
            }
            .greeting {
                color: #333;
                font-size: 18px;
                margin-bottom: 20px;
                font-weight: 500;
            }
            .message {
                color: #555;
                font-size: 16px;
                line-height: 1.8;
                margin-bottom: 15px;
            }
            .groom-name {
                color: #d4af37;
                font-weight: 600;
            }
            
            /* صندوق معلومات */
            .info-box {
                background: #f8f8f8;
                border-right: 4px solid #d4af37;
                padding: 20px;
                margin: 25px 0;
                border-radius: 4px;
            }
            .info-box p {
                color: #666;
                font-size: 15px;
                margin-bottom: 8px;
            }
            
            /* زر الإجراء */
            .cta-button {
                text-align: center;
                margin: 30px 0;
            }
            .cta-button a {
                display: inline-block;
                background: #d4af37;
                color: #ffffff !important;
                padding: 14px 40px;
                text-decoration: none;
                border-radius: 4px;
                font-weight: 600;
                font-size: 16px;
                transition: background 0.3s;
            }
            .cta-button a:hover {
                background: #b8961e;
            }
            
            /* الرابط البديل */
            .alt-link {
                text-align: center;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }
            .alt-link p {
                color: #999;
                font-size: 13px;
                margin-bottom: 8px;
            }
            .alt-link a {
                color: #d4af37;
                text-decoration: none;
                word-break: break-all;
                font-size: 13px;
            }
            
            /* الفوتر */
            .footer {
                background: #f5f5f5;
                padding: 25px 20px;
                text-align: center;
                border-top: 1px solid #e0e0e0;
            }
            .footer-title {
                color: #333;
                font-weight: 600;
                margin-bottom: 12px;
                font-size: 15px;
            }
            .social-links {
                margin-top: 15px;
            }
            .social-links a {
                color: #666;
                text-decoration: none;
                margin: 0 10px;
                font-size: 14px;
            }
            .social-links a:hover {
                color: #d4af37;
            }
            .copyright {
                color: #999;
                font-size: 12px;
                margin-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <!-- Header مع شعار جذلة -->
            <div class='header'>
                <div class='logo'>جَذْلَة</div>
                <div class='tagline'>التصوير الفوتوغرافي</div>
            </div>
            
            <!-- المحتوى الرئيسي -->
            <div class='content'>
                <div class='greeting'>مرحباً،</div>
                
                <p class='message'>
                    نود إعلامك بأن صور حفل زواج <span class='groom-name'>{$groom['groom_name']}</span> أصبحت متاحة الآن للاطلاع والتحميل.
                </p>
                
                <div class='info-box'>
                    <p><strong>📸 ما الذي ينتظرك:</strong></p>
                    <p>• جميع صور الحفل بجودة عالية</p>
                    <p>• إمكانية التحميل المباشر</p>
                    <p>• مشاهدة مريحة من أي جهاز</p>
                </div>
                
                <div class='cta-button'>
                    <a href='{$pageUrl}'>مشاهدة الصور</a>
                </div>
                
                <div class='alt-link'>
                    <p>أو انسخ الرابط التالي في المتصفح:</p>
                    <a href='{$pageUrl}'>{$pageUrl}</a>
                </div>
            </div>
            
            <!-- الفوتر -->
            <div class='footer'>
                <div class='footer-title'>جذلة للتصوير الفوتوغرافي</div>
                <div class='social-links'>
                    <a href='https://instagram.com/jadhlah'>Instagram</a>
                    <span style='color: #ddd;'>|</span>
                    <a href='https://wa.me/966544705859'>WhatsApp: 0544705859</a>
                </div>
                <div class='copyright'>
                    © 2024 Jadhlah Photography. جميع الحقوق محفوظة.
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // إعداد Headers متقدمة لتجنب Spam
    $boundary = md5(time());
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "From: جذلة للتصوير <noreply@jadhlah.com>\r\n";
    $headers .= "Reply-To: info@jadhlah.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 3\r\n"; // أولوية عادية
    $headers .= "Importance: Normal\r\n";
    
    // بناء الرسالة المتعددة (Plain Text + HTML)
    $fullMessage = "--{$boundary}\r\n";
    $fullMessage .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $fullMessage .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $fullMessage .= $plainText . "\r\n\r\n";
    
    $fullMessage .= "--{$boundary}\r\n";
    $fullMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
    $fullMessage .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $fullMessage .= $htmlMessage . "\r\n\r\n";
    
    $fullMessage .= "--{$boundary}--";
    
    // إرسال الإيميل
    if (@mail($to, $subject, $fullMessage, $headers)) {
        $sent++;
        $pdo->prepare("UPDATE email_subscriptions SET is_notified = 1, notified_at = NOW() WHERE id = ?")->execute([$sub['id']]);
    } else {
        $failed++;
    }
    
    usleep(500000); // 0.5 ثانية
}

echo json_encode([
    'success' => true,
    'groom_name' => $groom['groom_name'],
    'sent' => $sent,
    'failed' => $failed,
    'total' => count($subscribers)
], JSON_UNESCAPED_UNICODE);
?>