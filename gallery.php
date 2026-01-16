<?php
// gallery_complete_final.php - معرض أعمال نهائي مع جميع التحسينات

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
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات");
}
// API للحصول على جميع الصور
if (isset($_GET['get_all_photos'])) {
    header('Content-Type: application/json');
    
    // جلب الإعدادات
    $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM gallery_settings");
    $settings = [];
    foreach ($settingsQuery as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $showGroomFeatured = (int)($settings['show_groom_featured_in_gallery'] ?? 0);
    
    if ($showGroomFeatured == 1) {
        // صور المعرض أولاً ثم صور العرسان
        $allFeaturedStmt = $pdo->query("
            (SELECT gp.*, g.groom_name, g.id as groom_id, 1 as priority
            FROM groom_photos gp 
            JOIN grooms g ON gp.groom_id = g.id 
            WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1)
            
            UNION ALL
            
            (SELECT gp.*, g.groom_name, g.id as groom_id, 2 as priority
            FROM groom_photos gp 
            JOIN grooms g ON gp.groom_id = g.id 
            WHERE gp.hidden = 0 AND gp.is_featured = 1 AND gp.featured_for_gallery = 0)
            
            ORDER BY priority ASC, likes DESC
        ");
    } else {
        // صور المعرض فقط
        $allFeaturedStmt = $pdo->query("
            SELECT gp.*, g.groom_name, g.id as groom_id 
            FROM groom_photos gp 
            JOIN grooms g ON gp.groom_id = g.id 
            WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1 
            ORDER BY gp.likes DESC
        ");
    }
    
    $photos = [];
    foreach ($allFeaturedStmt as $photo) {
        $imagePath = getValidImagePath($photo['groom_id'], $photo['filename']);
        if ($imagePath) {
            $photos[] = [
                'thumb' => $imagePath,
                'original' => $imagePath,
                'title' => $photo['groom_name'],
                'likes' => (int)$photo['likes']
            ];
        }
    }
    
    echo json_encode($photos);
    exit;
}

// API محسّن للحصول على الصور مع Pagination
if (isset($_GET['get_photos_paginated'])) {
    header('Content-Type: application/json');
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    if ($limit < 1) $limit = 20;
    if ($limit > 50) $limit = 50;
    if ($offset < 0) $offset = 0;
    
    $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM gallery_settings");
    $settings = [];
    foreach ($settingsQuery as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $showGroomFeatured = (int)($settings['show_groom_featured_in_gallery'] ?? 0);
    
    if ($showGroomFeatured == 1) {
        $query = "
            (SELECT gp.*, g.groom_name, g.id as groom_id, 1 as priority
            FROM groom_photos gp 
            JOIN grooms g ON gp.groom_id = g.id 
            WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1 
            AND g.is_blocked = 0 AND g.ready = 1)
            UNION ALL
            (SELECT gp.*, g.groom_name, g.id as groom_id, 2 as priority
            FROM groom_photos gp 
            JOIN grooms g ON gp.groom_id = g.id 
            WHERE gp.hidden = 0 AND gp.is_featured = 1 AND gp.featured_for_gallery = 0
            AND g.is_blocked = 0 AND g.ready = 1)
ORDER BY 
    priority ASC,
    CASE WHEN priority = 1 THEN display_order_gallery ELSE 0 END DESC,
    likes DESC
    LIMIT {$limit} OFFSET {$offset}
        ";
    } else {
        $query = "
            SELECT gp.*, g.groom_name, g.id as groom_id
            FROM groom_photos gp 
            JOIN grooms g ON gp.groom_id = g.id 
            WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1 
            AND g.is_blocked = 0 AND g.ready = 1
            ORDER BY gp.likes DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
    }
    
    $stmt = $pdo->query($query);
    $photos = [];
    
    foreach ($stmt as $photo) {
        $thumbPath = getImagePath($photo['groom_id'], $photo['filename'], 'thumb');
        $originalPath = getImagePath($photo['groom_id'], $photo['filename'], 'original');
        
        if ($thumbPath && $originalPath) {
            $photos[] = [
                'thumb' => $thumbPath,
                'original' => $originalPath,
                'title' => $photo['groom_name'],
                'likes' => (int)$photo['likes'],
                'groom_id' => $photo['groom_id']
            ];
        }
    }
    
    $totalQuery = "SELECT COUNT(*) as total FROM (
        SELECT gp.id FROM groom_photos gp 
        JOIN grooms g ON gp.groom_id = g.id 
        WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1 
        AND g.is_blocked = 0 AND g.ready = 1
    ) as subquery";
    
    $totalResult = $pdo->query($totalQuery)->fetch();
    $total = (int)$totalResult['total'];
    
    echo json_encode([
        'photos' => $photos,
        'page' => $page,
        'total' => $total,
        'hasMore' => ($offset + $limit) < $total
    ]);
    exit;
}
// جلب الإعدادات
$settings = [];
$settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM gallery_settings");
foreach ($settingsQuery as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$photosLimit = (int)($settings['photos_limit'] ?? 30);
// التحقق من القيمة
if ($photosLimit < 1) $photosLimit = 30;
if ($photosLimit > 100) $photosLimit = 100;
$instagramToken = $settings['instagram_token'] ?? '';
$instagramEnabled = (int)($settings['instagram_enabled'] ?? 0);

// دالة للحصول على مسار الصورة
function getImagePath($groomId, $filename, $type = 'thumb') {
    if (empty($groomId) || empty($filename)) return false;
    
    $baseDir = $_SERVER['DOCUMENT_ROOT'];
    
    if ($type === 'original') {
        $paths = [
            "/grooms/{$groomId}/originals/{$filename}",
            "/grooms/{$groomId}/watermarked/{$filename}",
            "/grooms/{$groomId}/images/{$filename}",
            "/grooms/{$groomId}/{$filename}"
        ];
    } else {
        $paths = [
            "/grooms/{$groomId}/modal_thumb/{$filename}",
            "/grooms/{$groomId}/watermarked/{$filename}",
            "/grooms/{$groomId}/images/{$filename}",
            "/grooms/{$groomId}/{$filename}"
        ];
    }
    
    foreach ($paths as $path) {
        if (@file_exists($baseDir . $path) && @is_file($baseDir . $path)) {
            return $path;
        }
    }
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
// دالة للحصول على مسار صورة صحيح (نسخة من لوحة التحكم)
function getValidImagePath($groomId, $filename) {
    if (empty($groomId) || empty($filename)) return false;
    
    $baseDir = $_SERVER['DOCUMENT_ROOT'];
    
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
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo !== false) {
                return $path;
            }
        }
    }
    
    return false;
}
// جلب التصنيفات
$categories = $pdo->query("SELECT * FROM video_categories ORDER BY display_order, id")->fetchAll();

// جلب العرسان المعروضين
$grooms = $pdo->query("
    SELECT g.*, COUNT(DISTINCT gp.id) as photo_count
    FROM grooms g
    LEFT JOIN groom_photos gp ON g.id = gp.groom_id AND gp.hidden = 0
    WHERE g.is_blocked = 0 AND g.is_active = 1 AND g.ready = 1 AND g.show_in_gallery = 1
    GROUP BY g.id
    ORDER BY g.display_order DESC, g.total_likes DESC
    LIMIT 20
")->fetchAll();
// جلب الصور للعرض
$allPhotos = [];

// تأكد من أن $photosLimit رقم صحيح
$photosLimit = (int)($settings['photos_limit'] ?? 30);

if ($photosLimit < 1) $photosLimit = 30;
if ($photosLimit > 100) $photosLimit = 100;

// الصور المميزة من العرسان - استخدام query بدلاً من prepare
// جلب الصور حسب الإعدادات
$showGroomFeatured = (int)($settings['show_groom_featured_in_gallery'] ?? 0);

if ($showGroomFeatured == 1) {
    // إذا مفعّل: نعرض صور المعرض أولاً ثم صور العرسان
    $photosQuerySQL = "
        (SELECT gp.*, g.groom_name, 1 as priority
        FROM groom_photos gp
        JOIN grooms g ON gp.groom_id = g.id
        WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1
        AND g.is_blocked = 0 AND g.ready = 1)
        
        UNION ALL
        
        (SELECT gp.*, g.groom_name, 2 as priority
        FROM groom_photos gp
        JOIN grooms g ON gp.groom_id = g.id
        WHERE gp.hidden = 0 AND gp.is_featured = 1 AND gp.featured_for_gallery = 0
        AND g.is_blocked = 0 AND g.ready = 1)
       ORDER BY 
    priority ASC,
    CASE WHEN priority = 1 THEN display_order_gallery ELSE 0 END DESC,
    likes DESC
LIMIT {$photosLimit}

    ";
} else {
    // إذا معطّل: نعرض صور المعرض فقط
    $photosQuerySQL = "
        SELECT gp.*, g.groom_name
        FROM groom_photos gp
        JOIN grooms g ON gp.groom_id = g.id
        WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1
        AND g.is_blocked = 0 AND g.ready = 1
        ORDER BY gp.likes DESC
        LIMIT {$photosLimit}
    ";
}

try {
    $photosQuery = $pdo->query($photosQuerySQL);
    
    foreach ($photosQuery as $photo) {
        $thumbPath = getImagePath($photo['groom_id'], $photo['filename'], 'thumb');
        $originalPath = getImagePath($photo['groom_id'], $photo['filename'], 'original');
        if ($thumbPath && $originalPath) {
            $allPhotos[] = [
                'thumb' => $thumbPath,
                'original' => $originalPath,
                'title' => $photo['groom_name'],
                'likes' => $photo['likes'],
                'groom_id' => $photo['groom_id']
            ];
        }
    }
} catch (PDOException $e) {
    // في حالة وجود خطأ، نستخدم قيمة افتراضية
    error_log("Error fetching photos: " . $e->getMessage());
}

// الصور المرفوعة يدوياً
$uploadedImagesQuerySQL = "
    SELECT * FROM gallery_uploaded_images 
    WHERE is_featured = 1 
    ORDER BY display_order DESC 
    LIMIT {$photosLimit}
";

try {
    $uploadedImagesQuery = $pdo->query($uploadedImagesQuerySQL);
    
    foreach ($uploadedImagesQuery as $image) {
        $imagePath = '/gallery_uploads/' . $image['filename'];
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $imagePath)) {
            $allPhotos[] = [
                'thumb' => $imagePath,
                'original' => $imagePath,
                'title' => $image['title'] ?: 'جذلة',
                'likes' => $image['likes'],
                'groom_id' => null
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching uploaded images: " . $e->getMessage());
}
// تجميع الفيديوهات
// تجميع الفيديوهات بترتيب موحد
$videosByCategory = [];
foreach ($categories as $cat) {
    $videosByCategory[$cat['slug']] = [
        'name' => $cat['name_ar'],
        'icon' => $cat['icon'] ?? '🎬',
        'videos' => []
    ];
}

// جمع جميع الفيديوهات في مصفوفة واحدة للترتيب
$allVideosForDisplay = [];

// 1. الفيديوهات من جدول groom_videos (الجديدة + القديمة المُدارة)
$groomVideosQuery = $pdo->query("
    SELECT gv.*, g.groom_name, COALESCE(vc.slug, 'classic') as slug
    FROM groom_videos gv
    JOIN grooms g ON gv.groom_id = g.id
    LEFT JOIN video_categories vc ON gv.category_id = vc.id
    WHERE gv.is_active = 1 AND g.is_blocked = 0 AND g.ready = 1
    ORDER BY gv.display_order, gv.id
");

foreach ($groomVideosQuery as $video) {
    preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', 
               $video['youtube_url'], $matches);
    if (isset($matches[1])) {
        $allVideosForDisplay[] = [
            'id' => $matches[1],
            'url' => $video['youtube_url'],
            'category' => $video['slug'],
            'title' => $video['title'] ?? $video['groom_name'],
            'groom_id' => $video['groom_id'],
            'order' => $video['display_order']
        ];
    }
}

// 2. فيديوهات من الحقول القديمة (غير المُدارة في groom_videos)
foreach ($grooms as $groom) {
    for ($i = 1; $i <= 7; $i++) {
        $youtubeField = "youtube{$i}";
        if (!empty($groom[$youtubeField])) {
            // تحقق إذا كان الفيديو موجود في groom_videos
            $stmt = $pdo->prepare("SELECT id, display_order FROM groom_videos WHERE groom_id = ? AND youtube_url = ?");
            $stmt->execute([$groom['id'], $groom[$youtubeField]]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // تخطي - موجود في groom_videos بالفعل
                continue;
            }
            
            // فيديو قديم غير مُدار
            preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', 
                       $groom[$youtubeField], $matches);
            if (isset($matches[1])) {
                $allVideosForDisplay[] = [
                    'id' => $matches[1],
                    'url' => $groom[$youtubeField],
                    'category' => 'classic',
                    'title' => $groom['groom_name'],
                    'groom_id' => $groom['id'],
                    'order' => 9999 // آخر ترتيب للفيديوهات القديمة غير المُدارة
                ];
            }
        }
    }
}

// 3. الفيديوهات الخارجية
$externalVideosQuery = $pdo->query("
    SELECT ev.*, COALESCE(vc.slug, 'classic') as slug
    FROM external_videos ev
    LEFT JOIN video_categories vc ON ev.category_id = vc.id
    WHERE ev.is_active = 1
    ORDER BY ev.display_order, ev.id
");

foreach ($externalVideosQuery as $video) {
    preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/i', 
               $video['youtube_url'], $matches);
    if (isset($matches[1])) {
        $allVideosForDisplay[] = [
            'id' => $matches[1],
            'url' => $video['youtube_url'],
            'category' => $video['slug'],
            'title' => $video['title'] ?? 'فيديو',
            'groom_id' => null,
            'order' => $video['display_order']
        ];
    }
}

// ترتيب جميع الفيديوهات حسب display_order
usort($allVideosForDisplay, function($a, $b) {
    return $a['order'] - $b['order'];
});

// توزيع الفيديوهات على التصنيفات
foreach ($allVideosForDisplay as $video) {
    $category = $video['category'];
    if (isset($videosByCategory[$category])) {
        $videosByCategory[$category]['videos'][] = [
            'id' => $video['id'],
            'url' => $video['url'],
            'groom_id' => $video['groom_id'],
            'title' => $video['title']
        ];
    }
}


// فيديوهات العرسان مع التصنيفات

// جلب المصورين
$photographers = $pdo->query("
    SELECT * FROM photographers 
    WHERE is_active = 1 
    ORDER BY display_order, id
")->fetchAll();

// جلب Instagram Posts إذا كان مفعل
$instagramPosts = [];
if ($instagramEnabled && !empty($instagramToken)) {
    try {
        $instagramUrl = "https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,permalink,thumbnail_url&access_token={$instagramToken}&limit=6";
        $response = @file_get_contents($instagramUrl);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['data'])) {
                $instagramPosts = $data['data'];
            }
        }
    } catch (Exception $e) {
        // تجاهل الأخطاء
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>معرض أعمال جذلة | تصوير احترافي</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="استعرض أحدث أعمالنا في تصوير الأعراس والمناسبات">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --gold: #FFD700;
            --gold-light: #FFED4E;
            --gold-dark: #F59E0B;
            --black: #000000;
            --dark: #0A0A0A;
            --gray: #1A1A1A;
        }
        
/* تطبيق الخط على كامل الموقع */
body {
    font-family: 'The Year of The Camel', -apple-system, 'Segoe UI', 'Tajawal', sans-serif;
    background: var(--black);
    color: white;
    overflow-x: hidden;
}

/* تطبيق الخط على جميع العناصر */
h1, h2, h3, h4, h5, h6,
.section-title,
.hero h1,
.banner-main-title h2,
.gallery-info h3,
.photographer-name,
.logo-text,
.nav-links a,
.mobile-menu-links a,
.hero-btn,
.cta-button,
.contact-card a,
button,
input,
textarea,
select,
p, span, div, a {
    font-family: 'The Year of The Camel', -apple-system, 'Segoe UI', 'Tajawal', sans-serif !important;
}
        
        
/* استيراد خط The Year of Camel من المصدر مباشرة */
@font-face {
    font-family: 'The Year of The Camel';
    font-style: normal;
    font-weight: 100 200 300;
    src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtTGlnaHQub3RmMTcwNDYyMzYzODQwNQ==.otf');
    font-display: swap;
}

@font-face {
    font-family: 'The Year of The Camel';
    font-style: normal;
    font-weight: 400 500 600;
    src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtUmVndWxhci5vdGYxNzA0NjIzMjM0MzE5.otf');
    font-display: swap;
}

@font-face {
    font-family: 'The Year of The Camel';
    font-style: normal;
    font-weight: 700 800 900;
    src: url('https://twebs-uploads.s3.eu-west-1.amazonaws.com/1d4d9ab7-7e77-4872-8a15-76d095ecf7d2/custom_uploads/VGhlWWVhcm9mVGhlQ2FtZWwtQm9sZC5vdGYxNzA0NjIzNjY3ODA1.otf');
    font-display: swap;
}
        
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(20px);
            z-index: 1000;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            transition: all 0.3s;
        }
        
        header.scrolled { padding: 10px 0; box-shadow: 0 5px 30px rgba(255, 215, 0, 0.1); }
        
        nav {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo img { height: 50px; transition: height 0.3s; }
        header.scrolled .logo img { height: 40px; }
        
        .logo-text {
            font-size: 32px;
            font-weight: bold;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gold);
            transition: width 0.3s;
        }
        
        .nav-links a:hover::after,
        .nav-links a.active::after { width: 100%; }
        
        .nav-links a:hover, .nav-links a.active { color: var(--gold); }
        
        .cta-button {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--black) !important;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }
        
        .cta-button:hover { transform: translateY(-3px); }
        .cta-button::after { display: none; }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
        }
        
        .mobile-menu {
            display: none;
            position: fixed;
            top: 0;
            left: -100%;
            width: 85%;
            max-width: 350px;
            height: 100vh;
            background: linear-gradient(135deg, var(--dark), var(--gray));
            z-index: 2000;
            transition: left 0.4s;
            padding: 30px 20px;
        }
        
        .mobile-menu.active { left: 0; }
        
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1999;
            backdrop-filter: blur(5px);
        }
        
        .mobile-menu-overlay.active { display: block; }
        
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 215, 0, 0.3);
        }
        
        .mobile-menu-close {
            background: rgba(255, 215, 0, 0.1);
            border: 2px solid var(--gold);
            color: var(--gold);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
        }
        
        .mobile-menu-links {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .mobile-menu-links a {
            color: white;
            text-decoration: none;
            font-size: 18px;
            padding: 15px 20px;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .mobile-menu-links a:hover {
            background: rgba(255, 215, 0, 0.1);
            color: var(--gold);
        }
        
        .hero {
            margin-top: 80px;
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 215, 0, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(255, 215, 0, 0.05) 0%, transparent 50%);
            padding: 40px 20px;
            position: relative;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-20px, 20px); }
        }
        
        .hero-content {
            text-align: center;
            max-width: 1000px;
            position: relative;
            z-index: 1;
        }
        
        .hero h1 {
            font-size: clamp(3.5rem, 7vw, 5rem);
            margin-bottom: 25px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 900;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: clamp(1.1rem, 3vw, 1.5rem);
            color: #BBB;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .hero-btn {
    padding: 8px 22px;
    border-radius: 50px;
            font-weight: bold;
            font-size: 28px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .hero-btn-primary {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--black);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3);
        }
        
        .hero-btn-primary:hover { transform: translateY(-5px); }
        
        .hero-btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--gold);
            color: var(--gold);
        }
        
        section {
            padding: 80px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title {
            font-size: clamp(2rem, 5vw, 3rem);
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }
        
        .section-subtitle {
            color: #AAA;
            font-size: 1.1rem;
        }
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .photo-card {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            background: var(--gray);
            aspect-ratio: 1;
            cursor: pointer;
            transition: all 0.4s;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .photo-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 50px rgba(255, 215, 0, 0.3);
        }
        
        .photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .photo-card:hover img { transform: scale(1.1); }
        
        .photo-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 60%);
            display: flex;
            align-items: flex-end;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .photo-card:hover .photo-overlay { opacity: 1; }
        
        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        
        .lightbox.active { display: flex; }
        
        .lightbox-content {
            max-width: 90%;
            max-height: 90vh;
            position: relative;
        }
        
        .lightbox-content img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 10px;
        }
        
        .lightbox-close {
            position: absolute;
            top: -50px;
            right: 0;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid var(--gold);
            color: var(--gold);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
        }
        
        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid var(--gold);
            color: var(--gold);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
        }
        
        .lightbox-prev { left: -70px; }
        .lightbox-next { right: -70px; }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 35px;
        }
        
        .gallery-card {
            background: var(--gray);
            border-radius: 25px;
            overflow: hidden;
            transition: all 0.4s;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .gallery-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 30px 60px rgba(255, 215, 0, 0.25);
        }
        
        /* Gallery Card - عريضة بنسبة 5:2 */
