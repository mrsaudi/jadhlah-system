<?php
// admin/ajax_operations.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

require_once __DIR__ . '/config.php';

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

try {
    switch ($action) {
        case 'toggle_ready':
    $ready = (int)($_POST['ready'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('معرف العريس غير صحيح');
    }
    
    // ============================================
    // جلب الحالة السابقة قبل التحديث
    // ============================================
    $oldStateStmt = $pdo->prepare("SELECT ready FROM grooms WHERE id = ?");
    $oldStateStmt->execute([$id]);
    $oldState = $oldStateStmt->fetchColumn();
    
    // تحديث حالة الجاهزية
    $stmt = $pdo->prepare("UPDATE grooms SET ready = ? WHERE id = ?");
    $stmt->execute([$ready, $id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('لم يتم العثور على الصفحة');
    }
    
    // جلب معلومات العريس
    $groomStmt = $pdo->prepare("SELECT groom_name FROM grooms WHERE id = ?");
    $groomStmt->execute([$id]);
    $groom = $groomStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$groom) {
        throw new Exception('لم يتم العثور على معلومات العريس');
    }
    
    // ============================================
    // إرسال إشعارات الإيميل تلقائياً عند التفعيل
    // ============================================
    if ($ready == 1 && $oldState == 0) {
        // تم تفعيل "جاهز" لأول مرة
        $emailApiUrl = "https://jadhlah.com/api/send_email_notifications_simple.php?groom_id={$id}";
        
        // إرسال غير متزامن (بدون انتظار)
        $context = stream_context_create([
            'http' => [
                'timeout' => 1,
                'ignore_errors' => true
            ]
        ]);
        
        @file_get_contents($emailApiUrl, false, $context);
        
        // تسجيل في السجل
        if (function_exists('logInfo')) {
            logInfo("Email notifications triggered for groom: {$id} ({$groom['groom_name']})");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $ready ? 'تم تفعيل الصفحة وإرسال الإشعارات' : 'تم تعطيل الصفحة',
        'groom_id' => $id,
        'groom_name' => $groom['groom_name'],
        'ready' => $ready,
        'email_sent' => ($ready == 1 && $oldState == 0)
    ]);
    break;
            
        case 'delete_pending':
            if ($id <= 0) {
                throw new Exception('معرف الصفحة المنتظرة غير صحيح');
            }
            
            $stmt = $pdo->prepare("DELETE FROM pending_grooms WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('لم يتم العثور على الصفحة المنتظرة');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'تم حذف الصفحة المنتظرة بنجاح'
            ]);
            break;
            
        default:
            throw new Exception('عملية غير معروفة');
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في قاعدة البيانات',
        'details' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}