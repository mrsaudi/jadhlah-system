<?php
// ========== admin/check_logs.php ==========
// <?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    die(json_encode(['error' => 'غير مصرح']));
}

$logsDir = __DIR__ . '/logs';
$logs = [];

if (is_dir($logsDir)) {
    $files = scandir($logsDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $logsDir . '/' . $file;
            $logs[] = [
                'name' => $file,
                'size' => filesize($filePath) . ' bytes',
                'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                'recent_entries' => array_slice(file($filePath), -5) // آخر 5 سطور
            ];
        }
    }
}

echo json_encode(['logs' => $logs]);
// ?>