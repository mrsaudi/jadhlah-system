<?php
// admin-live-gallery.php - لوحة التحكم
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// الاتصال بقاعدة البيانات
$host = 'localhost';
$db   = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// التحقق من وجود الجداول وإنشائها إذا لزم الأمر
$conn->query("CREATE TABLE IF NOT EXISTS `grooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_name` (`folder_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `groom_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `groom_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `groom_id` (`groom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `active_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `groom_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `live_gallery_enabled` tinyint(1) DEFAULT 1,
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `groom_id` (`groom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $photoId = (int)($_POST['photo_id'] ?? 0);
    
    if ($action === 'delete' && $photoId) {
        // حذف الصورة
        $stmt = $conn->prepare("SELECT filename FROM live_gallery_photos WHERE id = ?");
        $stmt->bind_param("i", $photoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $photo = $result->fetch_assoc();
        
        if ($photo) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/live/' . $photo['filename'];
            $thumbPath = str_replace('.jpg', '_thumb.jpg', $filePath);
            $thumbPath = str_replace('.jpeg', '_thumb.jpg', $thumbPath);
            $thumbPath = str_replace('.JPG', '_thumb.jpg', $thumbPath);
            $thumbPath = str_replace('.JPEG', '_thumb.jpg', $thumbPath);
            
            if (file_exists($filePath)) unlink($filePath);
            if (file_exists($thumbPath)) unlink($thumbPath);
            
            $stmt = $conn->prepare("DELETE FROM live_gallery_photos WHERE id = ?");
            $stmt->bind_param("i", $photoId);
            $stmt->execute();
        }
        
        header('Location: admin-live-gallery.php?deleted=1');
        exit;
    }
    
    if ($action === 'move_to_groom' && $photoId) {
        $groomId = (int)($_POST['groom_id'] ?? 0);
        
        if ($groomId) {
            // الحصول على معلومات الصورة والعريس
            $stmt = $conn->prepare("SELECT filename FROM live_gallery_photos WHERE id = ?");
            $stmt->bind_param("i", $photoId);
            $stmt->execute();
            $result = $stmt->get_result();
            $photo = $result->fetch_assoc();
            
            $stmt = $conn->prepare("SELECT folder_name FROM grooms WHERE id = ?");
            $stmt->bind_param("i", $groomId);
            $stmt->execute();
            $result = $stmt->get_result();
            $groom = $result->fetch_assoc();
            
            if ($photo && $groom) {
                $sourcePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/live/' . $photo['filename'];
                $groomFolder = $_SERVER['DOCUMENT_ROOT'] . '/grooms/' . $groom['folder_name'] . '/';
                
                if (!is_dir($groomFolder)) {
                    mkdir($groomFolder, 0755, true);
                }
                
                $destPath = $groomFolder . $photo['filename'];
                copy($sourcePath, $destPath);
                
                // إضافة للجدول
                $stmt = $conn->prepare("INSERT INTO groom_photos (groom_id, filename, created_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("is", $groomId, $photo['filename']);
                $stmt->execute();
                
                // تحديث الصورة
                $stmt = $conn->prepare("UPDATE live_gallery_photos SET groom_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $groomId, $photoId);
                $stmt->execute();
            }
        }
        
        header('Location: admin-live-gallery.php?moved=1');
        exit;
    }
}

// جلب الإحصائيات
$stats = [
    'active' => 0,
    'expired' => 0,
    'expiring_soon' => 0,
    'total' => 0
];

try {
    $result = $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos WHERE is_expired = 0 AND expires_at > NOW()");
    if ($result) {
        $stats['active'] = $result->fetch_assoc()['c'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos WHERE is_expired = 1");
    if ($result) {
        $stats['expired'] = $result->fetch_assoc()['c'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos WHERE is_expired = 0 AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)");
    if ($result) {
        $stats['expiring_soon'] = $result->fetch_assoc()['c'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos");
    if ($result) {
        $stats['total'] = $result->fetch_assoc()['c'];
    }
} catch (Exception $e) {
    // تجاهل الأخطاء
}

// جلب الصور (مع الفلترة)
$filter = $_GET['filter'] ?? 'all';
$where = '';

switch ($filter) {
    case 'active':
        $where = "WHERE p.is_expired = 0 AND p.expires_at > NOW()";
        break;
    case 'expired':
        $where = "WHERE p.is_expired = 1";
        break;
    case 'expiring_soon':
        $where = "WHERE p.is_expired = 0 AND p.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)";
        break;
    default:
        $where = "";
}

$photos = [];
$result = $conn->query("
    SELECT p.*, g.name as groom_name 
    FROM live_gallery_photos p
    LEFT JOIN grooms g ON p.groom_id = g.id
    $where
    ORDER BY p.uploaded_at DESC
    LIMIT 100
");

if ($result) {
    $photos = $result->fetch_all(MYSQLI_ASSOC);
}

// جلب قائمة العرسان
$grooms = [];
$result = $conn->query("SELECT id, name FROM grooms ORDER BY name");
if ($result) {
    $grooms = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المعرض الحي</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: -30px auto 30px;
            padding: 0 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card i {
            font-size: 40px;
            margin-bottom: 15px;
            display: block;
        }
        
        .stat-card.active i { color: #4CAF50; }
        .stat-card.expired i { color: #f44336; }
        .stat-card.warning i { color: #ff9800; }
        .stat-card.total i { color: #2196F3; }
        
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .filters {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-btn {
            padding: 12px 25px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
            font-weight: 600;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: #D4AF37;
            border-color: #D4AF37;
            color: white;
        }
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .photo-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .photo-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .photo-info {
            padding: 15px;
        }
        
        .photo-meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .photo-meta div {
            margin-bottom: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .status-badge.active {
            background: #e8f5e9;
            color: #4CAF50;
        }
        
        .status-badge.expired {
            background: #ffebee;
            color: #f44336;
        }
        
        .status-badge.warning {
            background: #fff3e0;
            color: #ff9800;
        }
        
        .photo-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .action-btn.delete {
            background: #ffebee;
            color: #f44336;
        }
        
        .action-btn.move {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h2 {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Tajawal', sans-serif;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            font-family: 'Tajawal', sans-serif;
        }
        
        .btn-primary {
            background: #D4AF37;
            color: white;
        }
        
        .btn-secondary {
            background: #ddd;
            color: #333;
        }
        
        .empty-message {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px;
            color: #999;
        }
        
        .empty-message i {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
            color: #ddd;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .photos-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 لوحة تحكم المعرض الحي</h1>
        <p>إدارة الصور والأرشيف</p>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card active">
            <i class="fas fa-check-circle"></i>
            <h3><?php echo $stats['active']; ?></h3>
            <p>صور نشطة</p>
        </div>
        
        <div class="stat-card warning">
            <i class="fas fa-exclamation-triangle"></i>
            <h3><?php echo $stats['expiring_soon']; ?></h3>
            <p>تنتهي قريباً</p>
        </div>
        
        <div class="stat-card expired">
            <i class="fas fa-times-circle"></i>
            <h3><?php echo $stats['expired']; ?></h3>
            <p>صور منتهية</p>
        </div>
        
        <div class="stat-card total">
            <i class="fas fa-images"></i>
            <h3><?php echo $stats['total']; ?></h3>
            <p>إجمالي الصور</p>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">✅ تم حذف الصورة بنجاح</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['moved'])): ?>
            <div class="alert alert-success">✅ تم نقل الصورة للعريس بنجاح</div>
        <?php endif; ?>
        
        <div class="filters">
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> الكل
            </a>
            <a href="?filter=active" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">
                <i class="fas fa-check"></i> نشطة
            </a>
            <a href="?filter=expiring_soon" class="filter-btn <?php echo $filter === 'expiring_soon' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> تنتهي قريباً
            </a>
            <a href="?filter=expired" class="filter-btn <?php echo $filter === 'expired' ? 'active' : ''; ?>">
                <i class="fas fa-times"></i> منتهية
            </a>
        </div>
        
        <div class="photos-grid">
            <?php if (empty($photos)): ?>
                <div class="empty-message">
                    <i class="fas fa-images"></i>
                    <h3>لا توجد صور حالياً</h3>
                    <p>الصور ستظهر هنا بمجرد رفعها</p>
                </div>
            <?php else: ?>
                <?php foreach ($photos as $photo): 
                    $imagePath = '/uploads/live/' . $photo['filename'];
                    $thumbPath = '/uploads/live/' . str_replace(['.jpg', '.jpeg', '.JPG', '.JPEG'], '_thumb.jpg', $photo['filename']);
                    
                    $time = strtotime($photo['uploaded_at']);
                    $expiresTime = strtotime($photo['expires_at']);
                    $now = time();
                    
                    if ($photo['is_expired']) {
                        $status = 'expired';
                        $statusText = 'منتهية';
                    } elseif ($expiresTime - $now < 7200) {
                        $status = 'warning';
                        $statusText = 'تنتهي قريباً';
                    } else {
                        $status = 'active';
                        $statusText = 'نشطة';
                    }
                    
                    $displayImage = file_exists($_SERVER['DOCUMENT_ROOT'] . $thumbPath) ? $thumbPath : $imagePath;
                ?>
                <div class="photo-item">
                    <img src="<?php echo $displayImage; ?>" alt="صورة">
                    <div class="photo-info">
                        <span class="status-badge <?php echo $status; ?>">
                            <?php echo $statusText; ?>
                        </span>
                        
                        <div class="photo-meta">
                            <div><i class="fas fa-clock"></i> <?php echo date('Y-m-d H:i', $time); ?></div>
                            <div><i class="fas fa-hourglass-end"></i> ينتهي: <?php echo date('Y-m-d H:i', $expiresTime); ?></div>
                            <?php if (!empty($photo['groom_name'])): ?>
                            <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($photo['groom_name']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="photo-actions">
                            <button class="action-btn move" onclick="openMoveModal(<?php echo $photo['id']; ?>)">
                                <i class="fas fa-arrow-right"></i> نقل
                            </button>
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="photo_id" value="<?php echo $photo['id']; ?>">
                                <button type="submit" class="action-btn delete">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal نقل الصورة -->
    <div class="modal" id="moveModal">
        <div class="modal-content">
            <h2>نقل الصورة لعريس</h2>
            <form method="POST">
                <input type="hidden" name="action" value="move_to_groom">
                <input type="hidden" name="photo_id" id="movePhotoId">
                
                <div class="form-group">
                    <label>اختر العريس:</label>
                    <select name="groom_id" required>
                        <option value="">-- اختر --</option>
                        <?php foreach ($grooms as $groom): ?>
                        <option value="<?php echo $groom['id']; ?>"><?php echo htmlspecialchars($groom['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">نقل</button>
                    <button type="button" class="btn btn-secondary" onclick="closeMoveModal()">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function openMoveModal(photoId) {
        document.getElementById('movePhotoId').value = photoId;
        document.getElementById('moveModal').classList.add('active');
    }
    
    function closeMoveModal() {
        document.getElementById('moveModal').classList.remove('active');
    }
    
    // إغلاق المودال عند الضغط خارجه
    document.getElementById('moveModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeMoveModal();
        }
    });
    </script>
</body>
</html>