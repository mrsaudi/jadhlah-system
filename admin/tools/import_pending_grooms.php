<?php
// admin/tools/import_grooms_enhanced.php
// نظام استيراد محسن وآمن من Google Sheets

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '512M');

session_start();

// التحقق من الصلاحيات
if (empty($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

// التحقق من صلاحية المدير فقط
$role = $_SESSION['role'] ?? 'employ';
if (!in_array($role, ['manager', 'work'])) {
    die(json_encode(['success' => false, 'error' => 'ليس لديك صلاحية الاستيراد']));
}

require_once dirname(__DIR__) . '/config.php';

// ============ الإعدادات ============
class ImportConfig {
    // رابط Google Sheets
    const SHEET_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQwZKxSN1xw1hjbn6MY2eB-7cmG-S_AS11MswrhqkOoq8ALcECXf3EKb2ejFMRwQ80-7ds4_IPK90C8/pub?output=csv';
    
    // الحد الأقصى للسجلات في كل دفعة
    const BATCH_SIZE = 50;
    
    // مهلة الاتصال بـ Google Sheets
    const TIMEOUT = 30;
    
    // تفعيل وضع التجربة (لا يحفظ في قاعدة البيانات)
    const DRY_RUN = false;
    
    // الحقول المطلوبة
    const REQUIRED_FIELDS = ['groom_name'];
    
    // ترتيب الأعمدة في CSV (بناءً على الملف الفعلي)
    const COLUMN_MAPPING = [
        0 => 'timestamp',           // الطابع الزمني
        1 => 'groom_name',          // اسم العريس
        2 => 'phone',               // رقم الجوال
        3 => 'booking_date',        // تاريخ الحجز
        4 => 'location',            // الموقع/القاعة
        5 => 'package',             // الباقة
        6 => 'services',            // الخدمات
        7 => 'equipment',           // المعدات
        8 => 'time_slot',           // الوقت
        9 => 'delivery_method',     // طريقة التسليم
        10 => 'paid_amount',        // المبلغ المدفوع
        11 => 'remaining_amount',   // المبلغ المتبقي
        12 => 'total_amount',       // المبلغ الإجمالي
        13 => 'employee_name',      // اسم الموظف
        14 => 'employee_email',     // بريد الموظف
        15 => 'invoice_number',     // رقم الفاتورة
        16 => 'invoice_date',       // تاريخ الفاتورة
        17 => 'doc_id',             // معرف المستند
        18 => 'doc_url',            // رابط المستند
        19 => 'doc_view_url',       // رابط عرض المستند
        20 => 'doc_status',         // حالة المستند
        21 => 'groom_id'            // معرف العريس (إن وجد)
    ];
}

// ============ فئة الاستيراد الرئيسية ============
class GroomImporter {
    private $pdo;
    private $stats;
    private $errors;
    private $logFile;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->stats = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'updated' => 0,
            'failed' => 0
        ];
        $this->errors = [];
        $this->initializeLog();
    }
    
    /**
     * تهيئة ملف السجل
     */
    private function initializeLog() {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . '/import_' . date('Y-m-d') . '.log';
        $this->log("========================================");
        $this->log("بدء جلسة استيراد جديدة - " . $_SESSION['user']);
    }
    
    /**
     * كتابة في السجل
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message\n";
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * جلب البيانات من Google Sheets
     */
    private function fetchSheetData() {
        $this->log("جلب البيانات من Google Sheets...");
        
        $context = stream_context_create([
            'http' => [
                'timeout' => ImportConfig::TIMEOUT,
                'user_agent' => 'Mozilla/5.0 (compatible; GroomImporter/1.0)'
            ]
        ]);
        
        $csvContent = @file_get_contents(ImportConfig::SHEET_URL, false, $context);
        
        if (!$csvContent) {
            throw new Exception("فشل في الاتصال بـ Google Sheets");
        }
        
        $this->log("تم جلب البيانات بنجاح - الحجم: " . strlen($csvContent) . " bytes");
        return $csvContent;
    }
    
    /**
     * تحليل CSV إلى صفوف
     */
    private function parseCSV($csvContent) {
        $rows = [];
        $lines = explode("\n", $csvContent);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // تحليل السطر مع معالجة الفواصل داخل علامات الاقتباس
            $row = str_getcsv($line);
            if (!empty($row)) {
                $rows[] = $row;
            }
        }
        
        // إزالة السطر الأول (العناوين)
        if (!empty($rows)) {
            array_shift($rows);
        }
        
        $this->log("تم تحليل " . count($rows) . " سطر");
        return $rows;
    }
    
    /**
     * تنظيف وتحضير البيانات
     */
    private function prepareData($row) {
        $data = [];
        
        foreach (ImportConfig::COLUMN_MAPPING as $index => $field) {
            $value = isset($row[$index]) ? trim($row[$index]) : '';
            
            // معالجة خاصة لبعض الحقول
            switch ($field) {
                case 'timestamp':
                case 'booking_date':
                case 'invoice_date':
                    $data[$field] = $this->convertDate($value);
                    break;
                    
                case 'paid_amount':
                case 'remaining_amount':
                case 'total_amount':
                    $data[$field] = $this->cleanAmount($value);
                    break;
                    
                case 'phone':
                    $data[$field] = $this->cleanPhone($value);
                    break;
                    
                case 'groom_id':
                    $data[$field] = !empty($value) && $value !== 'NULL' ? (int)$value : null;
                    break;
                    
                default:
                    $data[$field] = !empty($value) ? $value : null;
            }
        }
        
        return $data;
    }
    
    /**
     * تحويل التاريخ العربي
     */
    private function convertDate($dateStr) {
        if (empty($dateStr)) return null;
        
        // تحويل ص و م إلى AM/PM
        $dateStr = str_replace(['ص', 'م'], ['AM', 'PM'], $dateStr);
        
        // محاولة تحويل التاريخ
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        // محاولة أخرى مع تنسيقات مختلفة
        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d'
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        
        return null;
    }
    
    /**
     * تنظيف المبلغ المالي
     */
    private function cleanAmount($amount) {
        if (empty($amount)) return '0.00';
        
        // إزالة كل شيء عدا الأرقام والنقطة
        $cleaned = preg_replace('/[^0-9.]/', '', $amount);
        
        // التأكد من أنه رقم صحيح
        return is_numeric($cleaned) ? number_format((float)$cleaned, 2, '.', '') : '0.00';
    }
    
    /**
     * تنظيف رقم الهاتف
     */
    private function cleanPhone($phone) {
        if (empty($phone)) return null;
        
        // إزالة كل شيء عدا الأرقام
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // إضافة رمز السعودية إذا لم يكن موجوداً
        if (strlen($cleaned) == 9 && substr($cleaned, 0, 1) == '5') {
            $cleaned = '966' . $cleaned;
        } elseif (strlen($cleaned) == 10 && substr($cleaned, 0, 2) == '05') {
            $cleaned = '966' . substr($cleaned, 1);
        }
        
        return !empty($cleaned) ? $cleaned : null;
    }
    
    /**
     * التحقق من صحة البيانات
     */
    private function validateData($data) {
        $errors = [];
        
        // التحقق من الحقول المطلوبة
        foreach (ImportConfig::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $errors[] = "الحقل المطلوب '$field' فارغ";
            }
        }
        
        // التحقق من طول اسم العريس
        if (isset($data['groom_name']) && strlen($data['groom_name']) < 3) {
            $errors[] = "اسم العريس قصير جداً";
        }
        
        // التحقق من صحة رقم الهاتف
        if (!empty($data['phone']) && !preg_match('/^[0-9]{9,15}$/', $data['phone'])) {
            $errors[] = "رقم الهاتف غير صحيح: " . $data['phone'];
        }
        
        return $errors;
    }
    
    /**
     * التحقق من وجود السجل
     */
    private function recordExists($data) {
        $stmt = $this->pdo->prepare("
            SELECT id, groom_id 
            FROM pending_grooms 
            WHERE groom_name = ? 
            AND (phone = ? OR (phone IS NULL AND ? IS NULL))
            LIMIT 1
        ");
        
        $stmt->execute([
            $data['groom_name'],
            $data['phone'],
            $data['phone']
        ]);
        
        return $stmt->fetch();
    }
    /**
 * التحقق من أن السجل محذوف مسبقاً
 */
private function isDeleted($data) {
    // التحقق من جدول المحذوفين
    $stmt = $this->pdo->prepare("
        SELECT id FROM deleted_pending_grooms 
        WHERE groom_name = ? 
        AND (phone = ? OR (phone IS NULL AND ? IS NULL))
        LIMIT 1
    ");
    
    $stmt->execute([
        $data['groom_name'],
        $data['phone'],
        $data['phone']
    ]);
    
    if ($stmt->fetch()) {
        $this->log("السجل محذوف مسبقاً ولن يتم استيراده: {$data['groom_name']}", 'DELETED');
        return true;
    }
    
    return false;
}
    /**
     * إدراج سجل جديد
     */
    private function insertRecord($data) {
        if (ImportConfig::DRY_RUN) {
            $this->log("وضع التجربة: سيتم إدراج " . $data['groom_name']);
            return true;
        }
        
        $sql = "INSERT INTO pending_grooms (
            timestamp, groom_name, phone, booking_date, location, package,
            services, equipment, time_slot, delivery_method,
            paid_amount, remaining_amount, total_amount,
            employee_name, employee_email, invoice_number, invoice_date,
            doc_id, doc_url, doc_view_url, doc_status,
            created_at, updated_at
        ) VALUES (
            :timestamp, :groom_name, :phone, :booking_date, :location, :package,
            :services, :equipment, :time_slot, :delivery_method,
            :paid_amount, :remaining_amount, :total_amount,
            :employee_name, :employee_email, :invoice_number, :invoice_date,
            :doc_id, :doc_url, :doc_view_url, :doc_status,
            NOW(), NOW()
        )";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            ':timestamp' => $data['timestamp'],
            ':groom_name' => $data['groom_name'],
            ':phone' => $data['phone'],
            ':booking_date' => $data['booking_date'],
            ':location' => $data['location'],
            ':package' => $data['package'],
            ':services' => $data['services'],
            ':equipment' => $data['equipment'],
            ':time_slot' => $data['time_slot'],
            ':delivery_method' => $data['delivery_method'],
            ':paid_amount' => $data['paid_amount'],
            ':remaining_amount' => $data['remaining_amount'],
            ':total_amount' => $data['total_amount'],
            ':employee_name' => $data['employee_name'],
            ':employee_email' => $data['employee_email'],
            ':invoice_number' => $data['invoice_number'],
            ':invoice_date' => $data['invoice_date'],
            ':doc_id' => $data['doc_id'],
            ':doc_url' => $data['doc_url'],
            ':doc_view_url' => $data['doc_view_url'],
            ':doc_status' => $data['doc_status']
        ]);
    }
    
    /**
     * تحديث سجل موجود
     */
    private function updateRecord($id, $data) {
        if (ImportConfig::DRY_RUN) {
            $this->log("وضع التجربة: سيتم تحديث #$id");
            return true;
        }
        
        $sql = "UPDATE pending_grooms SET
            booking_date = :booking_date,
            location = :location,
            package = :package,
            services = :services,
            equipment = :equipment,
            time_slot = :time_slot,
            delivery_method = :delivery_method,
            paid_amount = :paid_amount,
            remaining_amount = :remaining_amount,
            total_amount = :total_amount,
            employee_name = :employee_name,
            invoice_number = :invoice_number,
            invoice_date = :invoice_date,
            doc_status = :doc_status,
            updated_at = NOW()
        WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            ':id' => $id,
            ':booking_date' => $data['booking_date'],
            ':location' => $data['location'],
            ':package' => $data['package'],
            ':services' => $data['services'],
            ':equipment' => $data['equipment'],
            ':time_slot' => $data['time_slot'],
            ':delivery_method' => $data['delivery_method'],
            ':paid_amount' => $data['paid_amount'],
            ':remaining_amount' => $data['remaining_amount'],
            ':total_amount' => $data['total_amount'],
            ':employee_name' => $data['employee_name'],
            ':invoice_number' => $data['invoice_number'],
            ':invoice_date' => $data['invoice_date'],
            ':doc_status' => $data['doc_status']
        ]);
    }
    
    /**
     * معالجة سطر واحد
     */
    private function processRow($row, $rowIndex) {
        try {
            $this->stats['total']++;
            
            // تحضير البيانات
            $data = $this->prepareData($row);
            
            // تخطي إذا كان له groom_id (تم معالجته)
            if (!empty($data['groom_id'])) {
                $this->log("السطر $rowIndex: تخطي - له معرف عريس ({$data['groom_id']})", 'SKIP');
                $this->stats['skipped']++;
                return;
            }
            if ($this->isDeleted($data)) {
    $this->log("السطر $rowIndex: تخطي - محذوف مسبقاً ({$data['groom_name']})", 'DELETED');
    $this->stats['skipped']++;
    return;
}
            
            // التحقق من الصحة
            $validationErrors = $this->validateData($data);
            if (!empty($validationErrors)) {
                throw new Exception("أخطاء التحقق: " . implode(', ', $validationErrors));
            }
            
            // التحقق من الوجود
            $existing = $this->recordExists($data);
            
            if ($existing) {
                if (!empty($existing['groom_id'])) {
                    // تم معالجته مسبقاً
                    $this->log("السطر $rowIndex: تخطي - تم معالجته (groom_id: {$existing['groom_id']})", 'SKIP');
                    $this->stats['skipped']++;
                } else {
                    // تحديث السجل الموجود
                    if ($this->updateRecord($existing['id'], $data)) {
                        $this->log("السطر $rowIndex: تحديث - {$data['groom_name']}", 'UPDATE');
                        $this->stats['updated']++;
                    } else {
                        throw new Exception("فشل في تحديث السجل");
                    }
                }
            } else {
                // إدراج سجل جديد
                if ($this->insertRecord($data)) {
                    $this->log("السطر $rowIndex: استيراد - {$data['groom_name']}", 'SUCCESS');
                    $this->stats['imported']++;
                } else {
                    throw new Exception("فشل في إدراج السجل");
                }
            }
            
        } catch (Exception $e) {
            $this->stats['failed']++;
            $errorMsg = "السطر $rowIndex: " . $e->getMessage();
            $this->errors[] = $errorMsg;
            $this->log($errorMsg, 'ERROR');
        }
    }
    
    /**
     * تنفيذ الاستيراد
     */
    public function import() {
        try {
            $this->log("بدء عملية الاستيراد...");
            
            // جلب البيانات
            $csvContent = $this->fetchSheetData();
            
            // تحليل CSV
            $rows = $this->parseCSV($csvContent);
            
            if (empty($rows)) {
                throw new Exception("لا توجد بيانات للاستيراد");
            }
            
            $this->log("بدء معالجة " . count($rows) . " سطر");
            
            // معالجة الصفوف على دفعات
            $batches = array_chunk($rows, ImportConfig::BATCH_SIZE);
            
            foreach ($batches as $batchIndex => $batch) {
                $this->log("معالجة الدفعة " . ($batchIndex + 1) . " (" . count($batch) . " سطر)");
                
                foreach ($batch as $rowIndex => $row) {
                    $actualIndex = ($batchIndex * ImportConfig::BATCH_SIZE) + $rowIndex + 2; // +2 للعناوين والفهرسة من 1
                    $this->processRow($row, $actualIndex);
                }
                
                // راحة قصيرة بين الدفعات
                if ($batchIndex < count($batches) - 1) {
                    usleep(100000); // 0.1 ثانية
                }
            }
            
            $this->log("========================================");
            $this->log("انتهى الاستيراد - النتائج:");
            $this->log("  - الإجمالي: " . $this->stats['total']);
            $this->log("  - تم استيراد: " . $this->stats['imported']);
            $this->log("  - تم تحديث: " . $this->stats['updated']);
            $this->log("  - تم تخطي: " . $this->stats['skipped']);
            $this->log("  - فشل: " . $this->stats['failed']);
            $this->log("========================================");
            
            return true;
            
        } catch (Exception $e) {
            $this->log("خطأ عام: " . $e->getMessage(), 'CRITICAL');
            throw $e;
        }
    }
    
    /**
     * الحصول على النتائج
     */
    public function getResults() {
        return [
            'success' => true,
            'stats' => $this->stats,
            'errors' => array_slice($this->errors, 0, 10), // أول 10 أخطاء فقط
            'total_errors' => count($this->errors),
            'log_file' => basename($this->logFile)
        ];
    }
}

// ============ تنفيذ الاستيراد ============
header('Content-Type: application/json; charset=utf-8');

try {
    $importer = new GroomImporter($pdo);
    $importer->import();
    $results = $importer->getResults();
    
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'stats' => [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>