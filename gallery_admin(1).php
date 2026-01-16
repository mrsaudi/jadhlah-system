<?php
// gallery_admin_complete.php - نظام إدارة معرض كامل نهائي

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// دالة للحصول على مسار الصورة
// دالة للحصول على مسار الصورة
function getValidImagePath($groomId, $filename) {
    if (empty($groomId) || empty($filename)) return false;
    
    $baseDir = $_SERVER['DOCUMENT_ROOT'];
    
    // قائمة المسارات المحتملة بالترتيب
    $paths = [
        "/grooms/{$groomId}/modal_thumb/{$filename}",
        "/grooms/{$groomId}/thumb/{$filename}",
        "/grooms/{$groomId}/thumbnails/{$filename}",
        "/grooms/{$groomId}/originals/{$filename}",
        "/grooms/{$groomId}/watermarked/{$filename}",
        "/grooms/{$groomId}/images/{$filename}",
        "/grooms/{$groomId}/{$filename}"
    ];
    
    foreach ($paths as $path) {
        $fullPath = $baseDir . $path;
        if (@file_exists($fullPath) && @is_file($fullPath) && @is_readable($fullPath)) {
            // التحقق من أن الملف صورة صالحة
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo !== false) {
                return $path;
            }
        }
    }
    
    // إذا لم نجد الصورة، نعيد مسار placeholder
    return false;
}

// دالة للحصول على البنر
function getGroomBanner($groomId) {
    $baseDir = $_SERVER['DOCUMENT_ROOT'];
    $bannerPath = "/grooms/{$groomId}/banner.jpg";
    
    if (@file_exists($baseDir . $bannerPath)) {
        return $bannerPath;
    }
    return false;
}

