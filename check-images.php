<?php
$host = "localhost";
$user = "u709146392_jad_admin";
$pass = "1245@vmP";
$db = "u709146392_jadhlah_db";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

echo "<h2>📸 الصور الموجودة في قاعدة البيانات:</h2>";

// جلب صورة واحدة كمثال
$result = $conn->query("SELECT filename FROM gallery_uploaded_images LIMIT 1");
$photo = $result->fetch_assoc();

if ($photo) {
    echo "<p><strong>اسم الصورة:</strong> " . $photo['filename'] . "</p>";
    echo "<h3>تجربة مسارات مختلفة:</h3>";
    
    $paths = [
        'uploads/' . $photo['filename'],
        'gallery/' . $photo['filename'],
        'images/' . $photo['filename'],
        $photo['filename']
    ];
    
    foreach ($paths as $path) {
        echo "<div style='margin: 10px 0;'>";
        echo "<strong>المسار:</strong> $path<br>";
        if (file_exists($path)) {
            echo "✅ <span style='color: green;'>الملف موجود!</span><br>";
            echo "<img src='$path' style='max-width: 300px; margin-top: 10px;'>";
        } else {
            echo "❌ <span style='color: red;'>الملف غير موجود</span>";
        }
        echo "</div><hr>";
    }
} else {
    echo "لا توجد صور في الجدول";
}
?>