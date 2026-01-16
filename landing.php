<?php
// landing.php - نسخة ذكية مع كشف الأجهزة
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// دالة كشف نوع الجهاز
function detectDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $device = [
        'is_ios' => false,
        'is_safari' => false,
        'is_android' => false,
        'is_desktop' => false,
        'supports_push' => true,
        'browser' => 'Unknown'
    ];
    
    // كشف iOS
    if (preg_match('/iPad|iPhone|iPod/', $userAgent)) {
        $device['is_ios'] = true;
        
        // كشف Safari على iOS
        if (preg_match('/Safari/', $userAgent) && !preg_match('/Chrome|CriOS|FxiOS/', $userAgent)) {
            $device['is_safari'] = true;
            $device['browser'] = 'Safari';
            
            // iOS 16.4+ يدعم Push في PWA فقط
            if (preg_match('/OS (\d+)_/', $userAgent, $matches)) {
                $iosVersion = intval($matches[1]);
                $device['supports_push'] = ($iosVersion >= 16);
            } else {
                $device['supports_push'] = false;
            }
        } else {
            $device['browser'] = 'Chrome/Other';
            $device['supports_push'] = false;
        }
    }
    // كشف Android
    elseif (preg_match('/Android/', $userAgent)) {
        $device['is_android'] = true;
        $device['browser'] = 'Android Browser';
        $device['supports_push'] = true;
    }
    // Desktop
    else {
        $device['is_desktop'] = true;
        $device['supports_push'] = true;
        
        if (preg_match('/Safari/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $device['browser'] = 'Safari';
        } elseif (preg_match('/Chrome/', $userAgent)) {
            $device['browser'] = 'Chrome';
        } elseif (preg_match('/Firefox/', $userAgent)) {
            $device['browser'] = 'Firefox';
        }
    }
    
    return $device;
}

