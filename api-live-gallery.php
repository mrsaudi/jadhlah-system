<?php
// api-live-gallery.php - API لتحديث إحصائيات المعرض الحي
header('Content-Type: application/json');
require_once 'config/database.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'increment_view':
        $photoId = intval($_POST['photo_id'] ?? 0);
        
        if ($photoId > 0) {
            $stmt = $conn->prepare("UPDATE live_gallery_photos SET views = views + 1 WHERE id = ?");
            $stmt->bind_param("i", $photoId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update views']);
            }
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid photo ID']);
        }
        break;
        
    case 'increment_like':
        $photoId = intval($_POST['photo_id'] ?? 0);
        
        if ($photoId > 0) {
            $stmt = $conn->prepare("UPDATE live_gallery_photos SET likes = likes + 1 WHERE id = ?");
            $stmt->bind_param("i", $photoId);
            
            if ($stmt->execute()) {
                // جلب العدد المحدث
                $stmt = $conn->prepare("SELECT likes FROM live_gallery_photos WHERE id = ?");
                $stmt->bind_param("i", $photoId);
                $stmt->execute();
                $result = $stmt->get_result();
                $likes = $result->fetch_assoc()['likes'];
                
                echo json_encode(['success' => true, 'likes' => $likes]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update likes']);
            }
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid photo ID']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
?>