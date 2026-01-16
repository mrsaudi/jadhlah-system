<?php
// fix-archived-images.php - سكريبت إصلاح الصور المؤرشفة
require_once 'config/database.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

echo "=== سكريبت إصلاح الصور المؤرشفة ===\n\n";

// المسارات
$ftpArchiveBase = '/home/u709146392/domains/jadhlah.com/ftp/archive/';
$webLiveDir = '/home/u709146392/domains/jadhlah.com/public_html/uploads/live/';

// جلب جميع الصور من قاعدة البيانات
$query = "SELECT * FROM live_gallery_photos ORDER BY uploaded_at DESC";
$result = $conn->query($query);

if (!$result) {
    die("خطأ في الاستعلام: " . $conn->error);
}

$totalPhotos = $result->num_rows;
$foundCount = 0;
$notFoundCount = 0;
$copiedCount = 0;

echo "عدد الصور الكلي في قاعدة البيانات: $totalPhotos\n\n";

while ($photo = $result->fetch_assoc()) {
    $filename = $photo['filename'];
    $photoId = $photo['id'];
    $uploadDate = date('Y-m-d', strtotime($photo['uploaded_at']));
    
    echo "[$photoId] $filename - ";
    
    // التحقق من وجود الصورة في uploads/live
    $webLivePath = $webLiveDir . $filename;
    
    if (file_exists($webLivePath)) {
        echo "✓ موجودة في uploads/live\n";
        $foundCount++;
        continue;
    }
    
    // البحث في الأرشيف
    $found = false;
    
    // البحث في أرشيف تاريخ الرفع
    $archivePath = $ftpArchiveBase . $uploadDate . '/' . $filename;
    if (file_exists($archivePath)) {
        echo "وُجدت في الأرشيف ($uploadDate) - ";
        
        // نسخها إلى uploads/live
        if (@copy($archivePath, $webLivePath)) {
            echo "✓ تم النسخ\n";
            $copiedCount++;
            $found = true;
            
            // إنشاء thumbnail
            createThumbnail($webLivePath);
        } else {
            echo "✗ فشل النسخ\n";
        }
    }
    
    // إذا لم نجدها، نبحث في آخر 30 يوم
    if (!$found) {
        for ($i = 0; $i <= 30; $i++) {
            $checkDate = date('Y-m-d', strtotime("-$i days"));
            $checkPath = $ftpArchiveBase . $checkDate . '/' . $filename;
            
            if (file_exists($checkPath)) {
                echo "وُجدت في أرشيف $checkDate - ";
                
                if (@copy($checkPath, $webLivePath)) {
                    echo "✓ تم النسخ\n";
                    $copiedCount++;
                    $found = true;
                    
                    // إنشاء thumbnail
                    createThumbnail($webLivePath);
                    break;
                } else {
                    echo "✗ فشل النسخ\n";
                    break;
                }
            }
        }
    }
    
    // البحث في الملف الأصلي بنفس الاسم
    if (!$found && isset($photo['original_filename'])) {
        $originalName = $photo['original_filename'];
        
        for ($i = 0; $i <= 30; $i++) {
            $checkDate = date('Y-m-d', strtotime("-$i days"));
            $checkPath = $ftpArchiveBase . $checkDate . '/' . $originalName;
            
            if (file_exists($checkPath)) {
                echo "وُجد الأصلي في أرشيف $checkDate - ";
                
                if (@copy($checkPath, $webLivePath)) {
                    echo "✓ تم النسخ\n";
                    $copiedCount++;
                    $found = true;
                    
                    // إنشاء thumbnail
                    createThumbnail($webLivePath);
                    break;
                } else {
                    echo "✗ فشل النسخ\n";
                    break;
                }
            }
        }
    }
    
    if (!$found) {
        echo "✗ لم توجد في أي مكان\n";
        $notFoundCount++;
    }
}

echo "\n=== النتائج النهائية ===\n";
echo "الإجمالي: $totalPhotos صورة\n";
echo "موجودة أصلاً: $foundCount\n";
echo "تم استرجاعها: $copiedCount\n";
echo "لم توجد: $notFoundCount\n";

// إضافة حقل archived_copied إذا لم يكن موجوداً
echo "\n=== تحديث قاعدة البيانات ===\n";
$conn->query("ALTER TABLE live_gallery_photos ADD COLUMN IF NOT EXISTS archived_copied TINYINT(1) DEFAULT 0");
echo "✓ تم إضافة حقل archived_copied\n";

// دالة إنشاء thumbnail
function createThumbnail($imagePath) {
    try {
        $thumbPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '_thumb.$1', $imagePath);
        
        // إذا كان الـ thumb موجود، لا نعيد إنشاءه
        if (file_exists($thumbPath)) {
            return true;
        }
        
        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($origWidth, $origHeight) = $imageInfo;
        
        // قراءة الصورة
        $source = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $source = @imagecreatefromgif($imagePath);
                break;
        }
        
        if (!$source) return false;
        
        // حساب الأبعاد
        $thumbWidth = 400;
        $thumbHeight = 300;
        $ratio = min($thumbWidth / $origWidth, $thumbHeight / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);
        
        // إنشاء الصورة المصغرة
        $dest = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($dest, 255, 255, 255);
        imagefill($dest, 0, 0, $white);
        
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        imagejpeg($dest, $thumbPath, 85);
        
        imagedestroy($source);
        imagedestroy($dest);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$conn->close();
echo "\n✓ انتهى السكريبت\n";
?>