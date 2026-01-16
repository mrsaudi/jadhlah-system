<?php
// admin/generate_rating_link.php
session_start();

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';

$role = $_SESSION['role'] ?? 'employ';
$isManager = ($role === 'manager');

// جلب قائمة العرسان
$grooms = $pdo->query("
    SELECT id, groom_name, wedding_date, folder_name 
    FROM grooms 
    WHERE is_active = 1 
    ORDER BY wedding_date DESC 
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$ratingLink = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['groom_id'])) {
    $groomId = intval($_POST['groom_id']);
    
    try {
        // التحقق من وجود العريس
        $stmt = $pdo->prepare("SELECT groom_name FROM grooms WHERE id = ?");
        $stmt->execute([$groomId]);
        $groom = $stmt->fetch();
        
        if (!$groom) {
            throw new Exception("العريس غير موجود");
        }
        
        // توليد token فريد
        $token = bin2hex(random_bytes(32));
        
        // صلاحية لمدة 30 يوم
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // حفظ في قاعدة البيانات
        $stmt = $pdo->prepare("
            INSERT INTO rating_tokens (groom_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$groomId, $token, $expiresAt]);
        
        // إنشاء الرابط الصحيح
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $domain = $_SERVER['HTTP_HOST'];
        $ratingLink = "$protocol://$domain/rate.php?token=$token";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء رابط تقييم - جذلة</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 25px;
        }
        .form-select, .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e5e7eb;
        }
        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .result-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
        }
        .link-display {
            background: white;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 15px;
            font-family: monospace;
            word-break: break-all;
            direction: ltr;
            text-align: left;
            user-select: all;
            -webkit-user-select: all;
            -moz-user-select: all;
            -ms-user-select: all;
        }
        .whatsapp-btn {
            background: #25D366;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .whatsapp-btn:hover {
            background: #1da851;
            color: white;
            transform: translateY(-2px);
        }
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }
        .back-link:hover {
            color: #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">
                    <i class="bi bi-link-45deg"></i>
                    إنشاء رابط تقييم للعريس
                </h3>
            </div>
            <div class="card-body p-4">
                
                <?php if ($ratingLink): ?>
                <div class="result-box">
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle"></i>
                        <strong>تم إنشاء الرابط بنجاح!</strong>
                    </div>
                    
                    <h5 class="mb-3">الرابط المباشر:</h5>
                    <div class="link-display mb-3" id="ratingLink"><?= htmlspecialchars($ratingLink) ?></div>
                    
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <button onclick="copyLink(this)" class="btn btn-primary">
                            <i class="bi bi-clipboard"></i>
                            نسخ الرابط
                        </button>
                        
                        <a href="<?= htmlspecialchars($ratingLink) ?>" 
                           class="btn btn-secondary" target="_blank">
                            <i class="bi bi-box-arrow-up-right"></i>
                            فتح الرابط
                        </a>
                    </div>
                    
                    <h5 class="mb-3">إرسال عبر واتساب:</h5>
                    <?php
                    // رسالة الواتساب مع encoding صحيح
                    $whatsappMessage = "السلام عليكم ورحمة الله وبركاته" . "\n\n" .
                                       "نشكرك على اختيارنا لتصوير زواجك. نتمنى أن تشاركنا رأيك في خدماتنا من خلال هذا الرابط:" . "\n\n" .
                                       $ratingLink . "\n\n" .
                                       "⏰ صلاحية الرابط: 30 يوم" . "\n\n" .
                                       "فريق جذلة للتصوير 📸";
                    ?>
                    <a href="https://wa.me/?text=<?= rawurlencode($whatsappMessage) ?>" 
                       class="whatsapp-btn" target="_blank">
                        <i class="bi bi-whatsapp" style="font-size: 24px;"></i>
                        إرسال عبر واتساب
                    </a>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>ملاحظة:</strong> صلاحية الرابط 30 يوم من تاريخ الإنشاء
                    </div>
                </div>
                
                <button onclick="location.reload()" class="btn btn-success mt-3">
                    <i class="bi bi-plus-circle"></i>
                    إنشاء رابط جديد
                </button>
                
                <?php elseif ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>خطأ:</strong> <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <?php if (!$ratingLink): ?>
                <form method="POST">
                    <div class="mb-4">
                        <label for="groom_id" class="form-label">
                            <i class="bi bi-person-circle"></i>
                            اختر العريس:
                        </label>
                        <select name="groom_id" id="groom_id" class="form-select" required>
                            <option value="">-- اختر العريس --</option>
                            <?php foreach ($grooms as $groom): ?>
                            <option value="<?= $groom['id'] ?>">
                                <?= htmlspecialchars($groom['groom_name']) ?> 
                                (<?= date('Y-m-d', strtotime($groom['wedding_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-link-45deg"></i>
                        إنشاء رابط التقييم
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <a href="dashboard.php" class="back-link">
            <i class="bi bi-arrow-right"></i>
            العودة للوحة التحكم
        </a>
    </div>

    <script>
        function copyLink(button) {
            const linkElement = document.getElementById('ratingLink');
            const linkText = linkElement.textContent.trim();
            
            // استخدام Clipboard API الحديث
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(linkText).then(() => {
                    showCopySuccess(button);
                }).catch(err => {
                    // Fallback للطريقة القديمة
                    fallbackCopyTextToClipboard(linkText, button);
                });
            } else {
                // Fallback للمتصفحات القديمة
                fallbackCopyTextToClipboard(linkText, button);
            }
        }
        
        function fallbackCopyTextToClipboard(text, button) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.top = "-9999px";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(button);
                } else {
                    showCopyError(button);
                }
            } catch (err) {
                showCopyError(button);
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopySuccess(button) {
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-circle"></i> تم النسخ!';
            button.classList.add('btn-success');
            button.classList.remove('btn-primary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.add('btn-primary');
                button.classList.remove('btn-success');
            }, 2000);
        }
        
        function showCopyError(button) {
            alert('فشل نسخ الرابط. يرجى نسخه يدوياً');
        }
    </script>
</body>
</html>