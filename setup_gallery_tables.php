<?php
// setup_gallery_tables.php - إنشاء الجداول المطلوبة للنظام الجديد

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<h2 style='color: green;'>✅ تم الاتصال بقاعدة البيانات بنجاح</h2>";
} catch (PDOException $e) {
    die("<h2 style='color: red;'>❌ خطأ في الاتصال: " . $e->getMessage() . "</h2>");
}

echo "<h3>🔧 بدء إنشاء الجداول والأعمدة المطلوبة...</h3>";

// 1. إنشاء جدول تصنيفات الفيديو
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
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ تم إنشاء جدول video_categories</p>";
    
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
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO video_categories (slug, name, name_ar, color, icon, display_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($defaultCategories as $cat) {
        $stmt->execute($cat);
    }
    echo "<p style='color: blue;'>ℹ️ تم إضافة التصنيفات الافتراضية</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠️ جدول video_categories: " . $e->getMessage() . "</p>";
}

// 2. إنشاء جدول الفيديوهات الخارجية
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
            FOREIGN KEY (category_id) REFERENCES video_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color: green;'>✅ تم إنشاء جدول external_videos</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠️ جدول external_videos: " . $e->getMessage() . "</p>";
}

// 3. إضافة الأعمدة المطلوبة لجدول grooms إن لم تكن موجودة
try {
    // إضافة عمود show_in_gallery
    $columns = $pdo->query("SHOW COLUMNS FROM grooms LIKE 'show_in_gallery'")->fetchAll();
    if (count($columns) == 0) {
        $pdo->exec("ALTER TABLE grooms ADD COLUMN show_in_gallery BOOLEAN DEFAULT 1");
        echo "<p style='color: green;'>✅ تم إضافة عمود show_in_gallery</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ عمود show_in_gallery موجود بالفعل</p>";
    }
    
    // إضافة عمود display_order
    $columns = $pdo->query("SHOW COLUMNS FROM grooms LIKE 'display_order'")->fetchAll();
    if (count($columns) == 0) {
        $pdo->exec("ALTER TABLE grooms ADD COLUMN display_order INT DEFAULT 0");
        echo "<p style='color: green;'>✅ تم إضافة عمود display_order</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ عمود display_order موجود بالفعل</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠️ تحديث جدول grooms: " . $e->getMessage() . "</p>";
}

// 4. إضافة عمود is_featured لجدول groom_photos
try {
    $columns = $pdo->query("SHOW COLUMNS FROM groom_photos LIKE 'is_featured'")->fetchAll();
    if (count($columns) == 0) {
        $pdo->exec("ALTER TABLE groom_photos ADD COLUMN is_featured BOOLEAN DEFAULT 0");
        $pdo->exec("ALTER TABLE groom_photos ADD INDEX idx_featured (is_featured)");
        echo "<p style='color: green;'>✅ تم إضافة عمود is_featured مع الفهرس</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ عمود is_featured موجود بالفعل</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠️ تحديث جدول groom_photos: " . $e->getMessage() . "</p>";
}

// 5. تحديث عدادات الإعجابات
try {
    $pdo->exec("
        UPDATE grooms g
        SET total_likes = (
            SELECT COALESCE(SUM(gp.likes), 0)
            FROM groom_photos gp
            WHERE gp.groom_id = g.id
        )
    ");
    echo "<p style='color: green;'>✅ تم تحديث جميع عدادات الإعجابات</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠️ تحديث اللايكات: " . $e->getMessage() . "</p>";
}

// 6. إنشاء مجلد gallery_uploads
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gallery_uploads';
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0777, true)) {
        echo "<p style='color: green;'>✅ تم إنشاء مجلد /gallery_uploads</p>";
    } else {
        echo "<p style='color: red;'>❌ فشل إنشاء مجلد /gallery_uploads - قم بإنشائه يدوياً</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ مجلد /gallery_uploads موجود بالفعل</p>";
}

// 7. تمييز بعض الصور تلقائياً للاختبار
try {
    // البحث عن صور موجودة فعلياً
    $photosToCheck = $pdo->query("
        SELECT gp.id, gp.groom_id, gp.filename
        FROM groom_photos gp
        JOIN grooms g ON gp.groom_id = g.id
        WHERE gp.hidden = 0 
        AND g.is_blocked = 0 
        AND g.ready = 1
        LIMIT 50
    ")->fetchAll();
    
    $featuredCount = 0;
    $baseDir = $_SERVER['DOCUMENT_ROOT'];
    
    foreach ($photosToCheck as $photo) {
        // المسارات المحتملة بما فيها المجلدات الجديدة
        $possiblePaths = [
            "/grooms/{$photo['groom_id']}/modal_thumb/{$photo['filename']}",
            "/grooms/{$photo['groom_id']}/originals/{$photo['filename']}",
            "/grooms/{$photo['groom_id']}/watermarked/{$photo['filename']}",
            "/grooms/{$photo['groom_id']}/images/{$photo['filename']}",
            "/grooms/{$photo['groom_id']}/{$photo['filename']}"
        ];
        
        $found = false;
        foreach ($possiblePaths as $path) {
            if (file_exists($baseDir . $path)) {
                $found = true;
                break;
            }
        }
        
        if ($found && $featuredCount < 10) {
            $pdo->prepare("UPDATE groom_photos SET is_featured = 1 WHERE id = ?")
                ->execute([$photo['id']]);
            $featuredCount++;
        }
    }
    
    echo "<p style='color: green;'>✅ تم تمييز $featuredCount صورة موجودة</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠️ تمييز الصور: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2 style='color: green;'>✨ تم الانتهاء!</h2>";
echo "<p>يمكنك الآن:</p>";
echo "<ul>";
echo "<li><a href='gallery_admin_final.php'>الذهاب إلى لوحة التحكم</a></li>";
echo "<li><a href='gallery_fixed_final.php'>عرض المعرض</a></li>";
echo "</ul>";
?>