// إنشاء الجداول
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gallery_uploaded_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            title VARCHAR(255),
            is_featured BOOLEAN DEFAULT 1,
            display_order INT DEFAULT 0,
            likes INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
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
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS groom_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            groom_id INT NOT NULL,
            youtube_url VARCHAR(500) NOT NULL,
            video_number INT,
            category_id INT,
            title VARCHAR(255),
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (groom_id) REFERENCES grooms(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES video_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
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
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS photographers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            role VARCHAR(255) NOT NULL,
            description TEXT,
            image VARCHAR(255),
            display_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gallery_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // إضافة تصنيف كلاسيك الافتراضي إذا لم يكن موجوداً
$pdo->exec("INSERT IGNORE INTO video_categories (name, name_ar, slug, icon, display_order) 
            VALUES ('Classic', 'كلاسيك', 'classic', '🎬', 0)");
    
    // إضافة الإعدادات الافتراضية
    $pdo->exec("INSERT IGNORE INTO gallery_settings (setting_key, setting_value) VALUES 
        ('photos_limit', '30'),
        ('instagram_token', ''),
        ('instagram_enabled', '0')
    ");
    
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/gallery_uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $photographersDir = $_SERVER['DOCUMENT_ROOT'] . '/photographers';
    if (!is_dir($photographersDir)) {
        mkdir($photographersDir, 0755, true);
    }
    
} catch (PDOException $e) {
    $createError = $e->getMessage();
}

$message = '';
$messageType = 'success';

// معالجة الطلبات POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // حفظ الإعدادات
        if (isset($_POST['save_settings'])) {
            $photosLimit = max(1, min(100, (int)$_POST['photos_limit']));
            $instagramToken = trim($_POST['instagram_token']);
            $instagramEnabled = isset($_POST['instagram_enabled']) ? 1 : 0;
            
            $pdo->prepare("UPDATE gallery_settings SET setting_value = ? WHERE setting_key = 'photos_limit'")->execute([$photosLimit]);
            $pdo->prepare("UPDATE gallery_settings SET setting_value = ? WHERE setting_key = 'instagram_token'")->execute([$instagramToken]);
            $pdo->prepare("UPDATE gallery_settings SET setting_value = ? WHERE setting_key = 'instagram_enabled'")->execute([$instagramEnabled]);
            
            $message = "✅ تم حفظ الإعدادات بنجاح";
        }
        
        // إلغاء تمييز جميع الصور
        if (isset($_POST['unmark_all_photos'])) {
            $pdo->exec("UPDATE groom_photos SET is_featured = 0");
            $message = "✅ تم إلغاء تمييز جميع الصور";
        }
        
        // تحديث ترتيب العرسان
        if (isset($_POST['update_grooms_order'])) {
            $orders = json_decode($_POST['orders'], true);
            foreach ($orders as $id => $order) {
                $pdo->prepare("UPDATE grooms SET display_order = ? WHERE id = ?")->execute([$order, $id]);
            }
            $message = "✅ تم حفظ الترتيب";
        }
        
        // تحديث ترتيب الصور المرفوعة
        if (isset($_POST['update_images_order'])) {
            $orders = json_decode($_POST['orders'], true);
            foreach ($orders as $id => $order) {
                $pdo->prepare("UPDATE gallery_uploaded_images SET display_order = ? WHERE id = ?")->execute([$order, $id]);
            }
            $message = "✅ تم حفظ الترتيب";
        }
        
        // تحديث ترتيب الفيديوهات الخارجية
        if (isset($_POST['update_videos_order'])) {
            $orders = json_decode($_POST['orders'], true);
            foreach ($orders as $id => $order) {
                $pdo->prepare("UPDATE external_videos SET display_order = ? WHERE id = ?")->execute([$order, $id]);
            }
            $message = "✅ تم حفظ الترتيب";
        }
        
        // تحديث ترتيب التصنيفات
        if (isset($_POST['update_categories_order'])) {
            $orders = json_decode($_POST['orders'], true);
            foreach ($orders as $id => $order) {
                $pdo->prepare("UPDATE video_categories SET display_order = ? WHERE id = ?")->execute([$order, $id]);
            }
            $message = "✅ تم حفظ الترتيب";
        }
        
        // تحديث ترتيب المصورين
        if (isset($_POST['update_photographers_order'])) {
            $orders = json_decode($_POST['orders'], true);
            foreach ($orders as $id => $order) {
                $pdo->prepare("UPDATE photographers SET display_order = ? WHERE id = ?")->execute([$order, $id]);
            }
            $message = "✅ تم حفظ الترتيب";
        }
        
        // إضافة مصور
        if (isset($_POST['add_photographer']) && isset($_FILES['photographer_image'])) {
            $name = trim($_POST['photographer_name']);
            $role = trim($_POST['photographer_role']);
            $description = trim($_POST['photographer_description']);
            $file = $_FILES['photographer_image'];
            
            if ($file['error'] === UPLOAD_ERR_OK && !empty($name) && !empty($role)) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($file['tmp_name']);
                
                if (in_array($fileType, $allowedTypes)) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'photographer_' . time() . '_' . uniqid() . '.' . $ext;
                    $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/photographers/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $stmt = $pdo->prepare("INSERT INTO photographers (name, role, description, image) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $role, $description, $filename]);
                        $message = "✅ تم إضافة المصور بنجاح";
                    }
                }
            }
        }
        
        // حذف مصور
        if (isset($_POST['delete_photographer'])) {
            $photographerId = (int)$_POST['photographer_id'];
            $stmt = $pdo->prepare("SELECT image FROM photographers WHERE id = ?");
            $stmt->execute([$photographerId]);
            $photographer = $stmt->fetch();
            
            if ($photographer) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/photographers/' . $photographer['image'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $pdo->prepare("DELETE FROM photographers WHERE id = ?")->execute([$photographerId]);
                $message = "✅ تم حذف المصور";
            }
        }
        
        // باقي العمليات السابقة...
        if (isset($_POST['update_likes'])) {
            $pdo->exec("UPDATE grooms g SET total_likes = (SELECT COALESCE(SUM(gp.likes), 0) FROM groom_photos gp WHERE gp.groom_id = g.id)");
            $message = "✅ تم تحديث عدادات الإعجاب";
        }
        
        if (isset($_POST['upload_image']) && isset($_FILES['gallery_image'])) {
            $file = $_FILES['gallery_image'];
            $title = trim($_POST['image_title'] ?? '');
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($file['tmp_name']);
                
                if (in_array($fileType, $allowedTypes)) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'gallery_' . time() . '_' . uniqid() . '.' . $ext;
                    $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/gallery_uploads/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $stmt = $pdo->prepare("INSERT INTO gallery_uploaded_images (filename, title) VALUES (?, ?)");
                        $stmt->execute([$filename, $title]);
                        $message = "✅ تم رفع الصورة بنجاح";
                    }
                }
            }
        }
        
        if (isset($_POST['delete_uploaded_image'])) {
            $imageId = (int)$_POST['image_id'];
            $stmt = $pdo->prepare("SELECT filename FROM gallery_uploaded_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();
            
            if ($image) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/gallery_uploads/' . $image['filename'];
                if (file_exists($filePath)) unlink($filePath);
                $pdo->prepare("DELETE FROM gallery_uploaded_images WHERE id = ?")->execute([$imageId]);
                $message = "✅ تم حذف الصورة";
            }
        }
        
        if (isset($_POST['toggle_groom_photo_featured'])) {
            $photoId = (int)$_POST['photo_id'];
            $pdo->prepare("UPDATE groom_photos SET is_featured = NOT is_featured WHERE id = ?")->execute([$photoId]);
            $message = "✅ تم تحديث حالة التمييز";
        }
        
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['category_name']);
            $nameAr = trim($_POST['category_name_ar']);
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
            $icon = $_POST['category_icon'] ?? '🎬';
            $color = $_POST['category_color'] ?? '#FFD700';
            
            if (!empty($name) && !empty($nameAr)) {
                $stmt = $pdo->prepare("INSERT INTO video_categories (name, name_ar, slug, icon, color) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $nameAr, $slug, $icon, $color]);
                $message = "✅ تم إضافة التصنيف";
            }
        }
        
        if (isset($_POST['delete_category'])) {
            $categoryId = (int)$_POST['category_id'];
            $pdo->prepare("DELETE FROM video_categories WHERE id = ?")->execute([$categoryId]);
            $message = "✅ تم حذف التصنيف";
        }
        
        if (isset($_POST['add_external_video'])) {
            $url = trim($_POST['video_url']);
            $title = trim($_POST['video_title']);
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            
            if (!empty($url)) {
                $stmt = $pdo->prepare("INSERT INTO external_videos (youtube_url, title, category_id) VALUES (?, ?, ?)");
                $stmt->execute([$url, $title, $categoryId]);
                $message = "✅ تم إضافة الفيديو";
            }
        }
        
        if (isset($_POST['delete_video'])) {
            $videoId = (int)$_POST['video_id'];
            $pdo->prepare("DELETE FROM external_videos WHERE id = ?")->execute([$videoId]);
            $message = "✅ تم حذف الفيديو";
        }
        
        if (isset($_POST['update_groom_video_category'])) {
            $groomId = (int)$_POST['groom_id'];
            $videoNumber = (int)$_POST['video_number'];
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $youtubeUrl = $_POST['youtube_url'];
            
            $stmt = $pdo->prepare("SELECT id FROM groom_videos WHERE groom_id = ? AND video_number = ?");
            $stmt->execute([$groomId, $videoNumber]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE groom_videos SET category_id = ?, youtube_url = ? WHERE id = ?");
                $stmt->execute([$categoryId, $youtubeUrl, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO groom_videos (groom_id, youtube_url, video_number, category_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$groomId, $youtubeUrl, $videoNumber, $categoryId]);
            }
            
            $message = "✅ تم تحديث تصنيف الفيديو";
        }
        
        if (isset($_POST['toggle_gallery'])) {
            $groomId = (int)$_POST['groom_id'];
            $showInGallery = isset($_POST['show_in_gallery']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE grooms SET show_in_gallery = ? WHERE id = ?");
            $stmt->execute([$showInGallery, $groomId]);
            $message = "✅ تم تحديث حالة العرض";
        }
        
        // حذف/إخفاء فيديو عريس
if (isset($_POST['hide_groom_video'])) {
    $groomId = (int)$_POST['groom_id'];
    $videoNumber = (int)$_POST['video_number'];
    
    // حذف من جدول groom_videos إذا كان موجوداً
    $pdo->prepare("DELETE FROM groom_videos WHERE groom_id = ? AND video_number = ?")->execute([$groomId, $videoNumber]);
    
    // مسح الحقل من جدول العرسان
    $youtubeField = "youtube{$videoNumber}";
    $pdo->prepare("UPDATE grooms SET {$youtubeField} = '' WHERE id = ?")->execute([$groomId]);
    
    $message = "✅ تم حذف الفيديو من المعرض";
}

// تحريك صورة مميزة لأعلى
if (isset($_POST['move_photo_up'])) {
    $photoId = (int)$_POST['photo_id'];
    
    // جلب الصورة الحالية
    $current = $pdo->prepare("SELECT id, likes FROM groom_photos WHERE id = ?");
    $current->execute([$photoId]);
    $currentPhoto = $current->fetch();
    
    if ($currentPhoto) {
        // جلب الصورة التي فوقها
        $above = $pdo->prepare("SELECT id, likes FROM groom_photos WHERE is_featured = 1 AND hidden = 0 AND likes > ? ORDER BY likes ASC LIMIT 1");
        $above->execute([$currentPhoto['likes']]);
        $abovePhoto = $above->fetch();
        
        if ($abovePhoto) {
            // تبديل ترتيب اللايكات
            $tempLikes = $currentPhoto['likes'] + 100000;
            $pdo->prepare("UPDATE groom_photos SET likes = ? WHERE id = ?")->execute([$tempLikes, $photoId]);
            $pdo->prepare("UPDATE groom_photos SET likes = ? WHERE id = ?")->execute([$currentPhoto['likes'], $abovePhoto['id']]);
            $pdo->prepare("UPDATE groom_photos SET likes = ? WHERE id = ?")->execute([$abovePhoto['likes'], $photoId]);
            
            $message = "✅ تم تحريك الصورة لأعلى";
        }
    }
}

// تحريك صورة مميزة لأسفل
if (isset($_POST['move_photo_down'])) {
    $photoId = (int)$_POST['photo_id'];
    
    $current = $pdo->prepare("SELECT id, likes FROM groom_photos WHERE id = ?");
    $current->execute([$photoId]);
    $currentPhoto = $current->fetch();
    
    if ($currentPhoto) {
        $below = $pdo->prepare("SELECT id, likes FROM groom_photos WHERE is_featured = 1 AND hidden = 0 AND likes < ? ORDER BY likes DESC LIMIT 1");
        $below->execute([$currentPhoto['likes']]);
        $belowPhoto = $below->fetch();
        
        if ($belowPhoto) {
            $tempLikes = $currentPhoto['likes'] - 100000;
            $pdo->prepare("UPDATE groom_photos SET likes = ? WHERE id = ?")->execute([$tempLikes, $photoId]);
            $pdo->prepare("UPDATE groom_photos SET likes = ? WHERE id = ?")->execute([$currentPhoto['likes'], $belowPhoto['id']]);
            $pdo->prepare("UPDATE groom_photos SET likes = ? WHERE id = ?")->execute([$belowPhoto['likes'], $photoId]);
            
            $message = "✅ تم تحريك الصورة لأسفل";
        }
    }
}

    } catch (PDOException $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $messageType = 'error';
    }
    
    if (!empty($message)) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $messageType;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// جلب الإعدادات
$settings = [];
$settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM gallery_settings");
foreach ($settingsQuery as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// جلب الإحصائيات
try {
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM grooms WHERE is_blocked = 0 AND ready = 1) as total_grooms,
            (SELECT COUNT(*) FROM grooms WHERE show_in_gallery = 1) as shown_grooms,
            (SELECT COUNT(*) FROM groom_photos WHERE hidden = 0) as total_photos,
            (SELECT COUNT(*) FROM groom_photos WHERE is_featured = 1) as featured_photos,
            (SELECT COALESCE(SUM(total_likes), 0) FROM grooms) as total_likes,
            (SELECT COUNT(*) FROM gallery_uploaded_images) as uploaded_images,
            (SELECT COUNT(*) FROM photographers WHERE is_active = 1) as photographers_count
    ")->fetch();
} catch (PDOException $e) {
    $stats = ['total_grooms' => 0, 'shown_grooms' => 0, 'total_photos' => 0, 'featured_photos' => 0, 'total_likes' => 0, 'uploaded_images' => 0, 'photographers_count' => 0];
}

// جلب البيانات
$categories = $pdo->query("SELECT * FROM video_categories ORDER BY display_order, id")->fetchAll();
// Pagination للصور المميزة
$featuredPage = isset($_GET['featured_page']) ? max(1, (int)$_GET['featured_page']) : 1;
$featuredPerPage = 50;
$featuredOffset = ($featuredPage - 1) * $featuredPerPage;

$totalFeaturedStmt = $pdo->query("SELECT COUNT(*) as total FROM groom_photos WHERE hidden = 0 AND is_featured = 1");
$totalFeatured = $totalFeaturedStmt->fetch()['total'];
$totalFeaturedPages = ceil($totalFeatured / $featuredPerPage);

$featuredPhotosQuery = $pdo->prepare("
    SELECT gp.*, g.groom_name, g.id as groom_id 
    FROM groom_photos gp 
    JOIN grooms g ON gp.groom_id = g.id 
    WHERE gp.hidden = 0 AND gp.is_featured = 1 
    ORDER BY gp.likes DESC 
    LIMIT ? OFFSET ?
");
$featuredPhotosQuery->execute([$featuredPerPage, $featuredOffset]);
$featuredPhotos = $featuredPhotosQuery->fetchAll();$uploadedImages = $pdo->query("SELECT * FROM gallery_uploaded_images ORDER BY display_order DESC, id DESC")->fetchAll();
$photographers = $pdo->query("SELECT * FROM photographers ORDER BY display_order, id")->fetchAll();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$photosPerPage = 50;
$offset = ($page - 1) * $photosPerPage;

$totalPhotosStmt = $pdo->query("SELECT COUNT(*) as total FROM groom_photos WHERE hidden = 0 AND is_featured = 0");
$totalPhotos = $totalPhotosStmt->fetch()['total'];
$totalPages = ceil($totalPhotos / $photosPerPage);

// Pagination لتمييز الصور
$markPage = isset($_GET['mark_page']) ? max(1, (int)$_GET['mark_page']) : 1;
$markPerPage = 50;
$markOffset = ($markPage - 1) * $markPerPage;

$totalMarkStmt = $pdo->query("SELECT COUNT(*) as total FROM groom_photos WHERE hidden = 0 AND is_featured = 0");
$totalMark = $totalMarkStmt->fetch()['total'];
$totalMarkPages = ceil($totalMark / $markPerPage);

$nonFeaturedPhotosQuery = $pdo->prepare("
    SELECT gp.*, g.groom_name, g.id as groom_id
    FROM groom_photos gp
    JOIN grooms g ON gp.groom_id = g.id
    WHERE gp.hidden = 0 AND gp.is_featured = 0
    ORDER BY gp.likes DESC
    LIMIT ? OFFSET ?
");
$nonFeaturedPhotosQuery->execute([$markPerPage, $markOffset]);
$nonFeaturedPhotos = $nonFeaturedPhotosQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المعرض - النظام الكامل</title>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --gold: #FFD700;
            --gold-dark: #F59E0B;
            --black: #000;
            --dark: #1A1A1A;
            --gray: #2A2A2A;
            --success: #10B981;
            --danger: #EF4444;
        }
        
        body {
            font-family: -apple-system, 'Tajawal', 'Segoe UI', sans-serif;
            background: var(--black);
            color: white;
            line-height: 1.6;
        }
        
        .container { max-width: 1600px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: var(--dark);
            padding: 20px;
            border-bottom: 2px solid var(--gold);
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--dark);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(255, 215, 0, 0.2);
            transition: transform 0.3s;
        }
        
        .stat-card:hover { transform: translateY(-5px); border-color: var(--gold); }
        .stat-value { font-size: 32px; font-weight: bold; color: var(--gold); margin: 10px 0; }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.05);
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 10px 10px 0 0;
            transition: all 0.3s;
            font-size: 15px;
        }
        
        .tab:hover { background: rgba(255, 215, 0, 0.1); }
        .tab.active { background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: var(--black); font-weight: bold; }
        
