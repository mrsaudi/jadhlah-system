<?php
// check-and-add.php - إضافة الصور الموجودة للقاعدة
$host = "localhost";
$user = "u709146392_jad_admin";
$pass = "1245@vmP";
$db = "u709146392_jadhlah_db";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

$liveDir = 'uploads/live/';
$images = glob($liveDir . '*.{jpg,jpeg,JPG,JPEG,png,PNG}', GLOB_BRACE);

echo "<h2>🔍 الصور الموجودة في $liveDir</h2>";
echo "<p>عدد الصور: " . count($images) . "</p><hr>";

$added = 0;
foreach ($images as $imagePath) {
    $filename = basename($imagePath);
    
    // تحقق إذا كانت موجودة في القاعدة
    $stmt = $conn->prepare("SELECT id FROM live_gallery_photos WHERE filename = ?");
    $stmt->bind_param("s", $filename);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    
    if (!$exists) {
        // أضفها
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt = $conn->prepare("
            INSERT INTO live_gallery_photos 
            (filename, original_filename, uploaded_at, expires_at, is_processed, is_expired) 
            VALUES (?, ?, NOW(), ?, 1, 0)
        ");
        $stmt->bind_param("sss", $filename, $filename, $expiresAt);
        $stmt->execute();
        
        echo "✅ تمت الإضافة: $filename<br>";
        $added++;
    } else {
        echo "⏭️ موجودة مسبقاً: $filename<br>";
    }
}

echo "<hr><h3>النتيجة: تم إضافة $added صورة جديدة!</h3>";
$conn->close();
?>