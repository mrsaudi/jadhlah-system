
// ========== admin/check_database.php ==========
// <?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['error' => 'غير مصرح']));
}

require_once __DIR__ . '/config.php';

try {
    // فحص الجداول
    $tables = [];
    $tablesQuery = $pdo->query("SHOW TABLES");
    while ($row = $tablesQuery->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // فحص البيانات
    $stats = [
        'grooms_count' => $pdo->query("SELECT COUNT(*) FROM grooms")->fetchColumn(),
        'pending_count' => $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NULL")->fetchColumn(),
        'photos_count' => $pdo->query("SELECT COUNT(*) FROM groom_photos")->fetchColumn(),
        'tables' => $tables
    ];
    
    // فحص آخر الأخطاء
    $lastErrors = [];
    if (in_array('error_log', $tables)) {
        $errorsQuery = $pdo->query("SELECT * FROM error_log ORDER BY created_at DESC LIMIT 5");
        $lastErrors = $errorsQuery->fetchAll();
    }
    
    $stats['last_errors'] = $lastErrors;
    
    echo json_encode($stats);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
// ?>