.tab-content { 
    display: none; 
}

.tab-content.active { 
    display: block !important; 
    animation: fadeIn 0.3s; 
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.sortable-item,
.photo-card,
.photographer-card {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.sortable-item *,
.photo-card *,
.photographer-card * {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.sortable-ghost {
    opacity: 0.4;
    background: rgba(255, 215, 0, 0.2);
    cursor: grabbing !important;
}

.sortable-drag {
    opacity: 0.8;
    cursor: grabbing !important;
}

/* تحسين مظهر السحب */
.photo-card {
    cursor: grab;
}

.photo-card:active {
    cursor: grabbing;
}


        .section {
            background: var(--dark);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 24px;
            color: var(--gold);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 215, 0, 0.2);
        }
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .photo-card {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            background: var(--gray);
            aspect-ratio: 1;
            transition: transform 0.3s;
            cursor: move;
        }
        
        .photo-card:hover { transform: scale(1.05); }
        .photo-card img { width: 100%; height: 100%; object-fit: cover; }
        .photo-card.featured { border: 3px solid var(--gold); }
        
        .photo-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .photo-card:hover .photo-overlay { opacity: 1; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-primary { background: linear-gradient(135deg, var(--gold), var(--gold-dark)); color: var(--black); }
        .btn-secondary { background: var(--gray); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-small { padding: 6px 12px; font-size: 12px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3); }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        th { background: rgba(255, 215, 0, 0.1); padding: 15px; text-align: right; color: var(--gold); }
        td { padding: 15px; background: rgba(255, 255, 255, 0.02); }
        tr:hover td { background: rgba(255, 255, 255, 0.05); }
        
        .form-group { margin-bottom: 20px; }
        .form-control {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success); color: var(--success); }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger); color: var(--danger); }
        
        .sortable-list { list-style: none; }
        .sortable-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: move;
            border: 2px solid transparent;
            transition: all 0.3s;
            user-select: none;
        }
        .sortable-item:hover { border-color: var(--gold); background: rgba(255, 215, 0, 0.1); }
        .sortable-ghost { opacity: 0.4; background: rgba(255, 215, 0, 0.2); }
        
        .drag-handle {
            display: inline-block;
            padding: 5px 10px;
            background: rgba(255, 215, 0, 0.2);
            border-radius: 5px;
            cursor: move;
            margin-left: 10px;
            color: var(--gold);
            user-select: none;
        }
        
        .drag-handle:hover {
            background: rgba(255, 215, 0, 0.3);
        }
        
        .upload-area {
            border: 2px dashed rgba(255, 215, 0, 0.3);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover { border-color: var(--gold); }
        .upload-area input[type="file"] { display: none; }
        
        .info-box {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .photographer-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255, 215, 0, 0.2);
        }
        
        .photographer-card img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--gold);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .photo-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: var(--gray);
}

