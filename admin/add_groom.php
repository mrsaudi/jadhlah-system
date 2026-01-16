<?php
// ===================================
// admin/add_groom.php - إضافة عريس بواجهة محسنة مطابقة لـ edit_groom
// ===================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit','512M');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// إنشاء معرف مؤقت للرفع
if (!isset($_SESSION['temp_upload_id'])) {
    $_SESSION['temp_upload_id'] = uniqid('temp_', true);
}
$tempUploadId = $_SESSION['temp_upload_id'];

// تهيئة المتغيرات
$notes = 'بارك الله لهما وبارك عليهما وجمع بينهما في خير';
$errorMessage = '';
$successMessage = '';
$groomLink = '';
$groomId = null;

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. جمع البيانات
        $groom_name = trim($_POST['groom_name'] ?? '');
        $wedding_date = trim($_POST['wedding_date'] ?? '');
        $hall_name = trim($_POST['hall_name'] ?? '');
        $event_name = trim($_POST['event_name'] ?? '');
        $notes = trim($_POST['notes'] ?? 'بارك الله لهما وبارك عليهما وجمع بينهما في خير');
        
        if (empty($groom_name)) {
            throw new Exception('اسم العريس مطلوب');
        }
        
        // 2. جمع روابط يوتيوب
        $youtubeData = [];
        for ($i = 1; $i <= 7; $i++) {
            $field = "youtube$i";
            $youtubeData[$field] = trim($_POST[$field] ?? '');
        }
        
        // 3. إدراج في قاعدة البيانات
        $stmt = $pdo->prepare("
            INSERT INTO grooms (
                groom_name, wedding_date, hall_name, event_name, notes,
                youtube1, youtube2, youtube3, youtube4, youtube5, youtube6, youtube7,
                created_at, is_active
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                NOW(), 1
            )
        ");
        
        $stmt->execute([
            $groom_name, $wedding_date, $hall_name, $event_name, $notes,
            $youtubeData['youtube1'], $youtubeData['youtube2'], $youtubeData['youtube3'],
            $youtubeData['youtube4'], $youtubeData['youtube5'], $youtubeData['youtube6'],
            $youtubeData['youtube7']
        ]);
        
        $groomId = (int)$pdo->lastInsertId();
        logError("تم إنشاء عريس جديد: #$groomId", 'groom_creation');
        
        // 4. إنشاء المجلدات الجديدة (مع إضافة modal_thumb)
        $groomBaseDir = GROOMS_BASE . '/' . $groomId;
        $groomDirs = [
            $groomBaseDir,
            $groomBaseDir . '/originals',      // الصور الأصلية
            $groomBaseDir . '/modal_thumb',    // صور العرض في المودال (1500px)
            $groomBaseDir . '/thumbs',         // صور العرض الشبكي (300px)
            $groomBaseDir . '/temp'            // رفع مؤقت
        ];
        
        foreach ($groomDirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("فشل في إنشاء المجلد: $dir");
                }
                logError("تم إنشاء مجلد: $dir", 'groom_creation');
            }
        }
        
        // 5. معالجة البنر (إنشاء النسخة الأصلية والمتوسطة)
        if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            if (isValidImageMime($_FILES['banner']['tmp_name'])) {
                $bannerOriginalPath = $groomBaseDir . '/banner.jpg';
                $bannerModalPath = $groomBaseDir . '/modal_thumb/banner.jpg';
                
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $bannerOriginalPath)) {
                    // إنشاء نسخة مصغرة للبنر
                    createThumbnailEnhanced($bannerOriginalPath, $bannerModalPath, 1500);
                    
                    // تحديث قاعدة البيانات
                    $stmt = $pdo->prepare("UPDATE grooms SET banner = 'banner.jpg' WHERE id = ?");
                    $stmt->execute([$groomId]);
                    
                    logError("تم رفع البنر للعريس #$groomId", 'groom_creation');
                }
            }
        }
        
        // 6. نقل الصور من المؤقت إلى temp العريس مع معالجة محسنة
        $tempUploadDir = TEMP_UPLOADS_BASE . '/' . $tempUploadId;
        $groomTempDir = $groomBaseDir . '/temp';
        
        if (is_dir($tempUploadDir)) {
            $tempFiles = array_diff(scandir($tempUploadDir), ['.', '..']);
            $movedCount = 0;
            
            logError("بدء نقل الصور من: $tempUploadDir إلى: $groomTempDir", 'groom_creation');
            
            foreach ($tempFiles as $file) {
                $srcFile = $tempUploadDir . '/' . $file;
                $destFile = $groomTempDir . '/' . $file;
                
                if (is_file($srcFile)) {
                    if (copy($srcFile, $destFile)) {
                        // إضافة إلى قائمة الانتظار المحسنة
                        $stmt = $pdo->prepare("
                            INSERT INTO upload_queue (groom_id, filename, status, created_at) 
                            VALUES (?, ?, 'pending', NOW())
                        ");
                        $stmt->execute([$groomId, $file]);
                        
                        unlink($srcFile); // حذف الملف المؤقت
                        $movedCount++;
                        
                        logError("نقل الملف: $file إلى العريس #$groomId", 'groom_creation');
                    } else {
                        logError("فشل نقل الملف: $file", 'groom_creation');
                    }
                }
            }
            
            // حذف المجلد المؤقت إذا كان فارغاً
            @rmdir($tempUploadDir);
            logError("تم نقل $movedCount صورة للعريس #$groomId", 'groom_creation');
        }
        
        // 7. معالجة البيانات الإضافية
        $photoOrder = json_decode($_POST['photo_order'] ?? '[]', true);
        $featuredPhotos = json_decode($_POST['featured'] ?? '[]', true);
        $hiddenPhotos = json_decode($_POST['hidden'] ?? '[]', true);
        
        // إضافة السجلات لقاعدة البيانات مع البيانات الإضافية
        foreach ($photoOrder as $index => $filename) {
            if (!empty($filename)) {
                $isFeatured = in_array($filename, $featuredPhotos) ? 1 : 0;
                $isHidden = in_array($filename, $hiddenPhotos) ? 1 : 0;
                
                $checkStmt = $pdo->prepare("
                    SELECT id FROM groom_photos 
                    WHERE groom_id = ? AND filename = ?
                ");
                $checkStmt->execute([$groomId, $filename]);
                
                if (!$checkStmt->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO groom_photos 
                        (groom_id, filename, is_featured, hidden, photo_order, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$groomId, $filename, $isFeatured, $isHidden, $index]);
                }
            }
        }
        
        // 8. رسالة النجاح
        $successMessage = 'تم حفظ بيانات العريس بنجاح!';
        $groomLink = "https://jadhlah.com/groom.php?groom={$groomId}";
        
        // إعادة تعيين معرف مؤقت جديد
        $_SESSION['temp_upload_id'] = uniqid('temp_', true);
        
    } catch (Exception $e) {
        $errorMessage = "خطأ: " . $e->getMessage();
        logError("خطأ في add_groom.php: " . $e->getMessage(), 'errors');
    }
}

