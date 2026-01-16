<?php
$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
    exit;
}

// جلب آخر 6 عرسان نشطين وجاهزين
$grooms = $pdo->query(
    "SELECT id, groom_name, wedding_date, hall_name, folder_name
     FROM grooms
     WHERE is_blocked = 0 
     AND is_active = 1
     AND ready = 1
     ORDER BY id DESC
     LIMIT 6"
)->fetchAll();

// جلب صور مميزة من معرض الأعمال
$galleryPhotos = $pdo->query(
    "SELECT gp.*, g.groom_name, g.id as groom_id 
     FROM groom_photos gp 
     JOIN grooms g ON gp.groom_id = g.id 
     WHERE gp.hidden = 0 
     AND gp.featured_for_gallery = 1 
     ORDER BY gp.likes DESC
     LIMIT 12"
)->fetchAll();

// جلب آخر التقييمات المعتمدة
$reviews = $pdo->query(
    "SELECT r.name, r.message, r.rating, g.groom_name
     FROM groom_reviews r
     JOIN grooms g ON g.id = r.groom_id
     WHERE r.is_approved = 1
     AND r.blocked = 0
     ORDER BY r.id DESC
     LIMIT 6"
)->fetchAll();

// دالة للحصول على مسار صورة البنر
function getBannerPath($groomId) {
    $bannerPaths = [
        "/grooms/{$groomId}/images/banner.jpg",
        "/grooms/{$groomId}/images/banner.jpeg",
        "/grooms/{$groomId}/images/banner.png",
        "/grooms/{$groomId}/banner.jpg",
        "/grooms/{$groomId}/banner.jpeg",
        "/grooms/{$groomId}/banner.png",
        "/grooms/{$groomId}/watermarked/banner.jpg",
        "/grooms/{$groomId}/originals/banner.jpg"
    ];
    
    foreach ($bannerPaths as $path) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            return $path;
        }
    }
    
    // إذا لم توجد صورة، نستخدم gradient
    return null;
}

