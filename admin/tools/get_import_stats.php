// ========== admin/tools/get_import_stats.php ==========
// <?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

require_once dirname(__DIR__) . '/config.php';

try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM pending_grooms")->fetchColumn(),
        'imported' => $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NOT NULL")->fetchColumn(),
        'skipped' => 0,
        'updated' => 0,
        'failed' => 0
    ];
    
    // حساب المنتظرين
    $stats['skipped'] = $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NULL")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
// ?>