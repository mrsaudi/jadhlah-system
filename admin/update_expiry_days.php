<?php
// admin/update_expiry_days.php - تحديث مدة صلاحية الصفحة
session_start();

// التحقق من تسجيل الدخول والصلاحيات
if (empty($_SESSION['user'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

$role = $_SESSION['role'] ?? 'employ';
$canWrite = in_array($role, ['manager', 'employ']);

if (!$canWrite) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'لا تملك صلاحية التعديل']));
}

// تضمين ملف التكوين
require_once __DIR__ . '/config.php';

// التحقق من البيانات
$groomId = filter_input(INPUT_POST, 'groom_id', FILTER_VALIDATE_INT);
$expiryDays = filter_input(INPUT_POST, 'expiry_days', FILTER_VALIDATE_INT);

if (!$groomId || $expiryDays === false) {
    http_response_code(400);
    die(json_encode([
        'success' => false, 
        'error' => 'بيانات غير صحيحة'
    ]));
}

// التحقق من النطاق المسموح (7 أيام - سنة)
if ($expiryDays < 7 || $expiryDays > 365) {
    http_response_code(400);
    die(json_encode([
        'success' => false, 
        'error' => 'المدة يجب أن تكون بين 7 و 365 يوم'
    ]));
}

try {
    // التحقق من وجود الصفحة
    $checkStmt = $pdo->prepare("SELECT id, groom_name FROM grooms WHERE id = ?");
    $checkStmt->execute([$groomId]);
    $groom = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$groom) {
        throw new Exception('الصفحة غير موجودة');
    }
    
    // تحديث مدة الصلاحية
    $updateStmt = $pdo->prepare("
        UPDATE grooms 
        SET expiry_days = ? 
        WHERE id = ?
    ");
    
    $updateStmt->execute([$expiryDays, $groomId]);
    
    // حساب الأيام المتبقية
    $daysStmt = $pdo->prepare("
        SELECT 
            IFNULL(expiry_days, 90) as expiry_days,
            DATEDIFF(NOW(), IFNULL(ready_at, created_at)) as days_elapsed,
            (IFNULL(expiry_days, 90) - DATEDIFF(NOW(), IFNULL(ready_at, created_at))) as days_left
        FROM grooms 
        WHERE id = ?
    ");
    
    $daysStmt->execute([$groomId]);
    $daysInfo = $daysStmt->fetch(PDO::FETCH_ASSOC);
    
    // تسجيل في لوج التعديلات (اختياري)
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO admin_logs (user, action, details, created_at) 
            VALUES (?, 'update_expiry', ?, NOW())
        ");
        
        $logStmt->execute([
            $_SESSION['user'],
            json_encode([
                'groom_id' => $groomId,
                'groom_name' => $groom['groom_name'],
                'new_expiry_days' => $expiryDays
            ])
        ]);
    } catch (Exception $e) {
        // تجاهل خطأ اللوق إذا لم يكن الجدول موجوداً
    }
    
    // الاستجابة
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث مدة الصلاحية بنجاح',
        'data' => [
            'groom_id' => $groomId,
            'groom_name' => $groom['groom_name'],
            'expiry_days' => $expiryDays,
            'days_elapsed' => $daysInfo['days_elapsed'],
            'days_left' => max(0, $daysInfo['days_left'])
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error updating expiry days: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