// دالة للحصول على مسار صورة المعرض
function getGalleryImagePath($groomId, $filename) {
    if (empty($groomId) || empty($filename)) return false;
    
    $paths = [
        "/grooms/{$groomId}/watermarked/{$filename}",
        "/grooms/{$groomId}/thumbs/{$filename}",
        "/grooms/{$groomId}/originals/{$filename}"
    ];
    
    foreach ($paths as $path) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            return $path;
        }
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>جذلة | الرئيسية - تصوير المناسبات الفاخر</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, shrink-to-fit=no">
    <meta name="description" content="جذلة - تصوير احترافي للمناسبات والأفراح. نوثق أجمل لحظاتكم بعدسة إبداعية">
    <meta name="theme-color" content="#D4AF37">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <!-- Tailwind CSS -->
    <!-- ملاحظة: استخدام CDN للتطوير فقط، للإنتاج يفضل تثبيت Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome للأيقونات -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS للتحريكات -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Swiper للسلايدر -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <style>
        /* خط عام الإبل */
        @font-face {
            font-family: 'The Year of The Camel';
            font-weight: 100 300;
            src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtTGlnaHQub3RmMTcwNDYyMzYzODQwNQ==.otf');
        }
        
        @font-face {
            font-family: 'The Year of The Camel';
            font-weight: 400 600;
            src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtUmVndWxhci5vdGYxNzA0NjIzMjM0MzE5.otf');
        }
        
        @font-face {
            font-family: 'The Year of The Camel';
            font-weight: 700 900;
            src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtQm9sZC5vdGYxNzA0NjIzNjY3ODA1.otf');
        }
        
        /* تطبيق الخط */
        * {
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
        }
        
        body { 
            font-family: 'The Year of The Camel', 'Tajawal', sans-serif;
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overscroll-behavior-y: none;
            background: #0a0a0a;
            overflow-x: hidden;
            max-width: 100vw;
        }
        
        * {
            box-sizing: border-box;
        }
        
        img {
            max-width: 100%;
            height: auto;
        }
        
        /* منع التوسع الأفقي */
        .container {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        h1, h2, .main-title {
            font-family: 'The Year of The Camel', 'Tajawal', sans-serif;
            font-weight: 800;
        }
        
        h3, h4, h5, h6, .sub-title {
            font-family: 'The Year of The Camel', 'Tajawal', sans-serif;
            font-weight: 600;
        }
        
        /* ألوان جذلة */
        :root {
            --gold: #D4AF37;
            --gold-dark: #B8941E;
            --gold-light: #F4E5C2;
            --black: #0a0a0a;
            --gray-dark: #1a1a1a;
        }
        
        /* تحسينات الأداء */
        img {
            content-visibility: auto;
        }
        
        /* الهيرو */
        .hero-section {
            position: relative;
            height: 75vh;
            min-height: 500px;
            background-image: url('/assets/h_banner.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            overflow: hidden;
        }
        
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(10, 10, 10, 0.4),
                rgba(10, 10, 10, 0.6) 50%,
                rgba(10, 10, 10, 0.8)
            );
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0 1rem;
        }
        
        /* النافبار للموبايل */
        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(212, 175, 55, 0.2);
            z-index: 9999;
            padding: 0.5rem 0;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }
        
        .mobile-nav.hidden {
            transform: translateY(100%);
        }
        
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            padding: 0.5rem;
            color: #999;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .mobile-nav-item.active,
        .mobile-nav-item:active {
            color: var(--gold);
            transform: scale(1.1);
        }
        
        .mobile-nav-item i {
            font-size: 1.25rem;
        }
        
        .mobile-nav-item span {
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        /* قسم من نحن */
        .about-section {
            background: linear-gradient(135deg, var(--gray-dark) 0%, var(--black) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .about-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        /* كروت العرسان */
        .groom-card {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .groom-card:active {
            transform: scale(0.95);
        }
        
        @media (min-width: 768px) {
            .groom-card:hover {
                transform: translateY(-10px);
                box-shadow: 0 20px 40px rgba(212, 175, 55, 0.3);
            }
        }
        
        /* معرض الأعمال */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        
        @media (min-width: 640px) {
            .gallery-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
        }
        
        @media (min-width: 1024px) {
            .gallery-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .gallery-item {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .gallery-item:active img {
            transform: scale(1.05);
        }
        
        @media (min-width: 768px) {
            .gallery-item:hover img {
                transform: scale(1.1);
            }
        }
        
        .gallery-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: flex-end;
            padding: 1rem;
        }
        
        .gallery-item:active .gallery-overlay,
        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }
        
        /* سلايدر الخدمات */
        .services-swiper {
            padding: 1rem 0 2rem 0;
        }
        
        .service-card {
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.9), rgba(10, 10, 10, 0.9));
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 1rem;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .service-card:active {
            transform: scale(0.95);
            border-color: var(--gold);
        }
        
        /* قسم البث الحي */
        .live-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
            position: relative;
        }
        
        .live-pulse {
            animation: live-pulse 2s ease-in-out infinite;
        }
        
        @keyframes live-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }
        
        /* أزرار CTA */
        .cta-button {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            color: var(--black);
            padding: 1rem 2rem;
            border-radius: 9999px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }
        
        .cta-button:active {
            transform: scale(0.95);
            box-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);
        }
        
        @media (min-width: 768px) {
            .cta-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(212, 175, 55, 0.5);
            }
        }
        
        /* Scroll Snap للموبايل */
        .scroll-snap {
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            max-width: 100vw;
        }
        
        .scroll-snap::-webkit-scrollbar {
            display: none;
        }
        
        .snap-child {
            scroll-snap-align: start;
            flex-shrink: 0;
        }
        
        /* تحسينات سفاري */
        @supports (-webkit-touch-callout: none) {
            .hero-video {
                object-fit: cover;
                -webkit-transform: translate(-50%, -50%);
            }
            
            input, textarea, select {
                font-size: 16px !important;
            }
        }
        
        /* تحسينات الأداء */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }
        
        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #1a1a1a 25%, #2a2a2a 50%, #1a1a1a 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s ease-in-out infinite;
        }
        
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* مسافة للنافبار في الموبايل */
        @media (max-width: 768px) {
            body {
                padding-bottom: 70px;
            }
        }
        
        /* شاشة التحميل */
        #preloader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: linear-gradient(135deg, var(--black) 0%, var(--gray-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        #preloader.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .preloader-content {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .loader-logo {
            width: 80px;
            height: auto;
            margin: 0 auto 1.5rem;
            animation: pulse-logo 2s ease-in-out infinite;
        }
        
        @media (min-width: 768px) {
            .loader-logo {
                width: 96px;
            }
        }
        
        @keyframes pulse-logo {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        
        .loader-dots {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .loader-dots span {
            display: block;
            width: 8px;
            height: 8px;
            background: var(--gold);
            border-radius: 50%;
            animation: bounce-dot 1.4s ease-in-out infinite;
        }
        
        .loader-dots span:nth-child(1) {
            animation-delay: 0s;
        }
        
        .loader-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .loader-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes bounce-dot {
            0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

<!-- شاشة التحميل -->
<div id="preloader">
    <div class="preloader-content">
        <img src="/assets/black_logo_jadhlah_t.svg" 
             alt="جذلة" 
             class="loader-logo">
        <div class="loader-dots">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</div>

<!-- النافبار للموبايل -->
<nav class="mobile-nav md:hidden">
    <div class="flex justify-around items-center max-w-lg mx-auto">
        <a href="#home" class="mobile-nav-item active">
            <i class="fas fa-home"></i>
            <span>الرئيسية</span>
        </a>
        <a href="gallery.php" class="mobile-nav-item">
            <i class="fas fa-images"></i>
            <span>المعرض</span>
        </a>
        <a href="landing.php" class="mobile-nav-item">
            <i class="fas fa-calendar-check"></i>
            <span>الحفلات</span>
        </a>
        <a href="services.php" class="mobile-nav-item">
            <i class="fas fa-concierge-bell"></i>
            <span>الخدمات</span>
        </a>
        <a href="#contact" class="mobile-nav-item">
            <i class="fas fa-phone"></i>
            <span>تواصل</span>
        </a>
    </div>
</nav>

<!-- قسم الهيرو -->
<section id="home" class="hero-section">
    <div class="hero-overlay"></div>
    
    <div class="hero-content">
        <div data-aos="fade-down" data-aos-duration="1000">
            <img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="w-32 md:w-48 mx-auto mb-6 md:mb-8">
        </div>
        
        <h1 class="text-3xl md:text-5xl lg:text-6xl font-bold text-white mb-4 md:mb-6" 
            data-aos="fade-up" 
            data-aos-duration="1000" 
            data-aos-delay="200"
            style="text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);">
            لأن القلب يعيش هذه الليلة <span style="color: var(--gold);">ألف مرة</span> بعدستنا
        </h1>
        
        <p class="text-base md:text-xl text-gray-200 mb-8 md:mb-12 max-w-2xl" 
           data-aos="fade-up" 
           data-aos-duration="1000" 
           data-aos-delay="400"
           style="text-shadow: 0 2px 10px rgba(0, 0, 0, 0.7);">
            نوثق أجمل اللحظات بإبداع واحترافية
        </p>
        
        <div class="flex flex-col sm:flex-row gap-4" 
             data-aos="fade-up" 
             data-aos-duration="1000" 
             data-aos-delay="600">
            <a href="#grooms" class="cta-button">
                <i class="fas fa-images"></i>
                <span>شاهد أعمالنا</span>
            </a>
            <a href="gallery.php" class="cta-button" style="background: transparent; border: 2px solid var(--gold); color: var(--gold);">
                <i class="fas fa-photo-film"></i>
                <span>معرض الصور والفيديو</span>
            </a>
        </div>
        
        <!-- سكرول للأسفل -->
        <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 text-white animate-bounce">
            <i class="fas fa-chevron-down text-2xl" style="color: var(--gold);"></i>
        </div>
    </div>
</section>

<!-- قسم من نحن -->
<section class="about-section py-16 md:py-24" id="about">
    <div class="container mx-auto px-4 relative z-10">
        <div class="max-w-6xl mx-auto">
            <div class="grid md:grid-cols-2 gap-8 md:gap-12 items-center">
                <!-- الجزء الأيمن: النص والمحتوى -->
                <div data-aos="fade-right" data-aos-duration="1000" class="order-2 md:order-1">
                    <!-- العنوان الرئيسي -->
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6 leading-tight">
                        <span class="text-white">ليلة واحدة</span>
                        <br>
                        <span style="color: var(--gold);">و ذكرى لكل العمر</span>
                    </h2>
                    
                    <!-- الوصف العاطفي -->
                    <div class="mb-8 space-y-3">
                        <p class="text-lg md:text-xl text-gray-300 leading-relaxed">
                            نوثق مشاعر <span class="font-bold" style="color: var(--gold);">ما تنعاد مرتين</span>
                        </p>
                        <p class="text-base md:text-lg text-gray-400 leading-relaxed">
                            و نعيش العريس ليلة زواجه <span class="font-semibold text-white">ألف مرة</span> 
                            <br class="hidden md:block">
                            كل ما رجع للصور عاش اللحظة من جديد!
                        </p>
                    </div>
                    
                    <!-- ليش جذلة؟ -->
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold mb-6 text-white">
                            ليش <span style="color: var(--gold);">جذلة</span>؟
                        </h3>
                        
                        <div class="grid gap-4">
                            <!-- ميزة 1 -->
                            <div class="flex items-start gap-3 group">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300"
                                     style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2)); border: 2px solid rgba(212, 175, 55, 0.3);">
                                    <i class="fas fa-users text-lg" style="color: var(--gold);"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white mb-1">فريق إحترافي خبير</h4>
                                    <p class="text-sm text-gray-400">مصورون محترفون بخبرة 15+ عام</p>
                                </div>
                            </div>
                            
                            <!-- ميزة 2 -->
                            <div class="flex items-start gap-3 group">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300"
                                     style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2)); border: 2px solid rgba(212, 175, 55, 0.3);">
                                    <i class="fas fa-palette text-lg" style="color: var(--gold);"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white mb-1">نظرة فنية فاخرة</h4>
                                    <p class="text-sm text-gray-400">كل صورة تحفة فنية بلمسة إبداعية</p>
                                </div>
                            </div>
                            
                            <!-- ميزة 3 -->
                            <div class="flex items-start gap-3 group">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300"
                                     style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2)); border: 2px solid rgba(212, 175, 55, 0.3);">
                                    <i class="fas fa-shield-check text-lg" style="color: var(--gold);"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white mb-1">موثوق و مضمون</h4>
                                    <p class="text-sm text-gray-400">98% نسبة رضا العملاء</p>
                                </div>
                            </div>
                            
                            <!-- ميزة 4 -->
                            <div class="flex items-start gap-3 group">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300"
                                     style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2)); border: 2px solid rgba(212, 175, 55, 0.3);">
                                    <i class="fas fa-microchip text-lg" style="color: var(--gold);"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white mb-1">خدمات تقنية مبتكرة</h4>
                                    <p class="text-sm text-gray-400">بث مباشر، طباعة فورية، وأكثر</p>
                                </div>
                            </div>
                            
                            <!-- ميزة 5 -->
                            <div class="flex items-start gap-3 group">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300"
                                     style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2)); border: 2px solid rgba(212, 175, 55, 0.3);">
                                    <i class="fas fa-rocket text-lg" style="color: var(--gold);"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white mb-1">تسليم سريع و آمن</h4>
                                    <p class="text-sm text-gray-400">صورك جاهزة خلال ساعات من الحفل</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- الجملة الختامية -->
                    <div class="relative inline-block">
                        <p class="text-xl md:text-2xl font-bold text-white italic">
                            و الباقي <span style="color: var(--gold);">على كيفك</span> 🎉
                        </p>
                        <div class="absolute -bottom-2 left-0 right-0 h-1 rounded-full" 
                             style="background: linear-gradient(90deg, var(--gold), transparent);"></div>
                    </div>
                </div>
                
                <!-- الجزء الأيسر: الإحصائيات والتصميم -->
                <div data-aos="fade-left" data-aos-duration="1000" class="order-1 md:order-2">
                    <!-- بطاقة رئيسية -->
                    <div class="relative">
                        <!-- الدائرة الخلفية المتوهجة -->
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-64 h-64 md:w-80 md:h-80 rounded-full blur-3xl opacity-20"
                                 style="background: radial-gradient(circle, var(--gold) 0%, transparent 70%);"></div>
                        </div>
                        
                        <!-- المحتوى -->
                        <div class="relative z-10 text-center">
                            <!-- أيقونة كبيرة -->
                            <div class="mb-8 inline-block p-8 rounded-full relative group"
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(184, 148, 30, 0.1)); border: 3px solid rgba(212, 175, 55, 0.3);">
                                <i class="fas fa-camera-retro text-6xl md:text-7xl" 
                                   style="color: var(--gold);"></i>
                                <!-- تأثير النبض -->
                                <div class="absolute inset-0 rounded-full border-2 animate-ping opacity-20"
                                     style="border-color: var(--gold);"></div>
                            </div>
                            
                            <!-- الإحصائيات -->
                            <div class="grid grid-cols-3 gap-4 mb-6">
                                <!-- إحصائية 1 -->
                                <div class="p-4 rounded-xl backdrop-blur-sm transition-all duration-300 hover:scale-105"
                                     style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(10, 10, 10, 0.8)); border: 1px solid rgba(212, 175, 55, 0.2);">
                                    <div class="text-3xl md:text-4xl font-bold mb-2" style="color: var(--gold);">15+</div>
                                    <div class="text-xs md:text-sm text-gray-400">سنة خبرة</div>
                                </div>
                                
                                <!-- إحصائية 2 -->
                                <div class="p-4 rounded-xl backdrop-blur-sm transition-all duration-300 hover:scale-105"
                                     style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(10, 10, 10, 0.8)); border: 1px solid rgba(212, 175, 55, 0.2);">
                                    <div class="text-3xl md:text-4xl font-bold mb-2" style="color: var(--gold);">500+</div>
                                    <div class="text-xs md:text-sm text-gray-400">حفل موثق</div>
                                </div>
                                
                                <!-- إحصائية 3 -->
                                <div class="p-4 rounded-xl backdrop-blur-sm transition-all duration-300 hover:scale-105"
                                     style="background: linear-gradient(135deg, rgba(26, 26, 26, 0.8), rgba(10, 10, 10, 0.8)); border: 1px solid rgba(212, 175, 55, 0.2);">
                                    <div class="text-3xl md:text-4xl font-bold mb-2" style="color: var(--gold);">98%</div>
                                    <div class="text-xs md:text-sm text-gray-400">رضا العملاء</div>
                                </div>
                            </div>
                            
                            <!-- شعار إضافي -->
                            <div class="inline-block px-6 py-3 rounded-full text-sm font-semibold"
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2)); border: 1px solid rgba(212, 175, 55, 0.4); color: var(--gold);">
                                <i class="fas fa-award mr-2"></i>
                                الأفضل في المملكة
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- قسم أحدث العرسان -->
<section class="py-16 md:py-24" id="grooms" style="background: var(--black);">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12" data-aos="fade-up">
            <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                أحدث <span style="color: var(--gold);">العرسان</span>
            </h2>
            <p class="text-gray-400 text-lg">اكتشف أحدث معارضنا المميزة</p>
            <div class="w-20 h-1 mx-auto mt-4" style="background: var(--gold);"></div>
        </div>
        
        <?php if (count($grooms) > 0): ?>
        <div class="overflow-x-auto scroll-snap pb-4">
            <div class="flex gap-4 md:gap-6 px-2">
                <?php foreach ($grooms as $groom): 
                    $id = $groom['id'];
                    $name = htmlspecialchars($groom['groom_name']);
                    $date = date('d M Y', strtotime($groom['wedding_date']));
                    $hall = htmlspecialchars($groom['hall_name']);
                    $bgImage = getBannerPath($id);
                    
                    // إذا لم توجد صورة، استخدم gradient
                    $bgStyle = $bgImage 
                        ? "background-image: url('{$bgImage}');" 
                        : "background: linear-gradient(135deg, #D4AF37 0%, #B8941E 100%);";
                ?>
                <a href="/groom.php?groom=<?= $id ?>" 
                   class="snap-child min-w-[280px] sm:min-w-[320px] block groom-card"
                   data-aos="fade-up"
                   data-aos-delay="<?= $loop ?? 0 * 100 ?>">
                    <div class="h-72 md:h-80 bg-cover bg-center rounded-xl overflow-hidden relative group"
                         style="<?= $bgStyle ?>">
                        <!-- تراكب التدرج -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
                        
                        <!-- أيقونة التشغيل عند التحويم -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 group-active:opacity-100 transition-opacity duration-300">
                            <div class="bg-white/20 backdrop-blur-sm rounded-full p-6 border-2 border-white/50">
                                <i class="fas fa-images text-3xl text-white"></i>
                            </div>
                        </div>
                        
                        <!-- معلومات العريس -->
                        <div class="absolute bottom-0 left-0 right-0 p-5 text-white">
                            <h3 class="text-xl md:text-2xl font-bold mb-3"><?= $name ?></h3>
                            <div class="flex items-center gap-4 text-sm opacity-90">
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-calendar" style="color: var(--gold);"></i>
                                    <?= $date ?>
                                </span>
                                <span class="flex items-center gap-2">
                                    <i class="fas fa-location-dot" style="color: var(--gold);"></i>
                                    <?= $hall ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- شريط زخرفي علوي -->
                        <div class="absolute top-0 left-0 right-0 h-1" style="background: linear-gradient(90deg, transparent, var(--gold), transparent);"></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="text-center mt-8" data-aos="fade-up">
            <a href="landing.php" class="cta-button">
                <span>عرض جميع الحفلات</span>
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        <?php else: ?>
        <div class="text-center py-12" data-aos="fade-up">
            <i class="fas fa-camera text-6xl mb-4" style="color: var(--gold); opacity: 0.3;"></i>
            <p class="text-gray-500 text-lg">سيتم إضافة المعارض قريباً</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- قسم معرض الأعمال -->
