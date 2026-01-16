<?php
// admin/test_delete.php - ملف تشخيص مشكلة الحذف
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$response = [
    'test' => 'start',
    'steps' => []
];

try {
    // خطوة 1: فحص الجلسة
    session_start();
    $response['steps'][] = 'Session started';
    $response['session_user'] = $_SESSION['user'] ?? 'Not logged in';
    $response['session_role'] = $_SESSION['role'] ?? 'No role';
    
    // خطوة 2: فحص config.php
    if (file_exists(__DIR__ . '/config.php')) {
        $response['steps'][] = 'config.php exists';
        require_once __DIR__ . '/config.php';
        $response['steps'][] = 'config.php loaded';
    } else {
        throw new Exception('config.php not found');
    }
    
    // خطوة 3: فحص الاتصال بقاعدة البيانات
    if (isset($pdo)) {
        $response['steps'][] = 'PDO connection exists';
        
        // اختبار query بسيط
        $test = $pdo->query("SELECT 1")->fetchColumn();
        if ($test == 1) {
            $response['steps'][] = 'Database connection working';
        }
    } else {
        throw new Exception('PDO not initialized');
    }
    
    // خطوة 4: فحص جدول pending_grooms
    $stmt = $pdo->query("SHOW TABLES LIKE 'pending_grooms'");
    if ($stmt->rowCount() > 0) {
        $response['steps'][] = 'pending_grooms table exists';
        
        // عد السجلات
        $count = $pdo->query("SELECT COUNT(*) FROM pending_grooms")->fetchColumn();
        $response['pending_count'] = $count;
    } else {
        throw new Exception('pending_grooms table not found');
    }
    
    // خطوة 5: اختبار حذف وهمي
    if (isset($_GET['test_delete'])) {
        $testId = (int)($_GET['test_delete']);
        
        // جلب السجل
        $stmt = $pdo->prepare("SELECT * FROM pending_grooms WHERE id = ?");
        $stmt->execute([$testId]);
        $record = $stmt->fetch();
        
        if ($record) {
            $response['record_found'] = true;
            $response['record_name'] = $record['groom_name'];
            
            // محاولة الحذف
            $stmt = $pdo->prepare("DELETE FROM pending_grooms WHERE id = ?");
            $result = $stmt->execute([$testId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $response['delete_success'] = true;
                $response['message'] = 'تم الحذف بنجاح';
            } else {
                $response['delete_success'] = false;
                $response['message'] = 'فشل الحذف';
            }
        } else {
            $response['record_found'] = false;
            $response['message'] = 'السجل غير موجود';
        }
    }
    
    $response['success'] = true;
    $response['final_message'] = 'All tests passed';
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    $response['line'] = $e->getLine();
    $response['file'] = basename($e->getFile());
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>