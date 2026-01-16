<?php
// admin/get_visitors.php
// ملف جلب بيانات الزوار المباشرين

session_start();
if (empty($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'غير مصرح']));
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // جلب الزوار النشطين (آخر 5 دقائق)
    $visitors = $pdo->query("
        SELECT 
            s.session_id,
            s.groom_id,
            s.page,
            s.device_type,
            s.browser,
            s.page_views,
            s.ip_address,
            s.last_activity,
            g.groom_name,
            TIMESTAMPDIFF(MINUTE, s.last_activity, NOW()) as minutes_ago
        FROM sessions s
        LEFT JOIN grooms g ON s.groom_id = g.id
        WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY s.last_activity DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // معالجة البيانات للعرض
    $data = [];
    foreach ($visitors as $visitor) {
        $data[] = [
            'groom_id' => $visitor['groom_id'],
            'page_name' => $visitor['groom_name'] ?: 'الصفحة الرئيسية',
            'device_type' => $visitor['device_type'] ?: 'غير محدد',
            'browser' => $visitor['browser'] ?: 'غير محدد',
            'visitor_count' => $visitor['page_views'] ?: 1,
            'ip_address' => substr($visitor['ip_address'] ?? '', 0, 15),
            'last_seen' => $visitor['minutes_ago'] == 0 ? 'الآن' : 
                          ($visitor['minutes_ago'] == 1 ? 'منذ دقيقة' : 
                          'منذ ' . $visitor['minutes_ago'] . ' دقائق')
        ];
    }
    
    // إحصائيات إضافية
    $statsResult = $pdo->query("
        SELECT 
            COUNT(DISTINCT session_id) as total_visitors,
            COUNT(DISTINCT CASE WHEN device_type = 'Mobile' THEN session_id END) as mobile_visitors,
            COUNT(DISTINCT CASE WHEN device_type = 'Desktop' THEN session_id END) as desktop_visitors
        FROM sessions 
        WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ")->fetch(PDO::FETCH_ASSOC);
    
    // أكثر الصفحات زيارة في آخر 24 ساعة
    try {
        $topPages = $pdo->query("
            SELECT 
                g.id,
                g.groom_name,
                COUNT(DISTINCT s.session_id) as unique_visitors,
                SUM(s.page_views) as page_views
            FROM sessions s
            JOIN grooms g ON g.id = s.groom_id
            WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND g.groom_name IS NOT NULL
            GROUP BY g.id, g.groom_name
            ORDER BY unique_visitors DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $topPages = [];
    }
    
    $stats = [
        'total_visitors' => $statsResult['total_visitors'] ?? 0,
        'mobile_visitors' => $statsResult['mobile_visitors'] ?? 0,
        'desktop_visitors' => $statsResult['desktop_visitors'] ?? 0,
        'top_pages' => $topPages
    ];
    
    // إرجاع البيانات
    echo json_encode([
        'success' => true,
        'data' => $data,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    // في حالة الخطأ، إرجاع بيانات فارغة
    echo json_encode([
        'success' => true,
        'data' => [],
        'stats' => [
            'total_visitors' => 0,
            'mobile_visitors' => 0,
            'desktop_visitors' => 0,
            'top_pages' => []
        ],
        'error' => $e->getMessage()
    ]);
}
?>