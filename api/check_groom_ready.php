<?php
// api/check_groom_ready.php - التحقق من حالة العريس
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['groom_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Missing groom_id']));
}

$groomId = intval($_GET['groom_id']);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed");
    }
    $conn->set_charset("utf8mb4");
    
    $stmt = $conn->prepare("
        SELECT ready, folder_name, groom_name 
        FROM grooms 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->bind_param("i", $groomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $groom = $result->fetch_assoc();
    
    if (!$groom) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Groom not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'ready' => (bool)$groom['ready'],
        'folder' => $groom['folder_name'],
        'name' => $groom['groom_name']
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>