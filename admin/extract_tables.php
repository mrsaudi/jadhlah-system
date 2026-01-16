<?php
// extract_videos.php - استخراج جدول groom_videos
set_time_limit(0);

$backupFile = '/home/u709146392/domains/jadhlah.com/public_html/u709146392_jadhlah_db.sql';
$outputFile = $_SERVER['DOCUMENT_ROOT'] . '/admin/groom_videos.sql';

if (!file_exists($backupFile)) {
    die("❌ ملف الـ backup غير موجود!");
}

echo "<h2>🔍 استخراج جدول groom_videos...</h2>";

$input = fopen($backupFile, 'r');
$output = fopen($outputFile, 'w');

$found = false;
$lineCount = 0;
$writtenLines = 0;

while (($line = fgets($input)) !== false) {
    $lineCount++;
    
    if (strpos($line, "DROP TABLE IF EXISTS `groom_videos`") !== false || 
        strpos($line, "CREATE TABLE `groom_videos`") !== false) {
        $found = true;
        echo "✅ وجدت الجدول في السطر $lineCount<br>";
    }
    
    if ($found) {
        fwrite($output, $line);
        $writtenLines++;
        
        if ($writtenLines % 100 == 0) {
            echo "📝 $writtenLines سطر...<br>";
            flush();
        }
    }
    
    if ($found && 
        strpos($line, 'DROP TABLE IF EXISTS') !== false && 
        strpos($line, 'groom_videos') === false) {
        echo "✅ انتهى الاستخراج ($writtenLines سطر)<br>";
        break;
    }
}

fclose($input);
fclose($output);

if ($found) {
    $size = filesize($outputFile);
    echo "<p>💾 حجم الملف: " . number_format($size / 1024, 2) . " KB</p>";
    echo "<p>📥 <a href='/admin/groom_videos.sql' download style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>تحميل groom_videos.sql</a></p>";
    echo "<p><strong>✅ الآن استورده في phpMyAdmin!</strong></p>";
} else {
    echo "<p>❌ لم أجد جدول groom_videos في الـ backup</p>";
    echo "<p>⚠️ هذا يعني الجدول ما كان موجود أصلاً في الـ backup</p>";
    echo "<p><strong>الحل:</strong> استخدم الكود SQL الأول لإنشاء الجدول فارغ</p>";
}
?>