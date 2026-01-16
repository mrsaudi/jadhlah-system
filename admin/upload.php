<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// هذا الملف يُستخدم من قبل Uppy لرفع الصور بشكل منفصل عن نموذج العريس
// ويجب أن يُرسل groom_id في الاستعلام

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Only POST allowed');
}

if (empty($_FILES['file']['tmp_name']) || !isset($_GET['groom_id'])) {
    http_response_code(400);
    exit('Missing file or groom_id');
}

$groomId = (int)$_GET['groom_id'];
if ($groomId <= 0) {
    http_response_code(400);
    exit('Invalid groom_id');
}

$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png'];
if (!in_array($ext, $allowed)) {
    http_response_code(415);
    exit('Unsupported file type');
}

$uploadDir = __DIR__ . "/../uploads_final/$groomId";
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

$uniqueName = uniqid('img_', true) . '.' . $ext;
$target = "$uploadDir/$uniqueName";

if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    http_response_code(500);
    exit('Failed to move file');
}

// ✅ إنشاء ملف قائمة الانتظار queue_$groomId.json
$queueDir = __DIR__ . '/../admin/queue';
if (!file_exists($queueDir)) mkdir($queueDir, 0777, true);

$queueFile = "$queueDir/queue_{$groomId}.json";

$queueList = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
$queueList[] = $uniqueName;
file_put_contents($queueFile, json_encode($queueList));

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'filename' => $uniqueName
]);
