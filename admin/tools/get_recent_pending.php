
// ========== admin/tools/get_recent_pending.php ==========
// <?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

require_once dirname(__DIR__) . '/config.php';

try {
    $stmt = $pdo->query("
        SELECT * FROM pending_grooms 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    $records = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'records' => $records
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// ?>