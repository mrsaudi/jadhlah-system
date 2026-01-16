<?php
// ===================================
// admin/edit_groom_updated.php - نسخة محدثة لتعديل العريس
// ===================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$groomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($groomId <= 0) {
    die('رقم العريس غير صحيح');
}

// جلب بيانات العريس
$stmt = $pdo->prepare("SELECT * FROM grooms WHERE id = ?");
$stmt->execute([$groomId]);
$groom = $stmt->fetch();
if (!$groom) die('العريس غير موجود');

$errorMessage = '';
$successMessage = '';
$groomLink = '';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. جمع البيانات الأساسية
        $groom_name = trim($_POST['groom_name'] ?? '');
        $wedding_date = trim($_POST['wedding_date'] ?? '');
        $hall_name = trim($_POST['hall_name'] ?? '');
        $event_name = trim($_POST['event_name'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($groom_name)) {
            throw new Exception('اسم العريس مطلوب');
        }
        
        // 2. جمع روابط يوتيوب
        $youtubeData = [];
        for ($i = 1; $i <= 7; $i++) {
            $youtubeData["youtube$i"] = trim($_POST["youtube$i"] ?? '');
        }
        
        // 3. تحديث البيانات الأساسية
        $stmt = $pdo->prepare("
            UPDATE grooms SET
                groom_name = ?, wedding_date = ?, hall_name = ?, event_name = ?, notes = ?,
                youtube1 = ?, youtube2 = ?, youtube3 = ?, youtube4 = ?, youtube5 = ?, youtube6 = ?, youtube7 = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $groom_name, $wedding_date, $hall_name, $event_name, $notes,
            $youtubeData['youtube1'], $youtubeData['youtube2'], $youtubeData['youtube3'],
            $youtubeData['youtube4'], $youtubeData['youtube5'], $youtubeData['youtube6'],
            $youtubeData['youtube7'],
            $groomId
        ]);
        
        // 4. معالجة البنر الجديد (مع إنشاء النسخ الثلاث)
        if (!empty($_FILES['banner']['name']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            if (isValidImageMime($_FILES['banner']['tmp_name'])) {
                $groomDir = GROOMS_BASE . '/' . $groomId;
                
                // مسارات البنر
                $bannerOriginal = $groomDir . '/banner.jpg';
                $bannerModal = $groomDir . '/modal_thumb/banner.jpg';
                $bannerThumb = $groomDir . '/thumbs/banner.jpg';
                
                // رفع الصورة الأصلية
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $bannerOriginal)) {
                    // إنشاء النسخ المصغرة
                    createThumbnailEnhanced($bannerOriginal, $bannerModal, 1500);  // مودال
                    createThumbnailEnhanced($bannerOriginal, $bannerThumb, 300);   // شبكي
                    
                    $stmt = $pdo->prepare("UPDATE grooms SET banner = 'banner.jpg' WHERE id = ?");
                    $stmt->execute([$groomId]);
                    
                    logError("تم تحديث البنر للعريس #$groomId مع ثلاث نسخ", 'groom_update');
                }
            }
        }
        
        // 5. معالجة الصور المميزة والمخفية
        $featuredPhotos = json_decode($_POST['featured_json'] ?? '[]', true);
        $hiddenPhotos = json_decode($_POST['hidden_json'] ?? '[]', true);
        
        // إزالة جميع التحديدات السابقة
        $stmt = $pdo->prepare("UPDATE groom_photos SET is_featured = 0, hidden = 0 WHERE groom_id = ?");
        $stmt->execute([$groomId]);
        
        // تحديد الصور الجديدة كمميزة
        if (is_array($featuredPhotos) && !empty($featuredPhotos)) {
            $stmt = $pdo->prepare("UPDATE groom_photos SET is_featured = 1 WHERE groom_id = ? AND filename = ?");
            foreach ($featuredPhotos as $filename) {
                $stmt->execute([$groomId, $filename]);
            }
        }
        
        // تحديد الصور المخفية
        if (is_array($hiddenPhotos) && !empty($hiddenPhotos)) {
            $stmt = $pdo->prepare("UPDATE groom_photos SET hidden = 1 WHERE groom_id = ? AND filename = ?");
            foreach ($hiddenPhotos as $filename) {
                $stmt->execute([$groomId, $filename]);
            }
        }
        
        // 6. معالجة حذف الصور المحددة
        if (!empty($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $imageId) {
                deleteGroomImageEnhanced($imageId, $groomId);
            }
        }
        
        $successMessage = 'تم تحديث البيانات بنجاح';
        $groomLink = "https://jadhlah.com/groom.php?groom=" . $groomId;
        
        logError("تم تحديث بيانات العريس #$groomId", 'groom_update');
        
    } catch (Exception $e) {
        $errorMessage = "خطأ: " . $e->getMessage();
        logError("خطأ في تحديث العريس #$groomId: " . $e->getMessage(), 'errors');
    }
}

