<?php
// rate.php
require_once 'config/database.php';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$error = null;
$success = false;
$groomData = null;

if (!isset($_GET['token'])) {
    $error = "رابط غير صالح";
} else {
    $token = $_GET['token'];
    $stmt = $conn->prepare("
        SELECT g.*, rt.used, rt.expires_at 
        FROM rating_tokens rt 
        JOIN grooms g ON rt.groom_id = g.id 
        WHERE rt.token = ? AND rt.expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = "الرابط منتهي الصلاحية أو غير صالح";
    } else {
        $groomData = $result->fetch_assoc();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name']);
            $rating = intval($_POST['rating']);
            $message = trim($_POST['message']);
            
            if ($rating >= 1 && $rating <= 5 && !empty($name)) {
                $stmt = $conn->prepare("
                    INSERT INTO groom_reviews (groom_id, name, rating, message, is_approved) 
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmt->bind_param("isis", $groomData['id'], $name, $rating, $message);
                
                if ($stmt->execute()) {
                    $updateStmt = $conn->prepare("UPDATE rating_tokens SET used = 1 WHERE token = ?");
                    $updateStmt->bind_param("s", $token);
                    $updateStmt->execute();
                    $success = true;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تقييم الخدمة - جذلة للتصوير الفوتوغرافي</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold-dark: #8B6914;
            --gold-medium: #C9A651;
            --gold-light: #E8D5A8;
            --gold-shine: #FFD700;
            --bg-cream: #FAF8F3;
            --bg-white: #FFFFFF;
            --text-dark: #3D3526;
            --text-medium: #5D4E37;
            --shadow-gold: rgba(201, 166, 81, 0.2);
            --shadow-strong: rgba(61, 53, 38, 0.15);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            -webkit-tap-highlight-color: transparent;
        }
        
        html {
            overflow-x: hidden;
            width: 100%;
            -webkit-text-size-adjust: 100%;
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(165deg, 
                #FAF8F3 0%, 
                #F5F1E8 25%, 
                #EDE6D8 50%, 
                #E8DFC8 75%, 
                #DDD4BD 100%);
            min-height: 100vh;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            width: 100%;
            color: var(--text-dark);
        }
        
        body::before {
            content: '';
            position: fixed;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255, 215, 0, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(201, 166, 81, 0.04) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(139, 105, 20, 0.02) 0%, transparent 50%);
            animation: gentleFloat 25s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        
        @keyframes gentleFloat {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(1deg); }
            66% { transform: translate(-20px, 20px) rotate(-1deg); }
        }
        
        .main-wrapper {
            max-width: 520px;
            width: 100%;
            position: relative;
            z-index: 1;
            padding: 16px;
            margin: 0 auto;
        }
        
        .container {
            width: 100%;
            background: var(--bg-white);
            border-radius: 24px;
            padding: 28px 22px;
            box-shadow: 
                0 8px 32px var(--shadow-gold),
                0 2px 8px var(--shadow-strong),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            position: relative;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(201, 166, 81, 0.15);
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                var(--gold-medium) 25%, 
                var(--gold-shine) 50%, 
                var(--gold-medium) 75%, 
                transparent 100%);
            opacity: 0.6;
        }
        
        .decorative-corner {
            position: absolute;
            width: 60px;
            height: 60px;
            opacity: 0.08;
        }
        
        .decorative-corner.top-right {
            top: 0;
            right: 0;
            background: linear-gradient(135deg, var(--gold-medium), transparent);
            border-top-right-radius: 24px;
        }
        
        .decorative-corner.bottom-left {
            bottom: 0;
            left: 0;
            background: linear-gradient(-45deg, var(--gold-medium), transparent);
            border-bottom-left-radius: 24px;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 24px;
            animation: fadeInDown 0.6s ease-out;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo-section img {
            max-width: 130px;
            height: auto;
            margin-bottom: 16px;
            filter: drop-shadow(0 4px 12px rgba(201, 166, 81, 0.2));
            transition: transform 0.3s ease;
        }
        
        .logo-section img:active {
            transform: scale(0.98);
        }
        
        .page-title {
            color: var(--gold-dark);
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }
        
        .page-subtitle {
            color: var(--text-medium);
            font-size: 14px;
            font-weight: 400;
            opacity: 0.85;
        }
        
        .groom-info {
            background: linear-gradient(135deg, 
                #FFFDF7 0%, 
                #FFF9ED 50%, 
                #FFF5E1 100%);
            padding: 18px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            text-align: center;
            border: 1.5px solid var(--gold-light);
            box-shadow: 
                0 4px 16px rgba(201, 166, 81, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            position: relative;
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .groom-info::before,
        .groom-info::after {
            content: '✨';
            position: absolute;
            font-size: 14px;
            opacity: 0.3;
        }
        
        .groom-info::before {
            top: 10px;
            right: 15px;
        }
        
        .groom-info::after {
            bottom: 10px;
            left: 15px;
        }
        
        .groom-info h2 {
            color: var(--gold-dark);
            font-size: 19px;
            font-weight: 700;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease-out 0.3s both;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s ease;
        }
        
        input[type="text"],
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gold-light);
            border-radius: 14px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: var(--bg-cream);
            color: var(--text-dark);
            font-weight: 400;
        }
        
        input[type="text"]::placeholder,
        textarea::placeholder {
            color: var(--text-medium);
            opacity: 0.5;
        }
        
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--gold-medium);
            background: var(--bg-white);
            box-shadow: 
                0 0 0 4px rgba(201, 166, 81, 0.1),
                0 2px 8px rgba(201, 166, 81, 0.15);
            transform: translateY(-1px);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
            line-height: 1.6;
        }
        
        .rating-container {
            background: linear-gradient(135deg, 
                #FFFEFB 0%, 
                #FFFCF5 100%);
            padding: 24px 18px;
            border-radius: 18px;
            margin: 24px 0;
            border: 1.5px solid var(--gold-light);
            box-shadow: 
                0 4px 16px rgba(201, 166, 81, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.95);
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }
        
        .rating-label {
            text-align: center;
            color: var(--gold-dark);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -0.2px;
        }
        
        .rating-stars {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            margin: 24px 0;
            direction: rtl;
            padding: 0 4px;
        }
        
        .star {
            width: 52px;
            height: 52px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            flex-shrink: 0;
            position: relative;
        }
        
        .star svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 3px 8px rgba(201, 166, 81, 0.2));
            transition: filter 0.3s ease;
        }
        
        .star:not(.active) .star-fill {
            fill: #F5F0E5;
            stroke: var(--gold-light);
            stroke-width: 2;
            transition: all 0.3s ease;
        }
        
        .star.active .star-fill {
            fill: url(#goldGradient);
            stroke: var(--gold-dark);
            stroke-width: 2.5;
        }
        
        .star:active {
            transform: scale(0.9);
        }
        
        .star.active {
            animation: starBounce 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        @keyframes starBounce {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .star.active svg {
            filter: drop-shadow(0 4px 12px rgba(255, 215, 0, 0.4));
        }
        
        .rating-text {
            text-align: center;
            color: var(--gold-dark);
            font-size: 15px;
            font-weight: 600;
            margin-top: 16px;
            min-height: 24px;
            transition: all 0.3s ease;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, 
                var(--gold-medium) 0%, 
                var(--gold-dark) 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 
                0 6px 20px rgba(201, 166, 81, 0.3),
                0 2px 4px rgba(139, 105, 20, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            margin-top: 8px;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out 0.5s both;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        
        .btn:active::before {
            width: 300px;
            height: 300px;
        }
        
        .btn:active {
            transform: scale(0.98);
            box-shadow: 
                0 4px 12px rgba(201, 166, 81, 0.35),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn:disabled:active {
            transform: none;
        }
        
        .success-message {
            text-align: center;
            padding: 40px 20px;
            animation: fadeInScale 0.6s ease-out;
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: successPulse 1s ease-in-out;
        }
        
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }
        
        .success-message h2 {
            color: var(--gold-dark);
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 14px;
        }
        
        .success-message p {
            color: var(--text-medium);
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 12px;
            font-weight: 400;
        }
        
        .success-brand {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 2px solid var(--gold-light);
        }
        
        .success-brand img {
            max-width: 130px;
            margin-bottom: 12px;
            filter: drop-shadow(0 2px 8px rgba(201, 166, 81, 0.15));
        }
        
        .success-brand strong {
            color: var(--gold-dark);
            font-size: 17px;
            font-weight: 800;
        }
        
        .error-message {
            background: linear-gradient(135deg, #FFF5F5 0%, #FFECEC 100%);
            color: #C53030;
            padding: 28px 24px;
            border-radius: 18px;
            text-align: center;
            border: 2px solid rgba(197, 48, 48, 0.15);
            box-shadow: 0 4px 16px rgba(197, 48, 48, 0.1);
            animation: fadeInScale 0.6s ease-out;
        }
        
        .error-message h2 {
            font-size: 24px;
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .error-message p {
            font-size: 16px;
            line-height: 1.6;
        }
        
        .instagram-section {
            margin-top: 20px;
            width: 100%;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out 0.6s both;
        }
        
        .instagram-section iframe {
            background: white;
            width: 100%;
            max-width: 100%;
            border-radius: 4px;
            border: 1px solid rgba(201, 166, 81, 0.2);
            box-shadow: 0 4px 16px rgba(201, 166, 81, 0.12);
            display: block;
            margin: 0 auto;
        }
        
        .whatsapp-float {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 24px;
            left: 24px;
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            border-radius: 50%;
            text-align: center;
            font-size: 30px;
            box-shadow: 
                0 6px 24px rgba(37, 211, 102, 0.4),
                0 2px 8px rgba(18, 140, 126, 0.3);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            animation: floatPulse 3s ease-in-out infinite;
            text-decoration: none;
        }
        
        @keyframes floatPulse {
            0%, 100% {
                box-shadow: 
                    0 6px 24px rgba(37, 211, 102, 0.4),
                    0 2px 8px rgba(18, 140, 126, 0.3);
            }
            50% {
                box-shadow: 
                    0 8px 32px rgba(37, 211, 102, 0.6),
                    0 4px 12px rgba(18, 140, 126, 0.4);
                transform: translateY(-4px);
            }
        }
        
        .whatsapp-float:active {
            transform: scale(0.92) translateY(0);
        }
        
        /* Desktop optimization */
        @media (min-width: 769px) {
            .main-wrapper {
                padding: 32px 24px;
            }
            
            .container {
                padding: 40px 36px;
                border-radius: 28px;
            }
            
            .logo-section img {
                max-width: 160px;
            }
            
            .page-title {
                font-size: 26px;
            }
            
            .page-subtitle {
                font-size: 16px;
            }
            
            .groom-info {
                padding: 22px 26px;
            }
            
            .groom-info h2 {
                font-size: 22px;
            }
            
            .star {
                width: 58px;
                height: 58px;
            }
            
            .rating-stars {
                gap: 12px;
            }
            
            .star:hover {
                transform: scale(1.15);
            }
            
            .star:hover svg {
                filter: drop-shadow(0 4px 16px rgba(201, 166, 81, 0.35));
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 
                    0 8px 28px rgba(201, 166, 81, 0.4),
                    0 4px 8px rgba(139, 105, 20, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.25);
            }
            
            .whatsapp-float {
                width: 64px;
                height: 64px;
                font-size: 32px;
            }
            
            .whatsapp-float:hover {
                transform: scale(1.1) translateY(-4px);
            }
        }
        
        /* Tablet */
        @media (min-width: 481px) and (max-width: 768px) {
            .star {
                width: 54px;
                height: 54px;
            }
            
            .rating-stars {
                gap: 10px;
            }
        }
        
        /* Small phones */
        @media (max-width: 380px) {
            .main-wrapper {
                padding: 12px;
            }
            
            .container {
                padding: 24px 18px;
                border-radius: 20px;
            }
            
            .logo-section img {
                max-width: 110px;
            }
            
            .page-title {
                font-size: 19px;
            }
            
            .page-subtitle {
                font-size: 13px;
            }
            
            .groom-info {
                padding: 16px 18px;
            }
            
            .groom-info h2 {
                font-size: 17px;
            }
            
            .star {
                width: 44px;
                height: 44px;
            }
            
            .rating-stars {
                gap: 6px;
            }
            
            .rating-label {
                font-size: 15px;
            }
            
            label {
                font-size: 13px;
            }
            
            input[type="text"],
            textarea {
                font-size: 14px;
                padding: 12px 14px;
            }
            
            .btn {
                font-size: 16px;
                padding: 14px;
            }
            
            .whatsapp-float {
                width: 54px;
                height: 54px;
                bottom: 18px;
                left: 18px;
                font-size: 26px;
            }
        }
        
        /* Extra small phones */
        @media (max-width: 320px) {
            .star {
                width: 40px;
                height: 40px;
            }
            
            .rating-stars {
                gap: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <div class="container">
            <div class="decorative-corner top-right"></div>
            <div class="decorative-corner bottom-left"></div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <h2>❌ عذراً</h2>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php elseif ($success): ?>
                <div class="success-message">
                    <div class="success-icon">✨🎉✨</div>
                    <h2>شكراً لك!</h2>
                    <p>تم استلام تقييمك بنجاح</p>
                    <p>نقدّر وقتك وآرائك القيّمة</p>
                    <div class="success-brand">
                        <img src="/assets/whiti_logo_jadhlah_t.svg" alt="جذلة">
                        <p><strong>جذلة للتصوير الفوتوغرافي</strong></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="logo-section">
                    <img src="/assets/whiti_logo_jadhlah_t.svg" alt="جذلة">
                    <h1 class="page-title">قيّم تجربتك معنا</h1>
                    <p class="page-subtitle">رأيك يهمنا ويساعدنا على التطور</p>
                </div>
                
                <div class="groom-info">
                    <h2>حفل زواج <?php echo htmlspecialchars($groomData['groom_name']); ?></h2>
                </div>
                
                <form method="POST" id="ratingForm">
                    <div class="form-group">
                        <label for="name">اسمك الكريم *</label>
                        <input type="text" id="name" name="name" required placeholder="مثال: أحمد محمد">
                    </div>
                    
                    <div class="rating-container">
                        <div class="rating-label">كيف كانت تجربتك معنا؟</div>
                        <div class="rating-stars" id="ratingStars">
                            <div class="star" data-rating="5">
                                <svg viewBox="0 0 24 24">
                                    <defs>
                                        <linearGradient id="goldGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:#FFD700;stop-opacity:1" />
                                            <stop offset="30%" style="stop-color:#FFC700;stop-opacity:1" />
                                            <stop offset="70%" style="stop-color:#C9A651;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#8B6914;stop-opacity:1" />
                                        </linearGradient>
                                    </defs>
                                    <path class="star-fill" d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                </svg>
                            </div>
                            <div class="star" data-rating="4">
                                <svg viewBox="0 0 24 24"><path class="star-fill" d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                            </div>
                            <div class="star" data-rating="3">
                                <svg viewBox="0 0 24 24"><path class="star-fill" d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                            </div>
                            <div class="star" data-rating="2">
                                <svg viewBox="0 0 24 24"><path class="star-fill" d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                            </div>
                            <div class="star" data-rating="1">
                                <svg viewBox="0 0 24 24"><path class="star-fill" d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                            </div>
                        </div>
                        <div class="rating-text" id="ratingText">اختر تقييمك</div>
                        <input type="hidden" name="rating" id="ratingValue" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">رسالتك (اختياري)</label>
                        <textarea id="message" name="message" placeholder="شاركنا تجربتك..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn" id="submitBtn" disabled>✨ إرسال التقييم</button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if (!$error && !$success): ?>
        <div class="instagram-section">
            <iframe src="https://www.instagram.com/jadhlah/embed/?cr=1&v=14" 
                    allowtransparency="true" 
                    allowfullscreen="true" 
                    frameborder="0" 
                    height="474" 
                    scrolling="no"></iframe>
            <script async src="//www.instagram.com/embed.js"></script>
        </div>
        <?php endif; ?>
    </div>

    <a href="https://wa.me/966XXXXXXXXX?text=مرحباً، أرغب في حجز جلسة تصوير" 
       class="whatsapp-float" 
       target="_blank"
       aria-label="تواصل عبر واتساب">
        <i class="fab fa-whatsapp"></i>
    </a>

    <script>
        const stars = document.querySelectorAll('.star');
        const ratingValue = document.getElementById('ratingValue');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('submitBtn');
        let selectedRating = 0;
        
        const ratingTexts = {
            1: 'ضعيف جداً 😞',
            2: 'ضعيف 😕',
            3: 'مقبول 😐',
            4: 'جيد 😊',
            5: 'ممتاز 🌟'
        };

        stars.forEach(star => {
            star.addEventListener('click', function(e) {
                e.preventDefault();
                selectedRating = parseInt(this.dataset.rating);
                ratingValue.value = selectedRating;
                
                stars.forEach(s => {
                    const starRating = parseInt(s.dataset.rating);
                    if (starRating <= selectedRating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
                
                ratingText.textContent = ratingTexts[selectedRating];
                ratingText.style.color = 'var(--gold-dark)';
                submitBtn.disabled = false;
                
                // Haptic feedback for mobile
                if (navigator.vibrate) {
                    navigator.vibrate(10);
                }
            });
            
            // Touch feedback
            star.addEventListener('touchstart', function() {
                this.style.opacity = '0.7';
            });
            
            star.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        });
        
        document.getElementById('ratingForm').addEventListener('submit', function(e) {
            if (selectedRating === 0) {
                e.preventDefault();
                ratingText.textContent = '⚠️ الرجاء اختيار التقييم';
                ratingText.style.color = '#C53030';
                
                // Shake animation
                const ratingContainer = document.querySelector('.rating-container');
                ratingContainer.style.animation = 'none';
                setTimeout(() => {
                    ratingContainer.style.animation = 'shake 0.5s ease';
                }, 10);
                
                setTimeout(() => {
                    ratingText.textContent = 'اختر تقييمك';
                    ratingText.style.color = 'var(--gold-dark)';
                }, 2000);
                
                if (navigator.vibrate) {
                    navigator.vibrate([50, 100, 50]);
                }
            }
        });
        
        // Add shake animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-10px); }
                75% { transform: translateX(10px); }
            }
        `;
        document.head.appendChild(style);
        
        // Smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>