<section class="py-16 md:py-24" style="background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12" data-aos="fade-up">
            <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                معرض <span style="color: var(--gold);">الأعمال</span>
            </h2>
            <p class="text-gray-400 text-lg">لحظات مميزة من أعمالنا الإبداعية</p>
            <div class="w-20 h-1 mx-auto mt-4" style="background: var(--gold);"></div>
        </div>
        
        <?php if (count($galleryPhotos) > 0): ?>
        <div class="gallery-grid max-w-6xl mx-auto">
            <?php 
            $displayPhotos = array_slice($galleryPhotos, 0, 8);
            foreach ($displayPhotos as $index => $photo): 
                $imagePath = getGalleryImagePath($photo['groom_id'], $photo['filename']);
                if (!$imagePath) continue;
            ?>
            <div class="gallery-item" 
                 data-aos="zoom-in" 
                 data-aos-delay="<?= $index * 50 ?>">
                <img src="<?= $imagePath ?>" 
                     alt="<?= htmlspecialchars($photo['groom_name']) ?>" 
                     loading="lazy">
                <div class="gallery-overlay">
                    <div class="text-white">
                        <div class="text-sm font-semibold"><?= htmlspecialchars($photo['groom_name']) ?></div>
                        <div class="flex items-center gap-1 text-xs mt-1">
                            <i class="fas fa-heart" style="color: var(--gold);"></i>
                            <span><?= $photo['likes'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-12" data-aos="fade-up">
            <a href="gallery.php" class="cta-button">
                <span>عرض المعرض الكامل</span>
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
        <?php else: ?>
        <div class="text-center py-12" data-aos="fade-up">
            <i class="fas fa-image text-6xl mb-4" style="color: var(--gold); opacity: 0.3;"></i>
            <p class="text-gray-500 text-lg">سيتم إضافة الصور قريباً</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- قسم الخدمات والعروض -->
<section class="py-16 md:py-24" style="background: var(--black);">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12" data-aos="fade-up">
            <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                خدماتنا <span style="color: var(--gold);">المميزة</span>
            </h2>
            <p class="text-gray-400 text-lg">باقات وعروض تناسب جميع احتياجاتك</p>
            <div class="w-20 h-1 mx-auto mt-4" style="background: var(--gold);"></div>
        </div>
        
        <!-- Swiper Slider -->
        <div class="swiper services-swiper max-w-6xl mx-auto" data-aos="fade-up" data-aos-delay="200">
            <div class="swiper-wrapper">
                <!-- الخدمة 1 -->
                <div class="swiper-slide">
                    <div class="service-card">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4" 
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2));">
                                <i class="fas fa-camera-retro text-3xl" style="color: var(--gold);"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">تصوير الأفراح</h3>
                        </div>
                        <p class="text-gray-400 text-center text-sm">
                            تغطية احترافية كاملة لحفل زفافك بأعلى جودة
                        </p>
                    </div>
                </div>
                
                <!-- الخدمة 2 -->
                <div class="swiper-slide">
                    <div class="service-card">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4" 
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2));">
                                <i class="fas fa-video text-3xl" style="color: var(--gold);"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">التصوير السينمائي</h3>
                        </div>
                        <p class="text-gray-400 text-center text-sm">
                            فيديوهات سينمائية بتقنية 4K تحبس الأنفاس
                        </p>
                    </div>
                </div>
                
                <!-- الخدمة 3 -->
                <div class="swiper-slide">
                    <div class="service-card">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4" 
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2));">
                                <i class="fas fa-broadcast-tower text-3xl" style="color: var(--gold);"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">البث المباشر</h3>
                        </div>
                        <p class="text-gray-400 text-center text-sm">
                            نشر صور حفلك مباشرة على الإنترنت
                        </p>
                    </div>
                </div>
                
                <!-- الخدمة 4 -->
                <div class="swiper-slide">
                    <div class="service-card">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4" 
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2));">
                                <i class="fas fa-helicopter text-3xl" style="color: var(--gold);"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">التصوير الجوي</h3>
                        </div>
                        <p class="text-gray-400 text-center text-sm">
                            لقطات جوية مذهلة بطائرات الدرون
                        </p>
                    </div>
                </div>
                
                <!-- الخدمة 5 -->
                <div class="swiper-slide">
                    <div class="service-card">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4" 
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2));">
                                <i class="fas fa-print text-3xl" style="color: var(--gold);"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">الطباعة الفورية</h3>
                        </div>
                        <p class="text-gray-400 text-center text-sm">
                            طباعة فورية للصور في موقع الحفل
                        </p>
                    </div>
                </div>
                
                <!-- الخدمة 6 -->
                <div class="swiper-slide">
                    <div class="service-card">
                        <div class="text-center mb-4">
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4" 
                                 style="background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(184, 148, 30, 0.2));">
                                <i class="fas fa-book-open text-3xl" style="color: var(--gold);"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2">ألبومات فاخرة</h3>
                        </div>
                        <p class="text-gray-400 text-center text-sm">
                            تصميم وطباعة ألبومات فاخرة بجودة عالية
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Pagination -->
            <div class="swiper-pagination mt-8"></div>
        </div>
        
        <div class="text-center mt-12" data-aos="fade-up">
            <a href="services.php" class="cta-button">
                <span>عرض جميع الخدمات والأسعار</span>
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</section>

