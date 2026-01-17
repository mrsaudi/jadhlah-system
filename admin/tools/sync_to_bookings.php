<?php
/**
 * ============================================
 * مزامنة الحجوزات من pending_grooms إلى bookings
 * ============================================
 * 
 * الملف: admin/tools/sync_to_bookings.php
 * الوظيفة: نقل بيانات pending_grooms إلى جدول bookings
 * 
 * يمكن تشغيله يدوياً أو كـ Cron Job
 */

// التحقق من الوصول
if (php_sapi_name() !== 'cli') {
    session_start();
    if (empty($_SESSION['user'])) {
        die(json_encode(['success' => false, 'error' => 'غير مصرح']));
    }
}

// تحديد أن هذا التطبيق
define('JADHLAH_APP', true);

// الاتصال بقاعدة البيانات
$host = 'localhost';
$db   = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'فشل الاتصال: ' . $e->getMessage()]));
}

header('Content-Type: application/json; charset=utf-8');

// ============================================
// خريطة تحويل الباقات
// ============================================
$packageMapping = [
    'جوال' => ['id' => 1, 'price' => 400],
    'باقة جوال' => ['id' => 1, 'price' => 400],
    'جذلة كلاسيك' => ['id' => 2, 'price' => 2500],
    'جذله كلاسيك' => ['id' => 2, 'price' => 2500],
    'جذلة كلاسك' => ['id' => 2, 'price' => 2500],
    'كلاسيك' => ['id' => 2, 'price' => 2500],
    'جذلة الذهبية' => ['id' => 3, 'price' => 3800],
    'الذهبية' => ['id' => 3, 'price' => 3800],
    'جذلة vip' => ['id' => 4, 'price' => 5500],
    'vip' => ['id' => 4, 'price' => 5500],
    'عرض خاص' => ['id' => 2, 'price' => 2000],
    'تعامل خاص' => ['id' => 2, 'price' => 2000],
];

// ============================================
// تحديد المرحلة بناءً على البيانات
// ============================================
function determineStage($pending) {
    // إذا له groom_id = تم التسليم
    if (!empty($pending['groom_id'])) {
        return 'delivered';
    }
    
    // إذا التاريخ في الماضي
    if (!empty($pending['booking_date'])) {
        $bookingDate = strtotime($pending['booking_date']);
        $today = strtotime('today');
        
        if ($bookingDate < $today) {
            return 'processing'; // معالجة
        } elseif ($bookingDate == $today) {
            return 'wedding_day'; // يوم الزواج
        } elseif ($bookingDate <= strtotime('+1 day')) {
            return 'reminder_sent'; // تذكير
        } elseif ($bookingDate <= strtotime('+7 days')) {
            return 'guidelines_sent'; // إرشادات
        }
    }
    
    // الافتراضي: حجز جديد
    return 'new_booking';
}

// ============================================
// تحديد حالة الدفع
// ============================================
function determinePaymentStatus($pending) {
    $paid = floatval(preg_replace('/[^0-9.]/', '', $pending['paid_amount'] ?? '0'));
    $total = floatval(preg_replace('/[^0-9.]/', '', $pending['total_amount'] ?? '0'));
    $remaining = floatval(preg_replace('/[^0-9.]/', '', $pending['remaining_amount'] ?? '0'));
    
    if ($total <= 0) return 'unpaid';
    if ($remaining <= 0 || $paid >= $total) return 'paid';
    if ($paid > 0) return 'partial';
    
    return 'unpaid';
}

// ============================================
// البحث عن الباقة المناسبة
// ============================================
function findPackage($packageName, $mapping) {
    if (empty($packageName)) {
        return ['id' => null, 'name' => 'غير محدد', 'price' => 0];
    }
    
    $packageName = trim(strtolower($packageName));
    
    foreach ($mapping as $key => $value) {
        if (strpos($packageName, strtolower($key)) !== false) {
            return [
                'id' => $value['id'],
                'name' => $key,
                'price' => $value['price']
            ];
        }
    }
    
    return ['id' => null, 'name' => $packageName, 'price' => 0];
}

// ============================================
// تحويل الوقت
// ============================================
function parseTimeSlot($timeSlot) {
    // الافتراضي: 7 مساءً - 12 منتصف الليل
    $startTime = '19:00:00';
    $endTime = '00:00:00';
    
    if (empty($timeSlot)) {
        return [$startTime, $endTime];
    }
    
    // أنماط شائعة
    if (preg_match('/العِشاء|العشاء/', $timeSlot)) {
        $startTime = '19:30:00';
    }
    if (preg_match('/العصر/', $timeSlot)) {
        $startTime = '16:00:00';
    }
    if (preg_match('/المغرب/', $timeSlot)) {
        $startTime = '18:00:00';
    }
    if (preg_match('/12:00|12/', $timeSlot)) {
        $endTime = '00:00:00';
    }
    
    return [$startTime, $endTime];
}