.gallery-card {
    background: var(--gray);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    position: relative;
    aspect-ratio: 5 / 2; /* النسبة العريضة */
}

.gallery-card:hover {
    transform: translateY(-15px);
    box-shadow: 0 30px 60px rgba(255, 215, 0, 0.25);
}

/* الصورة تأخذ الحيز الكامل */
.gallery-banner {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background: var(--gray);
    overflow: hidden;
}

.gallery-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.gallery-card:hover .gallery-banner img { 
    transform: scale(1.08);
}

/* المعلومات فوق الصورة مع تدرج */
.gallery-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 30px 25px;
    background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.95) 0%,
        rgba(0, 0, 0, 0.85) 40%,
        rgba(0, 0, 0, 0.6) 70%,
        transparent 100%
    );
    z-index: 2;
    transition: all 0.4s ease;
}

.gallery-card:hover .gallery-info {
    background: linear-gradient(
        to top,
        rgba(0, 0, 0, 0.98) 0%,
        rgba(0, 0, 0, 0.9) 50%,
        rgba(0, 0, 0, 0.7) 80%,
        transparent 100%
    );
}

/* اسم العريس */
.gallery-info h3 {
    color: var(--gold);
    font-size: 1.8rem;
    margin-bottom: 15px;
    font-weight: bold;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
}

/* الإحصائيات */
.gallery-info > div {
    display: flex;
    justify-content: flex-start;
    gap: 40px;
    text-align: right;
}

