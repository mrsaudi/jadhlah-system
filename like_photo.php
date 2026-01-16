<?php
$host = 'localhost';
$db   = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';
$charset = 'utf8mb4';

header('Content-Type: application/json');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$groomId = $_POST['groom_id'] ?? null;
$photo = $_POST['photo'] ?? null;

if (!$groomId || !$photo) {
    http_response_code(400);
    echo json_encode(["error" => "Missing parameters"]);
    exit;
}

$photo = basename($photo); // حماية من محاولات حقن
$stmt = $pdo->prepare("UPDATE groom_photos SET likes = likes + 1 WHERE groom_id = ? AND filename = ?");
$stmt->execute([$groomId, $photo]);

echo json_encode(["status" => "ok"]);
?>