<!-- قسم البث الحي -->
<section class="live-section py-16 md:py-24" id="live">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto text-center">
            <div data-aos="fade-up">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full mb-6" 
                     style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);">
                    <span class="live-pulse w-3 h-3 rounded-full" style="background: #ef4444;"></span>
                    <span class="text-red-500 font-semibold text-sm">مباشر الآن</span>
                </div>
                
                <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                    ابحث عن <span style="color: var(--gold);">صورك وعريسك</span>
                </h2>
                <p class="text-gray-400 text-lg mb-8">
                    شاهد الصور الحية من الحفلات الجارية الآن، وابحث عن صورك بسهولة
                </p>
                <div class="w-20 h-1 mx-auto mb-12" style="background: var(--gold);"></div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6 mb-12">
                <!-- بطاقة البث المباشر -->
                <div class="bg-gradient-to-br from-gray-900 to-black p-8 rounded-2xl border border-gold/20" 
                     data-aos="fade-right">
                    <div class="text-center">
                        <i class="fas fa-broadcast-tower text-5xl mb-4" style="color: var(--gold);"></i>
                        <h3 class="text-2xl font-bold text-white mb-3">البث المباشر</h3>
                        <p class="text-gray-400 mb-6">
                            شاهد صور الحفلات المنقولة مباشرة وابحث عن صورك
                        </p>
                        <a href="live-gallery.php" class="cta-button w-full justify-center">
                            <i class="fas fa-play-circle"></i>
                            <span>دخول البث المباشر</span>
                        </a>
                    </div>
                </div>
                
                <!-- بطاقة البحث عن العريس -->
                <div class="bg-gradient-to-br from-gray-900 to-black p-8 rounded-2xl border border-gold/20" 
                     data-aos="fade-left">
                    <div class="text-center">
                        <i class="fas fa-search text-5xl mb-4" style="color: var(--gold);"></i>
                        <h3 class="text-2xl font-bold text-white mb-3">ابحث عن عريسك</h3>
                        <p class="text-gray-400 mb-6">
                            تصفح جميع الحفلات وابحث عن صور حفلك
                        </p>
                        <a href="landing.php" class="cta-button w-full justify-center">
                            <i class="fas fa-images"></i>
                            <span>تصفح جميع الحفلات</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- ميزات إضافية -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" data-aos="fade-up" data-aos-delay="200">
                <div class="flex items-center gap-3 p-4 bg-gray-900/50 rounded-lg">
                    <i class="fas fa-bolt text-2xl" style="color: var(--gold);"></i>
                    <div class="text-right">
                        <div class="font-semibold text-white">تحديث فوري</div>
                        <div class="text-xs text-gray-400">الصور تظهر لحظة التقاطها</div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 p-4 bg-gray-900/50 rounded-lg">
                    <i class="fas fa-download text-2xl" style="color: var(--gold);"></i>
                    <div class="text-right">
                        <div class="font-semibold text-white">تحميل مباشر</div>
                        <div class="text-xs text-gray-400">حمّل صورك بجودة عالية</div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 p-4 bg-gray-900/50 rounded-lg">
                    <i class="fas fa-share-alt text-2xl" style="color: var(--gold);"></i>
                    <div class="text-right">
                        <div class="font-semibold text-white">مشاركة سهلة</div>
                        <div class="text-xs text-gray-400">شارك الصور مع الأصدقاء</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- قسم التقييمات -->