// جلب الصور الحالية
$photosStmt = $pdo->prepare("
    SELECT * FROM groom_photos 
    WHERE groom_id = ? 
    ORDER BY photo_order ASC, id ASC
");
$photosStmt->execute([$groomId]);
$photos = $photosStmt->fetchAll();

// حساب الإحصائيات
$totalPhotos = count($photos);
$featuredCount = 0;
$hiddenCount = 0;
foreach ($photos as $photo) {
    if ($photo['is_featured']) $featuredCount++;
    if ($photo['hidden']) $hiddenCount++;
}

/**
 * حذف صورة مع جميع النسخ (الأصلية + المودال + الشبكي)
 */
function deleteGroomImageEnhanced($imageId, $groomId) {
    global $pdo;
    
    // جلب معلومات الصورة
    $stmt = $pdo->prepare("SELECT filename FROM groom_photos WHERE id = ? AND groom_id = ?");
    $stmt->execute([$imageId, $groomId]);
    $image = $stmt->fetch();
    
    if ($image) {
        $filename = $image['filename'];
        $groomDir = GROOMS_BASE . '/' . $groomId;
        
        // مسارات الصور الثلاث
        $paths = [
            $groomDir . '/originals/' . $filename,
            $groomDir . '/modal_thumb/' . $filename,
            $groomDir . '/thumbs/' . $filename,
            $groomDir . '/temp/' . $filename  // إضافي
        ];
        
        // حذف الملفات الفعلية
        foreach ($paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        
        // حذف السجل من قاعدة البيانات
        $stmt = $pdo->prepare("DELETE FROM groom_photos WHERE id = ?");
        $stmt->execute([$imageId]);
        
        // حذف من قائمة الانتظار إن وجد
        $stmt = $pdo->prepare("DELETE FROM upload_queue WHERE groom_id = ? AND filename = ?");
        $stmt->execute([$groomId, $filename]);
        
        logError("تم حذف الصورة $filename مع جميع النسخ للعريس #$groomId", 'image_delete');
    }
}

/**
 * دالة إنشاء صورة مصغرة محسنة (نفس الدالة من add_groom)
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
    <title>تعديل العريس - <?= htmlspecialchars($groom['groom_name']) ?></title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link href="https://releases.transloadit.com/uppy/v3.25.4/uppy.min.css" rel="stylesheet">
    <script src="https://releases.transloadit.com/uppy/v3.25.4/uppy.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    
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
        
        #drag-drop-area {
            min-height: 300px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px dashed #ddd;
            margin-bottom: 20px;
        }
        
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
        
        .banner-preview {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
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
        
        .processing-info {
            background: #fff3cd;
            color: #856404;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 13px;
        }
        
        
        /* زر الحفظ العائم */
.floating-save-btn {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #4caf50, #45a049);
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 50px;
    font-size: 18px;
    font-weight: bold;
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
    cursor: pointer;
    z-index: 9999;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.floating-save-btn.show {
    opacity: 1;
    visibility: visible;
    pointer-events: all;
}

.floating-save-btn:hover {
    transform: translateX(-50%) translateY(-3px);
    box-shadow: 0 8px 25px rgba(76, 175, 80, 0.5);
}

.floating-save-btn:active {
    transform: translateX(-50%) translateY(-1px);
}

.floating-save-btn .badge {
    background: rgba(255, 255, 255, 0.3);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 14px;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.floating-save-btn .icon {
    font-size: 20px;
    animation: rotate 2s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* إخفاء الزر عند التمرير لأعلى الصفحة */
.floating-save-btn.hide {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>تعديل بيانات العريس</h1>
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
                            يمكنك مشاركة هذا الرابط لعرض بيانات العريس المحدثة
                        </small>
                    </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <a href="edit_groom_updated.php?id=<?= $groomId ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> متابعة التعديل
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
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalPhotos ?></div>
                    <div class="stat-label">إجمالي الصور</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?= $featuredCount ?></div>
                    <div class="stat-label">صور مميزة</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?= $hiddenCount ?></div>
                    <div class="stat-label">صور مخفية</div>
                </div>
            </div>
        </div>
        
        <!-- Main Form -->
        <form method="POST" enctype="multipart/form-data" id="editForm">
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
                                   value="<?= htmlspecialchars($groom['event_name'] ?? '') ?>"
                                   placeholder="مثال: حفل زواج أحمد ومريم">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم العريس *</label>
                            <input type="text" name="groom_name" class="form-control" 
                                   value="<?= htmlspecialchars($groom['groom_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ الزواج</label>
                            <input type="text" name="wedding_date" class="form-control" 
                                   value="<?= htmlspecialchars($groom['wedding_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم القاعة</label>
                            <input type="text" name="hall_name" class="form-control" 
                                   value="<?= htmlspecialchars($groom['hall_name'] ?? '') ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($groom['notes'] ?? '') ?></textarea>
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
                    <?php if (!empty($groom['banner'])): ?>
                        <img src="/grooms/<?= $groomId ?>/modal_thumb/banner.jpg?<?= time() ?>" 
                             class="banner-preview" alt="البنر الحالي">
                    <?php else: ?>
                        <p class="text-muted">لا توجد صورة بنر حالياً</p>
                    <?php endif; ?>
                    <input type="file" name="banner" class="form-control" accept="image/*">
                    <div class="processing-info">
                        سيتم إنشاء 3 نسخ للبنر: أصلية + مودال (1500px) + شبكي (300px)
                    </div>
                </div>
            </div>
            
            <!-- YouTube Links Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">روابط يوتيوب</h5>
                </div>
                <div class="card-body">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <div class="mb-2">
                            <input type="url" name="youtube<?= $i ?>" class="form-control"
                                   placeholder="رابط يوتيوب <?= $i ?>"
                                   value="<?= htmlspecialchars($groom["youtube$i"] ?? '') ?>">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Hidden Inputs for Photo Management -->
            <input type="hidden" id="featured_input" name="featured_json" value="[]">
            <input type="hidden" id="hidden_input" name="hidden_json" value="[]">
            
            <!-- Action Buttons -->
            <div class="d-flex gap-2 my-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> حفظ التعديلات
                </button>
                <button type="button" class="btn btn-secondary" onclick="togglePhotos()">
                    <i class="bi bi-images"></i> إظهار/إخفاء الصور
                </button>
                <button type="button" class="btn btn-info" onclick="showUploadSection()">
                    <i class="bi bi-cloud-upload"></i> رفع صور جديدة
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteAll()">
                    <i class="bi bi-trash"></i> حذف جميع الصور
                </button>
            </div>
            

        </form>
                    <!-- زر الحفظ العائم -->
<!-- ============================================ -->
<!-- زر الحفظ العائم - خارج الفورم -->
<!-- ============================================ -->
<button type="button" id="floatingSaveBtn" class="floating-save-btn show" onclick="saveChanges()" style="display: flex !important; opacity: 1 !important; visibility: visible !important;">
    <span style="font-size: 24px;">💾</span>
    <span style="font-size: 18px; font-weight: bold;">حفظ التغييرات</span>
    <span class="badge" id="changeCounter" style="background: rgba(255,255,255,0.3); padding: 5px 12px; border-radius: 20px;">3</span>
</button>

<style>
/* زر الحفظ العائم - مضمون 100% */
.floating-save-btn {
    position: fixed !important;
    bottom: 30px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    background: linear-gradient(135deg, #4caf50, #45a049) !important;
    color: white !important;
    border: none !important;
    padding: 15px 40px !important;
    border-radius: 50px !important;
    font-size: 18px !important;
    font-weight: bold !important;
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4) !important;
    cursor: pointer !important;
    z-index: 999999 !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    transition: all 0.3s ease !important;
}

.floating-save-btn:hover {
    transform: translateX(-50%) translateY(-3px) !important;
    box-shadow: 0 8px 25px rgba(76, 175, 80, 0.5) !important;
}
</style>
        
        <!-- Photo Upload Card -->
        <div class="card" id="uploadSection" style="display:none;">
            <div class="card-header">
                <h5 class="mb-0">رفع صور جديدة</h5>
            </div>
            <div class="card-body">
                <div class="processing-info mb-3">
                    💡 الصور الجديدة ستُعالج بثلاث أحجام: أصلية + مودال (1500px) + شبكي (300px)
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
                <h5 class="mb-0">الصور الحالية (<?= $totalPhotos ?>)</h5>
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
                    <?php foreach ($photos as $index => $photo): ?>
                        <div class="photo-box <?= $photo['is_featured'] ? 'selected' : '' ?> <?= $photo['hidden'] ? 'hidden' : '' ?>"
                             data-id="<?= $photo['id'] ?>"
                             data-index="<?= $index ?>"
                             data-filename="<?= htmlspecialchars($photo['filename']) ?>">
                            <img src="/grooms/<?= $groomId ?>/thumbs/<?= htmlspecialchars($photo['filename']) ?>" 
                                 loading="lazy" decoding="async" alt="">
                            <div class="photo-actions">
                                <button type="button" class="btn-feature" title="تمييز">⭐</button>
                                <button type="button" class="btn-hide" title="إخفاء">👁️</button>
                                <button type="button" class="btn-delete" title="حذف">❌</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // متغيرات عامة
    let selected = [];
    let hidden = [];
    let lastIndex = null;
    const groomId = <?= $groomId ?>;
    const boxes = Array.from(document.querySelectorAll('.photo-box'));
    
    // تعبئة الصور المميزة والمخفية الحالية
    document.addEventListener('DOMContentLoaded', function() {
        boxes.forEach((box) => {
            const filename = box.dataset.filename;
            if (box.classList.contains('selected')) {
                selected.push(filename);
            }
            if (box.classList.contains('hidden')) {
                hidden.push(filename);
            }
        });
        updateInputs();
    });
    
    // تهيئة Uppy للرفع الجديد
    const uppy = new Uppy.Uppy({
        debug: true,
        autoProceed: false,
        restrictions: {
            maxNumberOfFiles: 2000,
            allowedFileTypes: ['image/*'],
            // maxFileSize: 10 * 1024 * 1024
        },
        locale: {
            strings: {
                dropPasteFiles: 'اسحب الصور هنا أو %{browseFiles}',
                browseFiles: 'تصفح الملفات',
                uploading: 'جاري الرفع...',
                complete: 'اكتمل الرفع',
                uploadComplete: 'اكتمل الرفع'
            }
        }
    });
    
    // واجهة Dashboard
    uppy.use(Uppy.Dashboard, {
        inline: true,
        target: '#drag-drop-area',
        proudlyDisplayPoweredByUppy: false,
        height: 300
    });
    
    // رفع الملفات
    uppy.use(Uppy.XHRUpload, {
        endpoint: `upload_chunked.php?groom_id=${groomId}`,
        fieldName: 'file',
        formData: true,
        bundle: false
    });
    
    // معالجات أحداث Uppy
    uppy.on('upload-progress', (file, progress) => {
        const progressBar = document.querySelector('#upload-progress .progress-bar');
        const totalProgress = uppy.getState().totalProgress;
        progressBar.style.width = totalProgress + '%';
        document.getElementById('upload-progress').style.display = 'block';
    });
    
    uppy.on('complete', (result) => {
        if (result.successful.length > 0) {
            alert(`تم رفع ${result.successful.length} صورة بنجاح! ستتم معالجتها بثلاث أحجام.`);
            setTimeout(() => location.reload(), 2000);
        }
    });
    
    // معالجات النقر على الصور
    boxes.forEach((box, index) => {
        box.addEventListener('click', (e) => {
            if (e.target.closest('.photo-actions')) return;
            
            const filename = box.dataset.filename;
            
            if (e.shiftKey && lastIndex !== null) {
                const start = Math.min(lastIndex, index);
                const end = Math.max(lastIndex, index);
                for (let i = start; i <= end; i++) {
                    const b = boxes[i];
                    const fn = b.dataset.filename;
                    if (!selected.includes(fn)) {
                        selected.push(fn);
                        b.classList.add('selected');
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
                lastIndex = index;
            }
            
            updateInputs();
        });
    });
    
    // معالجات أزرار الصور
    document.querySelectorAll('.btn-feature').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const box = e.target.closest('.photo-box');
            const filename = box.dataset.filename;
            
            if (selected.includes(filename)) {
                selected = selected.filter(f => f !== filename);
                box.classList.remove('selected');
            } else {
                selected.push(filename);
                box.classList.add('selected');
            }
            
            updateInputs();
        });
    });
    
    document.querySelectorAll('.btn-hide').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const box = e.target.closest('.photo-box');
            const filename = box.dataset.filename;
            
            if (hidden.includes(filename)) {
                hidden = hidden.filter(f => f !== filename);
                box.classList.remove('hidden');
            } else {
                hidden.push(filename);
                box.classList.add('hidden');
            }
            
            updateInputs();
        });
    });
    
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            if (confirm("هل أنت متأكد من حذف هذه الصورة مع جميع نسخها؟")) {
                const box = e.target.closest('.photo-box');
                const imageId = box.dataset.id;
                
                // إضافة إلى قائمة الحذف
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_images[]';
                deleteInput.value = imageId;
                document.getElementById('editForm').appendChild(deleteInput);
                
                // إخفاء الصورة
                box.style.display = 'none';
            }
        });
    });
    
    // دوال مساعدة
    function updateInputs() {
        document.getElementById('featured_input').value = JSON.stringify(selected);
        document.getElementById('hidden_input').value = JSON.stringify(hidden);
    }
    
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
            const filename = box.dataset.filename;
            selected.push(filename);
            box.classList.add('selected');
        });
        updateInputs();
    }
    
    function deselectAllPhotos() {
        selected = [];
        boxes.forEach(box => {
            box.classList.remove('selected');
        });
        updateInputs();
    }
    
    function hideAllPhotos() {
        hidden = [];
        boxes.forEach(box => {
            const filename = box.dataset.filename;
            hidden.push(filename);
            box.classList.add('hidden');
        });
        updateInputs();
    }
    
    function showAllPhotos() {
        hidden = [];
        boxes.forEach(box => {
            box.classList.remove('hidden');
        });
        updateInputs();
    }
    
    function confirmDeleteAll() {
        if (confirm("هل أنت متأكد أنك تريد حذف جميع صور هذا العريس مع جميع نسخها؟")) {
            fetch('delete_all_photos.php?groom_id=<?= $groomId ?>')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('تم حذف جميع الصور بنجاح');
                        location.reload();
                    } else {
                        alert('فشل الحذف: ' + (data.error || 'غير معروف'));
                    }
                })
                .catch(err => {
                    alert('حدث خطأ في الاتصال بالخادم');
                });
        }
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
    
    // تمكين السحب والترتيب
    if (typeof Sortable !== 'undefined') {
        new Sortable(document.getElementById('photoGallery'), {
            animation: 150,
            onEnd: function(evt) {
                // يمكن إضافة حفظ الترتيب هنا إذا أردت
                console.log('تم تغيير ترتيب الصور');
            }
        });
    }
    
    // ============================================
