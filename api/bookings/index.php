<?php
/**
 * ============================================
 * Bookings API - جذلة
 * ============================================
 * 
 * الملف: api/bookings/index.php
 * الوظيفة: إدارة الحجوزات (جلب، إضافة، تحديث)
 */

// منع الوصول المباشر
if (!defined('JADHLAH_APP')) {
    define('JADHLAH_APP', true);
}

// الهيدرات
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// تحميل إعدادات قاعدة البيانات
require_once __DIR__ . '/../../config/database.php';

// التأكد من وجود الاتصال
if (!isset($pdo)) {
    jsonResponse(['success' => false, 'error' => 'Database connection not available'], 500);
}

// تحديد نوع الطلب
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

switch ($method) {
    case 'GET':
        handleGet($pdo, $action);
        break;
    case 'POST':
        handlePost($pdo, $action);
        break;
    case 'PUT':
        handlePut($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

/**
 * معالجة طلبات GET
 */
function handleGet($pdo, $action) {
    switch ($action) {
        case 'list':
            getBookings($pdo);
            break;
        case 'single':
            getBooking($pdo, $_GET['id'] ?? 0);
            break;
        case 'stats':
            getStats($pdo);
            break;
        case 'stages':
            getStages();
            break;
        case 'packages':
            getPackages($pdo);
            break;
        case 'employees':
            getEmployees($pdo);
            break;
        default:
            getBookings($pdo);
    }
}

/**
 * جلب جميع الحجوزات
 */
function getBookings($pdo) {
    $where = ["1=1"];
    $params = [];
    
    // فلتر المرحلة
    if (!empty($_GET['stage'])) {
        $where[] = "b.stage = :stage";
        $params[':stage'] = $_GET['stage'];
    }
    
    // فلتر الشهر
    if (!empty($_GET['month']) && !empty($_GET['year'])) {
        $where[] = "MONTH(b.wedding_date) = :month AND YEAR(b.wedding_date) = :year";
        $params[':month'] = $_GET['month'];
        $params[':year'] = $_GET['year'];
    } elseif (!empty($_GET['month'])) {
        $where[] = "MONTH(b.wedding_date) = :month AND YEAR(b.wedding_date) = YEAR(CURRENT_DATE())";
        $params[':month'] = $_GET['month'];
    }
    
    // فلتر الباقة
    if (!empty($_GET['package_id'])) {
        $where[] = "b.package_id = :package_id";
        $params[':package_id'] = $_GET['package_id'];
    }
    
    // فلتر حالة الدفع
    if (!empty($_GET['payment_status'])) {
        $where[] = "b.payment_status = :payment_status";
        $params[':payment_status'] = $_GET['payment_status'];
    }
    
    // البحث
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(b.groom_name LIKE :search OR b.phone LIKE :search2 OR b.id LIKE :search3)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
        $params[':search3'] = $search;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT 
                b.*,
                p.name as package_name_full,
                p.color as package_color,
                (SELECT COUNT(*) FROM booking_team bt WHERE bt.booking_id = b.id) as team_count,
                (SELECT COALESCE(SUM(amount), 0) FROM booking_payments bp WHERE bp.booking_id = b.id AND bp.is_paid = 1) as total_paid
            FROM bookings b
            LEFT JOIN packages p ON b.package_id = p.id
            WHERE {$whereClause}
            ORDER BY 
                CASE b.stage
                    WHEN 'new_booking' THEN 1
                    WHEN 'coordination' THEN 2
                    WHEN 'team_assigned' THEN 3
                    WHEN 'guidelines_sent' THEN 4
                    WHEN 'reminder_sent' THEN 5
                    WHEN 'wedding_day' THEN 6
                    WHEN 'processing' THEN 7
                    WHEN 'delivered' THEN 8
                    WHEN 'review_requested' THEN 9
                    WHEN 'closed' THEN 10
                END,
                b.wedding_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تجميع حسب المرحلة
    $stages = ['new_booking', 'coordination', 'team_assigned', 'guidelines_sent',
               'reminder_sent', 'wedding_day', 'processing', 'delivered',
               'review_requested', 'closed'];
    
    $grouped = array_fill_keys($stages, []);
    
    foreach ($bookings as $booking) {
        $grouped[$booking['stage']][] = formatBooking($booking);
    }
    
    jsonResponse([
        'success' => true,
        'data' => $grouped,
        'total' => count($bookings)
    ]);
}

/**
 * جلب حجز واحد بالتفاصيل
 */
function getBooking($pdo, $id) {
    if (!$id) {
        jsonResponse(['success' => false, 'error' => 'معرف الحجز مطلوب'], 400);
    }
    
    $sql = "SELECT 
                b.*,
                p.name as package_name_full,
                p.color as package_color,
                p.price as package_price,
                g.folder_name as groom_folder
            FROM bookings b
            LEFT JOIN packages p ON b.package_id = p.id
            LEFT JOIN grooms g ON b.groom_id = g.id
            WHERE b.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        jsonResponse(['success' => false, 'error' => 'الحجز غير موجود'], 404);
    }
    
    // الدفعات
    $stmt = $pdo->prepare("SELECT * FROM booking_payments WHERE booking_id = :id ORDER BY created_at");
    $stmt->execute([':id' => $id]);
    $booking['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // فريق العمل
    $stmt = $pdo->prepare("
        SELECT bt.*, e.name as employee_name, e.phone as employee_phone, e.role as employee_role
        FROM booking_team bt
        JOIN employees e ON bt.employee_id = e.id
        WHERE bt.booking_id = :id
    ");
    $stmt->execute([':id' => $id]);
    $booking['team'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // سجل المراحل
    $stmt = $pdo->prepare("SELECT * FROM booking_stage_log WHERE booking_id = :id ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([':id' => $id]);
    $booking['stage_log'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // التكاليف
    $stmt = $pdo->prepare("SELECT * FROM booking_costs WHERE booking_id = :id");
    $stmt->execute([':id' => $id]);
    $booking['costs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'data' => $booking]);
}

/**
 * إحصائيات سريعة
 */
function getStats($pdo) {
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE stage != 'closed'");
    $stats['active_bookings'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE MONTH(wedding_date) = MONTH(CURRENT_DATE()) AND YEAR(wedding_date) = YEAR(CURRENT_DATE())");
    $stats['this_month'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status != 'paid' AND stage != 'closed'");
    $stats['pending_payments'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE wedding_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)");
    $stats['upcoming_week'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT stage, COUNT(*) as count FROM bookings GROUP BY stage");
    $stats['by_stage'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    jsonResponse(['success' => true, 'data' => $stats]);
}

/**
 * قائمة المراحل
 */
function getStages() {
    $stages = [
        ['key' => 'new_booking', 'name' => 'حجز جديد', 'color' => '#6366f1'],
        ['key' => 'coordination', 'name' => 'تنسيق', 'color' => '#8b5cf6'],
        ['key' => 'team_assigned', 'name' => 'تعيين الفريق', 'color' => '#a855f7'],
        ['key' => 'guidelines_sent', 'name' => 'إرشادات', 'color' => '#ec4899'],
        ['key' => 'reminder_sent', 'name' => 'تذكير', 'color' => '#f97316'],
        ['key' => 'wedding_day', 'name' => 'يوم الزواج', 'color' => '#eab308'],
        ['key' => 'processing', 'name' => 'معالجة', 'color' => '#22c55e'],
        ['key' => 'delivered', 'name' => 'تسليم', 'color' => '#14b8a6'],
        ['key' => 'review_requested', 'name' => 'تقييم', 'color' => '#06b6d4'],
        ['key' => 'closed', 'name' => 'مغلق', 'color' => '#64748b'],
    ];
    jsonResponse(['success' => true, 'data' => $stages]);
}

/**
 * قائمة الباقات
 */
function getPackages($pdo) {
    $stmt = $pdo->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY display_order");
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

/**
 * قائمة الموظفين
 */
function getEmployees($pdo) {
    $stmt = $pdo->query("SELECT * FROM employees WHERE is_active = 1 ORDER BY name");
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

/**
 * معالجة طلبات POST
 */
function handlePost($pdo, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            createBooking($pdo, $input);
            break;
        case 'update_stage':
            updateStage($pdo, $input);
            break;
        case 'add_payment':
            addPayment($pdo, $input);
            break;
        case 'assign_team':
            assignTeam($pdo, $input);
            break;
        default:
            createBooking($pdo, $input);
    }
}

/**
 * إنشاء حجز جديد
 */
function createBooking($pdo, $data) {
    $required = ['groom_name', 'phone', 'wedding_date', 'package_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            jsonResponse(['success' => false, 'error' => "الحقل {$field} مطلوب"], 400);
        }
    }
    
    // جلب بيانات الباقة
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = :id");
    $stmt->execute([':id' => $data['package_id']]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$package) {
        jsonResponse(['success' => false, 'error' => 'الباقة غير موجودة'], 400);
    }
    
    $sql = "INSERT INTO bookings (
                groom_name, bride_name, phone, phone_secondary, email,
                wedding_date, wedding_time, wedding_end_time,
                city, venue, venue_address, venue_google_maps,
                package_id, package_name, total_price, discount, total_cost,
                stage, source, notes, created_by, created_at
            ) VALUES (
                :groom_name, :bride_name, :phone, :phone_secondary, :email,
                :wedding_date, :wedding_time, :wedding_end_time,
                :city, :venue, :venue_address, :venue_google_maps,
                :package_id, :package_name, :total_price, :discount, :total_cost,
                'new_booking', :source, :notes, :created_by, NOW()
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':groom_name' => $data['groom_name'],
        ':bride_name' => $data['bride_name'] ?? null,
        ':phone' => $data['phone'],
        ':phone_secondary' => $data['phone_secondary'] ?? null,
        ':email' => $data['email'] ?? null,
        ':wedding_date' => $data['wedding_date'],
        ':wedding_time' => $data['wedding_time'] ?? null,
        ':wedding_end_time' => $data['wedding_end_time'] ?? null,
        ':city' => $data['city'] ?? null,
        ':venue' => $data['venue'] ?? null,
        ':venue_address' => $data['venue_address'] ?? null,
        ':venue_google_maps' => $data['venue_google_maps'] ?? null,
        ':package_id' => $data['package_id'],
        ':package_name' => $package['name'],
        ':total_price' => $data['total_price'] ?? $package['price'],
        ':discount' => $data['discount'] ?? 0,
        ':total_cost' => $package['cost'] ?? 0,
        ':source' => $data['source'] ?? 'website',
        ':notes' => $data['notes'] ?? null,
        ':created_by' => $data['created_by'] ?? 'admin'
    ]);
    
    $bookingId = $pdo->lastInsertId();
    
    // إضافة العربون
    if (!empty($data['deposit_amount']) && $data['deposit_amount'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO booking_payments (booking_id, payment_type, amount, is_paid, paid_at, payment_method, notes)
            VALUES (:booking_id, 'deposit', :amount, :is_paid, :paid_at, :method, 'عربون')
        ");
        $stmt->execute([
            ':booking_id' => $bookingId,
            ':amount' => $data['deposit_amount'],
            ':is_paid' => !empty($data['deposit_paid']) ? 1 : 0,
            ':paid_at' => !empty($data['deposit_paid']) ? date('Y-m-d') : null,
            ':method' => $data['payment_method'] ?? 'cash'
        ]);
        
        // تحديث حالة الدفع
        if (!empty($data['deposit_paid'])) {
            $pdo->prepare("UPDATE bookings SET deposit_paid = 1, payment_status = 'partial' WHERE id = :id")
                ->execute([':id' => $bookingId]);
        }
    }
    
    // تسجيل في سجل المراحل
    $stmt = $pdo->prepare("
        INSERT INTO booking_stage_log (booking_id, from_stage, to_stage, changed_by, change_type)
        VALUES (:booking_id, NULL, 'new_booking', :changed_by, 'manual')
    ");
    $stmt->execute([':booking_id' => $bookingId, ':changed_by' => $data['created_by'] ?? 'admin']);
    
    jsonResponse(['success' => true, 'message' => 'تم إنشاء الحجز بنجاح', 'booking_id' => $bookingId]);
}

/**
 * تحديث مرحلة الحجز
 */
function updateStage($pdo, $data) {
    if (empty($data['booking_id']) || empty($data['new_stage'])) {
        jsonResponse(['success' => false, 'error' => 'بيانات ناقصة'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT stage FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $data['booking_id']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        jsonResponse(['success' => false, 'error' => 'الحجز غير موجود'], 404);
    }
    
    $oldStage = $current['stage'];
    $newStage = $data['new_stage'];
    
    $stmt = $pdo->prepare("UPDATE bookings SET stage = :stage, stage_updated_at = NOW() WHERE id = :id");
    $stmt->execute([':stage' => $newStage, ':id' => $data['booking_id']]);
    
    $stmt = $pdo->prepare("
        INSERT INTO booking_stage_log (booking_id, from_stage, to_stage, changed_by, change_type, notes)
        VALUES (:booking_id, :from_stage, :to_stage, :changed_by, :change_type, :notes)
    ");
    $stmt->execute([
        ':booking_id' => $data['booking_id'],
        ':from_stage' => $oldStage,
        ':to_stage' => $newStage,
        ':changed_by' => $data['changed_by'] ?? 'admin',
        ':change_type' => $data['change_type'] ?? 'manual',
        ':notes' => $data['notes'] ?? null
    ]);
    
    jsonResponse(['success' => true, 'message' => 'تم تحديث المرحلة', 'old_stage' => $oldStage, 'new_stage' => $newStage]);
}

/**
 * إضافة دفعة
 */
function addPayment($pdo, $data) {
    if (empty($data['booking_id']) || empty($data['amount'])) {
        jsonResponse(['success' => false, 'error' => 'بيانات ناقصة'], 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO booking_payments (booking_id, payment_type, amount, is_paid, paid_at, payment_method, payment_reference, notes)
        VALUES (:booking_id, :type, :amount, :is_paid, :paid_at, :method, :reference, :notes)
    ");
    $stmt->execute([
        ':booking_id' => $data['booking_id'],
        ':type' => $data['payment_type'] ?? 'remaining',
        ':amount' => $data['amount'],
        ':is_paid' => $data['is_paid'] ?? 1,
        ':paid_at' => $data['paid_at'] ?? date('Y-m-d'),
        ':method' => $data['payment_method'] ?? 'cash',
        ':reference' => $data['payment_reference'] ?? null,
        ':notes' => $data['notes'] ?? null
    ]);
    
    // تحديث حالة الدفع
    updatePaymentStatus($pdo, $data['booking_id']);
    
    jsonResponse(['success' => true, 'message' => 'تم إضافة الدفعة', 'payment_id' => $pdo->lastInsertId()]);
}

/**
 * تحديث حالة الدفع
 */
function updatePaymentStatus($pdo, $bookingId) {
    $stmt = $pdo->prepare("
        SELECT b.total_after_discount, COALESCE(SUM(bp.amount), 0) as paid
        FROM bookings b
        LEFT JOIN booking_payments bp ON bp.booking_id = b.id AND bp.is_paid = 1
        WHERE b.id = :id
        GROUP BY b.id
    ");
    $stmt->execute([':id' => $bookingId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $status = 'unpaid';
        if ($result['paid'] >= $result['total_after_discount']) {
            $status = 'paid';
        } elseif ($result['paid'] > 0) {
            $status = 'partial';
        }
        
        $pdo->prepare("UPDATE bookings SET payment_status = :status WHERE id = :id")
            ->execute([':status' => $status, ':id' => $bookingId]);
    }
}

/**
 * تعيين فريق العمل
 */
function assignTeam($pdo, $data) {
    if (empty($data['booking_id']) || empty($data['employee_id'])) {
        jsonResponse(['success' => false, 'error' => 'بيانات ناقصة'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM booking_team WHERE booking_id = :bid AND employee_id = :eid");
    $stmt->execute([':bid' => $data['booking_id'], ':eid' => $data['employee_id']]);
    
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'الموظف معيّن مسبقاً'], 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO booking_team (booking_id, employee_id, role, is_lead, cost, notes)
        VALUES (:booking_id, :employee_id, :role, :is_lead, :cost, :notes)
    ");
    $stmt->execute([
        ':booking_id' => $data['booking_id'],
        ':employee_id' => $data['employee_id'],
        ':role' => $data['role'] ?? null,
        ':is_lead' => $data['is_lead'] ?? 0,
        ':cost' => $data['cost'] ?? null,
        ':notes' => $data['notes'] ?? null
    ]);
    
    jsonResponse(['success' => true, 'message' => 'تم تعيين الموظف']);
}

/**
 * معالجة PUT
 */
function handlePut($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        jsonResponse(['success' => false, 'error' => 'معرف الحجز مطلوب'], 400);
    }
    
    $allowedFields = [
        'groom_name', 'bride_name', 'phone', 'phone_secondary', 'email',
        'wedding_date', 'wedding_time', 'wedding_end_time',
        'city', 'venue', 'venue_address', 'venue_google_maps',
        'package_id', 'total_price', 'discount',
        'notes', 'internal_notes', 'coordination_notes',
        'expected_delivery_date', 'delivery_link'
    ];
    
    $updates = [];
    $params = [':id' => $input['id']];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "{$field} = :{$field}";
            $params[":{$field}"] = $input[$field];
        }
    }
    
    if (empty($updates)) {
        jsonResponse(['success' => false, 'error' => 'لا توجد بيانات للتحديث'], 400);
    }
    
    $sql = "UPDATE bookings SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";
    $pdo->prepare($sql)->execute($params);
    
    jsonResponse(['success' => true, 'message' => 'تم تحديث الحجز']);
}

/**
 * معالجة DELETE
 */
function handleDelete($pdo) {
    $id = $_GET['id'] ?? 0;
    
    if (!$id) {
        jsonResponse(['success' => false, 'error' => 'معرف الحجز مطلوب'], 400);
    }
    
    $pdo->prepare("UPDATE bookings SET stage = 'closed', notes = CONCAT(IFNULL(notes,''), '\n[محذوف]') WHERE id = :id")
        ->execute([':id' => $id]);
    
    jsonResponse(['success' => true, 'message' => 'تم حذف الحجز']);
}

/**
 * تنسيق بيانات الحجز
 */
function formatBooking($booking) {
    return [
        'id' => $booking['id'],
        'groom_name' => $booking['groom_name'],
        'phone' => $booking['phone'],
        'wedding_date' => $booking['wedding_date'],
        'wedding_date_formatted' => formatArabicDate($booking['wedding_date']),
        'wedding_time' => $booking['wedding_time'],
        'venue' => $booking['venue'],
        'city' => $booking['city'],
        'stage' => $booking['stage'],
        'package_id' => $booking['package_id'],
        'package_name' => $booking['package_name'] ?? $booking['package_name_full'] ?? 'غير محدد',
        'package_color' => $booking['package_color'] ?? '#d4af37',
        'total_price' => $booking['total_price'],
        'total_after_discount' => $booking['total_after_discount'],
        'payment_status' => $booking['payment_status'],
        'deposit_paid' => $booking['deposit_paid'],
        'remaining_paid' => $booking['remaining_paid'],
        'total_paid' => $booking['total_paid'] ?? 0,
        'team_count' => $booking['team_count'] ?? 0,
        'created_at' => $booking['created_at']
    ];
}

/**
 * تنسيق التاريخ بالعربي
 */
function formatArabicDate($date) {
    if (!$date) return '';
    $months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',
               7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
    $ts = strtotime($date);
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * إرسال استجابة JSON
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