<?php if (count($reviews) > 0): ?>
<section class="py-16 md:py-24" style="background: var(--black);">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12" data-aos="fade-up">
            <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                آراء <span style="color: var(--gold);">عملائنا</span>
            </h2>
            <p class="text-gray-400 text-lg">ماذا يقول عملاؤنا عن تجربتهم معنا</p>
            <div class="w-20 h-1 mx-auto mt-4" style="background: var(--gold);"></div>
        </div>
        
        <div class="overflow-x-auto scroll-snap pb-4">
            <div class="flex gap-4 md:gap-6 px-2">
                <?php foreach ($reviews as $index => $review): ?>
                <div class="bg-gradient-to-br from-gray-900 to-black p-6 rounded-xl min-w-[280px] sm:min-w-[320px] snap-child border border-gold/20" 
                     data-aos="fade-up" 
                     data-aos-delay="<?= $index * 100 ?>">
                    <!-- النجوم -->
                    <div class="flex gap-1 mb-4 justify-center">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-lg" 
                               style="color: <?= $i <= $review['rating'] ? 'var(--gold)' : '#333' ?>;"></i>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- نص التقييم -->
                    <p class="text-gray-300 mb-6 leading-relaxed min-h-[100px] text-center">
                        <i class="fas fa-quote-right opacity-30 ml-2" style="color: var(--gold);"></i>
                        <?= nl2br(htmlspecialchars($review['message'])) ?>
                    </p>
                    
                    <!-- معلومات المقيّم -->
                    <div class="border-t border-gray-800 pt-4 text-center">
                        <p class="font-bold text-white mb-1"><?= htmlspecialchars($review['name']) ?></p>
                        <p class="text-sm" style="color: var(--gold);">
                            عريس: <?= htmlspecialchars($review['groom_name']) ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- قسم الإحصائيات -->
