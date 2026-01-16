<?php
// fix_gallery_issues.php - ملف لإصلاح مشاكل المسارات والصور

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>تشخيص وإصلاح مشاكل المعرض</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; background: #1a1a1a; color: #fff; padding: 20px; }";
echo ".success { background: #10B981; padding: 10px; margin: 10px 0; border-radius: 5px; }";
echo ".error { background: #EF4444; padding: 10px; margin: 10px 0; border-radius: 5px; }";
echo ".info { background: #3B82F6; padding: 10px; margin: 10px 0; border-radius: 5px; }";
echo ".warning { background: #F59E0B; padding: 10px; margin: 10px 0; border-radius: 5px; }";
echo "pre { background: #2a2a2a; padding: 10px; border-radius: 5px; overflow-x: auto; }";
echo "h2 { color: #FFD700; margin-top: 30px; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1 style='color: #FFD700;'>🔧 تشخيص وإصلاح مشاكل المعرض</h1>";

// 1. فحص مجلد grooms الرئيسي
echo "<h2>1. فحص مجلد الصور الرئيسي</h2>";

$baseDir = $_SERVER['DOCUMENT_ROOT'];
$groomsDir = $baseDir . '/grooms';

if (is_dir($groomsDir)) {
    echo "<div class='success'>✅ مجلد /grooms موجود</div>";
    
    // عد المجلدات الفرعية
    $groomFolders = glob($groomsDir . '/*', GLOB_ONLYDIR);
    echo "<div class='info'>عدد مجلدات العرسان: " . count($groomFolders) . "</div>";
    
    // فحص أول 5 مجلدات
    echo "<h3>نماذج من المجلدات:</h3>";
    echo "<pre>";
    foreach (array_slice($groomFolders, 0, 5) as $folder) {
        $groomId = basename($folder);
        $imageCount = count(glob($folder . '/*.{jpg,jpeg,png,gif,JPG,JPEG,PNG}', GLOB_BRACE));
        $watermarkedCount = is_dir($folder . '/watermarked') ? 
            count(glob($folder . '/watermarked/*.{jpg,jpeg,png,gif,JPG,JPEG,PNG}', GLOB_BRACE)) : 0;
        $imagesCount = is_dir($folder . '/images') ? 
            count(glob($folder . '/images/*.{jpg,jpeg,png,gif,JPG,JPEG,PNG}', GLOB_BRACE)) : 0;
        
        echo "📁 /grooms/$groomId/\n";
        echo "   - الصور في المجلد الرئيسي: $imageCount\n";
        echo "   - الصور في /watermarked: $watermarkedCount\n";
        echo "   - الصور في /images: $imagesCount\n";
        echo "\n";
    }
    echo "</pre>";
} else {
    echo "<div class='error'>❌ مجلد /grooms غير موجود!</div>";
    echo "<div class='warning'>⚠️ يجب إنشاء المجلد: $groomsDir</div>";
}

// 2. فحص قاعدة البيانات
echo "<h2>2. فحص البيانات في قاعدة البيانات</h2>";

// فحص عدد الصور في قاعدة البيانات
$photosCount = $pdo->query("SELECT COUNT(*) FROM groom_photos WHERE hidden = 0")->fetchColumn();
echo "<div class='info'>عدد الصور في قاعدة البيانات: $photosCount</div>";

// فحص عينة من الصور
echo "<h3>عينة من الصور في قاعدة البيانات:</h3>";
$samplePhotos = $pdo->query("
    SELECT gp.*, g.groom_name
    FROM groom_photos gp
    JOIN grooms g ON gp.groom_id = g.id
    WHERE gp.hidden = 0
    LIMIT 10
")->fetchAll();

echo "<pre>";
foreach ($samplePhotos as $photo) {
    echo "📷 العريس: {$photo['groom_name']} (ID: {$photo['groom_id']})\n";
    echo "   - اسم الملف: {$photo['filename']}\n";
    
    // فحص وجود الملف
    $found = false;
    $possiblePaths = [
        "/grooms/{$photo['groom_id']}/watermarked/{$photo['filename']}",
        "/grooms/{$photo['groom_id']}/images/{$photo['filename']}",
        "/grooms/{$photo['groom_id']}/{$photo['filename']}"
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($baseDir . $path)) {
            echo "   ✅ موجود في: $path\n";
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "   ❌ الملف غير موجود في أي مسار!\n";
    }
    echo "\n";
}
echo "</pre>";

// 3. إنشاء جدول video_categories إذا لم يكن موجود
echo "<h2>3. إنشاء جدول تصنيفات الفيديو</h2>";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS video_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            name_ar VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            color VARCHAR(7) DEFAULT '#FFD700',
            icon VARCHAR(50),
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<div class='success'>✅ تم إنشاء جدول video_categories</div>";
    
    // إضافة التصنيفات الافتراضية
    $defaultCategories = [
        ['classic', 'Classic', 'كلاسيك', '#FFD700', '🎬', 1],
        ['golden', 'Golden', 'ذهبي', '#FFD700', '👑', 2],
        ['drone', 'Drone', 'تصوير جوي', '#00BCD4', '🚁', 3],
        ['reel', 'Reels', 'ريلز', '#E91E63', '📱', 4],
        ['talla', 'Talla', 'طلة العريس', '#9C27B0', '🏠', 5],
        ['mobile', 'Mobile', 'تصوير جوال', '#4CAF50', '📲', 6],
        ['folklore', 'Folklore', 'شعبي', '#FF5722', '🥁', 7]
    ];
    
    foreach ($defaultCategories as $cat) {
        $pdo->prepare("
            INSERT IGNORE INTO video_categories (slug, name, name_ar, color, icon, display_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute($cat);
    }
    echo "<div class='info'>تم إضافة التصنيفات الافتراضية</div>";
    
} catch (PDOException $e) {
    echo "<div class='warning'>⚠️ " . $e->getMessage() . "</div>";
}

// 4. إنشاء جدول external_videos للفيديوهات الخارجية
echo "<h2>4. إنشاء جدول الفيديوهات الخارجية</h2>";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS external_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            youtube_url VARCHAR(500) NOT NULL,
            title VARCHAR(255),
            category_id INT,
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES video_categories(id)
        )
    ");
    echo "<div class='success'>✅ تم إنشاء جدول external_videos</div>";
} catch (PDOException $e) {
    echo "<div class='warning'>⚠️ " . $e->getMessage() . "</div>";
}

