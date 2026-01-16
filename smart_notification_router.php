<?php
/**
 * نظام ذكي لتوجيه المستخدم لطريقة الإشعارات المناسبة
 * يكتشف نوع الجهاز والمتصفح ويعرض الخيار الأمثل
 */

// دالة اكتشاف الجهاز والمتصفح
function detectUserDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $device = [
        'is_mobile' => false,
        'is_ios' => false,
        'is_android' => false,
        'is_desktop' => false,
        'is_safari' => false,
        'is_chrome' => false,
        'is_firefox' => false,
        'ios_version' => null,
        'browser' => 'Unknown',
        'platform' => 'Unknown',
        'supports_push' => false,
        'requires_pwa' => false,
        'recommended_method' => 'sms'
    ];
    
    // اكتشاف iOS
    if (preg_match('/iPad|iPhone|iPod/', $userAgent)) {
        $device['is_mobile'] = true;
        $device['is_ios'] = true;
        $device['platform'] = 'iOS';
        
        // جلب إصدار iOS
        if (preg_match('/OS (\d+)_(\d+)/', $userAgent, $matches)) {
            $device['ios_version'] = floatval($matches[1] . '.' . $matches[2]);
        }
        
        // اكتشاف Safari على iOS
        if (preg_match('/Safari/', $userAgent) && !preg_match('/Chrome|CriOS|FxiOS/', $userAgent)) {
            $device['is_safari'] = true;
            $device['browser'] = 'Safari';
            
            // iOS 16.4+ في Safari يدعم Push للـ PWA فقط
            if ($device['ios_version'] >= 16.4) {
                $device['supports_push'] = true;
                $device['requires_pwa'] = true;
                $device['recommended_method'] = 'pwa';
            } else {
                // إصدارات قديمة لا تدعم Push
                $device['recommended_method'] = 'sms';
            }
        } else {
            // Chrome أو Firefox على iOS لا يدعمون Push
            $device['browser'] = preg_match('/CriOS/', $userAgent) ? 'Chrome' : 
                               (preg_match('/FxiOS/', $userAgent) ? 'Firefox' : 'Other');
            $device['recommended_method'] = 'sms';
        }
    }
    // اكتشاف Android
    elseif (preg_match('/Android/', $userAgent)) {
        $device['is_mobile'] = true;
        $device['is_android'] = true;
        $device['platform'] = 'Android';
        $device['supports_push'] = true;
        $device['requires_pwa'] = false;
        $device['recommended_method'] = 'push';
        
        if (preg_match('/Chrome/', $userAgent)) {
            $device['is_chrome'] = true;
            $device['browser'] = 'Chrome';
        } elseif (preg_match('/Firefox/', $userAgent)) {
            $device['is_firefox'] = true;
            $device['browser'] = 'Firefox';
        }
    }
    // Desktop
    else {
        $device['is_desktop'] = true;
        $device['platform'] = 'Desktop';
        $device['supports_push'] = true;
        $device['requires_pwa'] = false;
        $device['recommended_method'] = 'push';
        
        if (preg_match('/Safari/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $device['is_safari'] = true;
            $device['browser'] = 'Safari';
        } elseif (preg_match('/Chrome/', $userAgent)) {
            $device['is_chrome'] = true;
            $device['browser'] = 'Chrome';
        } elseif (preg_match('/Firefox/', $userAgent)) {
            $device['is_firefox'] = true;
            $device['browser'] = 'Firefox';
        }
    }
    
    return $device;
}

// اكتشاف الجهاز
$device = detectUserDevice();

// عنوان الصفحة والرسالة حسب نوع الجهاز
$pageTitle = 'تفعيل الإشعارات';
$pageIcon = 'bi-bell-fill';
$pageMessage = '';
$showPWAGuide = false;
$showSMSForm = false;
$showPushButton = false;

