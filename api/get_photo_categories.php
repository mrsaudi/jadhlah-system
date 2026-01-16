<?php
/**
 * ============================================
 * API - جلب تصنيفات الصور
 * Get Photo Categories API
 * ============================================
 * 
 * المسار: api/get_photo_categories.php
 * الوظيفة: جلب التصنيفات المخصصة للصور أو كلاهما (صور+فيديو)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// الاتصال بقاعدة البيانات
require_once '../config/database.php';

try {
    // استقبال المعاملات (اختياري)
    $includeAll = isset($_GET['all']) && $_GET['all'] == 1;
    
    // بناء الاستعلام
    if ($includeAll) {
        // جلب جميع التصنيفات النشطة
        $query = "
            SELECT 
                id,
                name_ar,
                name_en,
                slug,
                applies_to,
                color,
                icon,
                display_order,
                is_active
            FROM video_categories 
            WHERE is_active = 1
            ORDER BY display_order ASC, id ASC
        ";
    } else {
        // جلب تصنيفات الصور فقط
        $query = "
            SELECT 
                id,
                name_ar,
                name_en,
                slug,
                applies_to,
                color,
                icon,
                display_order,
                is_active
            FROM video_categories 
            WHERE applies_to IN ('photos', 'both') 
              AND is_active = 1
            ORDER BY display_order ASC, id ASC
        ";
    }
    
    $stmt = $pdo->query($query);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق البيانات
    $formattedCategories = array_map(function($cat) {
        return [
            'id' => (int)$cat['id'],
            'name_ar' => $cat['name_ar'],
            'name_en' => $cat['name_en'],
            'slug' => $cat['slug'],
            'applies_to' => $cat['applies_to'],
            'color' => $cat['color'],
            'icon' => $cat['icon'] ?: '📁',
            'display_order' => (int)$cat['display_order']
        ];
    }, $categories);
    
    // إحصائيات إضافية
    $stats = [
        'total' => count($formattedCategories),
        'photos_only' => count(array_filter($formattedCategories, fn($c) => $c['applies_to'] === 'photos')),
        'videos_only' => count(array_filter($formattedCategories, fn($c) => $c['applies_to'] === 'videos')),
        'both' => count(array_filter($formattedCategories, fn($c) => $c['applies_to'] === 'both'))
    ];
    
    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'data' => $formattedCategories
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // في حالة الخطأ
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>