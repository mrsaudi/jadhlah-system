<?php
// extract_tables.php - استخراج الجداول المفقودة من backup
set_time_limit(0);
ini_set('memory_limit', '1G');

// ⚠️ غيّر هذا المسار لمسار الـ backup عندك!
$backupFile = 'https://jadhlah.com/u709146392_jadhlah_db.sql'; 
$outputDir = 'https://jadhlah.com/u709146392/';

// الجداول المطلوب استخراجها
$tables = [
    'groom_photos',
    'active_events', 
    'email_subscriptions'
];

// إنشاء مجلد الناتج
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "<h2>🔍 استخراج الجداول من Backup...</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

foreach ($tables as $tableName) {
    echo "<br><strong>📊 معالجة جدول: $tableName</strong><br>";
    
    $outputFile = $outputDir . $tableName . '.sql';
    
    if (!file_exists($backupFile)) {
        echo "❌ ملف الـ backup غير موجود: $backupFile<br>";
        continue;
    }
    
    $input = fopen($backupFile, 'r');
    $output = fopen($outputFile, 'w');
    
    $found = false;
    $lineCount = 0;
    $writtenLines = 0;
    
    while (($line = fgets($input)) !== false) {
        $lineCount++;
        
        // بداية الجدول
        if (strpos($line, "DROP TABLE IF EXISTS `$tableName`") !== false || 
            strpos($line, "CREATE TABLE `$tableName`") !== false) {
            $found = true;
            echo "✅ وجدت الجدول في السطر $lineCount<br>";
        }
        
        // كتابة السطر إذا كنا داخل الجدول
        if ($found) {
            fwrite($output, $line);
            $writtenLines++;
            
            // عرض تقدم كل 1000 سطر
            if ($writtenLines % 1000 == 0) {
                echo "📝 كتبت $writtenLines سطر...<br>";
                flush();
            }
        }
        
        // نهاية الجدول (بداية جدول جديد)
        if ($found && 
            strpos($line, 'DROP TABLE IF EXISTS') !== false && 
            strpos($line, $tableName) === false) {
            echo "✅ انتهى الجدول عند السطر $lineCount (كتبت $writtenLines سطر)<br>";
            break;
        }
        
        // إذا وصلنا لنهاية الملف
        if ($found && feof($input)) {
            echo "✅ وصلنا لنهاية الملف (كتبت $writtenLines سطر)<br>";
        }
    }
    
    fclose($input);
    fclose($output);
    
    if (!$found) {
        echo "❌ لم أجد الجدول $tableName في الـ backup!<br>";
        unlink($outputFile);
    } else {
        $size = filesize($outputFile);
        echo "💾 حجم الملف: " . number_format($size / 1024, 2) . " KB<br>";
        echo "📥 <a href='/admin/extracted/$tableName.sql' download>تحميل $tableName.sql</a><br>";
    }
}

echo "</div>";

echo "<br><h3>✅ انتهى الاستخراج!</h3>";
echo "<p><strong>الخطوة التالية:</strong></p>";
echo "<ol>";
echo "<li>حمّل الملفات الثلاثة من الروابط أعلاه</li>";
echo "<li>افتح phpMyAdmin</li>";
echo "<li>استورد كل ملف على حدة (Import)</li>";
echo "</ol>";

// إحصائيات
echo "<br><h3>📊 ملخص:</h3>";
foreach ($tables as $tableName) {
    $file = $outputDir . $tableName . '.sql';
    if (file_exists($file)) {
        $size = filesize($file);
        $lines = count(file($file));
        echo "<p>✅ <strong>$tableName:</strong> " . number_format($size / 1024, 2) . " KB ($lines سطر)</p>";
    } else {
        echo "<p>❌ <strong>$tableName:</strong> غير موجود</p>";
    }
}

?>