.photo-card img[src=""], .photo-card img:not([src]) {
    display: none;
}

.photo-card::before {
    content: '📷';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 60px;
    opacity: 0.3;
}

.photo-card:has(img[src]) .photo-card::before {
    display: none;
}

    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">🎨 لوحة تحكم المعرض</div>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="gallery.php" target="_blank" class="btn btn-primary">👁️ عرض المعرض</a>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="update_likes" class="btn btn-secondary">🔄 تحديث اللايكات</button>
                    </form>
                    <a href="logout.php" class="btn btn-secondary">🚪 خروج</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div style="font-size: 30px;">👰</div>
                <div class="stat-value"><?= $stats['shown_grooms'] ?>/<?= $stats['total_grooms'] ?></div>
                <div>عريس معروض</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 30px;">⭐</div>
                <div class="stat-value"><?= $stats['featured_photos'] ?></div>
                <div>صورة مميزة</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 30px;">📤</div>
                <div class="stat-value"><?= $stats['uploaded_images'] ?></div>
                <div>صورة مرفوعة</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 30px;">👥</div>
                <div class="stat-value"><?= $stats['photographers_count'] ?></div>
                <div>مصور</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 30px;">📷</div>
                <div class="stat-value"><?= number_format($stats['total_photos']) ?></div>
                <div>إجمالي الصور</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 30px;">❤️</div>
                <div class="stat-value"><?= number_format($stats['total_likes']) ?></div>
                <div>إجمالي الإعجابات</div>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('featured')">⭐ الصور المميزة</button>
            <button class="tab" onclick="showTab('mark')">✨ تمييز صور</button>
            <button class="tab" onclick="showTab('upload')">📤 رفع صور</button>
            <button class="tab" onclick="showTab('photographers')">👥 المصورين</button>
            <button class="tab" onclick="showTab('categories')">🏷️ التصنيفات</button>
            <button class="tab" onclick="showTab('videos')">🎥 الفيديوهات</button>
            <button class="tab" onclick="showTab('grooms')">👰 العرسان</button>
            <button class="tab" onclick="showTab('settings')">⚙️ الإعدادات</button>
        </div>
 </div>