$device = detectDevice();

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $query = "
        SELECT 
            g.groom_name,
            g.wedding_date,
            g.hall_name as location,
            g.id as groom_id,
            g.ready,
            COALESCE(NULLIF(g.folder_name, ''), CAST(g.id AS CHAR)) as folder_name
        FROM grooms g
        WHERE (
            g.wedding_date IN ('$yesterday', '$today')
            OR DATE(g.created_at) IN ('$yesterday', '$today')
        )
        AND g.is_active = 1
        ORDER BY g.wedding_date DESC, g.created_at DESC, g.groom_name ASC
    ";

    $result = $conn->query($query);
    if (!$result) {
        die("Query Error: " . $conn->error);
    }

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جذلة - معرض الصور الحية</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap');
        
        :root {
            --gold: #D4AF37;
            --dark-gold: #B8941E;
            --light-gold: #F4E5C2;
            --black: #1a1a1a;
            --dark-gray: #2d2d2d;
            --white: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', 'Segoe UI', sans-serif;
            background: var(--black);
            color: var(--white);
            overflow-x: hidden;
        }
        
        .luxury-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%);
            z-index: -1;
        }
        
        .luxury-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(212, 175, 55, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(212, 175, 55, 0.1) 0%, transparent 50%);
            animation: shimmer 15s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 0.8; }
        }
        
        .top-bar {
            background: rgba(45, 45, 45, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 0;
            border-bottom: 2px solid var(--gold);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .top-bar-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-img {
            height: 60px;
            width: auto;
            filter: drop-shadow(0 2px 8px rgba(212, 175, 55, 0.3));
        }
        
        .logo-text p {
            font-size: 14px;
            color: var(--light-gold);
            margin: 5px 0 0 0;
            font-weight: 400;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 18px;
        }
        
        .social-btn.instagram {
            background: linear-gradient(135deg, #833AB4, #FD1D1D, #FCAF45);
            color: white;
        }
        
        .social-btn.whatsapp {
            background: #25D366;
            color: white;
        }
        
        .social-btn.website {
            background: var(--gold);
            color: var(--black);
        }
        
        .social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .hero-section {
            padding: 60px 30px;
            text-align: center;
            position: relative;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.1); }
        }
        
        .hero-title {
            font-size: 48px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gold), var(--light-gold), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .hero-subtitle {
            font-size: 20px;
            color: var(--light-gold);
            font-weight: 300;
            position: relative;
            z-index: 1;
        }
        
        .main-actions {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
        }
        
        .luxury-card {
            background: linear-gradient(135deg, rgba(45, 45, 45, 0.9), rgba(30, 30, 30, 0.9));
            border: 2px solid var(--gold);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .luxury-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(212, 175, 55, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s;
        }
        
        .luxury-card:hover::before {
            left: 100%;
        }
        
        .luxury-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(212, 175, 55, 0.3);
            border-color: var(--light-gold);
        }
        
        .card-icon {
            font-size: 60px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .card-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--gold);
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .card-description {
            font-size: 16px;
            color: var(--light-gold);
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 68, 68, 0.2);
            border: 2px solid #ff4444;
            padding: 8px 20px;
            border-radius: 25px;
            color: #ff4444;
            font-weight: 600;
            animation: blink 2s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .live-dot {
            width: 10px;
            height: 10px;
            background: #ff4444;
            border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }
        
        .events-container {
            max-width: 1400px;
            margin: 60px auto;
            padding: 0 30px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--gold), var(--light-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .section-subtitle {
            color: var(--light-gold);
            font-size: 16px;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .event-card {
            background: rgba(45, 45, 45, 0.6);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 15px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .event-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.1), transparent);
            transition: left 0.5s;
        }
        
        .event-card:hover::before {
            left: 100%;
        }
        
        .event-card:hover {
            border-color: var(--gold);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
        }
        
        .event-date-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--gold), var(--dark-gold));
            color: var(--black);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .event-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 10px;
        }
        
        .event-location {
            color: var(--light-gold);
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .event-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid rgba(212, 175, 55, 0.2);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-ready {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            color: #4CAF50;
        }
        
        .status-preparing {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid #FFC107;
            color: #FFC107;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: linear-gradient(135deg, rgba(45, 45, 45, 0.98), rgba(30, 30, 30, 0.98));
            border: 2px solid var(--gold);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(212, 175, 55, 0.2);
            color: var(--gold);
            border: none;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: var(--gold);
            color: var(--black);
            transform: rotate(90deg);
        }
        
        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .modal-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        .modal-title {
            font-size: 24px;
            color: var(--gold);
            margin-bottom: 10px;
        }
        
        .device-info {
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid var(--gold);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--light-gold);
        }
        
        .notification-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--gold), var(--dark-gold));
            color: var(--black);
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        }
        
        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .submit-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--gold);
            border: 2px solid var(--gold);
        }
        
        .email-input {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 10px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--white);
            text-align: center;
            direction: ltr;
        }
        
        .email-input:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .email-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .footer {
            background: rgba(45, 45, 45, 0.9);
            border-top: 2px solid var(--gold);
            padding: 40px 30px;
            margin-top: 80px;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-logo-img {
            height: 50px;
            width: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 2px 8px rgba(212, 175, 55, 0.3));
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .footer-link {
            color: var(--light-gold);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .footer-link:hover {
            color: var(--gold);
        }
        
        .footer-copyright {
            color: var(--light-gold);
            font-size: 14px;
            opacity: 0.8;
        }
        
        #notificationStatus .loading {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid #FFC107;
            color: #FFC107;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }

        #notificationStatus .success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4CAF50;
            color: #4CAF50;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }

        #notificationStatus .error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #F44336;
            color: #F44336;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 32px;
            }
            
            .main-actions {
                grid-template-columns: 1fr;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar-content {
                flex-direction: column;
                text-align: center;
            }
            
            .logo-img {
                height: 50px;
            }
            
            .modal-content {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="luxury-bg"></div>
    
    <div class="top-bar">
        <div class="top-bar-content">
            <div class="logo-section">
                <a href="index.php"><img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="logo-img"></a>
                <div class="logo-text">
                    <p>متخصص تصوير الزواجات</p>
                </div>
            </div>
            
            <div class="social-links">
                <a href="https://instagram.com/jadhlah" target="_blank" class="social-btn instagram" title="تابعنا على انستغرام">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://wa.me/966544705859" target="_blank" class="social-btn whatsapp" title="تواصل معنا واتساب">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="/index.php" class="social-btn website" title="الصفحة الرئيسية">
                    <i class="fas fa-home"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="hero-section">
        <h1 class="hero-title">✨ معرض الصور الحية</h1>
        <p class="hero-subtitle">شاهد صور حفلتك مباشرة وهي تحدث الآن</p>
    </div>
    
    <div class="main-actions">
        <a href="live-gallery.php" class="luxury-card">
            <div class="card-icon">🔴</div>
            <h2 class="card-title">البث المباشر</h2>
            <p class="card-description">شاهد أحدث الصور من الحفلات الجارية الآن</p>
            <div class="live-indicator">
                <span class="live-dot"></span>
                مباشر الآن
            </div>
        </a>
        
        <div class="luxury-card" onclick="scrollToEvents()">
            <div class="card-icon">🎊</div>
            <h2 class="card-title">حفلتك</h2>
            <p class="card-description">اختر حفلتك واحصل على إشعار فور جاهزية الصور</p>
            <div class="live-indicator" style="background: rgba(212, 175, 55, 0.2); border-color: var(--gold); color: var(--gold); animation: none;">
                <?php echo count($events); ?> حفلة نشطة
            </div>
        </div>
    </div>
    
    <div class="events-container" id="eventsSection">
        <div class="section-header">
            <h2 class="section-title">🎉 الحفلات النشطة</h2>
            <p class="section-subtitle">حفلات اليوم والأمس</p>
        </div>
        
        <?php if (count($events) > 0): ?>
        <div class="events-grid">
            <?php foreach ($events as $event): ?>
            <div class="event-card" 
                 data-groom-id="<?php echo htmlspecialchars($event['groom_id']); ?>" 
                 data-groom-name="<?php echo htmlspecialchars($event['groom_name']); ?>"
                 data-ready="<?php echo htmlspecialchars($event['ready']); ?>"
                 data-folder="<?php echo htmlspecialchars($event['folder_name'] ?? ''); ?>">
                <div class="event-date-badge">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d/m/Y', strtotime($event['wedding_date'])); ?>
                </div>
                <div class="event-name">
                    زواج <?php echo htmlspecialchars($event['groom_name']); ?>
                </div>
                <div class="event-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($event['location'] ?? 'غير محدد'); ?>
                </div>
                <div class="event-status">
                    <?php if ($event['ready'] == 1): ?>
                    <span class="status-badge status-ready">
                        <i class="fas fa-check-circle"></i>
                        الصور جاهزة
                    </span>
                    <?php else: ?>
                    <span class="status-badge status-preparing">
                        <i class="fas fa-clock"></i>
                        قيد الإعداد
                    </span>
                    <span style="font-size: 12px; color: var(--light-gold);">اضغط للإشعارات</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 80px; margin-bottom: 20px;">🎈</div>
            <h3 style="color: var(--gold); font-size: 24px; margin-bottom: 10px;">لا توجد حفلات نشطة</h3>
            <p style="color: var(--light-gold);">تابع معنا قريباً لمشاهدة الصور الجديدة</p>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <div class="footer-content">
            <img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="footer-logo-img">
            <div class="footer-links">
                <a href="/index.php" class="footer-link">الرئيسية</a>
                <a href="/gallery.php" class="footer-link">أعمالنا</a>
                <a href="https://instagram.com/jadhlah" target="_blank" class="footer-link">انستغرام</a>
                <a href="https://wa.me/966544705859" target="_blank" class="footer-link">واتساب</a>
            </div>
            <div class="footer-copyright">
                © 2025 جذلة - متخصص تصوير الزواجات - جميع الحقوق محفوظة
            </div>
        </div>
    </div>
    
    <div class="modal" id="notificationModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeNotificationModal()">×</button>
            
            <div class="modal-header">
                <div class="modal-icon">🔔</div>
                <h2 class="modal-title" id="modalEventName"></h2>
                <p style="color: var(--light-gold);">سيصلك إشعار فور جاهزية الصور</p>
            </div>
            
            <div class="device-info" id="deviceInfo"></div>
            
            <div id="notificationStatus"></div>
            
            <div class="notification-options" id="notificationOptions">
                <!-- سيتم ملؤها بواسطة JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
// معلومات الجهاز من PHP
const deviceInfo = <?php echo json_encode($device); ?>;
let selectedGroomId = null;
let selectedGroomName = '';
let isProcessing = false;

console.log('معلومات الجهاز:', deviceInfo);

// تحويل VAPID key
function urlBase64ToUint8Array(base64String) {
    try {
        base64String = base64String.trim().replace(/\s/g, '');
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    } catch (error) {
        console.error('خطأ في VAPID:', error);
        throw new Error('مفتاح الأمان غير صالح');
    }
}

// الاشتراك بالإيميل
async function subscribeEmail() {
    if (isProcessing) return;
    
    const statusDiv = document.getElementById('notificationStatus');
    const emailInput = document.getElementById('emailInput');
    const email = emailInput.value.trim();
    
    if (!email) {
        statusDiv.innerHTML = '<div class="error">❌ يرجى إدخال البريد الإلكتروني</div>';
        return;
    }
    
    // التحقق من صحة الإيميل
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        statusDiv.innerHTML = '<div class="error">❌ البريد الإلكتروني غير صحيح</div>';
        return;
    }
    
    isProcessing = true;
    const btn = document.getElementById('emailBtn');
    btn.disabled = true;
    
    try {
        statusDiv.innerHTML = '<div class="loading">⏳ جاري التسجيل...</div>';
        
        const response = await fetch('/api/subscribe_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                groom_id: selectedGroomId,
                email: email
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'فشل التسجيل');
        }
        
        statusDiv.innerHTML = `
            <div class="success">
                ✅ تم التسجيل بنجاح!<br>
                <small style="opacity: 0.8;">سيصلك إشعار على ${email} عند جاهزية الصور</small>
            </div>
        `;
        
        emailInput.value = '';
        btn.style.display = 'none';
        emailInput.style.display = 'none';
        
        setTimeout(() => closeNotificationModal(), 3000);
        
    } catch (error) {
        console.error('خطأ:', error);
        statusDiv.innerHTML = `<div class="error">❌ ${error.message}</div>`;
        btn.disabled = false;
    } finally {
        isProcessing = false;
    }
}

// الاشتراك في Push
async function subscribePush() {
    if (isProcessing) return;
    
    const statusDiv = document.getElementById('notificationStatus');
    const btn = document.getElementById('pushBtn');
    
    isProcessing = true;
    btn.disabled = true;
    
    try {
        statusDiv.innerHTML = '<div class="loading">⏳ جاري التحقق...</div>';
        
        // التحقق من جاهزية الصور
        const checkResponse = await fetch('/api/check_groom_ready.php?groom_id=' + selectedGroomId);
        const checkResult = await checkResponse.json();
        
        if (checkResult.ready) {
            statusDiv.innerHTML = `
                <div class="success">
                    🎉 الصور جاهزة الآن!<br>
                    <a href="/groom.php?groom=${selectedGroomId}" style="color: var(--gold); font-weight: bold; text-decoration: underline;">
                        مشاهدة الصور ←
                    </a>
                </div>
            `;
            btn.style.display = 'none';
            return;
        }
        
        // التحقق من دعم الإشعارات
        if (!('Notification' in window)) {
            throw new Error('المتصفح لا يدعم الإشعارات');
        }
        
        // طلب الإذن
        let permission = Notification.permission;
        
        if (permission === 'denied') {
            throw new Error('تم رفض الإشعارات. فعلها من إعدادات المتصفح');
        }
        
        if (permission === 'default') {
            statusDiv.innerHTML = '<div class="loading">⏳ يرجى الموافقة على الإشعارات...</div>';
            permission = await Notification.requestPermission();
        }
        
        if (permission !== 'granted') {
            throw new Error('يجب الموافقة على الإشعارات للمتابعة');
        }
        
        // تسجيل Service Worker
        statusDiv.innerHTML = '<div class="loading">⏳ جاري تجهيز الإشعارات...</div>';
        
        if (!('serviceWorker' in navigator)) {
            throw new Error('المتصفح لا يدعم Service Worker');
        }
        
        const registration = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;
        
        // الاشتراك في Push
        const vapidPublicKey = 'BIxYJhtuWzU00qHiGLpXE7RXbsdkapV4870OniWKAWedC1iCfxVMbiXLU7-CIngtuTM8IYcQ9j4PbVBFOiMOyhw';
        const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);
        
        const existingSubscription = await registration.pushManager.getSubscription();
        if (existingSubscription) {
            await existingSubscription.unsubscribe();
        }
        
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey
        });
        
        // حفظ في السيرفر
        statusDiv.innerHTML = '<div class="loading">⏳ جاري الحفظ...</div>';
        
        const response = await fetch('/api/subscribe_push.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                groom_id: selectedGroomId,
                subscription: subscription.toJSON()
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'فشل الحفظ');
        }
        
        statusDiv.innerHTML = '<div class="success">✅ تم التفعيل بنجاح!</div>';
        btn.style.display = 'none';
        
        setTimeout(() => closeNotificationModal(), 2000);
        
    } catch (error) {
        console.error('خطأ:', error);
        statusDiv.innerHTML = `<div class="error">❌ ${error.message}</div>`;
        btn.disabled = false;
    } finally {
        isProcessing = false;
    }
}