.gallery-info > div > div {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.gallery-info > div > div > div:first-child {
    color: var(--gold);
    font-size: 1.5rem;
    font-weight: bold;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
}

.gallery-info > div > div > div:last-child {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.95rem;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
}

/* Responsive */
@media (max-width: 1200px) {
    .gallery-card {
        aspect-ratio: 3 / 1; /* نسبة أقل عرضاً للشاشات المتوسطة */
    }
    
    .gallery-info h3 {
        font-size: 1.5rem;
    }
    
    .gallery-info > div {
        gap: 30px;
    }
}

@media (max-width: 768px) {
    .gallery-card {
        aspect-ratio: 16 / 9; /* نسبة أقل للموبايل */
    }
    
    .gallery-info {
        padding: 20px 15px;
    }
    
    .gallery-info h3 {
        font-size: 1.3rem;
        margin-bottom: 12px;
    }
    
    .gallery-info > div {
        gap: 20px;
    }
    
    .gallery-info > div > div > div:first-child {
        font-size: 1.2rem;
    }
    
    .gallery-info > div > div > div:last-child {
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .gallery-card {
        aspect-ratio: 4 / 3; /* نسبة مربعة تقريباً للموبايل الصغير */
    }
    
    .gallery-info {
        padding: 15px 12px;
    }
    
    .gallery-info h3 {
        font-size: 1.1rem;
    }
    
    .gallery-info > div {
        gap: 15px;
    }
}
        
        
      /* ============================================
   VIDEO SECTION - تصميم جديد أنيق
   ============================================ */

.video-categories {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 50px;
    flex-wrap: wrap;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(15px);
    padding: 8px;
    border-radius: 50px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
    border: 1px solid rgba(255, 215, 0, 0.15);
}

.category-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-radius: 50px;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 600;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
}

.category-tab:hover {
    color: white;
    background: rgba(255, 255, 255, 0.05);
}

.category-tab.active {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--black);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
}

.category-tab .count-badge {
    background: rgba(0, 0, 0, 0.3);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
}

.category-tab.active .count-badge {
    background: rgba(0, 0, 0, 0.2);
}

.video-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    max-width: 1400px;
    margin: 0 auto;
    position: relative;
}

.video-card {
    background: var(--gray);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 215, 0, 0.1);
    position: relative;
}

.video-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(255, 215, 0, 0.25);
    border-color: rgba(255, 215, 0, 0.3);
}

.video-wrapper {
    position: relative;
    padding-bottom: 56.25%;
    background: #000;
    overflow: hidden;
}

.video-wrapper iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: none;
}

.video-info {
    padding: 20px;
    text-align: center;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent);
}

.video-category-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    background: rgba(255, 215, 0, 0.15);
    border: 1px solid rgba(255, 215, 0, 0.3);
    border-radius: 25px;
    color: var(--gold);
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s;
}

.video-card:hover .video-category-badge {
    background: rgba(255, 215, 0, 0.25);
    border-color: rgba(255, 215, 0, 0.5);
}

/* زر "مشاهدة المزيد" */
.show-more-videos-btn {
    display: block;
    margin: 50px auto 0;
    padding: 16px 40px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid var(--gold);
    border-radius: 50px;
    color: var(--gold);
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
}

.show-more-videos-btn:hover {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--black);
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4);
}

.show-more-videos-btn.hidden {
    display: none;
}

/* Responsive */
@media (max-width: 1024px) {
    .video-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
}

/* Mobile Responsive - تصميم أفضل للموبايل */
@media (max-width: 768px) {
    .video-categories {
        gap: 8px;
        padding: 8px 12px;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        flex-wrap: nowrap;
        justify-content: flex-start;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE/Edge */
        border-radius: 25px;
    }
    
    .video-categories::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }
    
    .category-tab {
        padding: 10px 16px;
        font-size: 13px;
        white-space: nowrap;
        flex-shrink: 0;
        min-width: fit-content;
    }
    
    /* عرض النص + الأيقونة معاً */
    .category-tab span:not(.count-badge) {
        display: inline; /* إظهار النص */
    }
    
    /* تصغير حجم الأيقونة */
    .category-tab > span:first-child {
        font-size: 14px;
    }
    
    /* العداد أصغر */
    .category-tab .count-badge {
        font-size: 11px;
        padding: 1px 6px;
        margin-left: -2px;
    }
    
    .video-grid {
        gap: 20px;
    }
}

/* Extra Small Screens */
@media (max-width: 480px) {
    .video-categories {
        padding: 6px 10px;
        gap: 6px;
    }
    
    .category-tab {
        padding: 8px 14px;
        font-size: 12px;
    }
    
    .category-tab > span:first-child {
        font-size: 13px;
    }
}


/* Scroll Indicator - مؤشر السكرول */
@media (max-width: 768px) {
    .video-categories::after {
        content: '→';
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gold);
        font-size: 20px;
        opacity: 0.5;
        pointer-events: none;
        animation: slideHint 2s ease-in-out infinite;
    }
    
    @keyframes slideHint {
        0%, 100% { 
            opacity: 0.5;
            transform: translateY(-50%) translateX(0);
        }
        50% { 
            opacity: 0.8;
            transform: translateY(-50%) translateX(-5px);
        }
    }
}

        .photographers-section {
            background: var(--dark);
            padding: 80px 20px;
        }
        
       .photographers-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
}

.photographer-card {
    text-align: center;
    padding: 20px;
    transition: all 0.3s;
}

.photographer-card:hover {
    transform: translateY(-10px);
}

.photographer-card img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 15px;
    border: 3px solid var(--gold);
    transition: all 0.3s;
}

.photographer-card:hover img {
    border-width: 4px;
    box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4);
}

@media (max-width: 768px) {
    .photographers-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .photographer-card img {
        width: 100px;
        height: 100px;
    }
}

@media (max-width: 480px) {
    .photographers-grid {
    }
}
        .photographer-name {
            font-size: 1.3rem;
            color: var(--gold);
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .photographer-role {
            color: #AAA;
            margin-bottom: 15px;
        }
        
       
       
       .contact-section {
    padding: 60px 20px;
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    max-width: 900px;
    margin: 0 auto;
}

@media (max-width: 768px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }
}



        .contact-card {
            background: var(--dark);
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            border: 2px solid rgba(255, 215, 0, 0.2);
            transition: all 0.3s;
        }
        
        .contact-card:hover {
            transform: translateY(-10px);
            border-color: var(--gold);
        }
        
        .contact-icon { font-size: 50px; margin-bottom: 20px; }
        
        .contact-card h3 {
            color: var(--gold);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .contact-card a {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--black);
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
        }
        
        .instagram-section {
            background: var(--dark);
            padding: 80px 20px;
        }
        
        .instagram-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .instagram-post {
            position: relative;
            aspect-ratio: 1;
            border-radius: 15px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .instagram-post:hover {
            transform: scale(1.05);
        }
        
        .instagram-post img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        footer {
            background: var(--dark);
            padding: 40px 20px;
            text-align: center;
            border-top: 1px solid rgba(255, 215, 0, 0.2);
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }
        
        .social-links a {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            text-decoration: none;
            font-size: 24px;
            transition: all 0.3s;
        }
        
        .social-links a:hover {
            background: var(--gold);
            color: var(--black);
            transform: translateY(-5px);
        }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .mobile-menu-btn { display: block; }
            .mobile-menu { display: block; }
            .hero { min-height: 70vh; }
            .photos-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .gallery-grid, .video-grid { grid-template-columns: 1fr; }
            .lightbox-nav { width: 40px; height: 40px; }
            .lightbox-prev { left: 10px; }
            .lightbox-next { right: 10px; }
        }
        
        .hidden { display: none !important; }
        
        .whatsapp-float {
    position: fixed;
    width: 60px;
    height: 60px;
    bottom: 30px;
    right: 30px;
    background: #25D366;
    color: #FFF;
    border-radius: 50%;
    text-align: center;
    font-size: 35px;
    box-shadow: 2px 2px 15px rgba(37, 211, 102, 0.4);
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    text-decoration: none;
}

.whatsapp-float:hover {
    background: #128C7E;
    transform: scale(1.1);
    box-shadow: 2px 2px 25px rgba(37, 211, 102, 0.6);
}

@media (max-width: 768px) {
    .whatsapp-float {
        width: 50px;
        height: 50px;
        font-size: 28px;
        bottom: 20px;
        right: 20px;
    }
}

.photographer-card p {
    display: none;
}


/* Full Gallery Modal - تصميم محسّن */
.full-gallery-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.98);
    z-index: 10000;
    overflow: hidden;
    animation: fadeIn 0.3s ease;
}

.full-gallery-modal.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Header */
.full-gallery-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.7) 50%, transparent 100%);
    backdrop-filter: blur(10px);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 10001;
}

.full-gallery-close {
    background: rgba(255,255,255,0.1);
    color: var(--gold);
    border: 2px solid var(--gold);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 28px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.full-gallery-close:hover {
    background: var(--gold);
    color: var(--black);
    transform: rotate(90deg) scale(1.1);
}

.full-gallery-counter {
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
    padding: 12px 24px;
    border-radius: 30px;
    color: var(--gold);
    font-weight: 600;
    font-size: 16px;
    border: 1px solid rgba(255,215,0,0.3);
}

/* View Toggle */
.full-gallery-toggle {
    position: fixed;
    top: 90px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10001;
    display: flex;
    gap: 10px;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(10px);
    padding: 8px;
    border-radius: 50px;
    border: 1px solid rgba(255,215,0,0.3);
}

.gallery-view-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: transparent;
    border: none;
    color: white;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 15px;
    font-weight: 500;
}

.gallery-view-btn:hover {
    background: rgba(255,255,255,0.1);
}

.gallery-view-btn.active {
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    color: var(--black);
    font-weight: 600;
}

/* Grid View */
.full-gallery-content {
    position: absolute;
    top: 160px;
    left: 0;
    right: 0;
    bottom: 0;
    overflow-y: auto;
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.full-gallery-item {
    position: relative;
    width: 100%;
    padding-bottom: 100%; /* Aspect ratio 1:1 */
    overflow: hidden;
    border-radius: 16px;
    cursor: pointer;
    background: #1a1a1a;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.full-gallery-item > img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.full-gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.full-gallery-item:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(255,215,0,0.3);
}



.full-gallery-item:hover img {
    transform: scale(1.1);
}

.full-gallery-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 100%);
    padding: 20px;
    opacity: 0;
    transition: opacity 0.3s;
}

.full-gallery-item:hover .full-gallery-overlay {
    opacity: 1;
}

/* Single View - Instagram Style */
.full-gallery-content.single-view {
    display: flex;
    flex-direction: column;
    gap: 30px;
    padding: 20px 10px;
}

.full-gallery-content.single-view .full-gallery-item {
    aspect-ratio: auto;
    max-width: 800px;
    margin: 0 auto;
    width: 100%;
    border-radius: 12px;
}

.full-gallery-content.single-view .full-gallery-item img {
    height: auto;
    max-height: 85vh;
    width: 100%;
    object-fit: contain;
    background: #000;
}

.full-gallery-content.single-view .full-gallery-overlay {
    opacity: 1;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
}

/* Viewer Modal - مثل صفحة العريس تماماً */
.full-gallery-viewer {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.98);
    z-index: 10002;
    display: none;
}

.full-gallery-viewer.active {
    display: block;
}

.viewer-close {
    position: fixed;
    top: 20px;
    right: 20px;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    color: white;
    border: none;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    z-index: 10003;
    transition: all 0.3s;
}

.viewer-close:hover {
    background: rgba(255,255,255,0.2);
}

.viewer-counter {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
    color: white;
    padding: 12px 24px;
    border-radius: 30px;
    z-index: 10003;
    font-weight: 600;
}

.viewer-scroll {
    height: 100%;
    width: 100%;
    overflow-y: auto;
    scroll-snap-type: y mandatory;
    scroll-behavior: smooth;
}

.viewer-item {
    height: 100vh;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    scroll-snap-align: start;
    padding: 80px 20px 80px;
}

.viewer-item img {
    max-width: 90%;
    max-height: 80vh;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 12px;
}

.viewer-actions {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 30px;
    color: white;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
    padding: 15px 30px;
    border-radius: 30px;
    font-size: 16px;
}