if ($device['is_ios']) {
    if ($device['ios_version'] >= 16.4 && $device['is_safari']) {
        // iOS 16.4+ مع Safari - يحتاج PWA
        $pageTitle = 'تثبيت التطبيق وتفعيل الإشعارات';
        $pageIcon = 'bi-apple';
        $pageMessage = 'لتفعيل الإشعارات على iPhone، يجب تثبيت التطبيق أولاً';
        $showPWAGuide = true;
    } elseif ($device['is_safari']) {
        // iOS قديم - SMS فقط
        $pageTitle = 'اشترك بإشعارات SMS';
        $pageIcon = 'bi-chat-dots-fill';
        $pageMessage = 'إصدار iOS الحالي لا يدعم الإشعارات. استخدم SMS كبديل';
        $showSMSForm = true;
    } else {
        // متصفح آخر على iOS
        $pageTitle = 'يجب استخدام Safari';
        $pageIcon = 'bi-exclamation-triangle-fill';
        $pageMessage = 'للحصول على الإشعارات، افتح الموقع في Safari';
        $showSMSForm = true; // عرض SMS كبديل
    }
} elseif ($device['is_android'] || $device['is_desktop']) {
    // Android أو Desktop - Push مباشر
    $pageTitle = 'تفعيل الإشعارات';
    $pageIcon = 'bi-bell-fill';
    $pageMessage = 'فعّل الإشعارات للحصول على تحديثات فورية';
    $showPushButton = true;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($pageTitle) ?> - جذلة</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="manifest" href="/manifest.json">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .main-card {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .page-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            margin: 0 auto 1.5rem;
        }
        .device-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border-radius: 50px;
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 1rem;
        }
        .btn-action {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .alternative-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="main-card">
        <div class="text-center">
            <div class="page-icon">
                <i class="<?= $pageIcon ?>"></i>
            </div>
            
            <div class="device-badge">
                <i class="bi bi-<?= $device['is_mobile'] ? 'phone' : 'laptop' ?> me-1"></i>
                <?= htmlspecialchars($device['platform']) ?> · <?= htmlspecialchars($device['browser']) ?>
            </div>
            
            <h2 class="mb-3"><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="text-muted mb-4"><?= htmlspecialchars($pageMessage) ?></p>
        </div>

        <?php if ($showPWAGuide): ?>
            <!-- دليل تثبيت PWA لـ iOS -->
            <div class="alert alert-info">
                <h6 class="alert-heading">
                    <i class="bi bi-info-circle me-2"></i>
                    خطوات التثبيت:
                </h6>
                <ol class="mb-0">
                    <li>اضغط على زر المشاركة <i class="bi bi-box-arrow-up"></i> أسفل الشاشة</li>
                    <li>اختر "إضافة إلى الشاشة الرئيسية"</li>
                    <li>افتح التطبيق من الشاشة الرئيسية</li>
                    <li>فعّل الإشعارات عند الطلب</li>
                </ol>
            </div>
            
            <div class="text-center mt-4">
                <a href="دليل_تثبيت_التطبيق_iPhone.html" class="btn btn-action">
                    <i class="bi bi-book me-2"></i>
                    دليل مفصّل بالصور
                </a>
            </div>

        <?php elseif ($showPushButton): ?>
            <!-- زر تفعيل Push مباشر -->
            <div class="text-center">
                <button class="btn btn-action btn-lg" onclick="requestPushPermission()">
                    <i class="bi bi-bell-fill me-2"></i>
                    فعّل الإشعارات الآن
                </button>
                
                <p class="mt-3 text-muted">
                    <small>
                        <i class="bi bi-shield-check me-1"></i>
                        آمن ويمكنك إلغاء الاشتراك في أي وقت
                    </small>
                </p>
            </div>

        <?php elseif ($showSMSForm): ?>
            <!-- نموذج اشتراك SMS -->
            <div class="alert alert-warning">
                <i class="bi bi-info-circle me-2"></i>
                <strong>ملاحظة:</strong> إشعارات Push غير متاحة على جهازك. استخدم SMS كبديل موثوق
            </div>
            
            <form id="smsForm" onsubmit="subscribeSMS(event)">
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <select class="form-control" id="countryCode" required>
                            <option value="+966" selected>🇸🇦 +966</option>
                            <option value="+971">🇦🇪 +971</option>
                            <option value="+965">🇰🇼 +965</option>
                        </select>
                    </div>
                    <div class="col-8">
                        <input type="tel" 
                               class="form-control" 
                               id="phoneNumber" 
                               placeholder="5xxxxxxxx"
                               maxlength="9"
                               required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-action w-100">
                    <i class="bi bi-chat-dots-fill me-2"></i>
                    اشترك بإشعارات SMS
                </button>
            </form>
        <?php endif; ?>

        <!-- البدائل المتاحة -->
        <div class="alternative-box">
            <h6 class="mb-3">
                <i class="bi bi-grid-3x3 text-primary me-2"></i>
                طرق أخرى للإشعارات:
            </h6>
            
            <div class="d-grid gap-2">
                <?php if (!$showSMSForm): ?>
                <a href="sms_subscription_widget.html" class="btn btn-outline-success">
                    <i class="bi bi-chat-dots-fill me-2"></i>
                    إشعارات SMS
                </a>
                <?php endif; ?>
                
                <a href="mailto:?subject=اشترك في جذلة" class="btn btn-outline-primary">
                    <i class="bi bi-envelope-fill me-2"></i>
                    إشعارات Email
                </a>
                
                <a href="https://wa.me/?text=اشترك" class="btn btn-outline-success">
                    <i class="bi bi-whatsapp me-2"></i>
                    WhatsApp
                </a>
            </div>
        </div>

        <!-- معلومات الجهاز (للتطوير) -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="mt-4">
            <details>
                <summary class="text-muted">معلومات الجهاز (Debug)</summary>
                <pre class="mt-2 p-2 bg-light rounded" style="font-size: 0.75rem; overflow-x: auto;">
<?= htmlspecialchars(json_encode($device, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
                </pre>
            </details>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const GROOM_ID = <?= $_GET['groom'] ?? 'null' ?>;
        const device = <?= json_encode($device) ?>;

        // طلب إذن Push Notification
        async function requestPushPermission() {
            if (!('Notification' in window)) {
                Swal.fire({
                    icon: 'error',
                    title: 'غير مدعوم',
                    text: 'متصفحك لا يدعم الإشعارات'
                });
                return;
            }

            try {
                const permission = await Notification.requestPermission();
                
                if (permission === 'granted') {
                    // تسجيل Service Worker
                    if ('serviceWorker' in navigator) {
                        const registration = await navigator.serviceWorker.register('/sw.js');
                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: 'YOUR_VAPID_PUBLIC_KEY'
                        });
                        
                        // إرسال الاشتراك للسيرفر
                        await fetch('/api/subscribe_push.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                groom_id: GROOM_ID,
                                subscription: subscription
                            })
                        });
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'رائع! 🎉',
                            text: 'تم تفعيل الإشعارات بنجاح',
                            timer: 3000
                        });
                        
                        // إشعار تجريبي
                        new Notification('مرحباً! 👋', {
                            body: 'سنرسل لك إشعاراً عند إضافة صور جديدة',
                            icon: '/assets/icon-192.png'
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'تم الرفض',
                        text: 'لن تتلقى إشعارات. يمكنك تفعيلها لاحقاً من إعدادات المتصفح'
                    });
                }
            } catch (error) {
                console.error('Push subscription error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: 'حدث خطأ في التفعيل: ' + error.message
                });
            }
        }

        // اشتراك SMS
        function subscribeSMS(event) {
            event.preventDefault();
            
            const phone = document.getElementById('phoneNumber').value;
            const countryCode = document.getElementById('countryCode').value;
            
            Swal.fire({
                title: 'جاري المعالجة...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            fetch('/api/sms_subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    groom_id: GROOM_ID,
                    phone: phone,
                    country_code: countryCode
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الإرسال!',
                        text: 'تحقق من رسائلك النصية للحصول على كود التحقق',
                        confirmButtonText: 'حسناً'
                    }).then(() => {
                        window.location.href = 'sms_subscription_widget.html?subscription_id=' + data.subscription_id;
                    });
                } else {
                    throw new Error(data.error);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: error.message
                });
            });
        }

        // تنظيف إدخال الهاتف
        document.getElementById('phoneNumber')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>