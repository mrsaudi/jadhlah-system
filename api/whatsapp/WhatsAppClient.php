<?php
/**
 * ============================================
 * WhatsApp API Client - جذلة
 * ============================================
 * 
 * الملف: api/whatsapp/WhatsAppClient.php
 * الوظيفة: كلاس رئيسي للتعامل مع WhatsApp Business API
 */

class WhatsAppClient {
    
    private $phoneNumberId;
    private $accessToken;
    private $apiVersion;
    private $baseUrl;
    private $debug;
    
    /**
     * Constructor
     */
    public function __construct($config = null) {
        if ($config === null) {
            // تحميل من ملف الإعدادات
            define('JADHLAH_APP', true);
            require_once __DIR__ . '/../../config/whatsapp.php';
            $config = WHATSAPP_CONFIG;
        }
        
        $this->phoneNumberId = $config['phone_number_id'];
        $this->accessToken = $config['access_token'];
        $this->apiVersion = $config['api_version'] ?? 'v21.0';
        $this->baseUrl = $config['base_url'] ?? 'https://graph.facebook.com';
        $this->debug = $config['settings']['debug_mode'] ?? false;
    }
    
    /**
     * إرسال رسالة نصية بسيطة
     * 
     * @param string $to رقم الهاتف (مع كود الدولة)
     * @param string $message نص الرسالة
     * @return array
     */
    public function sendTextMessage($to, $message) {
        $to = $this->formatPhoneNumber($to);
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message
            ]
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال رسالة قالب (Template Message)
     * 
     * @param string $to رقم الهاتف
     * @param string $templateName اسم القالب
     * @param string $language لغة القالب
     * @param array $components متغيرات القالب
     * @return array
     */
    public function sendTemplate($to, $templateName, $language = 'ar', $components = []) {
        $to = $this->formatPhoneNumber($to);
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language
                ]
            ]
        ];
        
        // إضافة المتغيرات إذا وجدت
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال إشعار جاهزية الصور
     * 
     * @param string $to رقم الهاتف
     * @param string $groomName اسم العريس
     * @param string $pageUrl رابط الصفحة
     * @return array
     */
    public function sendPhotosReadyNotification($to, $groomName, $pageUrl) {
        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $groomName
                    ],
                    [
                        'type' => 'text',
                        'text' => $pageUrl
                    ]
                ]
            ]
        ];
        
        return $this->sendTemplate($to, 'grooms_ready', 'ar', $components);
    }
    
    /**
     * إرسال رسالة مع أزرار
     * 
     * @param string $to رقم الهاتف
     * @param string $body نص الرسالة
     * @param array $buttons الأزرار
     * @return array
     */
    public function sendInteractiveButtons($to, $body, $buttons) {
        $to = $this->formatPhoneNumber($to);
        
        $formattedButtons = [];
        foreach ($buttons as $index => $button) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => 'btn_' . ($index + 1),
                    'title' => substr($button['title'], 0, 20) // الحد الأقصى 20 حرف
                ]
            ];
        }
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $body
                ],
                'action' => [
                    'buttons' => $formattedButtons
                ]
            ]
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال صورة
     * 
     * @param string $to رقم الهاتف
     * @param string $imageUrl رابط الصورة
     * @param string $caption النص المرافق
     * @return array
     */
    public function sendImage($to, $imageUrl, $caption = '') {
        $to = $this->formatPhoneNumber($to);
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
                'caption' => $caption
            ]
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال طلب HTTP للـ API
     * 
     * @param array $payload البيانات المرسلة
     * @return array
     */
    private function sendRequest($payload) {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // تسجيل في حالة الـ Debug
        if ($this->debug) {
            $this->logRequest($url, $payload, $response, $httpCode);
        }
        
        // معالجة الخطأ
        if ($error) {
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $error,
                'http_code' => $httpCode
            ];
        }
        
        $result = json_decode($response, true);
        
        // التحقق من نجاح الإرسال
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['messages'])) {
            return [
                'success' => true,
                'message_id' => $result['messages'][0]['id'] ?? null,
                'data' => $result
            ];
        }
        
        // في حالة الخطأ
        return [
            'success' => false,
            'error' => $result['error']['message'] ?? 'Unknown error',
            'error_code' => $result['error']['code'] ?? null,
            'http_code' => $httpCode,
            'data' => $result
        ];
    }
    
    /**
     * تنسيق رقم الهاتف
     * 
     * @param string $phone رقم الهاتف
     * @return string
     */
    private function formatPhoneNumber($phone) {
        // إزالة كل الأحرف غير الرقمية
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // إذا بدأ بـ 0، أزل الصفر وأضف كود السعودية
        if (substr($phone, 0, 1) === '0') {
            $phone = '966' . substr($phone, 1);
        }
        
        // إذا لم يبدأ بكود الدولة، أضف كود السعودية
        if (strlen($phone) === 9) {
            $phone = '966' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * تسجيل الطلبات للتشخيص
     */
    private function logRequest($url, $payload, $response, $httpCode) {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/whatsapp_' . date('Y-m-d') . '.log';
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $url,
            'payload' => $payload,
            'response' => json_decode($response, true),
            'http_code' => $httpCode
        ];
        
        file_put_contents(
            $logFile, 
            json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n",
            FILE_APPEND
        );
    }
    
    /**
     * التحقق من حالة الرقم على واتساب
     * (غير متاح في الإصدار الأساسي)
     */
    public function checkNumberStatus($phone) {
        // ملاحظة: هذه الميزة تتطلب إذن خاص من Meta
        return [
            'success' => false,
            'error' => 'هذه الميزة غير متاحة حالياً'
        ];
    }
}
