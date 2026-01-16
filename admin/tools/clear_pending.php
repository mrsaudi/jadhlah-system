
// ========== admin/tools/clear_pending.php ==========
// <?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

// التحقق من الصلاحية
$role = $_SESSION['role'] ?? 'employ';
if ($role !== 'manager') {
    die(json_encode(['success' => false, 'error' => 'ليس لديك صلاحية الحذف']));
}

require_once dirname(__DIR__) . '/config.php';

try {
    // حذف السجلات غير المعالجة فقط
    $stmt = $pdo->prepare("DELETE FROM pending_grooms WHERE groom_id IS NULL");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    // تسجيل العملية
    $logFile = dirname(__DIR__) . '/logs/clear_pending_' . date('Y-m-d') . '.log';
    $logEntry = date('Y-m-d H:i:s') . " - " . $_SESSION['user'] . " - حذف $deleted سجل منتظر\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
        'message' => "تم حذف $deleted سجل منتظر"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// ?>
