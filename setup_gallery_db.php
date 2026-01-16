<?php
// setup_gallery_db.php - إنشاء الجداول المطلوبة للمعرض

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';
$charset = 'utf8mb4';

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>إعداد قاعدة البيانات</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; background: #1a1a1a; color: #fff; padding: 40px; }";
echo ".success { background: rgba(0,255,0,0.1); border: 1px solid #4caf50; padding: 10px; margin: 10px 0; border-radius: 5px; }";
echo ".error { background: rgba(255,0,0,0.1); border: 1px solid #f44336; padding: 10px; margin: 10px 0; border-radius: 5px; }";
echo ".info { background: rgba(0,100,255,0.1); border: 1px solid #2196F3; padding: 10px; margin: 10px 0; border-radius: 5px; }";
echo "h1 { color: #ffd700; }";
echo "pre { background: rgba(255,255,255,0.05); padding: 10px; border-radius: 5px; overflow-x: auto; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<h1>إعداد قاعدة البيانات لمعرض الأعمال</h1>";

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<div class='success'>✅ تم الاتصال بقاعدة البيانات بنجاح</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage() . "</div>";
    die("</body></html>");
}

// 1. إنشاء جدول الصور المرفوعة
echo "<h2>1. إنشاء جدول gallery_images</h2>";
try {
    $sql = "CREATE TABLE IF NOT EXISTS gallery_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        title VARCHAR(255),
        category VARCHAR(50) DEFAULT 'general',
        is_featured BOOLEAN DEFAULT 0,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_featured (is_featured),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "<div class='success'>✅ تم إنشاء جدول gallery_images بنجاح</div>";
    
    // عرض بنية الجدول
    $columns = $pdo->query("SHOW COLUMNS FROM gallery_images")->fetchAll();
    echo "<pre>";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    echo "</pre>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// 2. إضافة عمود is_featured لجدول groom_photos
echo "<h2>2. تحديث جدول groom_photos</h2>";
try {
    // التحقق من وجود العمود
    $columns = $pdo->query("SHOW COLUMNS FROM groom_photos LIKE 'is_featured'")->fetchAll();
    
    if (count($columns) == 0) {
        $sql = "ALTER TABLE groom_photos ADD COLUMN is_featured BOOLEAN DEFAULT 0";
        $pdo->exec($sql);
        echo "<div class='success'>✅ تم إضافة عمود is_featured</div>";
        
        // إضافة فهرس
        $sql = "ALTER TABLE groom_photos ADD INDEX idx_featured (is_featured)";
        $pdo->exec($sql);
        echo "<div class='success'>✅ تم إضافة الفهرس idx_featured</div>";
    } else {
        echo "<div class='info'>ℹ️ العمود is_featured موجود بالفعل</div>";
    }
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// 3. إنشاء مجلد gallery_uploads
echo "<h2>3. إنشاء مجلد الرفع</h2>";
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gallery_uploads';

if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0777, true)) {
        echo "<div class='success'>✅ تم إنشاء مجلد /gallery_uploads</div>";
        chmod($uploadDir, 0777);
    } else {
        echo "<div class='error'>❌ فشل إنشاء المجلد - قم بإنشائه يدوياً</div>";
    }
} else {
    echo "<div class='info'>ℹ️ المجلد /gallery_uploads موجود بالفعل</div>";
}

// التحقق من صلاحيات المجلد
if (is_writable($uploadDir)) {
    echo "<div class='success'>✅ المجلد قابل للكتابة</div>";
} else {
    echo "<div class='error'>❌ المجلد غير قابل للكتابة - قم بتغيير الصلاحيات إلى 777</div>";
}

// 4. إضافة بعض الصور المميزة كمثال
echo "<h2>4. تعيين بعض الصور كمميزة (اختياري)</h2>";
try {
    // تحديد 5 صور عشوائية كمميزة
    $sql = "UPDATE groom_photos 
            SET is_featured = 1 
            WHERE id IN (
                SELECT id FROM (
                    SELECT id FROM groom_photos 
                    WHERE hidden = 0 
                    ORDER BY likes DESC, RAND() 
                    LIMIT 5
                ) AS temp
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    
    if ($count > 0) {
        echo "<div class='success'>✅ تم تعيين $count صور كمميزة</div>";
    } else {
        echo "<div class='info'>ℹ️ لا توجد صور لتعيينها كمميزة</div>";
    }
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

// 5. عرض الإحصائيات
echo "<h2>5. الإحصائيات الحالية</h2>";
try {
    // عد العرسان
    $groomsCount = $pdo->query("SELECT COUNT(*) FROM grooms WHERE is_blocked = 0 AND ready = 1")->fetchColumn();
    echo "<div class='info'>عدد العرسان الجاهزين: $groomsCount</div>";
    
    // عد الصور
    $photosCount = $pdo->query("SELECT COUNT(*) FROM groom_photos WHERE hidden = 0")->fetchColumn();
    echo "<div class='info'>عدد الصور الغير مخفية: $photosCount</div>";
    
    // عد الصور المميزة
    $featuredCount = $pdo->query("SELECT COUNT(*) FROM groom_photos WHERE is_featured = 1")->fetchColumn();
    echo "<div class='info'>عدد الصور المميزة: $featuredCount</div>";
    
    // عد الصور المرفوعة
    $galleryCount = $pdo->query("SELECT COUNT(*) FROM gallery_images")->fetchColumn();
    echo "<div class='info'>عدد الصور في gallery_images: $galleryCount</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ في جلب الإحصائيات: " . $e->getMessage() . "</div>";
}

echo "<h2>✅ تم الإعداد بنجاح!</h2>";
echo "<p>يمكنك الآن:</p>";
echo "<ul>";
echo "<li><a href='admin_login.php' style='color: #ffd700;'>تسجيل الدخول للوحة التحكم</a></li>";
echo "<li><a href='gallery_fixed.php' style='color: #ffd700;'>عرض المعرض</a></li>";
echo "</ul>";
echo "<p style='background: rgba(255,215,0,0.1); padding: 15px; border-radius: 5px; margin-top: 20px;'>";
echo "<strong>بيانات الدخول الافتراضية:</strong><br>";
echo "اسم المستخدم: <code>admin</code><br>";
echo "كلمة المرور: <code>jadhlah2025</code>";
echo "</p>";

echo "</body>";
echo "</html>";

// =================================
// logout.php - تسجيل الخروج
// =================================
/*
<?php
// logout.php
session_start();
session_destroy();
header('Location: admin_login.php');
exit();
?>
*/