/* Smooth Scrollbar */
.full-gallery-content::-webkit-scrollbar,
.viewer-scroll::-webkit-scrollbar {
    width: 8px;
}

.full-gallery-content::-webkit-scrollbar-track,
.viewer-scroll::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}

.full-gallery-content::-webkit-scrollbar-thumb,
.viewer-scroll::-webkit-scrollbar-thumb {
    background: rgba(255,215,0,0.5);
    border-radius: 10px;
}

.full-gallery-content::-webkit-scrollbar-thumb:hover,
.viewer-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--gold);
}

/* Loading State */
.full-gallery-item img[src=""],
.viewer-item img[src=""] {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .full-gallery-content {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 10px;
        top: 150px;
    }
    
    .full-gallery-toggle {
        top: 80px;
        padding: 5px;
    }
    
    .gallery-view-btn {
        padding: 10px 16px;
        font-size: 14px;
    }
    
    .gallery-view-btn span {
        display: none;
    }
    
    .viewer-item {
        padding: 70px 10px 70px;
    }
    
    .viewer-item img {
        max-height: 70vh;
    }
    
    .full-gallery-close {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }
}

/* View Toggle */
.full-gallery-toggle {
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10001;
    display: flex;
    gap: 10px;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(10px);
    padding: 5px;
    border-radius: 30px;
}

.gallery-view-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: transparent;
    border: none;
    color: white;
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.gallery-view-btn:hover {
    background: rgba(255,255,255,0.1);
}

.gallery-view-btn.active {
    background: linear-gradient(135deg, #ffc107, #ff8c00);
    color: white;
}

/* Gallery Content - Grid View */
.full-gallery-content {
    position: absolute;
    top: 140px;
    left: 0;
    right: 0;
    bottom: 0;
    overflow-y: auto;
    padding: 20px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    max-width: 1200px;
    margin: 0 auto;
}

.full-gallery-item {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: 10px;
    cursor: pointer;
    background: #1a1a1a;
    transition: transform 0.3s;
}

.full-gallery-item:hover {
    transform: scale(1.05);
    z-index: 10;
}

.full-gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.full-gallery-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    padding: 15px;
    opacity: 0;
    transition: opacity 0.3s;
}

.full-gallery-item:hover .full-gallery-overlay {
    opacity: 1;
}

/* Single View Mode */
.full-gallery-content.single-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
    grid-template-columns: none;
}

.full-gallery-content.single-view .full-gallery-item {
    aspect-ratio: auto;
    max-width: 800px;
    margin: 0 auto;
    width: 100%;
}

.full-gallery-content.single-view .full-gallery-item img {
    height: auto;
    max-height: 80vh;
    object-fit: contain;
}

.full-gallery-content.single-view .full-gallery-overlay {
    opacity: 1;
}

/* Image Viewer */
.full-gallery-viewer {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.98);
    z-index: 10002;
}

.viewer-close {
    position: fixed;
    top: 20px;
    right: 20px;
    background: rgba(255,255,255,0.1);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-size: 20px;
    cursor: pointer;
    z-index: 10003;
}

.viewer-counter {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    z-index: 10003;
}

.viewer-scroll {
    height: 100%;
    overflow-y: auto;
    scroll-snap-type: y mandatory;
}

.viewer-item {
    height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    scroll-snap-align: start;
    padding: 80px 20px 60px;
}

.viewer-item img {
    max-width: 90%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: 10px;
}

.viewer-actions {
    margin-top: 20px;
    display: flex;
    gap: 20px;
    color: white;
    background: rgba(0,0,0,0.7);
    padding: 10px 20px;
    border-radius: 20px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .full-gallery-content {
        grid-template-columns: repeat(2, 1fr);
        gap: 5px;
        padding: 10px;
    }
    
    .full-gallery-toggle {
        top: 70px;
    }
    
    .gallery-view-btn span {
        display: none;
    }
}
@media (max-width: 768px) {
    .full-gallery-content {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 15px;
        top: 150px;
        grid-auto-rows: 1fr;
    }
}

@media (max-width: 480px) {
    .full-gallery-content {
        gap: 8px;
        padding: 10px;
        grid-template-columns: repeat(2, 1fr);
    }
}
/* ============================================
   FULL GALLERY MODAL - INFINITE SCROLL
   ============================================ */

.full-gallery-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: #000;
    z-index: 10000;
    overflow: hidden;
}

.full-gallery-modal.active {
    display: block;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Header */
.full-gallery-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.7) 70%, transparent 100%);
    backdrop-filter: blur(20px);
    padding: 25px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 10001;
}

.full-gallery-close {
    background: rgba(255,255,255,0.08);
    border: 2px solid rgba(255,215,0,0.6);
    color: var(--gold);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 28px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.full-gallery-close:hover {
    background: var(--gold);
    color: #000;
    transform: rotate(90deg) scale(1.1);
    box-shadow: 0 8px 25px rgba(255,215,0,0.4);
}

.full-gallery-counter {
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(15px);
    padding: 12px 28px;
    border-radius: 30px;
    color: var(--gold);
    font-weight: 700;
    font-size: 17px;
    border: 1px solid rgba(255,215,0,0.25);
    letter-spacing: 0.5px;
}

/* View Toggle */
.full-gallery-toggle {
    position: fixed;
    top: 100px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10001;
    display: flex;
    gap: 8px;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(15px);
    padding: 6px;
    border-radius: 50px;
    border: 1px solid rgba(255,215,0,0.2);
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
}

.gallery-view-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 28px;
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.7);
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 15px;
    font-weight: 600;
    white-space: nowrap;
}

.gallery-view-btn:hover {
    background: rgba(255,255,255,0.08);
    color: white;
}

.gallery-view-btn.active {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: #000;
    box-shadow: 0 4px 15px rgba(255,215,0,0.4);
}

.gallery-view-btn svg {
    width: 18px;
    height: 18px;
}

/* Grid View - Fixed */
.full-gallery-content {
    position: absolute;
    top: 180px;
    left: 0;
    right: 0;
    bottom: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 25px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    max-width: 1600px;
    margin: 0 auto;
    grid-auto-rows: 1fr; /* مهم جداً! */
}

.full-gallery-content::before {
    content: '';
    width: 0;
    padding-bottom: 100%;
    grid-row: 1 / 1;
    grid-column: 1 / 1;
}

.full-gallery-content > *:first-child {
    grid-row: 1 / 1;
    grid-column: 1 / 1;
}
.full-gallery-item {
    position: relative;
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: 16px;
    cursor: pointer;
    background: #1a1a1a;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.full-gallery-item:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(255,215,0,0.3);
}

.full-gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.full-gallery-item:hover img {
    transform: scale(1.12);
}

.full-gallery-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(0deg, rgba(0,0,0,0.95) 0%, rgba(0,0,0,0.6) 50%, transparent 100%);
    padding: 20px 15px;
    opacity: 0;
    transition: opacity 0.4s ease;
}

.full-gallery-item:hover .full-gallery-overlay {
    opacity: 1;
}

/* Single View - Natural Dimensions */
.full-gallery-content.single-view {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    padding: 30px 20px !important;
    gap: 40px !important;
}

.full-gallery-content.single-view .full-gallery-item {
    width: auto !important;
    max-width: 100% !important;
    height: auto !important;
    aspect-ratio: unset !important;
    padding-bottom: 0 !important;
    display: inline-block !important;
    margin: 0 !important;
}

.full-gallery-content.single-view .full-gallery-item img {
    position: static !important;
    width: auto !important;
    height: auto !important;
    max-width: 100% !important;
    max-height: 85vh !important;
    object-fit: contain !important;
    display: block !important;
    margin: 0 auto !important;
}

.full-gallery-content.single-view .full-gallery-overlay {
    position: relative !important;
    opacity: 1 !important;
    background: linear-gradient(0deg, rgba(0,0,0,0.85) 0%, transparent 100%) !important;
    margin-top: -80px !important;
    padding-top: 80px !important;
}

.full-gallery-content.single-view .full-gallery-item {
    aspect-ratio: auto;
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
    border-radius: 16px;
}

.full-gallery-content.single-view .full-gallery-item:hover {
    transform: translateY(0);
}

.full-gallery-content.single-view .full-gallery-item img {
    height: auto;
    max-height: 90vh;
    width: 100%;
    object-fit: contain;
    background: #000;
}

.full-gallery-content.single-view .full-gallery-item:hover img {
    transform: scale(1);
}

.full-gallery-content.single-view .full-gallery-overlay {
    opacity: 1;
    background: linear-gradient(0deg, rgba(0,0,0,0.85) 0%, transparent 100%);
}

/* Loading Indicator */
.gallery-loading {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px;
    color: var(--gold);
    font-size: 18px;
    font-weight: 600;
}

.gallery-loading::after {
    content: '';
    display: inline-block;
    width: 40px;
    height: 40px;
    margin-left: 15px;
    border: 4px solid rgba(255,215,0,0.2);
    border-top-color: var(--gold);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Image Viewer */
.full-gallery-viewer {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.98);
    z-index: 10002;
    display: none;
}

.full-gallery-viewer.active {
    display: block;
}

.viewer-close {
    position: fixed;
    top: 25px;
    right: 25px;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255,215,0,0.6);
    color: var(--gold);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 26px;
    cursor: pointer;
    z-index: 10003;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.viewer-close:hover {
    background: var(--gold);
    color: #000;
    transform: rotate(90deg) scale(1.1);
}

.viewer-counter {
    position: fixed;
    top: 25px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(15px);
    color: white;
    padding: 14px 30px;
    border-radius: 30px;
    z-index: 10003;
    font-weight: 700;
    font-size: 16px;
    border: 1px solid rgba(255,215,0,0.25);
}

.viewer-scroll {
    height: 100%;
    width: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-snap-type: y mandatory;
    scroll-behavior: smooth;
}

.viewer-item {
    min-height: 100vh;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    scroll-snap-align: start;
    padding: 100px 30px 100px;
}

.viewer-item img {
    max-width: 92%;
    max-height: 85vh;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.6);
}

.viewer-actions {
    position: absolute;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 35px;
    color: white;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(20px);
    padding: 18px 40px;
    border-radius: 50px;
    font-size: 17px;
    font-weight: 600;
    border: 1px solid rgba(255,215,0,0.2);
}

/* Scrollbar */
.full-gallery-content::-webkit-scrollbar,
.viewer-scroll::-webkit-scrollbar {
    width: 10px;
}

.full-gallery-content::-webkit-scrollbar-track,
.viewer-scroll::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.03);
}

.full-gallery-content::-webkit-scrollbar-thumb,
.viewer-scroll::-webkit-scrollbar-thumb {
    background: rgba(255,215,0,0.5);
    border-radius: 10px;
}

.full-gallery-content::-webkit-scrollbar-thumb:hover,
.viewer-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--gold);
}

