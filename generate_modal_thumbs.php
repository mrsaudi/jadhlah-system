<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

require_once __DIR__ . '/admin/config.php';
require_once __DIR__ . '/admin/functions.php';

$startId = 1030;
$endId = 1041;
$processed = 0;

for ($groomId = $startId; $groomId <= $endId; $groomId++) {
    $groomDir = __DIR__ . '/grooms/' . $groomId;
    if (!is_dir($groomDir)) {
        continue;
    }

    $modalDir = $groomDir . '/modal_thumb';
    if (!is_dir($modalDir)) {
        mkdir($modalDir, 0777, true);
    }

    // حدد مصدر الصور: إما من originals أو من مجلد العريس نفسه
    $sourceDir = is_dir($groomDir . '/originals') ? $groomDir . '/originals' : $groomDir;
    $images = glob($sourceDir . '/*.{jpg,jpeg,png}', GLOB_BRACE);

    foreach ($images as $imgPath) {
        $filename = basename($imgPath);
        $destPath = $modalDir . '/' . $filename;

        if (!file_exists($destPath)) {
            createThumbnail($imgPath, $destPath, 1500);
            $processed++;
        }
    }

    // ✅ معالجة البنر إن وجد
    $bannerSrc = $groomDir . '/banner.jpg';
    $bannerDest = $modalDir . '/banner.jpg';
    if (file_exists($bannerSrc) && !file_exists($bannerDest)) {
        createThumbnail($bannerSrc, $bannerDest, 1500);
        $processed++;
    }
}

echo "✅ تم إنشاء $processed صورة modal_thumb بنجاح من العريس {$startId} إلى {$endId}.";
?>