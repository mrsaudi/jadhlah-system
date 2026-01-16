<?php
/**
 * ============================================
 * API - جلب الخدمات
 * Get Services API
 * ============================================
 * 
 * المسار: api/get_services.php
 * الوظيفة: جلب قائمة الخدمات النشطة للعرض في السلايدر
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// الاتصال بقاعدة البيانات
require_once '../config/database.php';

try {
    // جلب الخدمات النشطة
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            description,
            price,
            features,
            icon,
            display_order,
            is_active
        FROM services 
        WHERE is_active = 1 
        ORDER BY display_order ASC, id ASC
        LIMIT 6
    ");
    
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق البيانات
    $formattedServices = array_map(function($service) {
        return [
            'id' => (int)$service['id'],
            'name' => $service['name'],
            'description' => $service['description'],
            'price' => $service['price'],
            'features' => $service['features'],
            'icon' => $service['icon'] ?: '🎉',
            'display_order' => (int)$service['display_order']
        ];
    }, $services);
    
    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'count' => count($formattedServices),
        'data' => $formattedServices
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