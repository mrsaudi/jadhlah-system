<?php
// image-handler.php - معالج عرض الصور من جميع المسارات
session_start();

// البحث عن ملف قاعدة البيانات
$dbPaths = ['config/database.php', '../config/database.php', 'database.php'];
$dbFound = false;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbFound = true;
        break;
    }
}

if (!$dbFound) {
    // إرسال صورة افتراضية
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="200" fill="#eee"/>
        <text text-anchor="middle" x="100" y="100" fill="#999" font-size="20">No DB</text>
    </svg>';
    exit;
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="200" fill="#eee"/>
        <text text-anchor="middle" x="100" y="100" fill="#999" font-size="20">DB Error</text>
    </svg>';
    exit;
}
$conn->set_charset("utf8mb4");

// الحصول على اسم الملف من الطلب
$filename = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'full'; // full or thumb

if (empty($filename)) {
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="200" fill="#eee"/>
        <text text-anchor="middle" x="100" y="100" fill="#999" font-size="20">No File</text>
    </svg>';
    exit;
}

// تنظيف اسم الملف
$filename = basename($filename);

// إضافة _thumb للصور المصغرة
if ($type === 'thumb') {
    $filename = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '_thumb.$1', $filename);
}

// قائمة المسارات المحتملة للبحث عن الصورة
$basePaths = [
    '/home/u709146392/domains/jadhlah.com/public_html',
    $_SERVER['DOCUMENT_ROOT'] ?? '',
    dirname(__DIR__),
    dirname(dirname(__DIR__))
];

$searchPaths = [];
foreach ($basePaths as $basePath) {
    if (!empty($basePath)) {
        // مسارات الصور المحتملة
        $searchPaths[] = $basePath . '/uploads/live/' . $filename;
        $searchPaths[] = $basePath . '/grooms/' . $filename;
        $searchPaths[] = $basePath . '/ftp/live/' . $filename;
        $searchPaths[] = $basePath . '/ftp/archive/' . date('Y-m-d') . '/' . $filename;
        $searchPaths[] = $basePath . '/ftp/archive/' . date('Y-m-d', strtotime('-1 day')) . '/' . $filename;
        $searchPaths[] = $basePath . '/ftp/archive/' . date('Y-m-d', strtotime('-2 days')) . '/' . $filename;
        
        // البحث في الأرشيف لآخر 7 أيام
        for ($i = 0; $i <= 7; $i++) {
            $archiveDate = date('Y-m-d', strtotime("-$i days"));
            $searchPaths[] = $basePath . '/ftp/archive/' . $archiveDate . '/' . $filename;
        }
    }
}

// إزالة المسارات المكررة
$searchPaths = array_unique($searchPaths);

// البحث عن الصورة
$imagePath = null;
foreach ($searchPaths as $path) {
    if (file_exists($path) && is_file($path)) {
        $imagePath = $path;
        break;
    }
}

// إذا لم نجد الصورة، نبحث في قاعدة البيانات عن معلومات إضافية
if (!$imagePath) {
    $stmt = $conn->prepare("SELECT * FROM live_gallery_photos WHERE filename = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $filename);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // محاولة البحث باستخدام تاريخ الرفع
            $uploadDate = date('Y-m-d', strtotime($row['uploaded_at']));
            foreach ($basePaths as $basePath) {
                if (!empty($basePath)) {
                    $archivePath = $basePath . '/ftp/archive/' . $uploadDate . '/' . $filename;
                    if (file_exists($archivePath)) {
                        $imagePath = $archivePath;
                        break;
                    }
                }
            }
        }
        $stmt->close();
    }
}

// إذا وجدنا الصورة، نعرضها
if ($imagePath) {
    // تحديد نوع الصورة
    $imageInfo = @getimagesize($imagePath);
    if ($imageInfo) {
        $mimeType = $imageInfo['mime'];
        
        // إرسال headers
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=86400'); // تخزين مؤقت ليوم واحد
        header('Content-Length: ' . filesize($imagePath));
        
        // قراءة وإرسال الصورة
        readfile($imagePath);
    } else {
        // الملف موجود لكنه ليس صورة صالحة
        sendErrorImage('Invalid Image');
    }
} else {
    // لم نجد الصورة في أي مكان
    sendErrorImage('Not Found');
}

// دالة إرسال صورة خطأ
function sendErrorImage($message = 'No Image') {
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="200" fill="#f0f0f0"/>
        <text text-anchor="middle" x="100" y="90" fill="#999" font-size="18">' . htmlspecialchars($message) . '</text>
        <text text-anchor="middle" x="100" y="110" fill="#ccc" font-size="14">' . date('H:i:s') . '</text>
    </svg>';
}

$conn->close();
?>