<!-- تبويب الصور المميزة -->
<div id="featured-tab" class="tab-content active">
    <div class="section">
        <h2 class="section-title">⭐ الصور المميزة - تظهر في المعرض</h2>
        <div class="info-box" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <strong>ℹ️ ملاحظة:</strong> هذه الصور تظهر في معرض الأعمال. يمكنك إلغاء تمييز جميع الصور لإعادة اختيارها من جديد.
    </div>
    <form method="POST" onsubmit="return confirm('⚠️ سيتم إلغاء تمييز <?= $stats['featured_photos'] ?> صورة. هل أنت متأكد؟')" style="margin: 0;">
        <button type="submit" name="unmark_all_photos" class="btn btn-danger">
            ❌ إلغاء تمييز جميع الصور (<?= $stats['featured_photos'] ?>)
        </button>
    </form>
</div>
        
        <h3 style="color: var(--gold); margin: 20px 0;">📤 الصور المرفوعة (<?= count($uploadedImages) ?>)</h3>
        <?php if (!empty($uploadedImages)): ?>
        <div class="photos-grid" id="uploaded-images-grid">
            <?php foreach ($uploadedImages as $image): ?>
            <div class="photo-card featured" data-id="<?= $image['id'] ?>">
                <img src="/gallery_uploads/<?= htmlspecialchars($image['filename']) ?>" alt="">
                <div class="photo-overlay">
                    <div style="color: white; font-size: 12px; margin-bottom: 5px; font-weight: bold;">
                        📤 <?= htmlspecialchars($image['title'] ?: 'صورة مرفوعة') ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد؟')">
                        <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                        <button type="submit" name="delete_uploaded_image" class="btn btn-small btn-danger" style="width: 100%;">
                            🗑️ حذف
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button onclick="saveImagesOrder()" class="btn btn-primary" style="margin-top: 20px;">💾 حفظ ترتيب الصور المرفوعة</button>
        <?php endif; ?>
        
        <h3 style="color: var(--gold); margin: 30px 0 20px;">⭐ الصور المميزة من العرسان (<?= count($featuredPhotos) ?>)</h3>
        <div class="photos-grid">
            <?php 
            $displayedPhotos = 0;
            foreach ($featuredPhotos as $photo): 
                $imagePath = getValidImagePath($photo['groom_id'], $photo['filename']);
                if ($imagePath):
                    $displayedPhotos++;
            ?>
            <div class="photo-card featured">
    <img src="<?= htmlspecialchars($imagePath) ?>" 
         alt="<?= htmlspecialchars($photo['groom_name']) ?>"
         onerror="this.style.display='none'; this.parentElement.style.border='2px dashed rgba(255,215,0,0.3)';">
    <div class="photo-overlay">
        <div style="color: white; font-size: 12px; margin-bottom: 5px; font-weight: bold;">
            <?= htmlspecialchars($photo['groom_name']) ?>
        </div>
        <div style="color: #AAA; font-size: 11px; margin-bottom: 10px;">
            ❤️ <?= number_format($photo['likes']) ?>
        </div>
        
        <!-- أزرار التحكم: أعلى، أسفل، حذف -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 5px;">
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                <button type="submit" name="move_photo_up" class="btn btn-small btn-secondary" 
                        style="width: 100%; padding: 8px 5px;" title="تحريك لأعلى">
                    ⬆️
                </button>
            </form>
            
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                <button type="submit" name="move_photo_down" class="btn btn-small btn-secondary" 
                        style="width: 100%; padding: 8px 5px;" title="تحريك لأسفل">
                    ⬇️
                </button>
            </form>
            
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                <button type="submit" name="toggle_groom_photo_featured" class="btn btn-small btn-danger" 
                        style="width: 100%; padding: 8px 5px;">
                    ❌ إلغاء
                </button>
            </form>
        </div>
    </div>
</div>
            <?php 
                endif;
            endforeach; 
            
            if ($displayedPhotos == 0):
            ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 60px; margin-bottom: 20px;">📷</div>
                <p>لا توجد صور مميزة متاحة للعرض</p>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($totalFeaturedPages > 1): ?>
<div style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; flex-wrap: wrap;">
    <?php if ($featuredPage > 1): ?>
    <a href="?featured_page=<?= $featuredPage - 1 ?>#featured-tab" class="btn btn-secondary">← السابق</a>
    <?php endif; ?>
    
    <?php for ($i = max(1, $featuredPage - 2); $i <= min($totalFeaturedPages, $featuredPage + 2); $i++): ?>
    <a href="?featured_page=<?= $i ?>#featured-tab" 
       class="btn <?= $i == $featuredPage ? 'btn-primary' : 'btn-secondary' ?>"
       style="min-width: 45px;">
        <?= $i ?>
    </a>
    <?php endfor; ?>
    
    <?php if ($featuredPage < $totalFeaturedPages): ?>
    <a href="?featured_page=<?= $featuredPage + 1 ?>#featured-tab" class="btn btn-secondary">التالي →</a>
    <?php endif; ?>
    
    <span style="color: #AAA; display: flex; align-items: center; margin-right: 15px;">
        صفحة <?= $featuredPage ?> من <?= $totalFeaturedPages ?> (<?= $totalFeatured ?> صورة)
    </span>
</div>
<?php endif; ?>
    </div>
</div>

<!-- تبويب تمييز الصور -->
        <!-- تبويب تمييز الصور -->
        <div id="mark-tab" class="tab-content">
            <div class="section">
                <h2 class="section-title">تمييز صور من العرسان</h2>
                
                <?php if ($totalPhotos == 0): ?>
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 60px; margin-bottom: 20px;">✨</div>
                    <p style="color: #AAA; margin-bottom: 20px;">جميع الصور مميزة بالفعل!</p>
                    <form method="POST" onsubmit="return confirm('سيتم إلغاء تمييز جميع الصور. هل أنت متأكد؟')">
                        <button type="submit" name="unmark_all_photos" class="btn btn-danger">
                            ❌ إلغاء تمييز جميع الصور
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="photos-grid">
                    <?php foreach ($nonFeaturedPhotos as $photo): 
                        $imagePath = getValidImagePath($photo['groom_id'], $photo['filename']);
                        if ($imagePath):
                    ?>
                    <div class="photo-card">
                        <img src="<?= htmlspecialchars($imagePath) ?>" alt="">
                        <div class="photo-overlay">
                            <div style="color: white; font-size: 12px; margin-bottom: 5px;">
                                <?= htmlspecialchars($photo['groom_name']) ?>
                            </div>
                            <div style="color: #AAA; font-size: 11px; margin-bottom: 10px;">
                                ❤️ <?= number_format($photo['likes']) ?>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                <button type="submit" name="toggle_groom_photo_featured" class="btn btn-small btn-primary" style="width: 100%;">
                                    ⭐ تمييز
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($totalMarkPages > 1): ?>
<div style="display: flex; justify-content: center; gap: 10px; margin-top: 30px; flex-wrap: wrap;">
    <?php if ($markPage > 1): ?>
    <a href="?mark_page=<?= $markPage - 1 ?>#mark-tab" class="btn btn-secondary">← السابق</a>
    <?php endif; ?>
    
    <?php for ($i = max(1, $markPage - 2); $i <= min($totalMarkPages, $markPage + 2); $i++): ?>
    <a href="?mark_page=<?= $i ?>#mark-tab" 
       class="btn <?= $i == $markPage ? 'btn-primary' : 'btn-secondary' ?>"
       style="min-width: 45px;">
        <?= $i ?>
    </a>
    <?php endfor; ?>
    
    <?php if ($markPage < $totalMarkPages): ?>
    <a href="?mark_page=<?= $markPage + 1 ?>#mark-tab" class="btn btn-secondary">التالي →</a>
    <?php endif; ?>
    
    <span style="color: #AAA; display: flex; align-items: center; margin-right: 15px;">
        صفحة <?= $markPage ?> من <?= $totalMarkPages ?> (<?= $totalMark ?> صورة)
    </span>
