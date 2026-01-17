<?php
/**
 * ============================================
 * إعدادات WhatsApp Business API - جذلة
 * ============================================
 * 
 * الملف: config/whatsapp.php
 * الوظيفة: تخزين إعدادات الاتصال بـ WhatsApp API
 * 
 * ⚠️ تنبيه: لا تشارك هذا الملف مع أي شخص!
 */

// منع الوصول المباشر
if (!defined('JADHLAH_APP')) {
    die('Access denied');
}

// ============================================
// إعدادات WhatsApp Business API
// ============================================

define('WHATSAPP_CONFIG', [
    // معرف رقم الهاتف (Phone Number ID)
    'phone_number_id' => '853227224550011',
    
    // معرف حساب واتساب للأعمال (WABA ID)
    'waba_id' => '755511313662622',
    
    // معرف التطبيق (App ID)
    'app_id' => '882850440903150',
    
    // رقم الهاتف
    'phone_number' => '+966552585043',
    
    // Access Token
    // ⚠️ هام: هذا Token مؤقت! يجب استبداله بـ Permanent Token
    // راجع: https://developers.facebook.com/docs/whatsapp/business-management-api/get-started
    'access_token' => 'EAAMi8qLree4BQTaTKJdo0LsWfupBs9ZCZA2Vewayyf3MehENDuQJMcDOna9dFi2cKOxLbO6YEZBuEQG1ypkKCZBHUbAxVZAlZBMsL2iFfbo5ZAvZCx8hcD9rdnI7av0WZAm0lWulF2ZAJmvYmqAGhZCXmgT5fNMoAaECHZAEhIjPQnqriIDy6KTlZBvQ287YJM4nrLHM6Ib9l9g7nZBj9eUryCb3n7ehZCSpdE4iubp9JLA',
    
    // إصدار الـ API
    'api_version' => 'v21.0',
    
    // رابط الـ API الأساسي
    'base_url' => 'https://graph.facebook.com',
    
    // اسم القالب لإشعار الجاهزية
    'templates' => [
        'grooms_ready' => 'grooms_ready',
        'booking_confirmation' => 'booking_confirmation',
        'reminder' => 'reminder',
        'thank_you' => 'thank_you'
    ],
    
    // إعدادات إضافية
    'settings' => [
        'retry_attempts' => 3,
        'retry_delay' => 5, // ثواني
        'log_messages' => true,
        'debug_mode' => false
    ]
]);

// ============================================
// دالة مساعدة للحصول على رابط API الكامل
// ============================================
function getWhatsAppApiUrl($endpoint = '') {
    $config = WHATSAPP_CONFIG;
    $base = $config['base_url'] . '/' . $config['api_version'];
    
    if ($endpoint) {
        return $base . '/' . $endpoint;
    }
    
    return $base . '/' . $config['phone_number_id'] . '/messages';
}

// ============================================
// دالة للتحقق من صحة الإعدادات
// ============================================
function validateWhatsAppConfig() {
    $config = WHATSAPP_CONFIG;
    $errors = [];
    
    if (empty($config['phone_number_id'])) {
        $errors[] = 'Phone Number ID مطلوب';
    }
    
    if (empty($config['access_token']) || $config['access_token'] === 'YOUR_PERMANENT_ACCESS_TOKEN_HERE') {
        $errors[] = 'Access Token غير مُعَد - يرجى إضافة Token صالح';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