// 5. إصلاح دالة الحصول على مسار الصورة
echo "<h2>5. إنشاء دالة محسنة للحصول على مسارات الصور</h2>";

echo "<pre style='background: #2a2a2a; padding: 15px; border-radius: 5px;'>";
echo htmlspecialchars('
function getValidImagePath($groomId, $filename) {
    // قائمة المسارات المحتملة
    $baseDir = $_SERVER["DOCUMENT_ROOT"];
    $paths = [
        "/grooms/{$groomId}/watermarked/{$filename}",
        "/grooms/{$groomId}/images/{$filename}",
        "/grooms/{$groomId}/{$filename}",
        "/uploads/grooms/{$groomId}/{$filename}",
        "/photos/{$groomId}/{$filename}"
    ];
    
    // البحث عن الملف في المسارات
    foreach ($paths as $path) {
        if (file_exists($baseDir . $path)) {
            return $path;
        }
    }
    
    // إذا لم نجد الملف بالاسم المحدد، نبحث عن أي صورة في المجلد
    $groomDir = $baseDir . "/grooms/{$groomId}";
    if (is_dir($groomDir)) {
        // البحث في المجلدات الفرعية
        $subdirs = ["watermarked", "images", ""];
        foreach ($subdirs as $subdir) {
            $searchPath = $groomDir . ($subdir ? "/" . $subdir : "");
            if (is_dir($searchPath)) {
                $images = glob($searchPath . "/*.{jpg,jpeg,png,gif,JPG,JPEG,PNG}", GLOB_BRACE);
                if (!empty($images)) {
                    return str_replace($baseDir, "", $images[0]);
                }
            }
        }
    }
    
    return false;
}
');
echo "</pre>";

// 6. إضافة بعض الصور للاختبار
echo "<h2>6. تمييز بعض الصور تلقائياً للاختبار</h2>";

try {
    // تمييز أول 10 صور موجودة
    $photosToFeature = $pdo->query("
        SELECT gp.id, gp.groom_id, gp.filename
        FROM groom_photos gp
        JOIN grooms g ON gp.groom_id = g.id
        WHERE gp.hidden = 0 
        AND g.is_blocked = 0 
        AND g.ready = 1
        LIMIT 10
    ")->fetchAll();
    
    $featuredCount = 0;
    foreach ($photosToFeature as $photo) {
        // التحقق من وجود الصورة
        $found = false;
        $possiblePaths = [
            "/grooms/{$photo['groom_id']}/watermarked/{$photo['filename']}",
            "/grooms/{$photo['groom_id']}/images/{$photo['filename']}",
            "/grooms/{$photo['groom_id']}/{$photo['filename']}"
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($baseDir . $path)) {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $pdo->prepare("UPDATE groom_photos SET is_featured = 1 WHERE id = ?")
                ->execute([$photo['id']]);
            $featuredCount++;
        }
    }
    
    echo "<div class='success'>✅ تم تمييز $featuredCount صورة</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ " . $e->getMessage() . "</div>";
}

// 7. تحديث عدادات اللايكات
echo "<h2>7. تحديث عدادات الإعجاب</h2>";

try {
    $pdo->exec("
        UPDATE grooms g
        SET total_likes = (
            SELECT COALESCE(SUM(gp.likes), 0)
            FROM groom_photos gp
            WHERE gp.groom_id = g.id
        )
    ");
    echo "<div class='success'>✅ تم تحديث جميع عدادات الإعجاب</div>";
} catch (PDOException $e) {
    echo "<div class='error'>❌ " . $e->getMessage() . "</div>";
}

// 8. الحلول المقترحة
echo "<h2>8. الحلول والخطوات التالية</h2>";
echo "<div class='info'>";
echo "<h3>لحل مشكلة الصور:</h3>";
echo "<ol>";
echo "<li>تأكد من رفع الصور في المجلدات الصحيحة: /grooms/[ID]/</li>";
echo "<li>تأكد من أن أسماء الملفات في قاعدة البيانات تطابق الملفات الفعلية</li>";
echo "<li>استخدم الدالة المحسنة getValidImagePath() في ملفات المعرض</li>";
echo "</ol>";

echo "<h3>لإضافة شعار جذلة:</h3>";
echo "<p>ارفع ملف الشعار إلى: /assets/logo.png أو /assets/logo.svg</p>";

echo "<h3>للقائمة الجانبية في الموبايل:</h3>";
echo "<p>تم إضافة كود JavaScript محسن في الملفات الجديدة</p>";

echo "</div>";

echo "</body>";
echo "</html>";
?>