</div>
<?php endif; ?>


            </div>
        </div>
        
        <!-- تبويب رفع الصور -->
        <div id="upload-tab" class="tab-content">
            <div class="section">
                <h2 class="section-title">رفع صور خارجية</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area" onclick="document.getElementById('file-input').click()">
                        <div style="font-size: 50px; margin-bottom: 15px;">📤</div>
                        <div style="font-size: 18px; color: var(--gold); margin-bottom: 10px;">اضغط لاختيار الصورة</div>
                        <input type="file" id="file-input" name="gallery_image" accept="image/*" required>
                    </div>
                    <div class="form-group" style="margin-top: 20px;">
                        <input type="text" name="image_title" placeholder="عنوان الصورة (اختياري)" class="form-control">
                    </div>
                    <button type="submit" name="upload_image" class="btn btn-primary" style="width: 100%;">✅ رفع</button>
                </form>
            </div>
        </div>
        
        <!-- تبويب المصورين -->
        <div id="photographers-tab" class="tab-content">
            <div class="section">
                <h2 class="section-title">إدارة المصورين</h2>
                
                <div style="background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                    <h3 style="color: var(--gold); margin-bottom: 15px;">➕ إضافة مصور</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="form-group">
                                <input type="text" name="photographer_name" placeholder="الاسم" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <input type="text" name="photographer_role" placeholder="الدور (مثال: مصور رئيسي)" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <textarea name="photographer_description" placeholder="وصف قصير" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <input type="file" name="photographer_image" accept="image/*" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" name="add_photographer" class="btn btn-primary">➕ إضافة</button>
                    </form>
                </div>
                
                <div class="photos-grid" id="photographers-grid">
                    <?php foreach ($photographers as $photographer): ?>
                    <div class="photographer-card" data-id="<?= $photographer['id'] ?>">
                        <img src="/photographers/<?= htmlspecialchars($photographer['image']) ?>" alt="">
                        <h3 style="color: var(--gold); margin-bottom: 10px;"><?= htmlspecialchars($photographer['name']) ?></h3>
                        <p style="color: #AAA; font-size: 14px; margin-bottom: 10px;"><?= htmlspecialchars($photographer['role']) ?></p>
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد؟')">
                            <input type="hidden" name="photographer_id" value="<?= $photographer['id'] ?>">
                            <button type="submit" name="delete_photographer" class="btn btn-small btn-danger">🗑️ حذف</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button onclick="savePhotographersOrder()" class="btn btn-primary" style="margin-top: 20px;">💾 حفظ الترتيب</button>
            </div>
        </div>
        
        <!-- تبويب التصنيفات -->
        <div id="categories-tab" class="tab-content">
            <div class="section">
                <h2 class="section-title">تصنيفات الفيديو</h2>
                
                <div style="background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                    <form method="POST">
                        <div class="form-grid">
                            <input type="text" name="category_name" placeholder="الاسم بالإنجليزية" class="form-control" required>
                            <input type="text" name="category_name_ar" placeholder="الاسم بالعربية" class="form-control" required>
                            <input type="text" name="category_icon" placeholder="الأيقونة" class="form-control" value="🎬">
                            <input type="color" name="category_color" value="#FFD700" class="form-control">
                            <button type="submit" name="add_category" class="btn btn-primary">➕ إضافة</button>
                        </div>
                    </form>
                </div>
                
                <div class="photos-grid" id="categories-grid">
                    <?php foreach ($categories as $cat): ?>
                    <div class="photographer-card" data-id="<?= $cat['id'] ?>">
                        <div style="font-size: 50px; margin-bottom: 10px;"><?= htmlspecialchars($cat['icon']) ?></div>
                        <h3 style="color: var(--gold);"><?= htmlspecialchars($cat['name_ar']) ?></h3>
                        <p style="color: #AAA; font-size: 14px;"><?= htmlspecialchars($cat['name']) ?></p>
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد؟')" style="margin-top: 10px;">
                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                            <button type="submit" name="delete_category" class="btn btn-small btn-danger">🗑️ حذف</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button onclick="saveCategoriesOrder()" class="btn btn-primary" style="margin-top: 20px;">💾 حفظ الترتيب</button>
            </div>
        </div>
        
       <!-- تبويب الفيديوهات -->
