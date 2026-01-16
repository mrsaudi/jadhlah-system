<?php
// api/list_grooms.php - عرض العرسان
header('Content-Type: application/json');

require_once '../config/database.php';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed");
    }
    $conn->set_charset("utf8mb4");
    
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $query = "
        SELECT 
            id,
            groom_name,
            wedding_date,
            folder_name,
            ready,
            is_active
        FROM grooms 
        WHERE wedding_date IN ('$yesterday', '$today')
        ORDER BY wedding_date DESC
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $grooms = [];
    while ($row = $result->fetch_assoc()) {
        $grooms[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($grooms),
        'grooms' => $grooms,
        'dates' => [
            'today' => $today,
            'yesterday' => $yesterday
        ]
    ], JSON_PRETTY_PRINT);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>