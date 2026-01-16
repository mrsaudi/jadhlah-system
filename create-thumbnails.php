<?php
/**
 * سكريبت لإنشاء thumbnails للصور الموجودة
 * نفذه مرة واحدة فقط
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

$uploadDir = '/home/u709146392/domains/jadhlah.com/public_html/uploads/live/';

echo "<h1>إنشاء Thumbnails للصور الموجودة</h1>";
echo "<pre>";

// البحث عن جميع الصور
$images = glob($uploadDir . '*.{jpg,jpeg,JPG,JPEG,png,PNG}', GLOB_BRACE);

$total = count($images);
$created = 0;
$skipped = 0;
$errors = 0;

echo "تم العثور على $total صورة\n\n";

foreach ($images as $imagePath) {
    $filename = basename($imagePath);
    
    // تخطي الـ thumbnails الموجودة
    if (strpos($filename, '_thumb.') !== false) {
        continue;
    }
    
    echo "معالجة: $filename ... ";
    
    // التحقق من وجود thumbnail
    $pathInfo = pathinfo($imagePath);
    $thumbPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
    
    if (file_exists($thumbPath)) {
        echo "✓ موجود مسبقاً\n";
        $skipped++;
        continue;
    }
    
    // إنشاء thumbnail
    if (createThumbnail($imagePath, $thumbPath)) {
        echo "✅ تم الإنشاء\n";
        $created++;
    } else {
        echo "❌ فشل\n";
        $errors++;
    }
}

echo "\n";
echo "========================================\n";
echo "النتائج:\n";
echo "- إجمالي الصور: $total\n";
echo "- تم الإنشاء: $created\n";
echo "- موجودة مسبقاً: $skipped\n";
echo "- أخطاء: $errors\n";
echo "========================================\n";

echo "</pre>";

function createThumbnail($imagePath, $thumbPath, $targetWidth = 300) {
    try {
        list($origWidth, $origHeight) = @getimagesize($imagePath);
        
        if (!$origWidth || !$origHeight) {
            return false;
        }
        
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        
        // قراءة الصورة
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $source = @imagecreatefromjpeg($imagePath);
        } elseif ($ext === 'png') {
            $source = @imagecreatefrompng($imagePath);
        } else {
            return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // حساب الأبعاد الجديدة
        $ratio = $targetWidth / $origWidth;
        $newWidth = $targetWidth;
        $newHeight = (int)($origHeight * $ratio);
        
        // إنشاء الصورة المصغرة
        $dest = imagecreatetruecolor($newWidth, $newHeight);
        
        // الحفاظ على الشفافية للـ PNG
        if ($ext === 'png') {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
            imagefill($dest, 0, 0, $transparent);
        }
        
        // نسخ الصورة مع تغيير الحجم
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        // حفظ الصورة
        if ($ext === 'png') {
            $result = imagepng($dest, $thumbPath, 8);
        } else {
            $result = imagejpeg($dest, $thumbPath, 85);
        }
        
        // تحرير الذاكرة
        imagedestroy($source);
        imagedestroy($dest);
        
        return $result;
        
    } catch (Exception $e) {
        return false;
    }
}
?>