/* Skeleton Loading */
.gallery-skeleton {
    aspect-ratio: 1;
    background: linear-gradient(90deg, #2a2a2a 0%, #3a3a3a 50%, #2a2a2a 100%);
    background-size: 200% 100%;
    animation: shimmer 2s infinite;
    border-radius: 16px;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Mobile */
@media (max-width: 768px) {
    .full-gallery-header {
        padding: 20px 15px;
    }
    
    .full-gallery-close {
        width: 45px;
        height: 45px;
        font-size: 22px;
    }
    
    .full-gallery-toggle {
        top: 85px;
        padding: 5px;
    }
    
    .gallery-view-btn {
        padding: 10px 18px;
        font-size: 14px;
    }
    
    .gallery-view-btn span {
        display: none;
    }
    
    .full-gallery-content {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 15px;
        top: 150px;
    }
    
    .full-gallery-content.single-view {
        gap: 25px;
    }
    
    .viewer-item {
        padding: 80px 15px 80px;
    }
    
    .viewer-item img {
        max-height: 75vh;
    }
    
    .viewer-actions {
        padding: 14px 25px;
        font-size: 15px;
        gap: 25px;
        bottom: 30px;
    }
}


/* Force Single View Display */
.full-gallery-content.single-view {
    display: block !important;
}

.full-gallery-content.single-view .full-gallery-item {
    display: block !important;
    float: none !important;
    margin: 0 auto 35px auto !important;
    width: 100% !important;
    max-width: 900px !important;
}

.full-gallery-content.single-view .full-gallery-item img {
    display: block !important;
    width: 100% !important;
    height: auto !important;
    max-height: 90vh !important;
    object-fit: contain !important;
}

/* Debug - Remove after testing */
/*.full-gallery-content.single-view .full-gallery-item {*/
    border: 2px solid rgba(255,0,0,0.3) !important; /* حدود حمراء للتأكد */
/*}*/
@media (max-width: 768px) {
    .full-gallery-content.single-view {
        gap: 30px !important;
        padding: 20px 10px !important;
    }
    
    .full-gallery-content.single-view .full-gallery-item img {
        max-height: 75vh !important;
    }
    
    .full-gallery-content.single-view .full-gallery-overlay {
        margin-top: -60px !important;
        padding-top: 60px !important;
    }
}

.full-gallery-content.single-view .full-gallery-overlay > div:first-child::before {
    content: 'من حفل زواج ';
    color: rgba(255, 215, 0, 0.8);
    font-weight: normal;
}

/* ============================================
   TESTIMONIALS SECTION
   ============================================ */

.testimonials-section {
    background: var(--dark);
    padding: 80px 20px;
    position: relative;
    overflow: hidden;
}

.testimonials-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
}

.testimonials-container {
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
}

.testimonials-slider {
    overflow: hidden;
    position: relative;
    padding: 20px 0;
}

.testimonials-track {
    display: flex;
    transition: transform 0.5s ease;
    gap: 30px;
}

.testimonial-card {
    min-width: 350px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 215, 0, 0.2);
    border-radius: 20px;
    padding: 30px;
    transition: all 0.3s;
    flex-shrink: 0;
}

.testimonial-card:hover {
    transform: translateY(-10px);
    border-color: var(--gold);
    box-shadow: 0 20px 40px rgba(255, 215, 0, 0.2);
}

.testimonial-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.testimonial-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), var(--gold-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    color: var(--black);
    flex-shrink: 0;
}

.testimonial-info h4 {
    color: var(--gold);
    font-size: 18px;
    margin-bottom: 5px;
}

.testimonial-date {
    color: #888;
    font-size: 13px;
}

.testimonial-stars {
    color: var(--gold);
    font-size: 18px;
    margin-bottom: 15px;
}

.testimonial-text {
    color: #CCC;
    line-height: 1.8;
    font-size: 15px;
}

.testimonial-quote {
    color: var(--gold);
    font-size: 40px;
    opacity: 0.3;
    line-height: 1;
}

.slider-controls {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 30px;
}

.slider-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 2px solid var(--gold);
    background: rgba(255, 215, 0, 0.1);
    color: var(--gold);
    font-size: 24px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.slider-btn:hover {
    background: var(--gold);
    color: var(--black);
    transform: scale(1.1);
}

.slider-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.slider-dots {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

.slider-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 215, 0, 0.3);
    cursor: pointer;
    transition: all 0.3s;
}

.slider-dot.active {
    background: var(--gold);
    width: 30px;
    border-radius: 5px;
}

@media (max-width: 768px) {
    .testimonial-card {
        min-width: 280px;
    }
    
    .testimonials-track {
        gap: 15px;
    }
}

/* Swiper Reviews Styling */
.mySwiper {
    padding: 20px 50px;
    margin: 0 auto;
}

.swiper-slide {
    height: auto;
    display: flex;
    flex-direction: column;
}

.swiper-button-next,
.swiper-button-prev {
    color: var(--gold);
    background: rgba(255, 215, 0, 0.1);
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

.swiper-button-next:hover,
.swiper-button-prev:hover {
    background: rgba(255, 215, 0, 0.2);
}

.swiper-button-next::after,
.swiper-button-prev::after {
    font-size: 18px;
    font-weight: bold;
}

@media (max-width: 768px) {
    .mySwiper {
        padding: 20px 30px;
    }
    
    .swiper-button-next,
    .swiper-button-prev {
        width: 35px;
        height: 35px;
    }
    
    .swiper-button-next::after,
    .swiper-button-prev::after {
        font-size: 16px;
    }
}


/* ===== تحسينات الأداء و Safari ===== */

/* Lazy Loading Styles */
.lazy-image {
    filter: blur(5px);
    transition: filter 0.3s ease;
}

.lazy-image.loaded {
    filter: blur(0);
}

/* تحسينات Safari للصور */
.full-gallery-item img,
.gallery-item img {
    -webkit-transform: translateZ(0);
    transform: translateZ(0);
    will-change: transform;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

/* تحسين Scroll في Safari */
#viewerScroll,
#fullGalleryContent {
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}

/* تحسين الأداء في Viewer */
.viewer-item {
    contain: content;
    will-change: transform;
}

.viewer-item img {
    -webkit-transform: translateZ(0);
    transform: translateZ(0);
}

/* Loading Spinner محسّن */
.gallery-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px;
    font-size: 16px;
    color: #d4af37;
}