<div id="videos-tab" class="tab-content">
    <div class="section">
        <h2 class="section-title">إدارة الفيديوهات</h2>
        
        <div style="background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
            <form method="POST">
                <div class="form-grid">
                    <input type="url" name="video_url" placeholder="رابط YouTube" class="form-control" required>
                    <input type="text" name="video_title" placeholder="العنوان" class="form-control">
                    <select name="category_id" class="form-control">
                        <option value="">-- التصنيف --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name_ar']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="add_external_video" class="btn btn-primary">➕ إضافة</button>
                </div>
            </form>
        </div>
        
        <h3 style="color: var(--gold); margin-bottom: 20px;">📹 فيديوهات العرسان من الحقول القديمة</h3>
        <?php
        // جلب جميع الفيديوهات من الحقول القديمة
        $groomsWithVideos = $pdo->query("
            SELECT id, groom_name, youtube1, youtube2, youtube3, youtube4, youtube5, youtube6, youtube7
            FROM grooms 
            WHERE is_blocked = 0 AND ready = 1 
            AND (youtube1 != '' OR youtube2 != '' OR youtube3 != '' OR youtube4 != '' OR youtube5 != '' OR youtube6 != '' OR youtube7 != '')
            ORDER BY groom_name
        ")->fetchAll();
        
        if (empty($groomsWithVideos)) {
            echo '<div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 50px; margin-bottom: 15px;">🎬</div>
                    <p>لا توجد فيديوهات في الحقول القديمة</p>
                  </div>';
        } else {
            echo '<table>';
            echo '<thead><tr><th>العريس</th><th>رقم الفيديو</th><th>رابط الفيديو</th><th>التصنيف</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($groomsWithVideos as $groom) {
                for ($i = 1; $i <= 7; $i++) {
                    $youtubeField = "youtube{$i}";
                    if (!empty($groom[$youtubeField])) {
                        // التحقق من وجود الفيديو في groom_videos
                        $stmt = $pdo->prepare("SELECT id, category_id FROM groom_videos WHERE groom_id = ? AND video_number = ?");
                        $stmt->execute([$groom['id'], $i]);
                        $videoData = $stmt->fetch();
                        
                        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', 
                                   $groom[$youtubeField], $matches);
                        $videoId = $matches[1] ?? '';
        ?>
                        <tr>
                            <td><?= htmlspecialchars($groom['groom_name']) ?></td>
                            <td>فيديو <?= $i ?></td>
                            <td>
                                <?php if ($videoId): ?>
                                <a href="https://www.youtube.com/watch?v=<?= $videoId ?>" target="_blank" style="color: var(--gold);">
                                    عرض الفيديو 🔗
                                </a>
                                <?php else: ?>
                                <span style="color: #666;">رابط غير صحيح</span>
                                <?php endif; ?>
                            </td>
                            <td>
    <div style="display: flex; gap: 10px; align-items: center;">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="groom_id" value="<?= $groom['id'] ?>">
            <input type="hidden" name="video_number" value="<?= $i ?>">
            <input type="hidden" name="youtube_url" value="<?= htmlspecialchars($groom[$youtubeField]) ?>">
            <select name="category_id" class="form-control" style="display: inline-block; width: auto;" onchange="this.form.submit()">
                <option value="">-- اختر --</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($videoData && $videoData['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name_ar']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="update_groom_video_category" value="1">
        </form>
        
        <form method="POST" onsubmit="return confirm('هل تريد حذف هذا الفيديو من المعرض؟')" style="display: inline;">
            <input type="hidden" name="groom_id" value="<?= $groom['id'] ?>">
            <input type="hidden" name="video_number" value="<?= $i ?>">
            <button type="submit" name="hide_groom_video" class="btn btn-small btn-danger">🗑️ حذف</button>
        </form>
    </div>
</td>
                        </tr>
        <?php 
                    }
                }
            }
            echo '</tbody></table>';
        }
        ?>
        
        <h3 style="color: var(--gold); margin: 40px 0 20px;">📹 الفيديوهات الخارجية</h3>
        <?php
        $videos = $pdo->query("
            SELECT ev.*, vc.name_ar as category_name 
            FROM external_videos ev 
            LEFT JOIN video_categories vc ON ev.category_id = vc.id 
            ORDER BY ev.display_order, ev.id DESC
        ")->fetchAll();
        
        if (empty($videos)) {
            echo '<div style="text-align: center; padding: 40px; color: #666;">
                    <div style="font-size: 50px; margin-bottom: 15px;">🎥</div>
                    <p>لا توجد فيديوهات خارجية</p>
                  </div>';
        } else {
        ?>
        <ul class="sortable-list" id="videos-list">
            <?php foreach ($videos as $video): 
                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', 
                           $video['youtube_url'], $matches);
                $videoId = $matches[1] ?? '';
            ?>
            <li class="sortable-item" data-id="<?= $video['id'] ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 15px;">
                    <span class="drag-handle">☰</span>
                    <div style="flex: 1;">
                        <strong><?= htmlspecialchars($video['title'] ?: 'فيديو') ?></strong>
                        <span style="color: #AAA; margin-right: 10px;"><?= htmlspecialchars($video['category_name'] ?: 'غير مصنف') ?></span>
                        <?php if ($videoId): ?>
                        <a href="https://www.youtube.com/watch?v=<?= $videoId ?>" target="_blank" style="color: var(--gold); margin-right: 10px;">
                            🔗 عرض
                        </a>
                        <?php endif; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد؟')" style="display: inline;">
                        <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                        <button type="submit" name="delete_video" class="btn btn-small btn-danger">🗑️ حذف</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <button onclick="saveVideosOrder()" class="btn btn-primary" style="margin-top: 20px;">💾 حفظ الترتيب</button>
        <?php } ?>
    </div>
</div>
        
        <!-- تبويب العرسان -->
        <div id="grooms-tab" class="tab-content">
            <div class="section">
                <h2 class="section-title">إدارة العرسان</h2>
                
                <?php
                $grooms = $pdo->query("SELECT g.*, COUNT(gp.id) as photo_count FROM grooms g LEFT JOIN groom_photos gp ON g.id = gp.groom_id AND gp.hidden = 0 WHERE g.is_blocked = 0 AND g.ready = 1 GROUP BY g.id ORDER BY g.display_order DESC, g.id DESC LIMIT 50")->fetchAll();
                ?>
                
                <ul class="sortable-list" id="grooms-list">
                    <?php foreach ($grooms as $groom): ?>
                    <li class="sortable-item" data-id="<?= $groom['id'] ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= htmlspecialchars($groom['groom_name']) ?></strong>
                                <span style="color: #AAA; margin-right: 10px;"><?= $groom['photo_count'] ?> صورة</span>
                            </div>
                            <div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="groom_id" value="<?= $groom['id'] ?>">
                                    <input type="hidden" name="toggle_gallery" value="1">
                                    <input type="checkbox" name="show_in_gallery" value="1" 
                                           <?= $groom['show_in_gallery'] ? 'checked' : '' ?>
                                           onchange="this.form.submit()"
                                           style="width: 20px; height: 20px; cursor: pointer;">
                                </form>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <button onclick="saveGroomsOrder()" class="btn btn-primary" style="margin-top: 20px;">💾 حفظ الترتيب</button>
            </div>
        </div>
        
        <!-- تبويب الإعدادات -->
        <div id="settings-tab" class="tab-content">
            <div class="section">
                <h2 class="section-title">⚙️ إعدادات المعرض</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label style="color: var(--gold); display: block; margin-bottom: 10px;">عدد الصور المعروضة في المعرض</label>
                        <input type="number" name="photos_limit" value="<?= $settings['photos_limit'] ?? 30 ?>" 
                               class="form-control" min="1" max="100" required>
                        <small style="color: #888;">الحد الأدنى: 1، الحد الأقصى: 100</small>
                    </div>
                    
                    <div class="form-group">
                        <label style="color: var(--gold); display: block; margin-bottom: 10px;">Instagram Access Token</label>
