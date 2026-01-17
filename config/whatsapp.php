<?php
/**
 * ============================================
 * إعدادات WhatsApp Business API - جذلة
 * ============================================
 * 
 * الملف: config/whatsapp.php
 * آخر تحديث: 17 يناير 2026
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
    // ⚠️ هام: Token مؤقت - يجب تجديده أو استبداله بـ Permanent Token
    'access_token' => 'EAAMi8qLree4BQTaTKJdo0LsWfupBs9ZCZA2Vewayyf3MehENDuQJMcDOna9dFi2cKOxLbO6YEZBuEQG1ypkKCZBHUbAxVZAlZBMsL2iFfbo5ZAvZCx8hcD9rdnI7av0WZAm0lWulF2ZAJmvYmqAGhZCXmgT5fNMoAaECHZAEhIjPQnqriIDy6KTlZBvQ287YJM4nrLHM6Ib9l9g7nZBj9eUryCb3n7ehZCSpdE4iubp9JLA',
    
    // إصدار الـ API
    'api_version' => 'v21.0',
    
    // رابط الـ API الأساسي
    'base_url' => 'https://graph.facebook.com',
    
    // ============================================
    // قوالب الرسائل - محدثة
    // ============================================
    'templates' => [
        // المرحلة 1: حجز جديد
        'booking_confirmation' => [
            'name' => 'booking_confirmation',
            'stage' => 'new_booking',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'wedding_date', 'package_name', 'total_price']
        ],
        
        // المرحلة 2: تنسيق
        'coordination_request' => [
            'name' => 'coordination_request',
            'stage' => 'coordination',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'coordination_link']
        ],
        
        // المرحلة 3: تعيين الفريق
        'team_assignment' => [
            'name' => 'team_assignment',
            'stage' => 'team_assigned',
            'recipient' => 'team',
            'variables' => ['employee_name', 'groom_name', 'wedding_date', 'venue', 'wedding_time']
        ],
        
        // المرحلة 4: إرشادات
        'photo_guidelines' => [
            'name' => 'photo_guidelines',
            'stage' => 'guidelines_sent',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'guidelines_text']
        ],
        
        // المرحلة 5: تذكير
        'reminder_groom' => [
            'name' => 'reminder_groom',
            'stage' => 'reminder_sent',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'venue', 'payment_note']
        ],
        'reminder_team' => [
            'name' => 'reminder_team',
            'stage' => 'reminder_sent',
            'recipient' => 'team',
            'variables' => ['groom_name', 'venue', 'wedding_time']
        ],
        
        // المرحلة 6: يوم الزواج
        'wedding_today' => [
            'name' => 'wedding_today',
            'stage' => 'wedding_day',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'venue', 'arrival_time']
        ],
        
        // المرحلة 7: معالجة
        'processing_start' => [
            'name' => 'processing_start',
            'stage' => 'processing',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'delivery_date']
        ],
        
        // المرحلة 8: تسليم
        'grooms_ready' => [
            'name' => 'grooms_ready',
            'stage' => 'delivered',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'gallery_link']
        ],
        
        // المرحلة 9: تقييم
        'review_request' => [
            'name' => 'review_request',
            'stage' => 'review_requested',
            'recipient' => 'groom',
            'variables' => ['groom_name', 'review_link']
        ],
        
        // المرحلة 10: شكر
        'thank_you' => [
            'name' => 'thank_you',
            'stage' => 'closed',
            'recipient' => 'groom',
            'variables' => ['groom_name']
        ]
    ],
    
    // ============================================
    // المراحل وقوالبها التلقائية
    // ============================================
    'stage_templates' => [
        'new_booking' => 'booking_confirmation',
        'coordination' => 'coordination_request',
        'team_assigned' => 'team_assignment',
        'guidelines_sent' => 'photo_guidelines',
        'reminder_sent' => 'reminder_groom',
        'wedding_day' => 'wedding_today',
        'processing' => 'processing_start',
        'delivered' => 'grooms_ready',
        'review_requested' => 'review_request',
        'closed' => 'thank_you'
    ],
    
    // إعدادات إضافية
    'settings' => [
        'retry_attempts' => 3,
        'retry_delay' => 5,
        'log_messages' => true,
        'debug_mode' => false,
        'auto_send_enabled' => true
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
    
    if (empty($config['access_token']) || strlen($config['access_token']) < 50) {
        $errors[] = 'Access Token غير صالح';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// ============================================
// دالة للحصول على قالب حسب المرحلة
// ============================================
function getTemplateForStage($stage, $recipientType = 'groom') {
    $config = WHATSAPP_CONFIG;
    
    foreach ($config['templates'] as $key => $template) {
        if ($template['stage'] === $stage && $template['recipient'] === $recipientType) {
            return $template;
        }
    }
    
    return null;
}