.gallery-loading::after {
    content: '';
    width: 30px;
    height: 30px;
    margin-left: 15px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #d4af37;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* تحسينات Safari خاصة */
@supports (-webkit-appearance: none) {
    .full-gallery-grid,
    .gallery-grid {
        -webkit-transform: translate3d(0, 0, 0);
    }
    
    .viewer-item {
        -webkit-transform: translate3d(0, 0, 0);
    }
}

/* تحسين الأداء للموبايل */
@media (max-width: 768px) {
    .full-gallery-item img,
    .gallery-item img {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }
}

    </style>
</head>
<body>
    <header id="header">
        <nav>
            <div class="logo">
                <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/black_logo_jadhlah_t.svg')): ?>
              <a href="index.php"><img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="logo-img"></a>
                <?php else: ?>
                <span class="logo-text">جذلة</span>
                <?php endif; ?>
            </div>
            
            <div class="nav-links">
                <a href="#home" class="active">الرئيسية</a>
                <a href="#photos">الصور</a>
                <a href="#galleries">المعارض</a>
                <a href="#videos">الفيديوهات</a>
                <?php if (!empty($photographers)): ?>
                <a href="#team">الفريق</a>
                <?php endif; ?>
                <a href="#contact">تواصل معنا</a>
                <a href="https://wa.me/966544705859" class="cta-button" target="_blank">احجز الآن 📱</a>
            </div>
            
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
        </nav>
    </header>
    
    <div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>
    <div class="mobile-menu">
        <div class="mobile-menu-header">
            <span class="logo-text">جذلة</span>
            <button class="mobile-menu-close" onclick="toggleMobileMenu()">×</button>
        </div>
        <div class="mobile-menu-links">
            <a href="#home" onclick="toggleMobileMenu()">الرئيسية</a>
            <a href="#photos" onclick="toggleMobileMenu()">الصور</a>
            <a href="#galleries" onclick="toggleMobileMenu()">معارض العرسان</a>
            <a href="#videos" onclick="toggleMobileMenu()">الفيديوهات</a>
            <?php if (!empty($photographers)): ?>
            <a href="#team" onclick="toggleMobileMenu()">الفريق</a>
            <?php endif; ?>
             <a href="/services.php" onclick="toggleMobileMenu()">الحدمات و الأسعار</a>

            <a href="#contact" onclick="toggleMobileMenu()">تواصل معنا</a>
            <a href="https://wa.me/966544705859" target="_blank">احجز الآن</a>
        </div>
    </div>
    
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>نوثق أجمل لحظاتك</h1>
            <p class="hero-subtitle">
                تصوير احترافي وإنتاج سينمائي للأعراس والمناسبات.<br>
(نحول ليلة العمر إلى ذكريات تعيشها  ألف مرة بعدستنا  )          </p>
            
            <div class="hero-buttons">
                <a href="https://wa.me/966544705859" class="hero-btn hero-btn-primary" target="_blank">
                    <span>احجز تصوير زواجك الآن</span>
                    <span></span>
                </a>
                <a href="#photos" class="hero-btn hero-btn-secondary">
                    <span>شوف شغلنا</span>
                    <span>📸</span>
                </a>
            </div>
        </div>
    </section>
    
    <?php if (count($allPhotos) > 0): ?>
    <section id="photos">
        <div class="section-header">
            <h2 class="section-title">معرض الصور</h2>
            <p class="section-subtitle">صور مختارة من أعمالنا الفوتوغرافية</p>
        </div>
        
        <div class="photos-grid">
            <?php foreach ($allPhotos as $index => $photo): ?>
            <div class="photo-card" onclick="openLightbox(<?= $index ?>)">
                <img src="<?= htmlspecialchars($photo['thumb']) ?>" 
                     alt="<?= htmlspecialchars($photo['title']) ?>"
                     loading="lazy">
                <div class="photo-overlay">
                    <div style="width: 100%;">
                        <div style="color: var(--gold); font-weight: bold;"><?= htmlspecialchars($photo['title']) ?></div>
                        <?php if ($photo['likes'] > 0): ?>
                        <div style="color: white; font-size: 14px; margin-top: 5px;">
                            ❤️ <?= number_format($photo['likes']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
</div>
        
        <!-- زر استكشاف جميع الصور -->
        <div style="text-align: center; margin: 60px 0 40px;">
            <button onclick="openFullGallery()" class="hero-btn hero-btn-primary" style="cursor: pointer; border: none; ;">
                <span></span>
                <span>تحب تشوف صور أكثر؟</span>
            </button>
        </div>
    </section>
    
    <!-- نافذة المعرض الكامل -->
    <?php
// جلب جميع الصور المميزة حسب الإعدادات
if ($showGroomFeatured == 1) {
    $allFeaturedStmt = $pdo->query("
        (SELECT gp.*, g.groom_name, g.id as groom_id, 1 as priority
        FROM groom_photos gp 
        JOIN grooms g ON gp.groom_id = g.id 
        WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1)
        
        UNION ALL
        
        (SELECT gp.*, g.groom_name, g.id as groom_id, 2 as priority
        FROM groom_photos gp 
        JOIN grooms g ON gp.groom_id = g.id 
        WHERE gp.hidden = 0 AND gp.is_featured = 1 AND gp.featured_for_gallery = 0)
        
ORDER BY 
    priority ASC,
    CASE WHEN priority = 1 THEN display_order_gallery ELSE 0 END DESC,
    likes DESC
    ");
} else {
    $allFeaturedStmt = $pdo->query("
        SELECT gp.*, g.groom_name, g.id as groom_id 
        FROM groom_photos gp 
        JOIN grooms g ON gp.groom_id = g.id 
        WHERE gp.hidden = 0 AND gp.featured_for_gallery = 1 
        ORDER BY gp.likes DESC
    ");
}
$allFeaturedPhotos = $allFeaturedStmt->fetchAll();
$totalFeaturedCount = count($allFeaturedPhotos);
?>
    
    <div id="fullGalleryModal" class="full-gallery-modal">
        <div class="full-gallery-header">
            <button class="full-gallery-close" onclick="closeFullGallery()">×</button>
            <h3 style="color: var(--gold); font-size: 10px; margin: 0;">معرض الأعمال المميزة</h3>
            <div class="full-gallery-counter">
                <?= number_format($totalFeaturedCount) ?> صورة
            </div>
        </div>
        
        <div class="full-gallery-toggle">
            <button class="gallery-view-btn active" data-view="grid" onclick="switchFullGalleryView('grid')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                </svg>
                <span>شبكي</span>
            </button>
            <button class="gallery-view-btn" data-view="single" onclick="switchFullGalleryView('single')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="4" y="4" width="16" height="16"/>
                </svg>
                <span>فردي</span>
            </button>
        </div>
        
        <div class="full-gallery-content" id="fullGalleryContent">
            <?php foreach ($allFeaturedPhotos as $index => $photo): 
                $imagePath = getValidImagePath($photo['groom_id'], $photo['filename']);
                if ($imagePath):
            ?>
            <div class="full-gallery-item" onclick="openFullGalleryImage(<?= $index ?>)">
                <img src="<?= htmlspecialchars($imagePath) ?>" 
                     alt="<?= htmlspecialchars($photo['groom_name']) ?>"
                     loading="lazy"
                     onerror="this.parentElement.style.display='none'">
                <div class="full-gallery-overlay">
                    <div style="color: white; font-size: 15px; font-weight: bold;">
                        <?= htmlspecialchars($photo['groom_name']) ?>
                    </div>
                    <div style="color: #ddd; font-size: 13px; margin-top: 5px;">
                        ❤️ <?= number_format($photo['likes']) ?>
                    </div>
                </div>
            </div>
            <?php endif; endforeach; ?>
        </div>
        
        <div id="fullGalleryViewer" class="full-gallery-viewer" style="display: none;">
            <button class="viewer-close" onclick="closeFullGalleryViewer()">×</button>
            <div class="viewer-counter">
                <span id="viewerCurrent">1</span> / <span id="viewerTotal"><?= $totalFeaturedCount ?></span>
            </div>
            
            <div class="viewer-scroll" id="viewerScroll">
                <?php foreach ($allFeaturedPhotos as $index => $photo): 
                    $imagePath = getValidImagePath($photo['groom_id'], $photo['filename']);
                    if ($imagePath):
                ?>
                <div class="viewer-item">
                    <img src="<?= htmlspecialchars($imagePath) ?>" 
                         alt="<?= htmlspecialchars($photo['groom_name']) ?>"
                         onerror="this.parentElement.style.display='none'">
                    <div class="viewer-actions">
                        <span>❤️ <?= number_format($photo['likes']) ?></span>
                        <span><?= htmlspecialchars($photo['groom_name']) ?></span>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
        <div class="lightbox-content">
            <button class="lightbox-close" onclick="closeLightbox(event)">×</button>
            <button class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1, event)">‹</button>
            <img id="lightbox-img" src="" alt="">
            <button class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1, event)">›</button>
        </div>
    </div>
    <?php endif; ?>
    
    
    
    <section id="galleries">
        <div class="section-header">
            <h2 class="section-title">معارض عرسان جذلة</h2>
            <p class="section-subtitle">شيّك على صفحات ويب لمعارض العرسان</p>
        </div>
        <!-- Banner Image -->
<section style="padding: 0px 0px 32px 0px;text-align: center;">
    <img src="/assets/pagebaner.jpg" 
         alt="مميزات معارض جذلة" 
         style="max-width: 100%; 
                height: auto; 
                border-radius: 20px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                transition: transform 0.3s ease;"
         loading="lazy"
         onmouseover="this.style.transform='scale(1.02)'"
         onmouseout="this.style.transform='scale(1)'">
</section>
        <div class="gallery-grid">
            <?php foreach ($grooms as $groom): 
                $bannerImage = getGroomBanner($groom['id']);
                
                if (!$bannerImage) {
                    $photosQuery = $pdo->prepare("SELECT filename FROM groom_photos WHERE groom_id = ? AND hidden = 0 ORDER BY is_featured DESC, likes DESC LIMIT 1");
                    $photosQuery->execute([$groom['id']]);
                    $photo = $photosQuery->fetch();
                    if ($photo) {
                        $bannerImage = getImagePath($groom['id'], $photo['filename'], 'thumb');
                    }
                }
            ?>
            <div class="gallery-card" onclick="window.location.href='/groom.php?groom=<?= $groom['id'] ?>'">
                <div class="gallery-banner">
                    <?php if ($bannerImage): ?>
                    <img src="<?= htmlspecialchars($bannerImage) ?>" alt="<?= htmlspecialchars($groom['groom_name']) ?>" loading="lazy">
                    <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666; font-size: 70px;">
                        👰
                    </div>
                    <?php endif; ?>
                </div>
                <div class="gallery-info">
                    <h3 style="color: var(--gold); font-size: 1.5rem; margin-bottom: 20px; font-weight: bold;">
                        <?= htmlspecialchars($groom['groom_name']) ?>
                    </h3>
                    <div style="display: flex; justify-content: space-around; text-align: center;">
                        <div>
                            <div style="color: var(--gold); font-size: 1.3rem; font-weight: bold;">
                                <?= number_format($groom['photo_count']) ?>
                            </div>
                            <div style="color: #666; font-size: 0.9rem;">صورة</div>
                        </div>
                        <div>
                            <div style="color: var(--gold); font-size: 1.3rem; font-weight: bold;">
                                <?= number_format($groom['page_views']) ?>
                            </div>
                            <div style="color: #666; font-size: 0.9rem;">مشاهدة</div>
                        </div>
                        <div>
                            <div style="color: var(--gold); font-size: 1.3rem; font-weight: bold;">
                                <?= number_format($groom['total_likes']) ?>
                            </div>
                            <div style="color: #666; font-size: 0.9rem;">إعجاب</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    
   <section id="videos">
    <div class="section-header">
        <h2 class="section-title">الفيديوهات والأفلام السينمائية</h2>
        <p class="section-subtitle">أعمالنا المميزة في الإنتاج السينمائي للزواجات</p>
    </div>
    
    <div class="video-categories">
        <div class="category-tab active" onclick="filterVideos('all', this)">
            <span>🎬</span>
            <span>جميع الفيديوهات</span>
            <span class="count-badge" id="count-all">0</span>
        </div>
        <?php foreach ($categories as $cat): 
            $videoCount = count($videosByCategory[$cat['slug']]['videos']);
            if ($videoCount > 0):
        ?>
        <div class="category-tab" onclick="filterVideos('<?= $cat['slug'] ?>', this)">
            <span><?= $cat['icon'] ?? '🎬' ?></span>
            <span><?= htmlspecialchars($cat['name_ar']) ?></span>
            <span class="count-badge"><?= $videoCount ?></span>
        </div>
        <?php endif; endforeach; ?>
    </div>
    
    <div class="video-grid" id="videoGrid">
        <?php 
        $videoIndex = 0;
        foreach ($videosByCategory as $catSlug => $catData): 
            foreach ($catData['videos'] as $video): 
                $videoIndex++;
        ?>
        <div class="video-card" data-category="<?= $catSlug ?>" data-index="<?= $videoIndex ?>">
            <div class="video-wrapper">
                <iframe src="https://www.youtube.com/embed/<?= $video['id'] ?>?rel=0" 
                        allowfullscreen loading="lazy"></iframe>
            </div>
            <div class="video-info">
                <div class="video-category-badge">
                    <?= $catData['icon'] ?> <?= htmlspecialchars($catData['name']) ?>
                </div>
            </div>
        </div>
        <?php 
            endforeach; 
        endforeach; 
        ?>
    </div>
    
    <button id="showMoreVideosBtn" class="show-more-videos-btn" onclick="showMoreVideos()">
        مشاهدة المزيد 🎬
    </button>
</section>

    
    <?php if (!empty($photographers)): ?>
    <section class="photographers-section" id="team">
        <div class="section-header">
            <h2 class="section-title">فريق العمل</h2>
            <p class="section-subtitle">مصورينا المخضرمين</p>
        </div>
        
        <div class="photographers-grid">
            <?php foreach ($photographers as $photographer): ?>
            <div class="photographer-card">
                <img src="/photographers/<?= htmlspecialchars($photographer['image']) ?>" 
                     alt="<?= htmlspecialchars($photographer['name']) ?>">
                <div class="photographer-name"><?= htmlspecialchars($photographer['name']) ?></div>
                <div class="photographer-role"><?= htmlspecialchars($photographer['role']) ?></div>
                <?php if (!empty($photographer['description'])): ?>
                <p style="color: #999; font-size: 14px; line-height: 1.6;">
                    <?= htmlspecialchars($photographer['description']) ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
  <!-- Reviews Display -->
<?php
// جلب التقييمات المعتمدة من جميع العرسان
try {
    $stmt = $pdo->prepare("
        SELECT name, message, rating, created_at
        FROM groom_reviews
        WHERE is_approved = 1
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $approvedReviews = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("خطأ في جلب التقييمات: " . $e->getMessage());
    $approvedReviews = [];
}
?>

<?php if (!empty($approvedReviews)): ?>
<section style="max-width:800px;margin:40px auto;padding:20px;background:#fff;border-radius:20px;" aria-label="تقييمات العملاء">
    <h3 style="text-align:center;color:#444;font-size:28px;margin-bottom:30px;">قالوا عن جذلة</h3>
    
    <div class="swiper mySwiper">
        <div class="swiper-wrapper">
            <?php foreach ($approvedReviews as $review): ?>
                <div class="swiper-slide" style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);max-width:300px;">
                    <div style="font-size:16px;color:#f5b301;margin-bottom:8px;" aria-label="تقييم <?= $review['rating'] ?> من 5">
                        <?= str_repeat("⭐", $review['rating']) ?>
                    </div>
                    <div style="color:#333;margin-bottom:10px;line-height:1.6;"><?= nl2br(htmlspecialchars($review['message'])) ?></div>
                    <div style="text-align:right;font-size:14px;color:#888;">— <?= htmlspecialchars($review['name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-button-next" aria-label="التقييم التالي"></div>
        <div class="swiper-button-prev" aria-label="التقييم السابق"></div>
    </div>
</section>
<?php endif; ?>

    <section class="contact-section" id="contact">
    <div class="section-header">
        <h2 class="section-title">تواصل معنا</h2>
        <p class="section-subtitle">يسعدنا خدمتكم و تصوير لحظاتكم المميزة</p>
    </div>
    
    <div class="contact-grid">
        <div class="contact-card">
            <div class="contact-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" fill="currentColor" viewBox="0 0 16 16" style="color: #25D366;">
                    <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
                </svg>
            </div>
            <h3>واتساب</h3>
            <p style="color: #AAA;">تواصل معنا مباشرة</p>
            <a href="https://wa.me/966544705859?text=مرحباً، أرغب في الاستفسار عن خدمات التصوير" target="_blank">محادثة الآن</a>
        </div>
        
        <div class="contact-card">
            <div class="contact-icon">💰</div>
            <h3>الباقات والأسعار</h3>
            <p style="color: #AAA;">تعرف على باقاتنا</p>
            <a href="/services.php">عرض الباقات</a>
        </div>
    </div>
</section>
    
<?php if ($instagramEnabled): ?>
<section class="instagram-section">
    <div class="section-header">
        <h2 class="section-title">تابعنا على Instagram</h2>
        <p class="section-subtitle">@jadhlah</p>
    </div>
    
    <!-- Instagram Embed Widget -->
    <div style="text-align: center; margin-bottom: 40px;">
        <blockquote class="instagram-media" 
                    data-instgrm-permalink="https://www.instagram.com/jadhlah/?utm_source=ig_embed&amp;utm_campaign=loading" 
                    data-instgrm-version="14" 
                    style="background:#FFF; border:0; border-radius:12px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px auto; max-width:540px; min-width:326px; padding:0; width:99.375%;">
            <div style="padding:16px;">
                <a href="https://www.instagram.com/jadhlah/?utm_source=ig_embed&amp;utm_campaign=loading" 
                   style="background:#FFFFFF; line-height:0; padding:0 0; text-align:center; text-decoration:none; width:100%;" 
                   target="_blank">
                    <div style="display: flex; flex-direction: row; align-items: center;">
                        <div style="background-color: #F4F4F4; border-radius: 50%; flex-grow: 0; height: 40px; margin-right: 14px; width: 40px;"></div>
                        <div style="display: flex; flex-direction: column; flex-grow: 1; justify-content: center;">
                            <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; margin-bottom: 6px; width: 100px;"></div>
                            <div style="background-color: #F4F4F4; border-radius: 4px; flex-grow: 0; height: 14px; width: 60px;"></div>
                        </div>
                    </div>
                    <div style="padding: 19% 0;"></div>
                    <div style="height:50px; margin:0 auto 12px; width:50px;">
                        <svg width="50px" height="50px" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g transform="translate(-511.000000, -20.000000)" fill="#000000">
                                    <g><path d="M556.869,30.41 C554.814,30.41 553.148,32.076 553.148,34.131 C553.148,36.186 554.814,37.852 556.869,37.852 C558.924,37.852 560.59,36.186 560.59,34.131 C560.59,32.076 558.924,30.41 556.869,30.41 M541,60.657 C535.114,60.657 530.342,55.887 530.342,50 C530.342,44.114 535.114,39.342 541,39.342 C546.887,39.342 551.658,44.114 551.658,50 C551.658,55.887 546.887,60.657 541,60.657 M541,33.886 C532.1,33.886 524.886,41.1 524.886,50 C524.886,58.899 532.1,66.113 541,66.113 C549.9,66.113 557.115,58.899 557.115,50 C557.115,41.1 549.9,33.886 541,33.886 M565.378,62.101 C565.244,65.022 564.756,66.606 564.346,67.663 C563.803,69.06 563.154,70.057 562.106,71.106 C561.058,72.155 560.06,72.803 558.662,73.347 C557.607,73.757 556.021,74.244 553.102,74.378 C549.944,74.521 548.997,74.552 541,74.552 C533.003,74.552 532.056,74.521 528.898,74.378 C525.979,74.244 524.393,73.757 523.338,73.347 C521.94,72.803 520.942,72.155 519.894,71.106 C518.846,70.057 518.197,69.06 517.654,67.663 C517.244,66.606 516.755,65.022 516.623,62.101 C516.479,58.943 516.448,57.996 516.448,50 C516.448,42.003 516.479,41.056 516.623,37.899 C516.755,34.978 517.244,33.391 517.654,32.338 C518.197,30.938 518.846,29.942 519.894,28.894 C520.942,27.846 521.94,27.196 523.338,26.654 C524.393,26.244 525.979,25.756 528.898,25.623 C532.057,25.479 533.004,25.448 541,25.448 C548.997,25.448 549.943,25.479 553.102,25.623 C556.021,25.756 557.607,26.244 558.662,26.654 C560.06,27.196 561.058,27.846 562.106,28.894 C563.154,29.942 563.803,30.938 564.346,32.338 C564.756,33.391 565.244,34.978 565.378,37.899 C565.522,41.056 565.552,42.003 565.552,50 C565.552,57.996 565.522,58.943 565.378,62.101 M570.82,37.631 C570.674,34.438 570.167,32.258 569.425,30.349 C568.659,28.377 567.633,26.702 565.965,25.035 C564.297,23.368 562.623,22.342 560.652,21.575 C558.743,20.834 556.562,20.326 553.369,20.18 C550.169,20.033 549.148,20 541,20 C532.853,20 531.831,20.033 528.631,20.18 C525.438,20.326 523.257,20.834 521.349,21.575 C519.376,22.342 517.703,23.368 516.035,25.035 C514.368,26.702 513.342,28.377 512.574,30.349 C511.834,32.258 511.326,34.438 511.181,37.631 C511.035,40.831 511,41.851 511,50 C511,58.147 511.035,59.17 511.181,62.369 C511.326,65.562 511.834,67.743 512.574,69.651 C513.342,71.625 514.368,73.296 516.035,74.965 C517.703,76.634 519.376,77.658 521.349,78.425 C523.257,79.167 525.438,79.673 528.631,79.82 C531.831,79.965 532.853,80.001 541,80.001 C549.148,80.001 550.169,79.965 553.369,79.82 C556.562,79.673 558.743,79.167 560.652,78.425 C562.623,77.658 564.297,76.634 565.965,74.965 C567.633,73.296 568.659,71.625 569.425,69.651 C570.167,67.743 570.674,65.562 570.82,62.369 C570.966,59.17 571,58.147 571,50 C571,41.851 570.966,40.831 570.82,37.631"></path></g></g>
                            </g>
                        </svg>
                    </div>
                    <div style="padding-top: 8px;">
                        <div style="color:#3897f0; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:550; line-height:18px;">عرض هذا المنشور على Instagram</div>
                    </div>
                </a>
            </div>
        </blockquote>
        <script async src="//www.instagram.com/embed.js"></script>
    </div>
    
    <div style="text-align: center;">
        <a href="https://instagram.com/jadhlah" target="_blank" class="hero-btn hero-btn-primary">
            <span>زيارة حسابنا</span>
            <span>📸</span>
        </a>
    </div>
</section>
<?php endif; ?>
    
    
    <!-- WhatsApp Float Button -->
<a href="https://wa.me/966544705859?text=مرحباً، أرغب في الاستفسار عن خدمات التصوير" 
   class="whatsapp-float" 
   target="_blank"
   aria-label="WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
        <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
    </svg>
</a>


    <footer>
        <div style="max-width: 800px; margin: 0 auto;">
            <div class="social-links">
                <a href="https://wa.me/966544705859" target="_blank" title="واتساب">📱</a>
                <a href="https://instagram.com/jadhlah" target="_blank" title="انستغرام">📸</a>
                <a href="https://twitter.com/jadhlah" target="_blank" title="تويتر">🐦</a>
                <a href="https://snapchat.com/add/jadhlah" target="_blank" title="سناب شات">👻</a>
            </div>
            
            <p style="color: #666; margin-top: 20px;">© <?= date('Y') ?> جذلة - جميع الحقوق محفوظة</p>
            <p style="color: #444; margin-top: 10px; font-size: 14px;">تصوير فوتوغرافي وسينمائي احترافي</p>
        </div>
    </footer>
    
        <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <!-- Swiper JS - مهم جداً! -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <script>
        const photos = <?= json_encode($allPhotos) ?>;
        let currentPhotoIndex = 0;
        
        function openLightbox(index) {
            currentPhotoIndex = index;
            updateLightboxImage();
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox(event) {
            if (event.target.id === 'lightbox' || event.target.classList.contains('lightbox-close')) {
                event.stopPropagation();
                document.getElementById('lightbox').classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }
        
        function changeLightboxImage(direction, event) {
            event.stopPropagation();
            currentPhotoIndex += direction;
            if (currentPhotoIndex < 0) currentPhotoIndex = photos.length - 1;
            if (currentPhotoIndex >= photos.length) currentPhotoIndex = 0;
            updateLightboxImage();
        }
        
        function updateLightboxImage() {
            const photo = photos[currentPhotoIndex];
            document.getElementById('lightbox-img').src = photo.original;
        }
        
        document.addEventListener('keydown', (e) => {
            const lightbox = document.getElementById('lightbox');
            if (lightbox.classList.contains('active')) {
                if (e.key === 'Escape') closeLightbox({target: lightbox});
                if (e.key === 'ArrowLeft') changeLightboxImage(1, e);
                if (e.key === 'ArrowRight') changeLightboxImage(-1, e);
            }
        });
        
        function toggleMobileMenu() {
            const menu = document.querySelector('.mobile-menu');
            const overlay = document.querySelector('.mobile-menu-overlay');
            menu.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
       // ============================================
// VIDEO SECTION - إدارة الفيديوهات
// ============================================

const videosPerPage = 4;
let currentVideosPage = 1;
let currentCategory = 'all';

// تحديث عدادات التصنيفات عند التحميل
document.addEventListener('DOMContentLoaded', function() {
    updateCategoryCounters();
    filterVideos('all', document.querySelector('.category-tab.active'));
});

function updateCategoryCounters() {
    const allVideos = document.querySelectorAll('.video-card').length;
    const countAll = document.getElementById('count-all');
    if (countAll) {
        countAll.textContent = allVideos;
    }
}

function filterVideos(category, element) {
    // تحديث التبويب النشط
    document.querySelectorAll('.category-tab').forEach(tab => tab.classList.remove('active'));
    element.classList.add('active');
    
    // حفظ التصنيف الحالي
    currentCategory = category;
    currentVideosPage = 1;
    
    // إخفاء/إظهار الفيديوهات حسب التصنيف
    const videos = document.querySelectorAll('.video-card');
    let visibleCount = 0;
    
    videos.forEach((video, index) => {
        const shouldShow = category === 'all' || video.dataset.category === category;
        
        if (shouldShow) {
            visibleCount++;
            // إظهار أول 4 فيديوهات فقط
            if (visibleCount <= videosPerPage) {
                video.classList.remove('hidden');
                video.style.display = 'block';
            } else {
                video.classList.add('hidden');
                video.style.display = 'none';
            }
        } else {
            video.classList.add('hidden');
            video.style.display = 'none';
        }
    });
    
    // إظهار/إخفاء زر "مشاهدة المزيد"
    const showMoreBtn = document.getElementById('showMoreVideosBtn');
    if (showMoreBtn) {
        if (visibleCount > videosPerPage) {
            showMoreBtn.classList.remove('hidden');
            showMoreBtn.style.display = 'block';
            showMoreBtn.textContent = `مشاهدة المزيد (${visibleCount - videosPerPage} فيديو) 🎬`;
        } else {
            showMoreBtn.classList.add('hidden');
            showMoreBtn.style.display = 'none';
        }
    }
}

function showMoreVideos() {
    currentVideosPage++;
    
    const videos = document.querySelectorAll('.video-card');
    let visibleCount = 0;
    let totalVisible = 0;
    
    videos.forEach(video => {
        const shouldShow = currentCategory === 'all' || video.dataset.category === currentCategory;
        
        if (shouldShow) {
            totalVisible++;
            
            if (totalVisible <= currentVideosPage * videosPerPage) {
                video.classList.remove('hidden');
                video.style.display = 'block';
                visibleCount++;
            }
        }
    });
    
    // تحديث أو إخفاء الزر
    const showMoreBtn = document.getElementById('showMoreVideosBtn');
    const remainingVideos = totalVisible - (currentVideosPage * videosPerPage);
    
    if (remainingVideos > 0) {
        showMoreBtn.textContent = `مشاهدة المزيد (${remainingVideos} فيديو) 🎬`;
    } else {
        showMoreBtn.classList.add('hidden');
        showMoreBtn.style.display = 'none';
    }
    
    // Scroll إلى الفيديو الجديد
    const firstNewVideo = document.querySelector(`.video-card[data-index="${(currentVideosPage - 1) * videosPerPage + 1}"]`);
    if (firstNewVideo) {
        firstNewVideo.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

        
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    document.querySelectorAll('.nav-links a').forEach(link => link.classList.remove('active'));
                    this.classList.add('active');
                    
                    const menu = document.querySelector('.mobile-menu');
                    if (menu.classList.contains('active')) {
                        toggleMobileMenu();
                    }
                }
            });
        });
        
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            header.classList.toggle('scrolled', window.scrollY > 50);
        });
        
        // Full Gallery Functions
// Full Gallery Functions - محسّن
// Full Gallery - Infinite Scroll Implementation
let allGalleryPhotos = [];
let displayedPhotos = [];
let currentViewerIndex = 0;
let photosPerPage = 30;
let currentPage = 1;
let isLoading = false;
let hasMorePhotos = true;

// Intersection Observer للـ Lazy Loading
let imageObserver = null;
if ('IntersectionObserver' in window) {
    imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                const src = img.getAttribute('data-src');
                if (src) {
                    img.src = src;
                    img.onload = () => img.classList.add('loaded');
                    img.removeAttribute('data-src');
                }
                imageObserver.unobserve(img);
            }
        });
    }, {
        rootMargin: '50px'
    });
}