// جلب الصور الموجودة للمعاينة
$existingFiles = [];
$tempUploadDir = TEMP_UPLOADS_BASE . '/' . $tempUploadId;
if (is_dir($tempUploadDir)) {
    $existingFiles = array_diff(scandir($tempUploadDir), ['.', '..']);
}

/**
 * دالة إنشاء صورة مصغرة محسنة
 */
function createThumbnailEnhanced($source, $destination, $maxSize) {
    try {
        $info = getimagesize($source);
        if (!$info) {
            throw new Exception("ليس ملف صورة صالح");
        }
        
        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];
        
        // إذا كانت الصورة أصغر من الحجم المطلوب، انسخها كما هي
        if ($width <= $maxSize && $height <= $maxSize) {
            return copy($source, $destination);
        }
        
        // حساب الأبعاد الجديدة مع الحفاظ على النسبة
        $ratio = $width / $height;
        if ($width > $height) {
            $newWidth = $maxSize;
            $newHeight = intval($maxSize / $ratio);
        } else {
            $newHeight = $maxSize;
            $newWidth = intval($maxSize * $ratio);
        }
        
        // إنشاء الصورة من المصدر
        switch ($mime) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($source);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($source);
                break;
            case 'image/webp':
                $srcImage = imagecreatefromwebp($source);
                break;
            default:
                throw new Exception("نوع الصورة غير مدعوم: $mime");
        }
        
        if (!$srcImage) {
            throw new Exception("فشل في قراءة الصورة");
        }
        
        // إنشاء صورة جديدة
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // الحفاظ على الشفافية للـ PNG و GIF
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            // خلفية بيضاء للصور الأخرى
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $white);
        }
        
        // نسخ وتغيير حجم الصورة
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );
        
        // حفظ الصورة
        $quality = JPEG_QUALITY ?? 85;
        $result = imagejpeg($dstImage, $destination, $quality);
        
        // تنظيف الذاكرة
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("خطأ في createThumbnailEnhanced: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة عريس جديد - جذلة</title>
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    
    <!-- Uppy -->
    <link href="https://releases.transloadit.com/uppy/v3.25.4/uppy.min.css" rel="stylesheet">
    <script src="https://releases.transloadit.com/uppy/v3.25.4/uppy.min.js"></script>
    
    <!-- Sortable.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
    <meta name="theme-color" content="#4caf50">
    
    <style>
        body {
            background: #f7f7f7;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            font-weight: bold;
            border-radius: 12px 12px 0 0 !important;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #4caf50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        /* Uppy Customization */
        #drag-drop-area {
            min-height: 300px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px dashed #ddd;
            margin-bottom: 20px;
        }
        
        /* Photo Gallery */
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .photo-box {
            position: relative;
            width: 100%;
            aspect-ratio: 1;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
        }
        .photo-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-box.selected {
            outline: 3px solid #ffd700;
            outline-offset: -3px;
        }
        .photo-box.hidden {
            opacity: 0.3;
        }
        .photo-actions {
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            display: flex;
            justify-content: space-between;
            gap: 5px;
        }
        .photo-actions button {
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .photo-actions button:hover {
            background: white;
            transform: scale(1.1);
        }
        
        /* Banner Preview */
        .banner-preview {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #4caf50;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Success/Error Messages */
        .success-section {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .link-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #4caf50, #45a049);
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            transition: all 0.3s;
        }
        
        /* Processing Info */
        .processing-info {
            background: #fff3cd;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 13px;
        }
        
        /* Loading Spinner */
        .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
        }
        
        .temp-upload-info {
            background: #e3f2fd;
            color: #1976d2;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>إضافة عريس جديد</h1>
            <a href="dashboard.php" class="btn btn-secondary">العودة للوحة التحكم</a>
        </div>
        
        <!-- Messages -->
        <?php if ($successMessage): ?>
            <div class="success-section">
                <div class="alert alert-success mb-3">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                </div>
                
                <?php if ($groomLink): ?>
                    <div class="link-section">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-link"></i> رابط العريس
                        </h6>
                        <div class="input-group">
                            <input type="text" class="form-control" id="groomLink" 
                                   value="<?= htmlspecialchars($groomLink) ?>" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copyLink()">
                                <i class="fas fa-copy"></i> نسخ الرابط
                            </button>
                        </div>
                        <small class="text-muted d-block mt-2">
                            يمكنك مشاركة هذا الرابط لعرض بيانات العريس
                        </small>
                    </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="add_groom.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> إضافة عريس آخر
                    </a>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> العودة للوحة التحكم
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Preview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?= count($existingFiles) ?></div>
                    <div class="stat-label">صور مرفوعة مؤقتاً</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number">3</div>
                    <div class="stat-label">أحجام لكل صورة</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number">0</div>
                    <div class="stat-label">صور مميزة</div>
                </div>
            </div>
        </div>
        
        <!-- Main Form -->
        <?php if (!$successMessage): ?>
            <form method="POST" enctype="multipart/form-data" id="groomForm">
                <!-- Basic Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">البيانات الأساسية</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم المناسبة</label>
                                <input type="text" name="event_name" class="form-control" 
                                       placeholder="مثال: حفل زواج أحمد ومريم">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم العريس *</label>
                                <input type="text" name="groom_name" class="form-control" 
                                       required placeholder="أدخل اسم العريس">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">تاريخ الزواج</label>
                                <input type="text" name="wedding_date" class="form-control" 
                                       placeholder="مثال: ٢٠٢٤/١٢/٢٥">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">اسم القاعة</label>
                                <input type="text" name="hall_name" class="form-control" 
                                       placeholder="أدخل اسم القاعة أو المكان">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($notes) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Banner Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">صورة البنر</h5>
                    </div>
                    <div class="card-body">
                        <input type="file" name="banner" class="form-control" accept="image/*">
                        <div class="processing-info">
                            سيتم إنشاء نسخة أصلية ونسخة مصغرة (1500px) للبنر تلقائياً
                        </div>
                    </div>
                </div>
                
                <!-- YouTube Links Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">روابط يوتيوب</h5>
                    </div>
                    <div class="card-body">
                        <div id="youtubeInputsContainer">
                            <div class="mb-2">
                                <input type="url" name="youtube1" class="form-control"
                                       placeholder="رابط يوتيوب 1">
                            </div>
                        </div>
                        <button type="button" onclick="addYoutubeInput()" id="addYoutubeBtn" class="btn btn-outline-primary btn-sm">
                            + إضافة رابط آخر
                        </button>
                    </div>
                </div>
                
                <!-- Hidden Inputs for Photo Management -->
                <input type="hidden" id="photo_order" name="photo_order" value="[]">
                <input type="hidden" id="featured_input" name="featured" value="[]">
                <input type="hidden" id="hidden_input" name="hidden" value="[]">
                
                <!-- Action Buttons -->
                <div class="d-flex gap-2 my-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> حفظ العريس
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="togglePhotos()">
                        <i class="bi bi-images"></i> إظهار/إخفاء الصور
                    </button>
                    <button type="button" class="btn btn-info" onclick="showUploadSection()">
                        <i class="bi bi-cloud-upload"></i> رفع الصور
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <!-- Photo Upload Card -->
        <div class="card" id="uploadSection" <?= $successMessage ? 'style="display:none;"' : '' ?>>
            <div class="card-header">
                <h5 class="mb-0">رفع صور الحفل</h5>
            </div>
            <div class="card-body">
                <div class="temp-upload-info">
                    💡 <strong>نظام معالجة محسن:</strong> سيتم حفظ الصور بثلاث أحجام مختلفة:
                    <br>• الأصلية (للتحميل)
                    <br>• المتوسطة 1500px (للعرض في المودال) 
                    <br>• المصغرة 300px (للعرض الشبكي)
                </div>
                <div id="drag-drop-area"></div>
                <div id="upload-progress" style="display:none;">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Photos Card -->
        <div class="card" id="photoSection" style="display:none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">الصور المرفوعة (<?= count($existingFiles) ?>)</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPhotos()">
                        ⭐ تحديد الكل كمميزة
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPhotos()">
                        ❌ إلغاء التحديد
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="hideAllPhotos()">
                        👁️ إخفاء الكل
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="showAllPhotos()">
                        👁️ إظهار الكل
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="photo-gallery" id="photoGallery">
                    <!-- الصور ستظهر هنا ديناميكياً عبر JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // متغيرات عامة
    const tempUploadId = <?= json_encode($tempUploadId) ?>;
    const existingFiles = <?= json_encode(array_values($existingFiles)) ?>;
    
    // متغيرات المعرض
    let selected = [];
    let hidden = [];
    let boxes = [];
    let lastIndex = null;
    let youtubeCount = 1;
    
    console.log('معلومات الرفع المحسن:', {
        tempUploadId: tempUploadId,
        existingFiles: existingFiles,
        uploadEndpoint: `upload_temp.php?temp_id=${tempUploadId}`
    });
    
    document.addEventListener('DOMContentLoaded', () => {
        const preview = document.getElementById('photoGallery');
        
        // تهيئة Uppy
        const { Uppy } = window;
        
        const uppy = new Uppy.Uppy({
            debug: true,
            autoProceed: false,
            restrictions: {
                maxNumberOfFiles: 2000,
                allowedFileTypes: ['image/*'],
                // maxFileSize: 100000 * 1024 * 1024
            },
            locale: {
                strings: {
                    dropPasteFiles: 'اسحب الصور هنا أو %{browseFiles}',
                    browseFiles: 'تصفح الملفات',
                    uploading: 'جاري الرفع...',
                    complete: 'مكتمل',
                    uploadComplete: 'اكتمل الرفع',
                    xFilesSelected: {
                        0: '%{smart_count} ملف',
                        1: '%{smart_count} ملف',
                        2: '%{smart_count} ملف'
                    }
                }
            }
        });
        
        // إضافة واجهة Dashboard
        uppy.use(Uppy.Dashboard, {
            inline: true,
            target: '#drag-drop-area',
            width: '100%',
            height: 300,
            proudlyDisplayPoweredByUppy: false,
            showProgressDetails: true
        });
        
        // إضافة XHRUpload للرفع المؤقت
        uppy.use(Uppy.XHRUpload, {
            endpoint: `upload_temp.php?temp_id=${tempUploadId}`,
            fieldName: 'file',
            formData: true,
            bundle: false,
            limit: 5,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        // دوال تحديث الحقول المخفية
        function updateOrder() {
            const order = boxes.map(b => b.querySelector('img')?.dataset.filename).filter(f => f);
            document.getElementById('photo_order').value = JSON.stringify(order);
        }
        
        function updateFeatured() {
            document.getElementById('featured_input').value = JSON.stringify(selected);
        }
        
        function updateHidden() {
            document.getElementById('hidden_input').value = JSON.stringify(hidden);
        }
        
        // إنشاء صندوق الصورة
        function createImageBox(filename) {
            const box = document.createElement('div');
            box.className = 'photo-box';
            
            const actions = document.createElement('div');
            actions.className = 'photo-actions';
            
            const featureBtn = document.createElement('button');
            featureBtn.textContent = '⭐';
            featureBtn.title = 'تمييز';
            featureBtn.onclick = e => {
                e.stopPropagation();
                if (selected.includes(filename)) {
                    selected = selected.filter(f => f !== filename);
                    box.classList.remove('selected');
                } else {
                    selected.push(filename);
                    box.classList.add('selected');
                }
                updateFeatured();
                updateStats();
            };
            
            const hideBtn = document.createElement('button');
            hideBtn.textContent = '👁️';
            hideBtn.title = 'إخفاء/إظهار';
            hideBtn.onclick = e => {
                e.stopPropagation();
                if (hidden.includes(filename)) {
                    hidden = hidden.filter(f => f !== filename);
                    box.classList.remove('hidden');
                } else {
                    hidden.push(filename);
                    box.classList.add('hidden');
                }
                updateHidden();
                updateStats();
            };
            
            const deleteBtn = document.createElement('button');
            deleteBtn.textContent = '❌';
            deleteBtn.title = 'حذف';
            deleteBtn.onclick = e => {
                e.stopPropagation();
                if (confirm('هل تريد حذف هذه الصورة؟')) {
                    box.remove();
                    boxes = boxes.filter(b => b !== box);
                    selected = selected.filter(f => f !== filename);
                    hidden = hidden.filter(f => f !== filename);
                    updateOrder();
                    updateFeatured();
                    updateHidden();
                    updateStats();
                }
            };
            
            actions.append(featureBtn, hideBtn, deleteBtn);
            
            const img = document.createElement('img');
            img.dataset.filename = filename;
            img.loading = 'lazy';
            img.src = `temp_uploads/${tempUploadId}/${filename}`;
            
            img.onerror = function() {
                console.error('فشل تحميل الصورة:', filename);
                this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPtiz2YjYsdipINi62YrYsSDZhdiq2KfYrdipPC90ZXh0Pjwvc3ZnPg==';
            };
            
            box.append(actions, img);
            preview.append(box);
            boxes.push(box);
            
            // معالج النقر للاختيار
            img.addEventListener('click', e => {
                const idx = boxes.indexOf(box);
                if (e.shiftKey && lastIndex !== null) {
                    const [start, end] = [lastIndex, idx].sort((a, b) => a - b);
                    for (let i = start; i <= end; i++) {
                        const currentBox = boxes[i];
                        const name = currentBox.querySelector('img').dataset.filename;
                        if (!selected.includes(name)) {
                            selected.push(name);
                            currentBox.classList.add('selected');
                        }
                    }
                } else {
                    if (selected.includes(filename)) {
                        selected = selected.filter(f => f !== filename);
                        box.classList.remove('selected');
                    } else {
                        selected.push(filename);
                        box.classList.add('selected');
                    }
                    lastIndex = idx;
                }
                updateFeatured();
                updateStats();
            });
            
            updateOrder();
            updateStats();
        }
        
        // تحديث الإحصائيات
        function updateStats() {
            const totalCount = boxes.length;
            const featuredCount = selected.length;
            const hiddenCount = hidden.length;
            
            // تحديث الأرقام في البطاقات
            const statCards = document.querySelectorAll('.stat-card .stat-number');
            if (statCards[0]) statCards[0].textContent = totalCount;
            if (statCards[2]) statCards[2].textContent = featuredCount;
        }
        
        // عرض الصور الموجودة
        existingFiles.forEach(filename => {
            if (filename) createImageBox(filename);
        });
        
        // معالجات أحداث Uppy
        uppy.on('file-added', (file) => {
            console.log('تم إضافة ملف:', file.name);
        });
        
        uppy.on('upload-success', (file, response) => {
            console.log('نجح رفع الملف:', file.name, response);
            if (response.body && response.body.filename) {
                createImageBox(response.body.filename);
                // إظهار قسم الصور تلقائياً بعد الرفع
                document.getElementById('photoSection').style.display = 'block';
            } else {
                console.error('استجابة غير متوقعة:', response);
            }
        });
        
        uppy.on('upload-error', (file, error, response) => {
            console.error('خطأ في رفع الملف:', file.name, error);
            alert(`فشل في رفع الملف: ${file.name}\nالخطأ: ${error.message || 'خطأ غير معروف'}`);
        });
        
        uppy.on('upload-progress', (file, progress) => {
            const progressBar = document.querySelector('#upload-progress .progress-bar');
            const totalProgress = uppy.getState().totalProgress;
            progressBar.style.width = totalProgress + '%';
            document.getElementById('upload-progress').style.display = 'block';
        });
        
        uppy.on('complete', (result) => {
            console.log('اكتمل الرفع:', result);
            if (result.failed.length > 0) {
                console.error('فشل في رفع:', result.failed);
            }
            // إخفاء شريط التقدم بعد انتهاء الرفع
            setTimeout(() => {
                document.getElementById('upload-progress').style.display = 'none';
            }, 2000);
        });
        
        // تمكين الترتيب بالسحب
        if (typeof Sortable !== 'undefined') {
            new Sortable(preview, { 
                animation: 150, 
                onEnd: updateOrder,
                ghostClass: 'sortable-ghost'
            });
        }
        
        // ربط الدوال بـ window للوصول إليها من HTML
        window.updateOrder = updateOrder;
        window.updateFeatured = updateFeatured;
        window.updateHidden = updateHidden;
        window.createImageBox = createImageBox;
        window.updateStats = updateStats;
    });
    
    // دالة إضافة حقل يوتيوب
    function addYoutubeInput() {
        if (youtubeCount >= 7) {
            alert('الحد الأقصى 7 روابط يوتيوب');
            return;
        }
        
        youtubeCount++;
        const container = document.getElementById('youtubeInputsContainer');
        const div = document.createElement('div');
        div.className = 'mb-2';
        div.innerHTML = `
            <input type="url" name="youtube${youtubeCount}" class="form-control"
                   placeholder="رابط يوتيوب ${youtubeCount}">
        `;
        container.appendChild(div);
        
        if (youtubeCount >= 7) {
            document.getElementById('addYoutubeBtn').style.display = 'none';
        }
    }
    
    // دوال إدارة الصور
    function togglePhotos() {
        const section = document.getElementById('photoSection');
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    }
    
    function showUploadSection() {
        const section = document.getElementById('uploadSection');
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    }
    
    function selectAllPhotos() {
        selected = [];
        boxes.forEach(box => {
            const filename = box.querySelector('img').dataset.filename;
            selected.push(filename);
            box.classList.add('selected');
        });
        window.updateFeatured();
        window.updateStats();
    }
    
    function deselectAllPhotos() {
        selected = [];
        boxes.forEach(box => {
            box.classList.remove('selected');
        });
        window.updateFeatured();
        window.updateStats();
    }
    
    function hideAllPhotos() {
        hidden = [];
        boxes.forEach(box => {
            const filename = box.querySelector('img').dataset.filename;
            hidden.push(filename);
            box.classList.add('hidden');
        });
        window.updateHidden();
        window.updateStats();
    }
    
    function showAllPhotos() {
        hidden = [];
        boxes.forEach(box => {
            box.classList.remove('hidden');
        });
        window.updateHidden();
        window.updateStats();
    }
    
    function copyLink() {
        const linkInput = document.getElementById('groomLink');
        linkInput.select();
        linkInput.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> تم النسخ!';
            button.classList.remove('btn-outline-primary');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-primary');
            }, 2000);
        } catch (err) {
            alert('فشل في نسخ الرابط. يرجى النسخ يدوياً.');
        }
    }
    
    // التحقق من صحة النموذج قبل الإرسال
    document.getElementById('groomForm')?.addEventListener('submit', function(e) {
        const groomName = this.querySelector('input[name="groom_name"]').value.trim();
        if (!groomName) {
            e.preventDefault();
            alert('اسم العريس مطلوب');
            return false;
        }
        
        // تحديث الحقول المخفية قبل الإرسال
        window.updateOrder();
        window.updateFeatured();
        window.updateHidden();
        
        // إظهار رسالة تحميل
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div>جاري الحفظ...';
        submitBtn.disabled = true;
        
        // إعادة تفعيل الزر في حالة فشل الإرسال
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 30000);
    });
    </script>
</body>
</html>