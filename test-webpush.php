<?php
// test-webpush.php - نسخة محسّنة
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

echo "<h2>Web-Push Test</h2>";
echo "<pre>";

// معلومات البيئة
echo "=== Environment ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current Dir: " . __DIR__ . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n\n";

// اختبار Vendor
echo "=== Vendor Test ===\n";
$vendorPath = __DIR__ . '/vendor';
$autoloadPath = $vendorPath . '/autoload.php';

echo "Vendor Path: $vendorPath\n";
echo "Vendor Exists: " . (is_dir($vendorPath) ? 'YES ✅' : 'NO ❌') . "\n";
echo "Autoload Exists: " . (file_exists($autoloadPath) ? 'YES ✅' : 'NO ❌') . "\n\n";

if (!file_exists($autoloadPath)) {
    die("❌ STOP: vendor/autoload.php not found!\n");
}

// تحميل Autoload
echo "=== Loading Autoload ===\n";
try {
    require_once $autoloadPath;
    echo "Autoload loaded ✅\n\n";
} catch (Exception $e) {
    die("❌ ERROR loading autoload: " . $e->getMessage() . "\n");
}

// اختبار المكتبة
echo "=== Web-Push Library Test ===\n";
if (class_exists('Minishlink\WebPush\WebPush')) {
    echo "Class found ✅\n";
    echo "Library: Minishlink Web-Push v6.0\n";
    
    // محاولة إنشاء instance
    try {
        $auth = [
            'VAPID' => [
                'subject' => 'mailto:144saud@gmail.com',
                'publicKey' => 'BGPg8NVEONBIQDl1Lebq3o6KvU6mwdTZshgSIpku778po5bpl_Z3_0XTD1R6WL-Cbm2oQFC0Er2Zf_kpTtRZN6A',
                'privateKey' => 'T0V6FetGsWIsbcbXNY4Ksp6Js26LZBofFNpWOR1vflc',
            ]
        ];
        
        $webPush = new Minishlink\WebPush\WebPush($auth);
        echo "WebPush instance created ✅\n";
        echo "\n🎉 ALL TESTS PASSED!\n";
        
    } catch (Exception $e) {
        echo "❌ ERROR creating instance: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "❌ Class NOT found!\n";
    echo "Available classes:\n";
    print_r(get_declared_classes());
}

echo "</pre>";
?>