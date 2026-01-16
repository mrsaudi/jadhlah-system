<?php
// admin/check_upload_status.php - فحص حالة معالجة الصور (AJAX)

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'غير مصرح']));
}

header('Content-Type: application/json');

try {
    $groomId = (int)($_GET['groom_id'] ?? 0);
    
    if (!$groomId) {
        throw new Exception('معرف العريس مطلوب');
    }
    
    // جلب حالة الصور من upload_queue
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            MAX(created_at) as last_update
        FROM upload_queue 
        WHERE groom_id = ?
        GROUP BY status
    ");
    $stmt->execute([$groomId]);
    $statusData = $stmt->fetchAll();
    
    $summary = [
        'pending' => 0,
        'processing' => 0,
        'done' => 0,
        'failed' => 0,
        'total' => 0
    ];
    
    foreach ($statusData as $row) {
        $summary[$row['status']] = (int)$row['count'];
        $summary['total'] += (int)$row['count'];
    }
    
    // حساب النسبة المئوية
    $progress = 0;
    if ($summary['total'] > 0) {
        $completed = $summary['done'] + $summary['failed'];
        $progress = round(($completed / $summary['total']) * 100);
    }
    
    // جلب الصور المعالجة مؤخراً
    $stmt = $pdo->prepare("
        SELECT filename, status, error_message
        FROM upload_queue 
        WHERE groom_id = ? AND status IN ('done', 'failed')
        ORDER BY id DESC
        LIMIT 5
    ");
    $stmt->execute([$groomId]);
    $recentProcessed = $stmt->fetchAll();
    
    // التحقق من اكتمال المعالجة
    $isComplete = ($summary['pending'] == 0 && $summary['processing'] == 0);
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'progress' => $progress,
        'is_complete' => $isComplete,
        'recent_processed' => $recentProcessed
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}