// فتح نافذة الإشعارات
function openNotificationModal(groomId, groomName, ready) {
    if (ready == '1') {
        window.location.href = '/groom.php?groom=' + groomId;
        return;
    }
    
    selectedGroomId = groomId;
    selectedGroomName = groomName;
    
    document.getElementById('modalEventName').textContent = 'زواج ' + groomName;
    document.getElementById('notificationModal').classList.add('active');
    document.getElementById('notificationStatus').innerHTML = '';
    isProcessing = false;
    
    // عرض معلومات الجهاز
    const deviceInfoDiv = document.getElementById('deviceInfo');
    let deviceText = '';
    
    if (deviceInfo.is_ios) {
        if (deviceInfo.supports_push) {
            deviceText = '📱 جهاز iPhone - يدعم الإشعارات عبر PWA';
        } else {
            deviceText = '📱 جهاز iPhone - استخدم البريد الإلكتروني للإشعارات';
        }
    } else if (deviceInfo.is_android) {
        deviceText = '📱 جهاز Android - يدعم الإشعارات المباشرة';
    } else {
        deviceText = '💻 جهاز كمبيوتر - يدعم الإشعارات المباشرة';
    }
    
    deviceInfoDiv.textContent = deviceText;
    
    // عرض الخيارات المناسبة
    const optionsDiv = document.getElementById('notificationOptions');
    optionsDiv.innerHTML = '';
    
    if (deviceInfo.supports_push && !deviceInfo.is_ios) {
        // Push متاح - Android أو Desktop
        optionsDiv.innerHTML = `
            <button class="submit-btn" id="pushBtn" onclick="subscribePush()">
                <i class="fas fa-bell"></i> تفعيل الإشعارات الفورية
            </button>
            <button class="submit-btn secondary" onclick="showEmailOption()">
                <i class="fas fa-envelope"></i> استخدام البريد الإلكتروني بدلاً
            </button>
        `;
    } else {
        // Push غير متاح - iOS أو متصفح قديم
        optionsDiv.innerHTML = `
            <input type="email" 
                   id="emailInput" 
                   class="email-input" 
                   placeholder="example@email.com"
                   dir="ltr">
            <button class="submit-btn" id="emailBtn" onclick="subscribeEmail()">
                <i class="fas fa-envelope"></i> إرسال إشعار بالبريد الإلكتروني
            </button>
        `;
        
        // إضافة حدث Enter للإيميل
        setTimeout(() => {
            const emailInput = document.getElementById('emailInput');
            if (emailInput) {
                emailInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        subscribeEmail();
                    }
                });
            }
        }, 100);
    }
}