<section class="py-16 md:py-24" style="background: linear-gradient(135deg, var(--gold-dark) 0%, var(--gold) 100%);">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8 max-w-5xl mx-auto">
            <div class="text-center" data-aos="fade-up" data-aos-delay="0">
                <div class="text-4xl md:text-6xl font-bold mb-2" style="color: var(--black);">500+</div>
                <div class="text-sm md:text-base font-semibold" style="color: var(--black); opacity: 0.8;">حفل موثق</div>
            </div>
            
            <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                <div class="text-4xl md:text-6xl font-bold mb-2" style="color: var(--black);">50K+</div>
                <div class="text-sm md:text-base font-semibold" style="color: var(--black); opacity: 0.8;">صورة ملتقطة</div>
            </div>
            
            <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                <div class="text-4xl md:text-6xl font-bold mb-2" style="color: var(--black);">98%</div>
                <div class="text-sm md:text-base font-semibold" style="color: var(--black); opacity: 0.8;">رضا العملاء</div>
            </div>
            
            <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                <div class="text-4xl md:text-6xl font-bold mb-2" style="color: var(--black);">15+</div>
                <div class="text-sm md:text-base font-semibold" style="color: var(--black); opacity: 0.8;">سنوات خبرة</div>
            </div>
        </div>
    </div>
