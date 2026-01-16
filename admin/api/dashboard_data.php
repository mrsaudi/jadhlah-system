<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config.php';

try {
    // جلب الإحصائيات
    $stats = [
        'totalPages' => $pdo->query("SELECT COUNT(*) FROM grooms")->fetchColumn(),
        'totalViews' => $pdo->query("SELECT SUM(page_views) FROM grooms")->fetchColumn() ?: 0,
        'totalLikes' => $pdo->query("SELECT COUNT(*) FROM groom_likes")->fetchColumn(),
        'activeVisitors' => $pdo->query("SELECT COUNT(DISTINCT session_id) FROM sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn(),
        'activePages' => $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_active = 1 AND is_blocked = 0")->fetchColumn(),
        'pendingPages' => $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NULL")->fetchColumn(),
        'pendingReviews' => $pdo->query("SELECT COUNT(*) FROM groom_reviews WHERE is_approved = 0")->fetchColumn()
    ];
    
    // جلب بيانات العرسان
    $groomsStmt = $pdo->query("
        SELECT g.*, 
               (SELECT COUNT(*) FROM groom_photos WHERE groom_id = g.id) as photo_count
        FROM grooms g
        ORDER BY g.id DESC
    ");
    $grooms = $groomsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // بيانات الرسوم البيانية
    $chartData = [
        'stats' => [
            'labels' => [],
            'views' => [],
            'likes' => []
        ],
        'distribution' => [
            'active' => $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_active = 1 AND is_blocked = 0")->fetchColumn(),
            'inactive' => $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_active = 0")->fetchColumn(),
            'blocked' => $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_blocked = 1")->fetchColumn()
        ]
    ];
    
    // بيانات آخر 7 أيام للرسم البياني
    $last7Days = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as views,
            (SELECT COUNT(*) FROM groom_likes WHERE DATE(created_at) = date) as likes
        FROM grooms 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ")->fetchAll();
    
    foreach ($last7Days as $day) {
        $chartData['stats']['labels'][] = date('d/m', strtotime($day['date']));
        $chartData['stats']['views'][] = $day['views'];
        $chartData['stats']['likes'][] = $day['likes'];
    }
    
    echo json_encode([
        'stats' => $stats,
        'grooms' => $grooms,
        'chartData' => $chartData
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}