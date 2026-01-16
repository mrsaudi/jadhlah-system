<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>خدماتنا | جذلة - تصوير احترافي للمناسبات</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="خدمات جذلة - تصوير فوتوغرافي وفيديو احترافي للأعراس والمناسبات">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <style>
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            overflow-x: hidden;
        }
        
        body { 
            font-family: 'The Year of The Camel', 'Tajawal', sans-serif;
            font-weight: 400;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            overflow-x: hidden;
        }
        
        h1, h2 { font-weight: 800; }
        h3, h4 { font-weight: 600; }
        
        .gold-text {
            background: linear-gradient(135deg, #ffd700, #ffed4e, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .national-badge {
            background: linear-gradient(135deg, #006C35, #00843D);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .service-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,215,0,0.2);
            transition: all 0.4s ease;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(255,215,0,0.3);
            border-color: #ffd700;
        }
        
        .package-card {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            position: relative;
            border: 2px solid rgba(255,215,0,0.3);
            cursor: pointer;
            height: 400px;
            perspective: 1000px;
        }

        .package-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.6s;
            transform-style: preserve-3d;
        }

        .package-card.flipped .package-card-inner {
            transform: rotateY(180deg);
        }

        .card-front, .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            border-radius: 1rem;
            overflow-y: auto;
        }

        .card-front {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
        }

        .card-back {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            transform: rotateY(180deg);
        }
        
        .package-card.vip {
            border: 3px solid #ffd700;
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
            z-index: 10;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            font-weight: 800;
        }

        .discount-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #006C35, #00843D);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 11px;
            z-index: 10;
        }
        
        .glass {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .navbar {
            backdrop-filter: blur(20px);
            background: rgba(0,0,0,0.9);
            border-bottom: 1px solid rgba(255,215,0,0.2);
        }
        
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

        .highlight-box {
            background: linear-gradient(135deg, rgba(255,215,0,0.1), rgba(255,215,0,0.05));
            border-right: 4px solid #ffd700;
            padding: 1rem;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .float-animation {
            animation: float 4s ease-in-out infinite;
        }

        @media (max-width: 768px) {
            .package-card {
                height: 420px;
            }
        }
    </style>
    
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
</head>
<body class="bg-black text-white">

<div id="loader" class="fixed inset-0 z-50 bg-black flex items-center justify-center transition-opacity duration-500">
    <div class="text-center">
        <div class="loader mx-auto mb-4"></div>
        <h2 class="gold-text text-2xl font-bold">جذلة</h2>
    </div>
</div>

<nav class="navbar fixed top-0 w-full z-40 transition-all duration-300">
    <div class="container mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
            <a href="/">
                <img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" style="height: 2rem;" class="hover:scale-105 transition">
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
            <button id="mobile-menu-btn" class="md:hidden">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <div class="absolute inset-0">
        <div class="absolute top-0 left-0 w-96 h-96 bg-yellow-400/20 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-green-600/20 rounded-full filter blur-3xl"></div>
    </div>
    
    <div class="container mx-auto px-4 text-center z-10 pt-20">
        <div class="national-badge text-white px-6 py-3 rounded-full inline-block mb-6" data-aos="zoom-in">
            <i class="fas fa-flag ml-2"></i>
            <span class="font-bold text-base md:text-lg">عرض اليوم الوطني 🇸🇦 خصم 15%</span>
        </div>
        
        <h1 class="text-4xl md:text-7xl font-extrabold mb-6 leading-tight" data-aos="fade-up" data-aos-delay="100">
            <span class="gold-text">لحظات لا تُنسى</span>
            <br>
            <span class="text-white text-2xl md:text-5xl">تستحق أن تُحفظ للأبد</span>
        </h1>
        
        <div class="max-w-3xl mx-auto mb-8 px-4" data-aos="fade-up" data-aos-delay="200">
            <p class="text-lg md:text-xl text-gray-300 mb-3">
                يوم زفافك هو أجمل أيام حياتك 💫
            </p>
            <p class="text-yellow-400 font-bold text-base md:text-xl">
                نوثق كل تفصيلة بعناية لتعيش هذه اللحظات للأبد ✨
            </p>
        </div>
        
        <div class="flex justify-center gap-4 flex-wrap" data-aos="fade-up" data-aos-delay="300">
            <a href="#packages" class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-black px-6 md:px-8 py-3 md:py-4 rounded-full font-bold text-base md:text-lg hover:scale-105 transition">
                <i class="fas fa-gift ml-2"></i>
                شاهد العروض الخاصة
            </a>
            <a href="#services" class="border-2 border-yellow-400 text-yellow-400 px-6 md:px-8 py-3 md:py-4 rounded-full font-bold text-base md:text-lg hover:bg-yellow-400 hover:text-black transition">
                اكتشف خدماتنا
            </a>
        </div>
    </div>
    
    <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 float-animation">
        <i class="fas fa-chevron-down text-yellow-400 text-2xl"></i>
    </div>
</section>

<!-- Emotional Introduction -->
<section class="py-12 md:py-16 bg-gradient-to-b from-black to-gray-900">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto" data-aos="fade-up">
            <div class="highlight-box rounded-2xl">
                <h3 class="text-xl md:text-2xl font-bold mb-3 gold-text text-center">
                    لماذا تختار جذلة؟
                </h3>
                <p class="text-base text-gray-300 text-center leading-relaxed">
                    نؤمن بأن زفافك قصة حب تستحق أن تُروى بأجمل صورة 📖❤️
                    <br><br>
                    <span class="text-yellow-400 font-bold">مع جذلة، ذكرياتك في أيدٍ أمينة 💛</span>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Main Packages Section -->
<section id="packages" class="py-20">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <div class="national-badge text-white px-6 py-2 rounded-full inline-block mb-4" data-aos="zoom-in">
                <i class="fas fa-percentage ml-2"></i>
                عرض اليوم الوطني 🇸🇦
            </div>
            <h2 class="text-3xl md:text-5xl font-extrabold mb-4 gold-text" data-aos="fade-up">باقاتنا الشاملة</h2>
            <p class="text-lg md:text-xl text-gray-300 mb-2" data-aos="fade-up">اختر الباقة التي تناسب حلمك</p>
            <p class="text-green-400 font-bold text-base md:text-lg" data-aos="fade-up">✨ خصم 15% لفترة محدودة</p>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto mt-4"></div>
        </div>
        
        <div class="max-w-6xl mx-auto">
            <!-- First Row -->
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <!-- الباقة الكلاسيكية -->
                <div class="package-card rounded-2xl" data-aos="flip-up">
                    <div class="package-card-inner">
                        <!-- Front -->
                        <div class="card-front p-5">
                            <div class="discount-badge">خصم 15% 🎉</div>
                            
                            <div class="text-center mb-4 mt-8">
                                <div class="text-5xl mb-3">
                                    <i class="fas fa-camera text-yellow-400"></i>
                                </div>
                                <h3 class="text-2xl font-bold mb-2">الباقة الكلاسيكية</h3>
                                <p class="text-gray-400 text-sm">تغطية أساسية أنيقة</p>
                            </div>

                            <div class="text-center mb-4">
                                <div class="text-gray-400 text-sm line-through mb-1">3500 ريال</div>
                                <div class="price-badge rounded-full py-2 px-5 inline-block">
                                    <span class="text-3xl font-bold">2550</span>
                                    <span class="text-base mr-2">ريال</span>
                                </div>
                                <p class="text-green-400 font-bold text-xs mt-2">وفّر 950 ريال 💰</p>
                            </div>

                            <div class="text-center text-xs text-yellow-400 mb-3">
                                👆 اضغط لمعرفة التفاصيل
                            </div>

                            <a href="https://wa.me/966544705859?text=مرحباً، أريد الباقة الكلاسيكية" 
                               class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-2.5 rounded-full font-bold text-center text-sm hover:scale-105 transition">
                                <i class="fab fa-whatsapp ml-2"></i> احجز الآن
                            </a>
                        </div>

                        <!-- Back -->
                        <div class="card-back p-5">
                            <div class="text-center mb-3">
                                <h3 class="text-xl font-bold gold-text mb-1">الكلاسيكية</h3>
                                <p class="text-xs text-gray-400">تفاصيل كاملة</p>
                            </div>

                            <div class="mb-4">
                                <h4 class="font-bold text-yellow-400 mb-2 text-xs">✨ تشمل:</h4>
                                <ul class="text-xs text-gray-300 space-y-2">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>فوتو كلاسيك 3 ساعات</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>فيديو كلاسيك Full HD</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>مصورَين محترفين</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>تغطية كاملة للحفل</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>تسليم أسبوعين</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="bg-yellow-900/20 rounded-xl p-2 mb-3">
                                <p class="text-xs text-center">
                                    <strong class="text-yellow-400">مثالية لـ:</strong> توثيق حفلك بجودة عالية 🎯
                                </p>
                            </div>

                            <div class="text-center text-xs text-yellow-400 mb-3">
                                👆 اضغط للرجوع
                            </div>

                            <a href="https://wa.me/966544705859?text=أريد الكلاسيكية" 
                               class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-2.5 rounded-full font-bold text-center text-sm hover:scale-105 transition">
                                <i class="fab fa-whatsapp ml-2"></i> احجز
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- الباقة الذهبية -->
                <div class="package-card rounded-2xl" data-aos="flip-up" data-aos-delay="100">
                    <div class="package-card-inner">
                        <!-- Front -->
                        <div class="card-front p-5">
                            <div class="discount-badge">خصم 15% 🎉</div>
                            <div class="absolute top-3 left-3 bg-yellow-400 text-black px-2 py-1 rounded-full text-xs font-bold">
                                الأكثر طلباً ⭐
                            </div>

                            <div class="text-center mb-4 mt-8">
                                <div class="text-5xl mb-3">
                                    <i class="fas fa-crown text-yellow-400"></i>
                                </div>
                                <h3 class="text-2xl font-bold mb-2">الباقة الذهبية</h3>
                                <p class="text-gray-400 text-sm">جودة أعلى وتفاصيل أدق</p>
                            </div>

                            <div class="text-center mb-4">
                                <div class="text-gray-400 text-sm line-through mb-1">5000 ريال</div>
                                <div class="price-badge rounded-full py-2 px-5 inline-block">
                                    <span class="text-3xl font-bold">3825</span>
                                    <span class="text-base mr-2">ريال</span>
                                </div>
                                <p class="text-green-400 font-bold text-xs mt-2">وفّر 1175 ريال 💰</p>
                            </div>

                            <div class="text-center text-xs text-yellow-400 mb-3">
                                👆 اضغط لمعرفة التفاصيل
                            </div>

                            <a href="https://wa.me/966544705859?text=مرحباً، أريد الباقة الذهبية" 
                               class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-2.5 rounded-full font-bold text-center text-sm hover:scale-105 transition">
                                <i class="fab fa-whatsapp ml-2"></i> احجز الآن
                            </a>
                        </div>

                        <!-- Back -->
                        <div class="card-back p-5">
                            <div class="text-center mb-3">
                                <h3 class="text-xl font-bold gold-text mb-1">الذهبية ⭐</h3>
                                <p class="text-xs text-gray-400">تفاصيل كاملة</p>
                            </div>

                            <div class="mb-4">
                                <h4 class="font-bold text-yellow-400 mb-2 text-xs">✨ تشمل:</h4>
                                <ul class="text-xs text-gray-300 space-y-2">
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>فوتو ذهبي 4 ساعات</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>فيديو ذهبي 4K</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>3 فيديوهات احترافية</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>جلسة موسعة</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>صورة رسمية</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check-circle text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>تسليم 7 أيام</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="bg-yellow-900/20 rounded-xl p-2 mb-3">
                                <p class="text-xs text-center">
                                    <strong class="text-yellow-400">مثالية لـ:</strong> تجربة سينمائية 🎬✨
                                </p>
                            </div>

                            <div class="text-center text-xs text-yellow-400 mb-3">
                                👆 اضغط للرجوع
                            </div>

                            <a href="https://wa.me/966544705859?text=أريد الذهبية" 
                               class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-2.5 rounded-full font-bold text-center text-sm hover:scale-105 transition">
                                <i class="fab fa-whatsapp ml-2"></i> احجز
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIP Row -->
            <div class="max-w-2xl mx-auto">
                <div class="package-card vip rounded-2xl" data-aos="flip-up" data-aos-delay="200">
                    <div class="package-card-inner">
                        <!-- Front -->
                        <div class="card-front p-5">
                            <div class="discount-badge">خصم 2000 ريال 🔥</div>

                            <div class="text-center mb-4 mt-12">
                                <div class="text-5xl mb-3">
                                    <i class="fas fa-gem text-yellow-400"></i>
                                </div>
                                <h3 class="text-2xl font-bold mb-2">باقة VIP</h3>
                                <p class="text-gray-400 text-sm">التجربة الفاخرة الشاملة 👑</p>
                            </div>

                            <div class="text-center mb-4">
                                <div class="text-gray-400 text-sm line-through mb-1">9000 ريال</div>
                                <div class="price-badge rounded-full py-2 px-5 inline-block">
                                    <span class="text-3xl font-bold">7000</span>
                                    <span class="text-base mr-2">ريال</span>
                                </div>
                                <p class="text-green-400 font-bold text-xs mt-2">وفّر 2000 ريال 🎁</p>
                            </div>

                            <div class="text-center text-xs text-yellow-400 mb-3">
                                👆 اضغط لمعرفة التفاصيل
                            </div>

                            <a href="https://wa.me/966544705859?text=مرحباً، أريد باقة VIP" 
                               class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-2.5 rounded-full font-bold text-center text-sm hover:scale-105 transition">
                                <i class="fab fa-whatsapp ml-2"></i> احجز VIP
                            </a>
                        </div>

                        <!-- Back -->
                        <div class="card-back p-5">
                            <div class="text-center mb-3">
                                <h3 class="text-xl font-bold gold-text mb-1">VIP 👑</h3>
                                <p class="text-xs text-gray-400">كل شيء!</p>
                            </div>

                            <div class="mb-4">
                                <h4 class="font-bold text-yellow-400 mb-2 text-xs">👑 تشمل:</h4>
                                <ul class="text-xs text-gray-300 space-y-1.5">
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>فوتو ذهبي 4 ساعات</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>فيديو ذهبي 4K</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>3 فيديوهات</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>درون جوي</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>كاميرا الاستقبال</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>تصوير الطلة</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>مصور جوال</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-crown text-yellow-400 ml-2 mt-0.5 text-xs"></i>
                                        <span>فريق متكامل</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="bg-yellow-900/30 rounded-xl p-2 mb-3">
                                <p class="text-xs text-center">
                                    <strong class="text-yellow-300">للعريس المميز:</strong> تغطية 360° 👑
                                </p>
                            </div>

                            <div class="text-center text-xs text-yellow-400 mb-3">
                                👆 اضغط للرجوع
                            </div>

                            <a href="https://wa.me/966544705859?text=أريد VIP" 
                               class="block bg-gradient-to-r from-yellow-400 to-yellow-600 text-black py-2.5 rounded-full font-bold text-center text-sm hover:scale-105 transition">
                                <i class="fab fa-whatsapp ml-2"></i> احجز
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message -->
        <div class="max-w-3xl mx-auto mt-12" data-aos="fade-up">
            <div class="glass rounded-2xl p-6">
                <h3 class="text-xl font-bold gold-text mb-3 text-center">💬 رسالة من القلب</h3>
                <p class="text-sm text-gray-300 text-center leading-relaxed mb-3">
                    نعلم أن يوم زفافك يمر سريعاً... لكن مع جذلة، ستعيش كل لحظة منه مرة بعد مرة 🎥💕
                </p>
                <p class="text-sm text-yellow-400 font-bold text-center">
                    استثمر في ذكرياتك، فهي تزداد قيمة مع الأيام ✨
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section id="services" class="py-20 bg-gradient-to-b from-gray-900 to-black">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">خدمات منفصلة</h2>
            <p class="text-lg text-gray-300 mb-4" data-aos="fade-up">يمكنك تخصيص باقتك الخاصة</p>
            <p class="text-green-400 font-bold" data-aos="fade-up">🎉 خصم 15% على جميع الخدمات</p>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto mt-4"></div>
        </div>

        <!-- Photography -->
        <div class="mb-16">
            <h3 class="text-2xl md:text-3xl font-bold mb-8 text-center gold-text" data-aos="fade-up">
                <i class="fas fa-camera ml-2"></i> التصوير الفوتوغرافي
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-2 gap-4 md:gap-6 max-w-4xl mx-auto">
                <div class="service-card rounded-xl p-4 relative" data-aos="zoom-in">
                    <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                    <div class="text-center mb-3">
                        <i class="fas fa-camera text-4xl text-yellow-400 mb-2"></i>
                        <h4 class="text-lg font-bold mb-1">فوتو كلاسيك</h4>
                    </div>
                    <ul class="text-xs text-gray-300 mb-3 space-y-1">
                        <li>• 3 ساعات تصوير</li>
                        <li>• جلسة للعريس</li>
                        <li>• تسليم أسبوعين</li>
                    </ul>
                    <div class="text-center">
                        <div class="text-gray-400 line-through text-xs mb-1">1500</div>
                        <span class="text-2xl font-bold text-yellow-400">1275</span>
                        <span class="text-gray-400 text-xs mr-1">ر.س</span>
                    </div>
                </div>

                <div class="service-card rounded-xl p-4 relative" data-aos="zoom-in" data-aos-delay="50">
                    <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                    <div class="text-center mb-3">
                        <i class="fas fa-camera-retro text-4xl text-yellow-400 mb-2"></i>
                        <h4 class="text-lg font-bold mb-1">فوتو ذهبي</h4>
                        <span class="text-xs bg-yellow-400 text-black px-2 py-0.5 rounded-full">الأفضل</span>
                    </div>
                    <ul class="text-xs text-gray-300 mb-3 space-y-1">
                        <li>• 4 ساعات تصوير</li>
                        <li>• جلسة موسعة</li>
                        <li>• تسليم 7 أيام</li>
                    </ul>
                    <div class="text-center">
                        <div class="text-gray-400 line-through text-xs mb-1">2500</div>
                        <span class="text-2xl font-bold text-yellow-400">2125</span>
                        <span class="text-gray-400 text-xs mr-1">ر.س</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Video -->
        <div>
            <h3 class="text-2xl md:text-3xl font-bold mb-8 text-center gold-text" data-aos="fade-up">
                <i class="fas fa-video ml-2"></i> التصوير بالفيديو
            </h3>
            
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-6 max-w-5xl mx-auto">
                <div class="service-card rounded-xl p-4 relative" data-aos="zoom-in">
                    <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                    <div class="text-center mb-3">
                        <i class="fas fa-film text-4xl text-yellow-400 mb-2"></i>
                        <h4 class="text-lg font-bold mb-1">فيديو كلاسيك</h4>
                    </div>
                    <ul class="text-xs text-gray-300 mb-3 space-y-1">
                        <li>• 3 ساعات</li>
                        <li>• Full HD</li>
                        <li>• برومو واحد</li>
                    </ul>
                    <div class="text-center">
                        <div class="text-gray-400 line-through text-xs mb-1">2000</div>
                        <span class="text-2xl font-bold text-yellow-400">1700</span>
                        <span class="text-gray-400 text-xs mr-1">ر.س</span>
                    </div>
                </div>

                <div class="service-card rounded-xl p-4 relative" data-aos="zoom-in" data-aos-delay="50">
                    <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                    <div class="text-center mb-3">
                        <i class="fas fa-video text-4xl text-yellow-400 mb-2"></i>
                        <h4 class="text-lg font-bold mb-1">فيديو ذهبي</h4>
                        <span class="text-xs bg-yellow-400 text-black px-2 py-0.5 rounded-full">الأفضل</span>
                    </div>
                    <ul class="text-xs text-gray-300 mb-3 space-y-1">
                        <li>• 4 ساعات</li>
                        <li>• 4K سينمائي</li>
                        <li>• 3 فيديوهات</li>
                    </ul>
                    <div class="text-center">
                        <div class="text-gray-400 line-through text-xs mb-1">2500</div>
                        <span class="text-2xl font-bold text-yellow-400">2125</span>
                        <span class="text-gray-400 text-xs mr-1">ر.س</span>
                    </div>
                </div>

                <div class="service-card rounded-xl p-4 relative" data-aos="zoom-in" data-aos-delay="100">
                    <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                    <div class="text-center mb-3">
                        <i class="fas fa-home text-4xl text-yellow-400 mb-2"></i>
                        <h4 class="text-lg font-bold mb-1">تصوير الطلة</h4>
                    </div>
                    <ul class="text-xs text-gray-300 mb-3 space-y-1">
                        <li>• من البيت</li>
                        <li>• ساعة تصوير</li>
                        <li>• مونتاج احترافي</li>
                    </ul>
                    <div class="text-center">
                        <div class="text-gray-400 line-through text-xs mb-1">1000</div>
                        <span class="text-2xl font-bold text-yellow-400">850</span>
                        <span class="text-gray-400 text-xs mr-1">ر.س</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Additional Services -->
<section class="py-20 bg-gradient-to-b from-black to-gray-900">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-4xl font-extrabold mb-4 gold-text" data-aos="fade-up">خدمات إضافية مميزة</h2>
            <p class="text-lg text-gray-300 mb-4" data-aos="fade-up">أضف لمسة خاصة على تغطيتك</p>
            <p class="text-green-400 font-bold" data-aos="fade-up">🎉 خصم 15% على جميع الخدمات</p>
            <div class="w-24 h-1 bg-gradient-to-r from-yellow-400 to-yellow-600 mx-auto mt-4"></div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
            <div class="service-card rounded-xl p-5 text-center relative" data-aos="zoom-in">
                <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                <i class="fas fa-helicopter text-4xl text-yellow-400 mb-3"></i>
                <h3 class="text-lg font-bold mb-2">تصوير جوي (درون)</h3>
                <p class="text-gray-400 text-xs mb-3 leading-relaxed">لقطات جوية مذهلة للحفل والقاعة من السماء 🚁</p>
                <div class="mb-1">
                    <span class="text-gray-400 line-through text-xs">1000</span>
                </div>
                <div class="text-2xl font-bold text-yellow-400">850 <span class="text-xs">ر.س</span></div>
            </div>
            
            <div class="service-card rounded-xl p-5 text-center relative" data-aos="zoom-in" data-aos-delay="50">
                <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                <i class="fas fa-broadcast-tower text-4xl text-yellow-400 mb-3"></i>
                <h3 class="text-lg font-bold mb-2">بث مباشر</h3>
                <p class="text-gray-400 text-xs mb-3 leading-relaxed">بث حي مع تصوير مستمر ونقل للشاشات والمنصات 📡</p>
                <div class="mb-1">
                    <span class="text-gray-400 line-through text-xs">2000</span>
                </div>
                <div class="text-2xl font-bold text-yellow-400">1700 <span class="text-xs">ر.س</span></div>
            </div>
            
            <div class="service-card rounded-xl p-5 text-center relative" data-aos="zoom-in" data-aos-delay="100">
                <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                <i class="fas fa-print text-4xl text-yellow-400 mb-3"></i>
                <h3 class="text-lg font-bold mb-2">طباعة فورية</h3>
                <p class="text-gray-400 text-xs mb-3 leading-relaxed">100 صورة فورية للضيوف كهدايا تذكارية 🎁📸</p>
                <div class="mb-1">
                    <span class="text-gray-400 line-through text-xs">1000</span>
                </div>
                <div class="text-2xl font-bold text-yellow-400">850 <span class="text-xs">ر.س</span></div>
            </div>
            
            <div class="service-card rounded-xl p-5 text-center relative" data-aos="zoom-in" data-aos-delay="150">
                <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                <i class="fas fa-user-friends text-4xl text-yellow-400 mb-3"></i>
                <h3 class="text-lg font-bold mb-2">كاميرا الاستقبال</h3>
                <p class="text-gray-400 text-xs mb-3 leading-relaxed">كاميرا ثابتة مع مصور لتصوير مستمر للضيوف 🤝</p>
                <div class="mb-1">
                    <span class="text-gray-400 line-through text-xs">1000</span>
                </div>
                <div class="text-2xl font-bold text-yellow-400">850 <span class="text-xs">ر.س</span></div>
            </div>

            <div class="service-card rounded-xl p-5 text-center relative" data-aos="zoom-in" data-aos-delay="200">
                <div class="absolute top-2 right-2 bg-green-600 text-white px-2 py-1 rounded-lg text-xs font-bold">-15%</div>
                <i class="fas fa-mobile-alt text-4xl text-yellow-400 mb-3"></i>
                <h3 class="text-lg font-bold mb-2">مصور جوال</h3>
                <p class="text-gray-400 text-xs mb-3 leading-relaxed">مصور جوال احترافي لتصوير فوتو وفيديو سريع 📱</p>
                <div class="mb-1">
                    <span class="text-gray-400 line-through text-xs">500</span>
                </div>
                <div class="text-2xl font-bold text-yellow-400">425 <span class="text-xs">ر.س</span></div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-16 bg-gradient-to-r from-yellow-600 via-yellow-500 to-yellow-400 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-full h-full" style="background-image: url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23000000\" fill-opacity=\"0.4\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>
    
    <div class="container mx-auto px-4 text-center relative z-10">
        <div class="national-badge bg-green-700 text-white px-6 py-2 rounded-full inline-block mb-6" data-aos="zoom-in">
            <i class="fas fa-clock ml-2"></i>
            العرض لفترة محدودة 🇸🇦
        </div>
        
        <h2 class="text-3xl md:text-5xl font-extrabold text-black mb-4 px-4" data-aos="zoom-in" data-aos-delay="100">
            لا تفوّت هذه الفرصة! ⏰
        </h2>
        <p class="text-lg md:text-2xl text-black/90 mb-3 max-w-3xl mx-auto px-4" data-aos="zoom-in" data-aos-delay="200">
            يوم زفافك يستحق أفضل تصوير 🇸🇦✨
        </p>
        <p class="text-base md:text-lg text-black/80 mb-8 font-bold" data-aos="zoom-in" data-aos-delay="250">
            احجز الآن ووفّر حتى 2000 ريال! 💰
        </p>
        
        <div class="flex justify-center gap-3 md:gap-4 flex-wrap px-4" data-aos="zoom-in" data-aos-delay="300">
            <a href="https://wa.me/966544705859?text=مرحباً 👋 أريد الاستفسار عن عروض اليوم الوطني" 
               class="bg-black text-white px-6 md:px-8 py-3 md:py-4 rounded-full font-bold text-sm md:text-lg hover:scale-105 transition inline-flex items-center gap-2 shadow-2xl">
                <i class="fab fa-whatsapp text-xl md:text-2xl"></i>
                احجز عبر واتساب
            </a>
            <a href="tel:966544705859" 
               class="bg-white text-black px-6 md:px-8 py-3 md:py-4 rounded-full font-bold text-sm md:text-lg hover:scale-105 transition inline-flex items-center gap-2 shadow-2xl">
                <i class="fas fa-phone"></i>
                اتصل بنا الآن
            </a>
        </div>
        
        <p class="mt-8 text-black/70 text-xs md:text-sm px-4" data-aos="fade-up" data-aos-delay="400">
            📍 نخدم جميع مناطق المملكة | 🎥 فريق محترف جاهز لخدمتك
        </p>
    </div>
</section>

<!-- Footer -->
<footer class="bg-black py-12 border-t border-yellow-400/20">
    <div class="container mx-auto px-4">
        <div class="text-center">
            <img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="h-16 mx-auto mb-6 filter brightness-0 invert">
            <p class="text-gray-400 mb-2 text-xl font-bold gold-text">لقطاتنا تعيش أطول من لحظاتها</p>
            <p class="text-gray-500 mb-6">نحفظ لك أجمل ذكريات حياتك 💛</p>
            
            <div class="flex justify-center gap-4 mb-8">
                <a href="https://instagram.com/jadhlah" target="_blank" 
                   class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-instagram text-black text-xl"></i>
                </a>
                <a href="https://tiktok.com/@jadhlah" target="_blank" 
                   class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-tiktok text-black text-xl"></i>
                </a>
                <a href="https://snapchat.com/add/jadhlah" target="_blank" 
                   class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-snapchat text-black text-xl"></i>
                </a>
                <a href="https://x.com/jadhlah" target="_blank" 
                   class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-full flex items-center justify-center hover:scale-110 transition">
                    <i class="fab fa-x-twitter text-black text-xl"></i>
                </a>
            </div>
            
            <div class="text-sm text-gray-500">
                <p>© 2025 جميع الحقوق محفوظة - جذلة</p>
                <p class="mt-2">علامة تابعة لمؤسسة تحفة بصرية</p>
                <p class="mt-4 text-yellow-400">📞 0544705859 | 📧 info@jadhlah.sa</p>
            </div>
        </div>
    </div>
</footer>

<script>
AOS.init({
    duration: 1000,
    once: true,
    offset: 100
});

window.addEventListener('load', () => {
    setTimeout(() => {
        document.getElementById('loader').style.opacity = '0';
        setTimeout(() => {
            document.getElementById('loader').style.display = 'none';
        }, 500);
    }, 1000);
});

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
            <a href="https://wa.me/966544705859" class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-black px-6 py-3 rounded-full font-bold">احجز الآن</a>
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

window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.style.background = 'rgba(0,0,0,0.95)';
    } else {
        navbar.style.background = 'rgba(0,0,0,0.9)';
    }
});

// Flip cards functionality
document.querySelectorAll('.package-card').forEach(card => {
    card.addEventListener('click', function(e) {
        // Don't flip if clicking on a link
        if (e.target.closest('a')) {
            return;
        }
        this.classList.toggle('flipped');
    });
});
</script>

</body>
</html>