// نظام الزر العائم للحفظ
// ============================================

let changesMade = 0;
const floatingBtn = document.getElementById('floatingSaveBtn');
const changeCounter = document.getElementById('changeCounter');
const editForm = document.getElementById('editForm');

// تحديث عداد التغييرات
function updateChangeCounter() {
    changesMade = selected.length + hidden.length;
    
    // إضافة الصور المحددة للحذف
    const deleteInputs = document.querySelectorAll('input[name="delete_images[]"]');
    changesMade += deleteInputs.length;
    
    if (changesMade > 0) {
        floatingBtn.classList.add('show');
        changeCounter.style.display = 'inline-block';
        changeCounter.textContent = changesMade;
    } else {
        floatingBtn.classList.remove('show');
        changeCounter.style.display = 'none';
    }
}

// حفظ التغييرات
// حفظ التغييرات - بدون حوارات
function saveChanges() {
    // تحديث الحقول المخفية
    updateInputs();
    
    // إرسال النموذج مباشرة
    editForm.submit();
}

// مراقبة التغييرات على الصور
const originalUpdateInputs = updateInputs;
updateInputs = function() {
    originalUpdateInputs();
    updateChangeCounter();
};

// مراقبة التغييرات على الحقول النصية
document.querySelectorAll('input[type="text"], input[type="url"], textarea, input[type="file"]').forEach(input => {
    input.addEventListener('change', updateChangeCounter);
});

// إخفاء الزر عند التمرير لأعلى الصفحة
let lastScrollTop = 0;
window.addEventListener('scroll', function() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop < 100) {
        floatingBtn.classList.add('hide');
    } else {
        floatingBtn.classList.remove('hide');
    }
    
    lastScrollTop = scrollTop;
}, false);

// تحديث العداد عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    updateChangeCounter();
});

// منع مغادرة الصفحة مع تغييرات غير محفوظة
// منع مغادرة الصفحة مع تغييرات غير محفوظة - معطّل
/*
window.addEventListener('beforeunload', function(e) {
    if (changesMade > 0) {
        e.preventDefault();
        e.returnValue = 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟';
        return e.returnValue;
    }
});
*/

// إعادة تعيين العداد بعد الحفظ الناجح
if (document.querySelector('.success-section')) {
    changesMade = 0;
    floatingBtn.classList.remove('show');
}

    </script>
    

</body>
</html>