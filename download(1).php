<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$groomId = isset($_GET['groom']) ? (int)$_GET['groom'] : 0;
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

$directory = __DIR__ . "/grooms/{$groomId}/originals";
$filepath = $directory . "/{$filename}";

if ($groomId <= 0 || empty($filename)) {
    http_response_code(400);
    echo "❌ بيانات غير مكتملة.";
    exit;
}

if (!is_file($filepath)) {
    http_response_code(404);
    echo "❌ الملف غير موجود.";
    exit;
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . filesize($filepath));
flush();
readfile($filepath);
exit;