// // تحميل بيانات الصور من PHP
// fetch(window.location.pathname + '?get_all_photos=1')
// .then(res => res.json())
//     .then(data => {
//         allGalleryPhotos = data;
//     })
//     .catch(err => console.error('Error loading photos:', err));

function openFullGallery() {
    const modal = document.getElementById('fullGalleryModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reset and load first page
        displayedPhotos = [];
        currentPage = 1;
        hasMorePhotos = true;
        const content = document.getElementById('fullGalleryContent');
        content.innerHTML = '';
        
        loadMorePhotos();
    }
}

// تحميل الصور من API pagination محسّن
async function loadMorePhotos() {
    if (isLoading || !hasMorePhotos) return;
    
    isLoading = true;
    const content = document.getElementById('fullGalleryContent');
    
    // Add loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'gallery-loading';
    loadingDiv.textContent = 'جاري التحميل...';
    content.appendChild(loadingDiv);
    
    try {
        const response = await fetch(`?get_photos_paginated=1&page=${currentPage}&limit=20`);
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        
        loadingDiv.remove();
        
        if (data.photos && data.photos.length > 0) {
            data.photos.forEach((photo) => {
                const actualIndex = displayedPhotos.length;
                displayedPhotos.push(photo);
                
                const item = document.createElement('div');
                item.className = 'full-gallery-item';
                item.onclick = () => openFullGalleryImage(actualIndex);
                
                item.innerHTML = `
                    <img data-src="${photo.thumb}" 
                         alt="${photo.title}"
                         class="lazy-image"
                         loading="lazy"
                         onerror="this.parentElement.style.display='none'">
                    <div class="full-gallery-overlay">
                        <div style="color: white; font-size: 15px; font-weight: bold;">
                            ${photo.title}
                        </div>
                        <div style="color: #ddd; font-size: 13px; margin-top: 5px;">
                            ❤️ ${photo.likes.toLocaleString()}
                        </div>
                    </div>
                `;
                
                content.appendChild(item);
            });
            
            // Lazy load الصور الجديدة
            const lazyImages = content.querySelectorAll('.lazy-image:not(.observed)');
            lazyImages.forEach(img => {
                img.classList.add('observed');
                if (typeof imageObserver !== 'undefined') {
                    imageObserver.observe(img);
                } else {
                    // fallback
                    const src = img.getAttribute('data-src');
                    if (src) img.src = src;
                }
            });
            
            hasMorePhotos = data.hasMore;
            currentPage++;
            allGalleryPhotos = displayedPhotos;
        } else {
            hasMorePhotos = false;
        }
        
        updateCounter();
    } catch (error) {
        console.error('Error loading photos:', error);
        loadingDiv.textContent = 'حدث خطأ في تحميل الصور. يرجى المحاولة مرة أخرى.';
        setTimeout(() => loadingDiv.remove(), 3000);
    } finally {
        isLoading = false;
    }
}

