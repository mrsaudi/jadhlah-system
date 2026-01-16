<?php
// services.php - صفحة الخدمات
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>خدماتنا | جذلة - تصوير احترافي للمناسبات</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="خدمات جذلة - تصوير فوتوغرافي وفيديو احترافي للأعراس والمناسبات">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
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
        
        body { 
            font-family: 'The Year of The Camel', 'Tajawal', sans-serif;
            font-weight: 400;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
        }
        
        h1, h2 { font-weight: 800; }
        h3, h4 { font-weight: 600; }
        
        /* Gold Gradient Text */
        .gold-text {
            background: linear-gradient(135deg, #ffd700, #ffed4e, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Service Card Hover Effect */
        .service-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,215,0,0.2);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .service-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,215,0,0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s;
            opacity: 0;
        }
        
        .service-card:hover::before {
            animation: shine 0.6s ease-in-out;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(255,215,0,0.3);
            border-color: #ffd700;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); opacity: 0; }
        }
        
        /* Package Card */
        .package-card {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            position: relative;
            overflow: hidden;
        }
        
        .package-card.vip {
            background: linear-gradient(145deg, #2a2a0a, #1a1a0a);
            border: 2px solid #ffd700;
        }
        
        .package-card.vip::after {
            content: 'VIP';
            position: absolute;
            top: 20px;
            right: -30px;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            padding: 5px 40px;
            transform: rotate(45deg);
            font-weight: bold;
            font-size: 12px;
        }
        
        /* Price Badge */
        .price-badge {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            font-weight: 800;
            position: relative;
        }
        
        .old-price {
            text-decoration: line-through;
            opacity: 0.6;
        }
        
        /* Floating Animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .float-animation {
            animation: float 4s ease-in-out infinite;
        }
        
        /* Glass Effect */
        .glass {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        /* Navbar */
        .navbar {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: rgba(0,0,0,0.9);
            border-bottom: 1px solid rgba(255,215,0,0.2);
        }
        
        /* Loading Animation */
        .loader {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,215,0,0.2);
            border-top-color: #ffd700;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <meta name="theme-color" content="#ffc107">
</head>
<body class="bg-black text-white">

<!-- Loading Screen -->
<div id="loader" class="fixed inset-0 z-50 bg-black flex items-center justify-center transition-opacity duration-500">
    <div class="text-center">
        <div class="loader mx-auto mb-4"></div>
        <h2 class="gold-text text-2xl font-bold">جذلة</h2>
    </div>
</div>

<!-- Navbar -->
<nav class="navbar fixed top-0 w-full z-40 transition-all duration-300">
    <div class="container mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
            <a href="/">
                <img src="/assets/whiti_logo_jadhlah_t.svg" alt="جذلة" class="h-12 hover:scale-105 transition">
            </a>
            <div class="hidden md:flex gap-6 items-center">
                <a href="/" class="hover:text-yellow-400 transition">الرئيسية</a>
                <a href="services.php" class="text-yellow-400">خدماتنا</a>
                <a href="gallery.php" class="hover:text-yellow-400 transition">معرض الأعمال</a>
                <a href="about.php" class="hover:text-yellow-400 transition">من نحن</a>
                <a href="https://wa.me/966544705859" target="_blank" 
                   class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-black px-6 py-2 rounded-full font-bold hover:scale-105 transition">
                    <i class="fab fa-whatsapp ml-2"></i> احجز الآن
                </a>
            </div>
            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <!-- Background Effect -->
    <div class="absolute inset-0">
        <div class="absolute top-0 left-0 w-96 h-96 bg-yellow-400/20 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-yellow-600/20 rounded-full filter blur-3xl"></div>
    </div>
    
    <div class="container mx-auto px-4 text-center z-10 pt-20">
        <h1 class="text-5xl md:text-7xl font-extrabold mb-6" data-aos="fade-up">
            <span class="gold-text">خدمات جذلة</span>
        </h1>
        <p class="text-xl md:text-2xl text-gray-300 mb-8 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="100">
            نقدم لكم باقة متكاملة من خدمات التصوير الاحترافي لتوثيق أجمل لحظات حياتكم
        </p>
        <div class="flex justify-center gap-4" data-aos="fade-up" data-aos-delay="200">
            <a href="#services" class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-black px-8 py-3 rounded-full font-bold hover:scale-105 transition">
                استكشف الخدمات
            </a>
            <a href="#packages" class="border-2 border-yellow-400 text-yellow-400 px-8 py-3 rounded-full font-bold hover:bg-yellow-400 hover:text-black transition">
                الباقات الشاملة
            </a>
        </div>
    </div>
    
    <!-- Scroll Indicator -->
    <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 float-animation">
        <i class="fas fa-chevron-down text-yellow-400 text-2xl"></i>
    </div>
</section>

<!-- Main Services Section -->
<section id="services" class="py-20">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">خدماتنا الأساسية</h2>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto"></div>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- باقة جذلة الذهبية -->
            <div class="service-card rounded-2xl p-8 text-center" data-aos="zoom-in">
                <div class="text-6xl mb-4 gold-text">
                    <i class="fas fa-video"></i>
                </div>
                <h3 class="text-2xl font-bold mb-3">باقة جذلة الذهبية</h3>
                <p class="text-gray-400 mb-4">تشمل الباقة على:</p>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة فيديو ذهبي</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة مونتج ذهبي</li>
                </ul>
                <div class="price-badge rounded-full py-2 px-4 inline-block">
                    <span class="old-price text-sm">5000</span>
                    <span class="text-2xl font-bold mx-2">4500</span>
                    <span class="text-sm">ريال</span>
                </div>
            </div>
            
            <!-- باقة جذلة كلاسيك -->
            <div class="service-card rounded-2xl p-8 text-center" data-aos="zoom-in" data-aos-delay="100">
                <div class="text-6xl mb-4 gold-text">
                    <i class="fas fa-camera"></i>
                </div>
                <h3 class="text-2xl font-bold mb-3">باقة جذلة كلاسيك</h3>
                <p class="text-gray-400 mb-4">تشمل الباقة على:</p>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة فيديو كلاسيك</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة فوتو كلاسيك</li>
                </ul>
                <div class="price-badge rounded-full py-2 px-4 inline-block">
                    <span class="text-2xl font-bold">3000</span>
                    <span class="text-sm">ريال</span>
                </div>
            </div>
            
            <!-- باقة جذلة الماسية -->
            <div class="service-card rounded-2xl p-8 text-center" data-aos="zoom-in" data-aos-delay="200">
                <div class="text-6xl mb-4 gold-text">
                    <i class="fas fa-gem"></i>
                </div>
                <h3 class="text-2xl font-bold mb-3">باقة جذلة الماسية</h3>
                <p class="text-gray-400 mb-4">تشمل الباقة على:</p>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة فيديو فوتو ذهبي</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة تصوير الدرون</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة فيديو طنة</li>
                </ul>
                <div class="price-badge rounded-full py-2 px-4 inline-block">
                    <span class="text-2xl font-bold">6000</span>
                    <span class="text-sm">ريال</span>
                </div>
            </div>
            
            <!-- باقة VIP -->
            <div class="service-card rounded-2xl p-8 text-center relative" data-aos="zoom-in" data-aos-delay="300">
                <div class="absolute top-0 right-0 bg-gradient-to-r from-yellow-400 to-yellow-600 text-black px-4 py-1 rounded-tr-2xl rounded-bl-2xl font-bold text-sm">
                    VIP
                </div>
                <div class="text-6xl mb-4 gold-text">
                    <i class="fas fa-crown"></i>
                </div>
                <h3 class="text-2xl font-bold mb-3">باقة جذلة VIP</h3>
                <p class="text-gray-400 mb-4">تشمل الباقة على:</p>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة فيديو ذهبي + فوتو ذهبي</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة تصوير الدرون</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة فيديو طنة</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة تصوير الاستقبال</li>
                    <li><i class="fas fa-check text-yellow-400 ml-2"></i>خدمة صور جوال</li>
                </ul>
                <div class="price-badge rounded-full py-2 px-4 inline-block">
                    <span class="text-2xl font-bold">7000</span>
                    <span class="text-sm">ريال</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Photography Services -->
<section class="py-20 bg-gradient-to-b from-black to-gray-900">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">خدمات التصوير الفوتوغرافي</h2>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto"></div>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- فوتو كلاسيك -->
            <div class="glass rounded-2xl p-6" data-aos="fade-up">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-2xl font-bold mb-2">فوتو كلاسيك</h3>
                        <p class="text-gray-400 text-sm">التصوير الفوتوغرافي الكلاسيكي</p>
                    </div>
                    <i class="fas fa-camera text-3xl text-yellow-400"></i>
                </div>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li>• كاميرا واحدة - عدد الكاميرات</li>
                    <li>• مصور واحد - نطاق التصوير القاعة</li>
                    <li>• 3 ساعات - مدة التصوير</li>
                    <li>• تعديل أساسي - تعديلات الصور</li>
                    <li>• خلال 3 أسابيع - مدة التسليم</li>
                    <li>• جوجل درايف - نوع التسليم</li>
                </ul>
                <div class="text-center">
                    <span class="text-3xl font-bold text-yellow-400">1500</span>
                    <span class="text-gray-400 mr-2">ريال</span>
                </div>
            </div>
            
            <!-- فوتو ذهبي -->
            <div class="glass rounded-2xl p-6" data-aos="fade-up" data-aos-delay="100">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-2xl font-bold mb-2">فوتو ذهبي</h3>
                        <p class="text-gray-400 text-sm">التصوير الفوتوغرافي المتميز</p>
                    </div>
                    <i class="fas fa-camera-retro text-3xl text-yellow-400"></i>
                </div>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li>• كمرتين - عدد الكاميرات</li>
                    <li>• مصور واحد - نطاق التصوير القاعة</li>
                    <li>• 4 ساعات - مدة التصوير</li>
                    <li>• احترافية وفلاتر - تعديلات الصور</li>
                    <li>• خلال 7 أيام - مدة التسليم</li>
                    <li>• تصوير للعريس - تصوير خاص</li>
                    <li>• رسمية للعريس - صورة رسمية</li>
                </ul>
                <div class="text-center">
                    <span class="text-3xl font-bold text-yellow-400">2500</span>
                    <span class="text-gray-400 mr-2">ريال</span>
                </div>
            </div>
            
            <!-- تصوير جوال احترافي -->
            <div class="glass rounded-2xl p-6" data-aos="fade-up" data-aos-delay="200">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-2xl font-bold mb-2">تصوير جوال احترافي</h3>
                        <p class="text-gray-400 text-sm">مصور جوال مختص للحفل</p>
                    </div>
                    <i class="fas fa-mobile-alt text-3xl text-yellow-400"></i>
                </div>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li>• جوال فقط - نوع التصوير</li>
                    <li>• فوتو وفيديو - نوع التصوير</li>
                    <li>• مصور واحد - عدد المصورين</li>
                    <li>• مونتاج بسيط - التسليم</li>
                    <li>• خلال 3 أيام - مدة التسليم</li>
                    <li>• تسليم صور فوري بعد الحفل</li>
                </ul>
                <div class="text-center">
                    <span class="text-3xl font-bold text-yellow-400">500</span>
                    <span class="text-gray-400 mr-2">ريال</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Video Services -->
<section class="py-20">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">خدمات التصوير بالفيديو</h2>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto"></div>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- فيديو كلاسيك -->
            <div class="glass rounded-2xl p-6" data-aos="fade-up">
                <h3 class="text-2xl font-bold mb-4">فيديو كلاسيك</h3>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li>• كاميرا واحدة</li>
                    <li>• مصور واحد</li>
                    <li>• نطاق التصوير: القاعة</li>
                    <li>• 3 ساعات</li>
                    <li>• مونتاج برومو أساسي واحد</li>
                    <li>• خلال 3 أسابيع</li>
                    <li>• دقة الفيديو: Full HD</li>
                </ul>
                <div class="text-center">
                    <span class="text-3xl font-bold text-yellow-400">2000</span>
                    <span class="text-gray-400 mr-2">ريال</span>
                </div>
            </div>
            
            <!-- فيديو ذهبي -->
            <div class="glass rounded-2xl p-6" data-aos="fade-up" data-aos-delay="100">
                <h3 class="text-2xl font-bold mb-4">فيديو ذهبي</h3>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li>• كمرتين</li>
                    <li>• مصور واحد</li>
                    <li>• نطاق التصوير: القاعة</li>
                    <li>• 4 ساعات</li>
                    <li>• 3 فديوهات احترافية</li>
                    <li>• خلال 7 أيام</li>
                    <li>• دقة الفيديو: 4K</li>
                    <li>• برومو ريل + برومو الفن الشعبي</li>
                </ul>
                <div class="text-center">
                    <span class="text-3xl font-bold text-yellow-400">2500</span>
                    <span class="text-gray-400 mr-2">ريال</span>
                </div>
            </div>
            
            <!-- فيديو طنة -->
            <div class="glass rounded-2xl p-6" data-aos="fade-up" data-aos-delay="200">
                <h3 class="text-2xl font-bold mb-4">فيديو طنة</h3>
                <ul class="text-right text-sm text-gray-300 mb-6 space-y-2">
                    <li>• تصوير فيديو للعريس في المنزل أو الفندق</li>
                    <li>• كاميرا واحدة</li>
                    <li>• مصور واحد</li>
                    <li>• الفندق أو البيت</li>
                    <li>• ساعة واحدة</li>
                    <li>• تسليم خلال أسبوع</li>
                    <li>• مونتاج احترافي حسب الفيديو</li>
                </ul>
                <div class="text-center">
                    <span class="text-3xl font-bold text-yellow-400">1000</span>
                    <span class="text-gray-400 mr-2">ريال</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Special Services -->
<section class="py-20 bg-gradient-to-b from-gray-900 to-black">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">خدمات مميزة</h2>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto"></div>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- التصوير الجوي درون -->
            <div class="service-card rounded-2xl p-6 text-center" data-aos="zoom-in">
                <i class="fas fa-helicopter text-4xl text-yellow-400 mb-4"></i>
                <h3 class="text-xl font-bold mb-3">التصوير الجوي (درون)</h3>
                <p class="text-gray-400 text-sm mb-4">تصوير لقطات جوية للقاعة حسب المتاح ولقطات للأحداث في الحفل</p>
                <div class="text-2xl font-bold text-yellow-400">1000 ريال</div>
            </div>
            
            <!-- بث مباشر -->
            <div class="service-card rounded-2xl p-6 text-center" data-aos="zoom-in" data-aos-delay="100">
                <i class="fas fa-broadcast-tower text-4xl text-yellow-400 mb-4"></i>
                <h3 class="text-xl font-bold mb-3">بث مباشر</h3>
                <p class="text-gray-400 text-sm mb-4">إضافة كاميرا بث مباشر مع مصور خاص للبث على منصات البث المباشر</p>
                <div class="text-2xl font-bold text-yellow-400">2000 ريال</div>
            </div>
            
            <!-- طباعة فورية -->
            <div class="service-card rounded-2xl p-6 text-center" data-aos="zoom-in" data-aos-delay="200">
                <i class="fas fa-print text-4xl text-yellow-400 mb-4"></i>
                <h3 class="text-xl font-bold mb-3">طباعة فورية</h3>
                <p class="text-gray-400 text-sm mb-4">طباعة 100 صورة صغيرة فورية في الموقع للضيوف والعريس</p>
                <div class="text-2xl font-bold text-yellow-400">1000 ريال</div>
            </div>
            
            <!-- تصوير الاستقبال -->
            <div class="service-card rounded-2xl p-6 text-center" data-aos="zoom-in" data-aos-delay="300">
                <i class="fas fa-user-friends text-4xl text-yellow-400 mb-4"></i>
                <h3 class="text-xl font-bold mb-3">تصوير الاستقبال</h3>
                <p class="text-gray-400 text-sm mb-4">يتم إضافة كاميرا خاصة مع مصور خاص يتكون الكاميرا ثابتة</p>
                <div class="text-2xl font-bold text-yellow-400">1000 ريال</div>
            </div>
        </div>
    </div>
</section>

<!-- Packages Section -->
<section id="packages" class="py-20">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">باقات جذلة الشاملة</h2>
            <p class="text-gray-400 text-lg" data-aos="fade-up" data-aos-delay="100">اختر الباقة المناسبة لاحتياجاتك</p>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto mt-4"></div>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- الباقة الكلاسيكية -->
            <div class="package-card rounded-2xl p-8 text-center" data-aos="flip-left">
                <h3 class="text-2xl font-bold mb-4">الباقة الكلاسيكية</h3>
                <div class="text-5xl font-extrabold gold-text mb-2">3000</div>
                <p class="text-gray-400 mb-6">ريال سعودي</p>
                <ul class="text-right text-sm text-gray-300 space-y-3 mb-8">
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>تصوير فيديو</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>تصوير فوتو</li>
                </ul>
                <div class="text-gray-500 text-sm line-through mb-2">3500 ريال</div>
                <a href="https://wa.me/966544705859?text=أريد حجز الباقة الكلاسيكية" 
                   class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-3 rounded-full font-bold hover:scale-105 transition">
                    احجز الآن
                </a>
            </div>
            
            <!-- الباقة الذهبية -->
            <div class="package-card rounded-2xl p-8 text-center" data-aos="flip-left" data-aos-delay="100">
                <h3 class="text-2xl font-bold mb-4">الباقة الذهبية</h3>
                <div class="text-5xl font-extrabold gold-text mb-2">4500</div>
                <p class="text-gray-400 mb-6">ريال سعودي</p>
                <ul class="text-right text-sm text-gray-300 space-y-3 mb-8">
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>فيديو ذهبي</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>مونتاج ذهبي</li>
                </ul>
                <div class="text-gray-500 text-sm line-through mb-2">5000 ريال</div>
                <a href="https://wa.me/966544705859?text=أريد حجز الباقة الذهبية" 
                   class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-3 rounded-full font-bold hover:scale-105 transition">
                    احجز الآن
                </a>
            </div>
            
            <!-- الباقة الماسية -->
            <div class="package-card rounded-2xl p-8 text-center" data-aos="flip-left" data-aos-delay="200">
                <h3 class="text-2xl font-bold mb-4">الباقة الماسية</h3>
                <div class="text-5xl font-extrabold gold-text mb-2">6000</div>
                <p class="text-gray-400 mb-6">ريال سعودي</p>
                <ul class="text-right text-sm text-gray-300 space-y-3 mb-8">
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>فيديو ذهبي</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>فوتو ذهبي</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>تصوير درون</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>فيديو طنة</li>
                </ul>
                <div class="text-gray-500 text-sm line-through mb-2">7500 ريال</div>
                <a href="https://wa.me/966544705859?text=أريد حجز الباقة الماسية" 
                   class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-3 rounded-full font-bold hover:scale-105 transition">
                    احجز الآن
                </a>
            </div>
            
            <!-- باقة VIP -->
            <div class="package-card vip rounded-2xl p-8 text-center" data-aos="flip-left" data-aos-delay="300">
                <h3 class="text-2xl font-bold mb-4">باقة VIP</h3>
                <div class="text-5xl font-extrabold gold-text mb-2">7000</div>
                <p class="text-gray-400 mb-6">ريال سعودي</p>
                <ul class="text-right text-sm text-gray-300 space-y-3 mb-8">
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>فيديو ذهبي</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>فوتو ذهبي</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>تصوير درون</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>فيديو طنة</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>تصوير استقبال</li>
                    <li><i class="fas fa-check-circle text-yellow-400 ml-2"></i>بث مباشر</li>
                </ul>
                <div class="text-gray-500 text-sm line-through mb-2">9000 ريال</div>
                <a href="https://wa.me/966544705859?text=أريد حجز باقة VIP" 
                   class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-3 rounded-full font-bold hover:scale-105 transition">
                    احجز الآن
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Additional Services -->
<section class="py-20 bg-gradient-to-b from-black to-gray-900">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">خدمات إضافية</h2>
            <p class="text-gray-400 text-lg" data-aos="fade-up" data-aos-delay="100">يمكن إضافتها على أي باقة لتخصيصها حسب احتياجاتك</p>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto mt-4"></div>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="glass rounded-xl p-6" data-aos="fade-up">
                <h4 class="text-xl font-bold mb-3">خدمة تمديد الوقت</h4>
                <p class="text-gray-400 text-sm mb-4">تمديد مدة التصوير ساعة إلى ساعتين</p>
                <div class="text-yellow-400 font-bold">500 ريال</div>
            </div>
            
            <div class="glass rounded-xl p-6" data-aos="fade-up" data-aos-delay="100">
                <h4 class="text-xl font-bold mb-3">خدمة تسليم سريع</h4>
                <p class="text-gray-400 text-sm mb-4">تسليم خلال 24 ساعة للباقات</p>
                <div class="text-yellow-400 font-bold">500 ريال</div>
            </div>
            
            <div class="glass rounded-xl p-6" data-aos="fade-up" data-aos-delay="200">
                <h4 class="text-xl font-bold mb-3">خدمة تسليم فوري من الموقع</h4>
                <p class="text-gray-400 text-sm mb-4">معالجة بعد الحفل خلال 5 ساعات</p>
                <div class="text-yellow-400 font-bold">500 ريال لكل نوع تصوير</div>
            </div>
            
            <div class="glass rounded-xl p-6" data-aos="fade-up" data-aos-delay="300">
                <h4 class="text-xl font-bold mb-3">خدمة معالجة ميدانية</h4>
                <p class="text-gray-400 text-sm mb-4">تعديل ميداني فوري</p>
                <div class="text-yellow-400 font-bold">1000 ريال لكل نوع تصوير</div>
            </div>
            
            <div class="glass rounded-xl p-6" data-aos="fade-up" data-aos-delay="400">
                <h4 class="text-xl font-bold mb-3">خدمة زيادة عريس</h4>
                <p class="text-gray-400 text-sm mb-4">إضافة عريس في نفس الحفل</p>
                <div class="text-yellow-400 font-bold">500 ريال لكل عريس إضافي</div>
            </div>
            
            <div class="glass rounded-xl p-6" data-aos="fade-up" data-aos-delay="500">
                <h4 class="text-xl font-bold mb-3">خدمة زيادة عدد المصورين</h4>
                <p class="text-gray-400 text-sm mb-4">إضافة مصور ثاني لتغطية الحفل بشكل أكبر</p>
                <div class="text-yellow-400 font-bold">1000 ريال لكل مصور إضافي</div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-20 bg-gradient-to-r from-yellow-600 to-yellow-400">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-4xl font-extrabold text-black mb-6" data-aos="zoom-in">جاهز لتوثيق لحظاتك؟</h2>
        <p class="text-xl text-black/80 mb-8" data-aos="zoom-in" data-aos-delay="100">
            احجز موعدك الآن واحصل على أفضل خدمات التصوير الاحترافي
        </p>
        <div class="flex justify-center gap-4" data-aos="zoom-in" data-aos-delay="200">
            <a href="https://wa.me/966544705859" 
               class="bg-black text-white px-8 py-4 rounded-full font-bold text-lg hover:scale-105 transition inline-flex items-center gap-2">
                <i class="fab fa-whatsapp text-2xl"></i>
                احجز عبر واتساب
            </a>
            <a href="tel:966544705859" 
               class="bg-white text-black px-8 py-4 rounded-full font-bold text-lg hover:scale-105 transition inline-flex items-center gap-2">
                <i class="fas fa-phone"></i>
                اتصل بنا
            </a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-black py-12 border-t border-yellow-400/20">
    <div class="container mx-auto px-4">
        <div class="text-center">
            <img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="h-16 mx-auto mb-6 filter brightness-0 invert">
            <p class="text-gray-400 mb-6">لقطاتنا تعيش أطول من لحظاتها</p>
            
            <div class="flex justify-center gap-4 mb-8">
                <a href="https://instagram.com/jadhlah" target="_blank" 
                   class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-instagram text-black"></i>
                </a>
                <a href="https://tiktok.com/@jadhlah" target="_blank" 
                   class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-tiktok text-black"></i>
                </a>
                <a href="https://snapchat.com/add/jadhlah" target="_blank" 
                   class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-snapchat text-black"></i>
                </a>
                <a href="https://x.com/jadhlah" target="_blank" 
                   class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-x-twitter text-black"></i>
                </a>
            </div>
            
            <div class="text-sm text-gray-500">
                <p>© 2025 جميع الحقوق محفوظة - جذلة</p>
                <p class="mt-2">علامة تابعة لمؤسسة تحفة بصرية</p>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script>
// Initialize AOS
AOS.init({
    duration: 1000,
    once: true,
    offset: 100
});

// Hide loader after page load
window.addEventListener('load', () => {
    setTimeout(() => {
        document.getElementById('loader').style.opacity = '0';
        setTimeout(() => {
            document.getElementById('loader').style.display = 'none';
        }, 500);
    }, 1000);
});

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.createElement('div');
mobileMenu.className = 'fixed inset-0 bg-black/95 z-50 flex items-center justify-center hidden';
mobileMenu.innerHTML = `
    <div class="text-center">
        <button id="close-menu" class="absolute top-4 right-4 text-white text-3xl">
            <i class="fas fa-times"></i>
        </button>
        <nav class="flex flex-col gap-6 text-2xl">
            <a href="/" class="text-white hover:text-yellow-400 transition">الرئيسية</a>
            <a href="services.php" class="text-yellow-400">خدماتنا</a>
            <a href="gallery.php" class="text-white hover:text-yellow-400 transition">معرض الأعمال</a>
            <a href="about.php" class="text-white hover:text-yellow-400 transition">من نحن</a>
            <a href="https://wa.me/966544705859" class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-black px-6 py-3 rounded-full font-bold">
                احجز الآن
            </a>
        </nav>
    </div>
`;
document.body.appendChild(mobileMenu);

mobileMenuBtn.addEventListener('click', () => {
    mobileMenu.classList.remove('hidden');
});

document.getElementById('close-menu').addEventListener('click', () => {
    mobileMenu.classList.add('hidden');
});

// Smooth scroll for anchor links
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

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.background = 'rgba(0,0,0,0.95)';
    } else {
        navbar.style.background = 'rgba(0,0,0,0.9)';
    }
});
</script>

</body>
</html>