<?php
// create-thumbnails.php - إنشاء الصور المصغرة لتحسين الأداء
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "=== إنشاء الصور المصغرة ===\n\n";

$sourceDir = '/home/u709146392/domains/jadhlah.com/public_html/uploads/live/';

if (!is_dir($sourceDir)) {
    die("خطأ: المجلد غير موجود: $sourceDir\n");
}

// البحث عن جميع الصور
$images = glob($sourceDir . '*.{jpg,jpeg,JPG,JPEG,png,PNG}', GLOB_BRACE);

if (empty($images)) {
    die("لا توجد صور في المجلد\n");
}

$totalImages = count($images);
$createdCount = 0;
$skippedCount = 0;
$errorCount = 0;

echo "عدد الصور الكلي: $totalImages\n\n";

foreach ($images as $imagePath) {
    $filename = basename($imagePath);
    
    // تخطي الصور المصغرة نفسها
    if (strpos($filename, '_thumb') !== false) {
        continue;
    }
    
    // اسم الصورة المصغرة
    $thumbPath = preg_replace('/\.(jpg|jpeg|png)$/i', '_thumb.$1', $imagePath);
    
    echo "[$filename] - ";
    
    // التحقق من وجود الصورة المصغرة
    if (file_exists($thumbPath)) {
        echo "✓ موجودة مسبقاً\n";
        $skippedCount++;
        continue;
    }
    
    // إنشاء الصورة المصغرة
    if (createThumbnail($imagePath, $thumbPath)) {
        echo "✓ تم الإنشاء\n";
        $createdCount++;
    } else {
        echo "✗ فشل الإنشاء\n";
        $errorCount++;
    }
}

echo "\n=== النتائج النهائية ===\n";
echo "تم إنشاء: $createdCount صورة مصغرة\n";
echo "موجودة مسبقاً: $skippedCount\n";
echo "فشل: $errorCount\n";

function createThumbnail($sourcePath, $thumbPath) {
    try {
        // الأبعاد المطلوبة
        $thumbWidth = 400;
        $thumbHeight = 400;
        
        // قراءة معلومات الصورة
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($origWidth, $origHeight) = $imageInfo;
        
        // قراءة الصورة حسب النوع
        $source = null;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // حساب الأبعاد الجديدة مع الحفاظ على النسبة
        $ratio = min($thumbWidth / $origWidth, $thumbHeight / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);
        
        // إنشاء الصورة المصغرة
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // خلفية بيضاء للشفافية
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        
        // نسخ وتصغير الصورة
        imagecopyresampled(
            $thumb, $source,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $origWidth, $origHeight
        );
        
        // حفظ الصورة المصغرة
        $result = false;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($thumb, $thumbPath, 85); // جودة 85%
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($thumb, $thumbPath, 6); // ضغط 6/9
                break;
        }
        
        // تحرير الذاكرة
        imagedestroy($source);
        imagedestroy($thumb);
        
        return $result;
        
    } catch (Exception $e) {
        return false;
    }
}

echo "\n✓ انتهى السكريبت\n";
?>