</section>

<!-- قسم فريق العمل -->
<section class="py-16 md:py-24" style="background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12" data-aos="fade-up">
            <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                فريق <span style="color: var(--gold);">العمل</span>
            </h2>
            <p class="text-gray-400 text-lg">فريق محترف من المصورين والمبدعين</p>
            <div class="w-20 h-1 mx-auto mt-4" style="background: var(--gold);"></div>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6 max-w-6xl mx-auto">
            <?php
            // جلب فريق العمل من قاعدة البيانات - نفس المصدر المستخدم في gallery.php
            try {
                $teamQuery = "SELECT id, name, role, description, image FROM photographers WHERE is_active = 1 ORDER BY display_order ASC, id ASC LIMIT 8";
                $teamStmt = $pdo->query($teamQuery);
                $teamMembers = $teamStmt->fetchAll();
            } catch (PDOException $e) {
                error_log("خطأ في جلب فريق العمل: " . $e->getMessage());
                $teamMembers = [];
            }
            
            // إذا لم يكن هناك فريق في قاعدة البيانات، استخدم بيانات افتراضية
            if (count($teamMembers) == 0) {
                $teamMembers = [
                    ['name' => 'محمد العتيبي', 'role' => 'المصور الرئيسي', 'image' => null],
                    ['name' => 'أحمد السالم', 'role' => 'مصور فيديو', 'image' => null],
                    ['name' => 'خالد الزهراني', 'role' => 'مونتاج', 'image' => null],
                    ['name' => 'عبدالله القحطاني', 'role' => 'مصور', 'image' => null],
                    ['name' => 'فهد المطيري', 'role' => 'مساعد مصور', 'image' => null],
                    ['name' => 'سعد العمري', 'role' => 'تصوير جوي', 'image' => null]
                ];
            }
            
            foreach ($teamMembers as $index => $member):
                $name = htmlspecialchars($member['name']);
                $role = htmlspecialchars($member['role']);
                // مسار الصورة من نفس المجلد المستخدم في gallery.php
                $imagePath = !empty($member['image']) ? "/photographers/" . $member['image'] : null;
            ?>
            <div class="text-center" data-aos="fade-up" data-aos-delay="<?= $index * 50 ?>">
                <div class="relative mb-4 group">
                    <div class="w-full aspect-square rounded-2xl overflow-hidden border-2 border-gold/20 transition-all duration-300 group-hover:border-gold">
                        <?php if ($imagePath && file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)): ?>
                        <img src="<?= $imagePath ?>" 
                             alt="<?= $name ?>" 
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                             loading="lazy">
                        <?php else: ?>
                        <!-- أيقونة افتراضية عند عدم وجود صورة -->
                        <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center">
                            <i class="fas fa-user text-5xl" style="color: var(--gold); opacity: 0.5;"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Overlay عند التحويم -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-4">
                            <div class="w-full">
                                <div class="flex justify-center gap-3 text-white">
                                    <a href="#" class="w-8 h-8 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center hover:bg-gold transition-colors">
                                        <i class="fab fa-instagram text-sm"></i>
                                    </a>
                                    <a href="#" class="w-8 h-8 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center hover:bg-gold transition-colors">
                                        <i class="fab fa-twitter text-sm"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <h3 class="text-lg md:text-xl font-bold text-white mb-1"><?= $name ?></h3>
                <p class="text-sm text-gray-400" style="color: var(--gold);"><?= $role ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- قسم التواصل -->
