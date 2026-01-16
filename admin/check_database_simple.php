<?php
// admin/check_database_simple.php - فحص قاعدة البيانات بدون تضارب
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['error' => 'غير مصرح']));
}

// الاتصال بقاعدة البيانات مباشرة بدون include config.php لتجنب التضارب
$host = 'localhost';
$dbname = 'u709146392_jadhlah_db';
$username = 'u709146392_jad_admin';
$password = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // فحص الجداول
    $tables = [];
    $tablesQuery = $pdo->query("SHOW TABLES");
    while ($row = $tablesQuery->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // فحص البيانات الأساسية
    $stats = [
        'status' => 'connected',
        'database' => $dbname,
        'tables_count' => count($tables),
        'tables' => $tables
    ];
    
    // فحص جداول محددة
    if (in_array('grooms', $tables)) {
        $stats['grooms_count'] = $pdo->query("SELECT COUNT(*) FROM grooms")->fetchColumn();
        $stats['active_grooms'] = $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_active = 1 AND is_blocked = 0")->fetchColumn();
        $stats['blocked_grooms'] = $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_blocked = 1")->fetchColumn();
        $stats['inactive_grooms'] = $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_active = 0 AND is_blocked = 0")->fetchColumn();
    }
    
    if (in_array('pending_grooms', $tables)) {
        $stats['pending_count'] = $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NULL")->fetchColumn();
        $stats['processed_pending'] = $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NOT NULL")->fetchColumn();
    }
    
    if (in_array('groom_photos', $tables)) {
        $stats['photos_count'] = $pdo->query("SELECT COUNT(*) FROM groom_photos")->fetchColumn();
    }
    
    if (in_array('groom_likes', $tables)) {
        $stats['likes_count'] = $pdo->query("SELECT COUNT(*) FROM groom_likes")->fetchColumn();
    }
    
    if (in_array('groom_reviews', $tables)) {
        $stats['reviews_count'] = $pdo->query("SELECT COUNT(*) FROM groom_reviews")->fetchColumn();
        $stats['pending_reviews'] = $pdo->query("SELECT COUNT(*) FROM groom_reviews WHERE is_approved = 0")->fetchColumn();
    }
    
    // فحص أعمدة جدول grooms
    if (in_array('grooms', $tables)) {
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM grooms");
        $columns = [];
        while ($col = $columnsQuery->fetch()) {
            $columns[] = $col['Field'];
        }
        $stats['grooms_columns'] = $columns;
        
        // التحقق من وجود الأعمدة المطلوبة
        $requiredColumns = ['is_active', 'is_blocked', 'ready', 'ready_at'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            $stats['missing_columns'] = $missingColumns;
            $stats['warning'] = 'بعض الأعمدة المطلوبة غير موجودة';
        } else {
            $stats['columns_status'] = 'جميع الأعمدة المطلوبة موجودة';
        }
    }
    
    echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'error_code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
}
?>