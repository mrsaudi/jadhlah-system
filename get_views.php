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

$groomId = $_GET['groom_id'] ?? null;

if (!$groomId) {
    http_response_code(400);
    echo json_encode(["error" => "Missing groom_id"]);
    exit;
}

$stmt = $pdo->prepare("SELECT photo, views FROM photo_views WHERE groom_id = ?");
$stmt->execute([$groomId]);

$result = [];
foreach ($stmt->fetchAll() as $row) {
    $result[$row['photo']] = $row['views'];
}

echo json_encode($result);
?>