<section class="py-16 md:py-24" id="contact" style="background: var(--black);">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto text-center">
            <div data-aos="fade-up">
                <h2 class="text-3xl md:text-5xl font-bold mb-4 text-white">
                    احجز <span style="color: var(--gold);">موعدك الآن</span>
                </h2>
                <p class="text-gray-400 text-lg mb-12">
                    تواصل معنا لحجز تصوير حفلك القادم أو للاستفسار عن خدماتنا
                </p>
                <div class="w-20 h-1 mx-auto mb-12" style="background: var(--gold);"></div>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6 mb-12">
                <div class="bg-gradient-to-br from-gray-900 to-black p-6 rounded-xl border border-gold/20" 
                     data-aos="fade-up" 
                     data-aos-delay="0">
                    <i class="fas fa-phone text-4xl mb-4" style="color: var(--gold);"></i>
                    <h3 class="text-xl font-bold text-white mb-2">اتصل بنا</h3>
                    <a href="tel:+966500000000" class="text-gray-400 hover:text-gold transition-colors">
                        +966 50 000 0000
                    </a>
                </div>
                
                <div class="bg-gradient-to-br from-gray-900 to-black p-6 rounded-xl border border-gold/20" 
                     data-aos="fade-up" 
                     data-aos-delay="100">
                    <i class="fab fa-whatsapp text-4xl mb-4" style="color: var(--gold);"></i>
                    <h3 class="text-xl font-bold text-white mb-2">واتساب</h3>
                    <a href="https://wa.me/966500000000" class="text-gray-400 hover:text-gold transition-colors">
                        راسلنا الآن
                    </a>
                </div>
                
                <div class="bg-gradient-to-br from-gray-900 to-black p-6 rounded-xl border border-gold/20" 
                     data-aos="fade-up" 
                     data-aos-delay="200">
                    <i class="fab fa-instagram text-4xl mb-4" style="color: var(--gold);"></i>
                    <h3 class="text-xl font-bold text-white mb-2">انستقرام</h3>
                    <a href="https://instagram.com/jadhlah" class="text-gray-400 hover:text-gold transition-colors">
                        @jadhlah
                    </a>
                </div>
            </div>
            
            <div data-aos="fade-up" data-aos-delay="300">
                <a href="https://wa.me/966500000000?text=مرحباً، أود الاستفسار عن خدمات التصوير" 
                   class="cta-button inline-flex">
                    <i class="fab fa-whatsapp"></i>
                    <span>احجز موعدك الآن</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="py-8 md:py-12 border-t border-gray-800" style="background: var(--black);">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="text-center md:text-right">
                <img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="w-24 mx-auto md:mx-0 mb-3">
                <p class="text-gray-500 text-sm">جميع الحقوق محفوظة © 2024</p>
            </div>
            
            <div class="flex gap-4">
                <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center border border-gray-800 text-gray-400 hover:border-gold hover:text-gold transition-colors">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center border border-gray-800 text-gray-400 hover:border-gold hover:text-gold transition-colors">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center border border-gray-800 text-gray-400 hover:border-gold hover:text-gold transition-colors">
                    <i class="fab fa-tiktok"></i>
                </a>
                <a href="#" class="w-10 h-10 rounded-full flex items-center justify-center border border-gray-800 text-gray-400 hover:border-gold hover:text-gold transition-colors">
                    <i class="fab fa-snapchat"></i>
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
// تهيئة AOS
AOS.init({
    duration: 1000,
    once: true,
    offset: 50
});

// إخفاء شاشة التحميل
window.addEventListener('load', () => {
    const preloader = document.getElementById('preloader');
    setTimeout(() => {
        preloader.classList.add('hidden');
        document.body.classList.add('loaded');
    }, 500);
});

// تهيئة Swiper للخدمات
const servicesSwiper = new Swiper('.services-swiper', {
    slidesPerView: 1,
    spaceBetween: 20,
    loop: true,
    autoplay: {
        delay: 3000,
        disableOnInteraction: false,
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
        dynamicBullets: true,
    },
    breakpoints: {
        640: {
            slidesPerView: 2,
            spaceBetween: 20,
        },
        1024: {
            slidesPerView: 3,
            spaceBetween: 30,
        },
    }
});

// إخفاء/إظهار النافبار عند السكرول
let lastScroll = 0;
const mobileNav = document.querySelector('.mobile-nav');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    
    if (currentScroll <= 0) {
        mobileNav.classList.remove('hidden');
        return;
    }
    
    if (currentScroll > lastScroll && currentScroll > 200) {
        // السكرول للأسفل
        mobileNav.classList.add('hidden');
    } else {
        // السكرول للأعلى
        mobileNav.classList.remove('hidden');
    }
    
    lastScroll = currentScroll;
});

// تفعيل العنصر النشط في النافبار
const navItems = document.querySelectorAll('.mobile-nav-item');
const sections = document.querySelectorAll('section[id]');

window.addEventListener('scroll', () => {
    let current = '';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (pageYOffset >= sectionTop - 200) {
            current = section.getAttribute('id');
        }
    });
    
    navItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('href') === `#${current}`) {
            item.classList.add('active');
        }
    });
});

// منع التكبير المزعج على iOS
if ('loading' in HTMLImageElement.prototype) {
    const images = document.querySelectorAll('img[loading="lazy"]');
    images.forEach(img => {
        img.src = img.src;
    });
} else {
    // Fallback للمتصفحات القديمة
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js';
    document.body.appendChild(script);
}

// منع التكبير المزعج على iOS
document.addEventListener('gesturestart', function (e) {
    e.preventDefault();
});

// سكرول سلس
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

// تحسين الأداء - استخدام requestAnimationFrame
let ticking = false;
function update() {
    // تحديثات الأنيميشن هنا
    ticking = false;
}

function requestTick() {
    if (!ticking) {
        requestAnimationFrame(update);
        ticking = true;
    }
}

// معالجة الأخطاء العامة
window.addEventListener('error', function(e) {
    console.error('Error:', e.error);
});

// إظهار رسالة تحميل
window.addEventListener('load', () => {
    document.body.classList.add('loaded');
});
</script>

</body>
</html>