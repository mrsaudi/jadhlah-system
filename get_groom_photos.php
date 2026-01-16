
// ======================================
// 2. get_groom_photos.php - جلب صور العريس عبر AJAX
// ======================================
<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $groomId = $_GET['groom_id'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT id, filename, is_featured, likes 
        FROM groom_photos 
        WHERE groom_id = ? AND hidden = 0
        ORDER BY is_featured DESC, likes DESC
    ");
    $stmt->execute([$groomId]);
    $photos = $stmt->fetchAll();
    
    $result = [];
    foreach ($photos as $photo) {
        $photoPath = "/grooms/{$groomId}/watermarked/{$photo['filename']}";
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $photoPath)) {
            $photoPath = "/grooms/{$groomId}/images/{$photo['filename']}";
        }
        
        $result[] = [
            'id' => $photo['id'],
            'path' => $photoPath,
            'is_featured' => $photo['is_featured'],
            'likes' => $photo['likes']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