// ============================================
// تنفيذ المزامنة
// ============================================
try {
    $stats = [
        'synced' => 0,
        'skipped' => 0,
        'updated' => 0,
        'errors' => 0
    ];
    $errors = [];
    
    // جلب pending_grooms التي لم تُمزامن بعد
    // (نستخدم phone + booking_date للتحقق من عدم التكرار)
    $stmt = $pdo->query("
        SELECT pg.* 
        FROM pending_grooms pg
        WHERE pg.is_deleted = 0
        AND pg.groom_name IS NOT NULL
        AND pg.groom_name != ''
        ORDER BY pg.booking_date ASC
    ");
    
    $pendings = $stmt->fetchAll();
    
    foreach ($pendings as $pending) {
        try {
            // التحقق من عدم وجوده في bookings
            $checkStmt = $pdo->prepare("
                SELECT id FROM bookings 
                WHERE phone = ? AND wedding_date = ?
                LIMIT 1
            ");
            $checkStmt->execute([
                $pending['phone'],
                $pending['booking_date']
            ]);
            
            $existing = $checkStmt->fetch();
            
            // تجهيز البيانات
            $package = findPackage($pending['package'], $packageMapping);
            $stage = determineStage($pending);
            $paymentStatus = determinePaymentStatus($pending);
            list($startTime, $endTime) = parseTimeSlot($pending['time_slot']);
            
            $totalPrice = floatval(preg_replace('/[^0-9.]/', '', $pending['total_amount'] ?? '0'));
            if ($totalPrice <= 0 && $package['price'] > 0) {
                $totalPrice = $package['price'];
            }
            
            if ($existing) {
                // تحديث الموجود
                $updateStmt = $pdo->prepare("
                    UPDATE bookings SET
                        groom_name = ?,
                        venue = ?,
                        stage = CASE WHEN stage = 'new_booking' THEN ? ELSE stage END,
                        payment_status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $pending['groom_name'],
                    $pending['location'],
                    $stage,
                    $paymentStatus,
                    $existing['id']
                ]);
                $stats['updated']++;
            } else {
                // إدراج جديد
                $insertStmt = $pdo->prepare("
                    INSERT INTO bookings (
                        groom_name, phone, wedding_date, wedding_time, wedding_end_time,
                        city, venue, 
                        package_id, package_name, total_price, discount,
                        stage, payment_status, deposit_paid,
                        groom_id, pending_groom_id,
                        notes, source, created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, 0,
                        ?, ?, ?,
                        ?, ?,
                        ?, 'google_sheets', 'sync', NOW()
                    )
                ");
                
                $paidAmount = floatval(preg_replace('/[^0-9.]/', '', $pending['paid_amount'] ?? '0'));
                $depositPaid = ($paidAmount > 0) ? 1 : 0;
                
                // تجميع الملاحظات
                $notes = [];
                if (!empty($pending['services'])) $notes[] = "الخدمات: " . $pending['services'];
                if (!empty($pending['equipment'])) $notes[] = "المعدات: " . $pending['equipment'];
                if (!empty($pending['delivery_method'])) $notes[] = "التسليم: " . $pending['delivery_method'];
                if (!empty($pending['employee_name'])) $notes[] = "الموظف: " . $pending['employee_name'];
                if (!empty($pending['invoice_number'])) $notes[] = "الفاتورة: " . $pending['invoice_number'];
                
                $insertStmt->execute([
                    $pending['groom_name'],
                    $pending['phone'],
                    $pending['booking_date'],
                    $startTime,
                    $endTime,
                    'المدينة المنورة', // الافتراضي
                    $pending['location'],
                    $package['id'],
                    $package['name'],
                    $totalPrice,
                    $stage,
                    $paymentStatus,
                    $depositPaid,
                    $pending['groom_id'],
                    $pending['id'],
                    implode("\n", $notes)
                ]);
                
                $bookingId = $pdo->lastInsertId();
                
                // إضافة الدفعات
                if ($paidAmount > 0) {
                    $pdo->prepare("
                        INSERT INTO booking_payments (booking_id, payment_type, amount, is_paid, paid_at, notes)
                        VALUES (?, 'deposit', ?, 1, ?, 'مزامنة من Google Sheets')
                    ")->execute([
                        $bookingId,
                        $paidAmount,
                        date('Y-m-d', strtotime($pending['timestamp']))
                    ]);
                }
                
                // تسجيل في سجل المراحل
                $pdo->prepare("
                    INSERT INTO booking_stage_log (booking_id, from_stage, to_stage, changed_by, change_type, notes)
                    VALUES (?, NULL, ?, 'sync', 'auto', 'مزامنة تلقائية من Google Sheets')
                ")->execute([$bookingId, $stage]);
                
                $stats['synced']++;
            }
            
        } catch (Exception $rowError) {
            $stats['errors']++;
            $errors[] = "خطأ في {$pending['groom_name']}: " . $rowError->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'errors' => array_slice($errors, 0, 10),
        'message' => "تمت المزامنة: {$stats['synced']} جديد، {$stats['updated']} محدث، {$stats['errors']} أخطاء"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