function updateCounter() {
    const counter = document.querySelector('.full-gallery-counter');
    if (counter) {
        counter.textContent = hasMorePhotos ? `${displayedPhotos.length} صورة` : `${displayedPhotos.length} صورة (جميع الصور)`;
    }
}

function closeFullGallery() {
    const modal = document.getElementById('fullGalleryModal');
    const viewer = document.getElementById('fullGalleryViewer');
    if (modal) {
        modal.classList.remove('active');
        if (viewer) {
            viewer.classList.remove('active');
            viewer.style.display = 'none';
        }
        document.body.style.overflow = '';
    }
}

function switchFullGalleryView(view) {
    const content = document.getElementById('fullGalleryContent');
    const buttons = document.querySelectorAll('.gallery-view-btn');
    
    buttons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
    
    if (content) {
        content.classList.add('view-switching');
        
        setTimeout(() => {
            if (view === 'single') {
                content.classList.add('single-view');
                content.style.display = 'block';
            } else {
                content.classList.remove('single-view');
                content.style.display = 'grid';
            }
            
            setTimeout(() => {
                content.classList.remove('view-switching');
            }, 200);
        }, 50);
    }
}
function openFullGalleryImage(index) {
    const viewer = document.getElementById('fullGalleryViewer');
    const viewerScroll = document.getElementById('viewerScroll');
    const viewerCurrent = document.getElementById('viewerCurrent');
    
    if (!viewer || !viewerScroll) return;
    
    currentViewerIndex = index;
    viewer.style.display = 'block';
    viewer.classList.add('active');
    
    // Build viewer content if not exists
    if (viewerScroll.children.length === 0) {
        displayedPhotos.forEach((photo, i) => {
            const item = document.createElement('div');
            item.className = 'viewer-item';
            item.innerHTML = `
                <img src="${photo.original}" 
                     alt="${photo.title}"
                     onerror="this.parentElement.style.display='none'">
                <div class="viewer-actions">
                    <span>❤️ ${photo.likes.toLocaleString()}</span>
                    <span>${photo.title}</span>
                </div>
            `;
            viewerScroll.appendChild(item);
        });
    }
    
    setTimeout(() => {
        const targetItem = viewerScroll.children[index];
        if (targetItem) {
            targetItem.scrollIntoView({ behavior: 'instant', block: 'center' });
        }
    }, 100);
    
    if (viewerCurrent) {
        viewerCurrent.textContent = index + 1;
    }
    
    setupViewerScroll();
}

function setupViewerScroll() {
    const viewerScroll = document.getElementById('viewerScroll');
    if (!viewerScroll) return;
    
    let scrollTimeout;
    viewerScroll.onscroll = function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            updateViewerCounter();
        }, 150);
    };
}

function updateViewerCounter() {
    const viewerScroll = document.getElementById('viewerScroll');
    const viewerCurrent = document.getElementById('viewerCurrent');
    const items = viewerScroll.querySelectorAll('.viewer-item');
    
    items.forEach((item, i) => {
        const rect = item.getBoundingClientRect();
        if (rect.top >= -200 && rect.top <= window.innerHeight / 2) {
            currentViewerIndex = i;
            if (viewerCurrent) {
                viewerCurrent.textContent = i + 1;
            }
        }
    });
}

function closeFullGalleryViewer() {
    const viewer = document.getElementById('fullGalleryViewer');
    if (viewer) {
        viewer.classList.remove('active');
        viewer.style.display = 'none';
    }
}

// Infinite Scroll Handler
document.addEventListener('DOMContentLoaded', function() {
    const content = document.getElementById('fullGalleryContent');
    if (content) {
        content.addEventListener('scroll', function() {
            if (this.scrollTop + this.clientHeight >= this.scrollHeight - 200) {
                loadMorePhotos();
            }
        });
    }
});

// Keyboard Support
document.addEventListener('keydown', (e) => {
    const modal = document.getElementById('fullGalleryModal');
    const viewer = document.getElementById('fullGalleryViewer');
    
    if (!modal || !modal.classList.contains('active')) return;
    
    if (e.key === 'Escape') {
        if (viewer && viewer.classList.contains('active')) {
            closeFullGalleryViewer();
        } else {
            closeFullGallery();
        }
    }
    
    if (viewer && viewer.classList.contains('active')) {
        const viewerScroll = document.getElementById('viewerScroll');
        const items = viewerScroll.querySelectorAll('.viewer-item');
        
        if (e.key === 'ArrowDown' && currentViewerIndex < items.length - 1) {
            e.preventDefault();
            items[currentViewerIndex + 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (e.key === 'ArrowUp' && currentViewerIndex > 0) {
            e.preventDefault();
            items[currentViewerIndex - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

// Debug - Console Log
console.log('Total Photos:', allGalleryPhotos.length);
console.log('Sample Photo:', allGalleryPhotos[0]);

// عند فتح المعرض
function openFullGallery() {
    const modal = document.getElementById('fullGalleryModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Reset
        displayedPhotos = [];
        currentPage = 1;
        const content = document.getElementById('fullGalleryContent');
        content.innerHTML = '';
        
        console.log('Opening gallery with', allGalleryPhotos.length, 'photos'); // Debug
        
        loadMorePhotos();
    }
}

// // Testimonials Slider
// let currentTestimonialSlide = 0;
// const testimonialTrack = document.getElementById('testimonialsTrack');
// const testimonialCards = document.querySelectorAll('.testimonial-card');
// const totalTestimonials = testimonialCards.length;
// let testimonialsPerView = window.innerWidth > 768 ? 3 : 1;
// const maxSlide = Math.max(0, totalTestimonials - testimonialsPerView);

// // Create dots
// const dotsContainer = document.getElementById('sliderDots');
// for (let i = 0; i <= maxSlide; i++) {
//     const dot = document.createElement('div');
//     dot.className = 'slider-dot' + (i === 0 ? ' active' : '');
//     dot.onclick = () => goToSlide(i);
//     dotsContainer.appendChild(dot);
// }

// function updateSlider() {
//     if (!testimonialTrack) return;
    
//     const cardWidth = testimonialCards[0].offsetWidth;
//     const gap = 30;
//     const offset = -(currentTestimonialSlide * (cardWidth + gap));
//     testimonialTrack.style.transform = `translateX(${offset}px)`;
    
//     // Update dots
//     document.querySelectorAll('.slider-dot').forEach((dot, index) => {
//         dot.classList.toggle('active', index === currentTestimonialSlide);
//     });
    
//     // Update buttons
//     document.getElementById('prevBtn').disabled = currentTestimonialSlide === 0;
//     document.getElementById('nextBtn').disabled = currentTestimonialSlide >= maxSlide;
// }

// function slideTestimonials(direction) {
//     if (direction === 'next' && currentTestimonialSlide < maxSlide) {
//         currentTestimonialSlide++;
//     } else if (direction === 'prev' && currentTestimonialSlide > 0) {
//         currentTestimonialSlide--;
//     }
//     updateSlider();
// }

// function goToSlide(index) {
//     currentTestimonialSlide = index;
//     updateSlider();
// }

// // Auto slide every 5 seconds
// setInterval(() => {
//     if (currentTestimonialSlide >= maxSlide) {
//         currentTestimonialSlide = 0;
//     } else {
//         currentTestimonialSlide++;
//     }
//     updateSlider();
// }, 5000);

// // Update on resize
// window.addEventListener('resize', () => {
//     testimonialsPerView = window.innerWidth > 768 ? 3 : 1;
//     updateSlider();
// });

// updateSlider();

// Initialize Swiper for Reviews
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swiper !== 'undefined' && document.querySelector('.mySwiper')) {
        new Swiper(".mySwiper", {
            slidesPerView: 1.2,
            spaceBetween: 20,
            centeredSlides: false,
            navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev"
            },
            breakpoints: {
                640: { 
                    slidesPerView: 2.2,
                    spaceBetween: 20
                },
                1024: { 
                    slidesPerView: 3,
                    spaceBetween: 20
                }
            },
            loop: false,
            grabCursor: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true
            }
        });
    }
});

    </script>

</body>
</html>