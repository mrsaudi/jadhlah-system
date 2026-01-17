<?php
/**
 * ============================================
 * WhatsApp API Client - جذلة
 * ============================================
 * 
 * الملف: api/whatsapp/WhatsAppClient.php
 * آخر تحديث: 17 يناير 2026
 * 
 * دعم كامل لـ:
 * - رسائل نصية
 * - قوالب مع متغيرات
 * - أزرار تفاعلية
 * - إرسال صور ومستندات
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
            if (!defined('JADHLAH_APP')) {
                define('JADHLAH_APP', true);
            }
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
     * إرسال رسالة قالب (Template Message) - محدثة
     * 
     * @param string $to رقم الهاتف
     * @param string $templateName اسم القالب
     * @param string $language لغة القالب
     * @param array $variables متغيرات القالب (مصفوفة بسيطة)
     * @param array $headerParams معاملات الهيدر (صورة/مستند)
     * @param array $buttons أزرار ديناميكية
     * @return array
     */
    public function sendTemplate($to, $templateName, $language = 'ar', $variables = [], $headerParams = null, $buttons = null) {
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
        
        // بناء الـ components
        $components = [];
        
        // 1. Header (صورة أو مستند)
        if ($headerParams) {
            $headerComponent = ['type' => 'header'];
            
            if (isset($headerParams['image'])) {
                $headerComponent['parameters'] = [[
                    'type' => 'image',
                    'image' => ['link' => $headerParams['image']]
                ]];
            } elseif (isset($headerParams['document'])) {
                $headerComponent['parameters'] = [[
                    'type' => 'document',
                    'document' => [
                        'link' => $headerParams['document'],
                        'filename' => $headerParams['filename'] ?? 'document.pdf'
                    ]
                ]];
            } elseif (isset($headerParams['text'])) {
                $headerComponent['parameters'] = [[
                    'type' => 'text',
                    'text' => $headerParams['text']
                ]];
            }
            
            $components[] = $headerComponent;
        }
        
        // 2. Body (المتغيرات)
        if (!empty($variables)) {
            $bodyParams = [];
            foreach ($variables as $value) {
                $bodyParams[] = [
                    'type' => 'text',
                    'text' => (string) $value
                ];
            }
            
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams
            ];
        }
        
        // 3. Buttons (أزرار ديناميكية)
        if ($buttons) {
            foreach ($buttons as $index => $button) {
                if (isset($button['url_suffix'])) {
                    // زر URL ديناميكي
                    $components[] = [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => $index,
                        'parameters' => [[
                            'type' => 'text',
                            'text' => $button['url_suffix']
                        ]]
                    ];
                }
            }
        }
        
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال إشعار جاهزية الصور (الدالة القديمة - للتوافق)
     */
    public function sendPhotosReadyNotification($to, $groomName, $pageUrl) {
        return $this->sendTemplate($to, 'grooms_ready', 'ar', [$groomName, $pageUrl]);
    }
    
    /**
     * إرسال قالب حجز جديد
     */
    public function sendBookingConfirmation($to, $groomName, $weddingDate, $packageName, $totalPrice) {
        return $this->sendTemplate(
            $to, 
            'booking_confirmation', 
            'ar', 
            [$groomName, $weddingDate, $packageName, $totalPrice]
        );
    }
    
    /**
     * إرسال طلب تنسيق
     */
    public function sendCoordinationRequest($to, $groomName, $coordinationLink) {
        return $this->sendTemplate(
            $to, 
            'coordination_request', 
            'ar', 
            [$groomName, $coordinationLink]
        );
    }
    
    /**
     * إرسال إشعار تعيين للموظف
     */
    public function sendTeamAssignment($to, $employeeName, $groomName, $date, $venue, $time) {
        return $this->sendTemplate(
            $to, 
            'team_assignment', 
            'ar', 
            [$employeeName, $groomName, $date, $venue, $time]
        );
    }
    
    /**
     * إرسال تذكير للعريس
     */
    public function sendGroomReminder($to, $groomName, $venue, $paymentNote = '') {
        return $this->sendTemplate(
            $to, 
            'reminder_groom', 
            'ar', 
            [$groomName, $venue, $paymentNote]
        );
    }
    
    /**
     * إرسال تذكير للفريق
     */
    public function sendTeamReminder($to, $groomName, $venue, $time) {
        return $this->sendTemplate(
            $to, 
            'reminder_team', 
            'ar', 
            [$groomName, $venue, $time]
        );
    }
    
    /**
     * إرسال إشعار بدء المعالجة
     */
    public function sendProcessingStart($to, $groomName, $deliveryDate) {
        return $this->sendTemplate(
            $to, 
            'processing_start', 
            'ar', 
            [$groomName, $deliveryDate]
        );
    }
    
    /**
     * إرسال طلب تقييم
     */
    public function sendReviewRequest($to, $groomName, $reviewLink) {
        return $this->sendTemplate(
            $to, 
            'review_request', 
            'ar', 
            [$groomName, $reviewLink]
        );
    }
    
    /**
     * إرسال شكر وتوديع
     */
    public function sendThankYou($to, $groomName) {
        return $this->sendTemplate(
            $to, 
            'thank_you', 
            'ar', 
            [$groomName]
        );
    }
    
    /**
     * إرسال رسالة مع أزرار تفاعلية
     */
    public function sendInteractiveButtons($to, $body, $buttons, $header = null, $footer = null) {
        $to = $this->formatPhoneNumber($to);
        
        $formattedButtons = [];
        foreach ($buttons as $index => $button) {
            $formattedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'] ?? 'btn_' . ($index + 1),
                    'title' => substr($button['title'], 0, 20)
                ]
            ];
        }
        
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $body],
            'action' => ['buttons' => array_slice($formattedButtons, 0, 3)]
        ];
        
        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }
        
        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال قائمة تفاعلية
     */
    public function sendInteractiveList($to, $body, $buttonText, $sections, $header = null, $footer = null) {
        $to = $this->formatPhoneNumber($to);
        
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $body],
            'action' => [
                'button' => substr($buttonText, 0, 20),
                'sections' => $sections
            ]
        ];
        
        if ($header) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }
        
        if ($footer) {
            $interactive['footer'] = ['text' => $footer];
        }
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال صورة
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
     * إرسال مستند (PDF)
     */
    public function sendDocument($to, $documentUrl, $filename, $caption = '') {
        $to = $this->formatPhoneNumber($to);
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption
            ]
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال موقع
     */
    public function sendLocation($to, $latitude, $longitude, $name = '', $address = '') {
        $to = $this->formatPhoneNumber($to);
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'location',
            'location' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $name,
                'address' => $address
            ]
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * إرسال طلب HTTP للـ API
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
        
        if ($this->debug) {
            $this->logRequest($url, $payload, $response, $httpCode);
        }
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $error,
                'http_code' => $httpCode
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['messages'])) {
            return [
                'success' => true,
                'message_id' => $result['messages'][0]['id'] ?? null,
                'data' => $result
            ];
        }
        
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
     */
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '966' . substr($phone, 1);
        }
        
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
     * تعيين Access Token جديد
     */
    public function setAccessToken($token) {
        $this->accessToken = $token;
    }
    
    /**
     * تفعيل/تعطيل وضع التشخيص
     */
    public function setDebugMode($enabled) {
        $this->debug = $enabled;
    }
}