<textarea name="instagram_token" class="form-control" rows="3" 
                                  placeholder="ضع توكن Instagram API هنا"><?= htmlspecialchars($settings['instagram_token'] ?? '') ?></textarea>
                        <small style="color: #888;">احصل على التوكن من: <a href="https://developers.facebook.com/docs/instagram-basic-display-api" target="_blank" style="color: var(--gold);">Instagram API</a></small>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="instagram_enabled" value="1" 
                                   <?= ($settings['instagram_enabled'] ?? 0) ? 'checked' : '' ?>
                                   style="width: 20px; height: 20px;">
                            <span>تفعيل عرض Instagram Feed</span>
                        </label>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn btn-primary">💾 حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    </div>
    
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
// حفظ التبويب الحالي
let currentTab = localStorage.getItem('adminCurrentTab') || 'featured';

// وظيفة تبديل التبويبات
function showTab(tabName) {
    // إخفاء جميع التبويبات
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
        content.style.display = 'none';
    });
    
    // إظهار التبويب المطلوب
    const targetTab = document.getElementById(tabName + '-tab');
    if (targetTab) {
        targetTab.classList.add('active');
        targetTab.style.display = 'block';
    }
    
    // تحديث أزرار التبويبات
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // حفظ التبويب الحالي
    localStorage.setItem('adminCurrentTab', tabName);
    currentTab = tabName;
}

// استعادة التبويب المحفوظ عند التحميل
document.addEventListener('DOMContentLoaded', function() {
    // إخفاء جميع التبويبات أولاً
    document.querySelectorAll('.tab-content').forEach((content, index) => {
        content.style.display = 'none';
        content.classList.remove('active');
    });
    
    // إظهار التبويب المحفوظ
    const savedTab = document.getElementById(currentTab + '-tab');
    if (savedTab) {
        savedTab.classList.add('active');
        savedTab.style.display = 'block';
    }
    
    // تحديث زر التبويب النشط
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.textContent.includes(getTabLabel(currentTab))) {
            tab.classList.add('active');
        }
    });
    
    // استعادة الصفحة الصحيحة للـ pagination
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('featured_page')) {
        showTab('featured');
    } else if (urlParams.has('mark_page')) {
        showTab('mark');
    }
    
    // Initialize Sortable بعد التحميل
    initializeSortable();
});

function getTabLabel(tabName) {
    const labels = {
        'featured': 'الصور المميزة',
        'mark': 'تمييز صور',
        'upload': 'رفع صور',
        'photographers': 'المصورين',
        'categories': 'التصنيفات',
        'videos': 'الفيديوهات',
        'grooms': 'العرسان',
        'settings': 'الإعدادات'
    };
    return labels[tabName] || '';
}

// Initialize Sortable
function initializeSortable() {
    // Uploaded Images Grid
    let imagesGrid = document.getElementById('uploaded-images-grid');
    if (imagesGrid && typeof Sortable !== 'undefined') {
        new Sortable(imagesGrid, {
            animation: 200,
            ghostClass: 'sortable-ghost',
            forceFallback: true,
            fallbackTolerance: 3,
            touchStartThreshold: 5
        });
    }
    
    // Photographers Grid
    let photographersGrid = document.getElementById('photographers-grid');
    if (photographersGrid && typeof Sortable !== 'undefined') {
        new Sortable(photographersGrid, {
            animation: 200,
            ghostClass: 'sortable-ghost',
            forceFallback: true
        });
    }
    
    // Categories Grid
    let categoriesGrid = document.getElementById('categories-grid');
    if (categoriesGrid && typeof Sortable !== 'undefined') {
        new Sortable(categoriesGrid, {
            animation: 200,
            ghostClass: 'sortable-ghost',
            forceFallback: true
        });
    }
    
    // Videos List
    let videosList = document.getElementById('videos-list');
    if (videosList && typeof Sortable !== 'undefined') {
        new Sortable(videosList, {
            animation: 200,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            forceFallback: true
        });
    }
    
    // Grooms List
    let groomsList = document.getElementById('grooms-list');
    if (groomsList && typeof Sortable !== 'undefined') {
        new Sortable(groomsList, {
            animation: 200,
            ghostClass: 'sortable-ghost',
            forceFallback: true
        });
    }
}

function saveOrder(gridId, action) {
    let items = document.querySelectorAll(`#${gridId} [data-id]`);
    let orders = {};
    items.forEach((item, index) => {
        orders[item.dataset.id] = index;
    });
    
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `${action}=1&orders=` + encodeURIComponent(JSON.stringify(orders))
    }).then(response => {
        if (response.ok) {
            alert('✅ تم حفظ الترتيب بنجاح');
            location.reload();
        } else {
            alert('❌ حدث خطأ أثناء الحفظ');
        }
    }).catch(error => {
        alert('❌ خطأ: ' + error.message);
    });
}

function saveImagesOrder() { saveOrder('uploaded-images-grid', 'update_images_order'); }
function saveGroomsOrder() { saveOrder('grooms-list', 'update_grooms_order'); }
function saveVideosOrder() { saveOrder('videos-list', 'update_videos_order'); }
function saveCategoriesOrder() { saveOrder('categories-grid', 'update_categories_order'); }
function savePhotographersOrder() { saveOrder('photographers-grid', 'update_photographers_order'); }

// منع تحديد النص أثناء السحب
document.addEventListener('selectstart', function(e) {
    if (e.target.closest('.sortable-item, .photo-card, .photographer-card')) {
        e.preventDefault();
    }
});

// فحص الصور المعطلة
const images = document.querySelectorAll('.photo-card img');
images.forEach(img => {
    img.addEventListener('error', function() {
        console.warn('Failed to load image:', this.src);
        this.style.display = 'none';
        const card = this.closest('.photo-card');
        if (card) {
            card.style.border = '2px dashed rgba(255, 215, 0, 0.3)';
            card.style.background = 'rgba(255, 255, 255, 0.02)';
        }
    });
});
</script>
</body>
</html>