// عرض خيار الإيميل
function showEmailOption() {
    const optionsDiv = document.getElementById('notificationOptions');
    optionsDiv.innerHTML = `
        <input type="email" 
               id="emailInput" 
               class="email-input" 
               placeholder="example@email.com"
               dir="ltr">
        <button class="submit-btn" id="emailBtn" onclick="subscribeEmail()">
            <i class="fas fa-envelope"></i> إرسال إشعار بالبريد الإلكتروني
        </button>
        <button class="submit-btn secondary" onclick="openNotificationModal(selectedGroomId, selectedGroomName, '0')">
            <i class="fas fa-arrow-right"></i> رجوع
        </button>
    `;
    
    // إضافة حدث Enter
    setTimeout(() => {
        const emailInput = document.getElementById('emailInput');
        if (emailInput) {
            emailInput.focus();
            emailInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    subscribeEmail();
                }
            });
        }
    }, 100);
}

// إغلاق النافذة
function closeNotificationModal() {
    document.getElementById('notificationModal').classList.remove('active');
    selectedGroomId = null;
    selectedGroomName = '';
    isProcessing = false;
}

// التمرير للحفلات
function scrollToEvents() {
    const section = document.getElementById('eventsSection');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// إضافة أحداث الضغط على الكروت
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ الصفحة جاهزة');
    
    const eventCards = document.querySelectorAll('.event-card');
    console.log('عدد الكروت:', eventCards.length);
    
    eventCards.forEach(function(card) {
        card.addEventListener('click', function() {
            const groomId = this.getAttribute('data-groom-id');
            const groomName = this.getAttribute('data-groom-name');
            const ready = this.getAttribute('data-ready');
            
            console.log('تم الضغط على:', groomName, 'Ready:', ready);
            
            openNotificationModal(groomId, groomName, ready);
        });
    });
    
    // إغلاق بالضغط على الخلفية
    document.getElementById('notificationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeNotificationModal();
        }
    });
    
    // إغلاق بزر ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('notificationModal');
            if (modal.classList.contains('active')) {
                closeNotificationModal();
            }
        }
    });
});

console.log('✅ السكريبت محمل بنجاح');
    </script>
</body>
</html>