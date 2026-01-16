
// ======================================
// 3. toggle_photo_featured.php - تبديل حالة الصورة المميزة
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
    
    $photoId = $_POST['photo_id'] ?? 0;
    
    // تبديل حالة is_featured
    $stmt = $pdo->prepare("
        UPDATE groom_photos 
        SET is_featured = NOT is_featured 
        WHERE id = ?
    ");
    $stmt->execute([$photoId]);
    
    // جلب الحالة الجديدة
    $stmt = $pdo->prepare("SELECT is_featured FROM groom_photos WHERE id = ?");
    $stmt->execute([$photoId]);
    $photo = $stmt->fetch();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'is_featured' => $photo['is_featured']
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
