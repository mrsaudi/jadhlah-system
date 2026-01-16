<?php
// admin/manage-photos-ultimate.php - النسخة النهائية مع إدارة كاملة للأرشيف
session_start();

// البحث عن ملف قاعدة البيانات
$dbPaths = ['../config/database.php', '../../config/database.php', '../database.php'];
$dbFound = false;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbFound = true;
        break;
    }
}

if (!$dbFound) {
    die("خطأ: لم يتم العثور على ملف قاعدة البيانات");
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("فشل الاتصال: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// تسجيل دخول مؤقت للاختبار
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'Admin';
}

// دالة تسجيل الإجراءات
function logAdminAction($conn, $photoId, $action, $groomId = null, $adminId = null) {
    $tableExists = $conn->query("SHOW TABLES LIKE 'admin_photo_logs'")->num_rows > 0;
    if (!$tableExists) {
        $conn->query("CREATE TABLE `admin_photo_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `photo_id` INT NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `groom_id` INT NULL,
            `admin_id` INT NULL,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    $stmt = $conn->prepare("
        INSERT INTO admin_photo_logs (photo_id, action, groom_id, admin_id, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    if ($stmt) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param("isiiss", $photoId, $action, $groomId, $adminId, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

// معالجة الإجراءات الجماعية
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // إذا كان طلب AJAX، نرسل JSON headers أولاً
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    $response = ['success' => false, 'message' => ''];
    
    $action = $_POST['action'] ?? '';
    $adminId = $_SESSION['admin_id'] ?? 0;
    
    // معالجة الإجراءات الجماعية
    if (isset($_POST['photo_ids']) && is_array($_POST['photo_ids'])) {
        $photoIds = array_map('intval', $_POST['photo_ids']);
        $successCount = 0;
        
        foreach ($photoIds as $photoId) {
            if ($photoId > 0) {
                switch ($action) {
                    case 'hide_multiple':
                        $stmt = $conn->prepare("UPDATE live_gallery_photos SET is_hidden = 1, hidden_at = NOW(), hidden_by = ? WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("ii", $adminId, $photoId);
                            if ($stmt->execute()) {
                                $successCount++;
                                logAdminAction($conn, $photoId, 'hide', null, $adminId);
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'show_multiple':
                        $stmt = $conn->prepare("UPDATE live_gallery_photos SET is_hidden = 0, hidden_at = NULL, hidden_by = NULL WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $photoId);
                            if ($stmt->execute()) {
                                $successCount++;
                                logAdminAction($conn, $photoId, 'show', null, $adminId);
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'restore_multiple':
                        // استرجاع الصور المؤرشفة للبث المباشر
                        $stmt = $conn->prepare("UPDATE live_gallery_photos SET expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR), is_expired = 0 WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $photoId);
                            if ($stmt->execute()) {
                                $successCount++;
                                logAdminAction($conn, $photoId, 'restore', null, $adminId);
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'archive_multiple':
                        // أرشفة الصور فوراً
                        $stmt = $conn->prepare("UPDATE live_gallery_photos SET expires_at = NOW(), is_expired = 1 WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $photoId);
                            if ($stmt->execute()) {
                                $successCount++;
                                logAdminAction($conn, $photoId, 'archive', null, $adminId);
                            }
                            $stmt->close();
                        }
                        break;
                        
                    case 'transfer_multiple':
                        $groomId = intval($_POST['groom_id'] ?? 0);
                        if ($groomId > 0) {
                            $stmt = $conn->prepare("UPDATE live_gallery_photos SET groom_id = ?, transferred_to_groom = 1, transferred_at = NOW() WHERE id = ?");
                            if ($stmt) {
                                $stmt->bind_param("ii", $groomId, $photoId);
                                if ($stmt->execute()) {
                                    $successCount++;
                                    logAdminAction($conn, $photoId, 'transfer', $groomId, $adminId);
                                }
                                $stmt->close();
                            }
                        }
                        break;
                        
                    case 'delete_multiple':
                        $stmt = $conn->prepare("DELETE FROM live_gallery_photos WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $photoId);
                            if ($stmt->execute()) {
                                $successCount++;
                                logAdminAction($conn, $photoId, 'delete', null, $adminId);
                            }
                            $stmt->close();
                        }
                        break;
                }
            }
        }
        
        $response = [
            'success' => $successCount > 0,
            'message' => "تم تنفيذ الإجراء على $successCount صورة من أصل " . count($photoIds)
        ];
        
    } else {
        // معالجة إجراء واحد
        $photoId = intval($_POST['photo_id'] ?? 0);
        
        if ($photoId > 0) {
            switch ($action) {
                case 'hide':
                    $stmt = $conn->prepare("UPDATE live_gallery_photos SET is_hidden = 1, hidden_at = NOW(), hidden_by = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("ii", $adminId, $photoId);
                        if ($stmt->execute()) {
                            logAdminAction($conn, $photoId, 'hide', null, $adminId);
                            $response = ['success' => true, 'message' => 'تم إخفاء الصورة'];
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'show':
                    $stmt = $conn->prepare("UPDATE live_gallery_photos SET is_hidden = 0, hidden_at = NULL, hidden_by = NULL WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $photoId);
                        if ($stmt->execute()) {
                            logAdminAction($conn, $photoId, 'show', null, $adminId);
                            $response = ['success' => true, 'message' => 'تم إظهار الصورة'];
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'restore':
                    $stmt = $conn->prepare("UPDATE live_gallery_photos SET expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR), is_expired = 0 WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $photoId);
                        if ($stmt->execute()) {
                            logAdminAction($conn, $photoId, 'restore', null, $adminId);
                            $response = ['success' => true, 'message' => 'تم استرجاع الصورة للبث المباشر'];
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'archive':
                    $stmt = $conn->prepare("UPDATE live_gallery_photos SET expires_at = NOW(), is_expired = 1 WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $photoId);
                        if ($stmt->execute()) {
                            logAdminAction($conn, $photoId, 'archive', null, $adminId);
                            $response = ['success' => true, 'message' => 'تم أرشفة الصورة'];
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'transfer':
                    $groomId = intval($_POST['groom_id'] ?? 0);
                    if ($groomId > 0) {
                        $stmt = $conn->prepare("UPDATE live_gallery_photos SET groom_id = ?, transferred_to_groom = 1, transferred_at = NOW() WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("ii", $groomId, $photoId);
                            if ($stmt->execute()) {
                                logAdminAction($conn, $photoId, 'transfer', $groomId, $adminId);
                                $response = ['success' => true, 'message' => 'تم نقل الصورة'];
                            }
                            $stmt->close();
                        }
                    }
                    break;
                    
                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM live_gallery_photos WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $photoId);
                        if ($stmt->execute()) {
                            logAdminAction($conn, $photoId, 'delete', null, $adminId);
                            $response = ['success' => true, 'message' => 'تم حذف الصورة'];
                        }
                        $stmt->close();
                    }
                    break;
            }
        }
    }
    
    if (isset($_POST['ajax'])) {
        echo json_encode($response);
        exit;
    }
}

// جلب قائمة العرسان - استخدام groom_name
$grooms = [];
$groomsQuery = "SELECT id, groom_name, folder_name FROM grooms ORDER BY id DESC";
$groomsResult = $conn->query($groomsQuery);
if ($groomsResult) {
    while ($row = $groomsResult->fetch_assoc()) {
        $grooms[] = $row;
    }
}

// معالجة الفلاتر
$filter = $_GET['filter'] ?? 'all';
$groomFilter = intval($_GET['groom_id'] ?? 0);
$dateFilter = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24; // تقليل من 36 إلى 24 لتحسين الأداء
$offset = ($page - 1) * $perPage;

// بناء شروط الاستعلام - عرض جميع الصور بما فيها المؤرشفة
$whereConditions = [];

// لا نضع شرط expires_at هنا - نريد عرض كل الصور في لوحة التحكم

if ($filter === 'live') {
    // الصور النشطة في البث المباشر
    $whereConditions[] = "(expires_at > NOW() OR expires_at IS NULL) AND (is_expired = 0 OR is_expired IS NULL) AND (is_hidden = 0 OR is_hidden IS NULL)";
} elseif ($filter === 'archived') {
    // الصور المؤرشفة
    $whereConditions[] = "(expires_at < NOW() AND expires_at IS NOT NULL) OR is_expired = 1";
} elseif ($filter === 'visible') {
    // الصور الظاهرة (غير مخفية)
    $whereConditions[] = "(is_hidden = 0 OR is_hidden IS NULL)";
} elseif ($filter === 'hidden') {
    // الصور المخفية
    $whereConditions[] = "is_hidden = 1";
} elseif ($filter === 'transferred') {
    // الصور المنقولة
    $whereConditions[] = "transferred_to_groom = 1";
}

if ($groomFilter > 0) {
    $whereConditions[] = "groom_id = $groomFilter";
}

if ($dateFilter) {
    $whereConditions[] = "DATE(uploaded_at) = '$dateFilter'";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// عد الصور
$totalPhotos = 0;
$countResult = $conn->query("SELECT COUNT(*) as total FROM live_gallery_photos $whereClause");
if ($countResult) {
    $totalPhotos = $countResult->fetch_assoc()['total'];
}
$totalPages = ceil($totalPhotos / $perPage);

// جلب الصور مع معلومات العريس
$photos = [];
$photosQuery = "
    SELECT p.*, 
           g.groom_name,
           CASE 
               WHEN p.expires_at < NOW() OR p.is_expired = 1 THEN 1 
               ELSE 0 
           END as is_archived,
           TIMESTAMPDIFF(HOUR, p.uploaded_at, NOW()) as hours_old
    FROM live_gallery_photos p
    LEFT JOIN grooms g ON p.groom_id = g.id
    $whereClause
    ORDER BY p.uploaded_at DESC
    LIMIT $perPage OFFSET $offset
";

$photosResult = $conn->query($photosQuery);
if ($photosResult) {
    while ($row = $photosResult->fetch_assoc()) {
        $photos[] = $row;
    }
}

// إحصائيات
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos")->fetch_assoc()['c'] ?? 0,
    'live' => $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos WHERE (expires_at > NOW() OR expires_at IS NULL) AND (is_expired = 0 OR is_expired IS NULL) AND (is_hidden = 0 OR is_hidden IS NULL)")->fetch_assoc()['c'] ?? 0,
    'archived' => $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos WHERE (expires_at < NOW() AND expires_at IS NOT NULL) OR is_expired = 1")->fetch_assoc()['c'] ?? 0,
    'hidden' => $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos WHERE is_hidden = 1")->fetch_assoc()['c'] ?? 0,
    'transferred' => $conn->query("SELECT COUNT(*) as c FROM live_gallery_photos WHERE transferred_to_groom = 1")->fetch_assoc()['c'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>لوحة التحكم الشاملة - إدارة الصور والأرشيف</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --success: #48bb78;
            --danger: #f56565;
            --warning: #ed8936;
            --info: #4299e1;
            --archived: #718096;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }
        
        .container-fluid {
            padding: 0;
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.98);
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Action Bar */
        .action-bar {
            background: rgba(255, 255, 255, 0.95);
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            position: sticky;
            top: 70px;
            z-index: 99;
        }
        
        .action-bar.active {
            background: #fff3cd;
            border-color: #ffc107;
        }
        
        .selected-count {
            background: var(--warning);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        /* Filters */
        .filters {
            background: white;
            padding: 15px;
            margin: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            padding: 15px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .stat-card h3 {
            font-size: 1.8rem;
            margin: 5px 0;
            font-weight: bold;
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Photos Grid */
        .photos-container {
            padding: 15px;
        }
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            /* تحسين الأداء */
            will-change: transform;
            backface-visibility: hidden;
        }
        
        @media (min-width: 768px) {
            .photos-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 12px;
            }
        }
        
        .photo-card {
            position: relative;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            /* تحسين الأداء */
            will-change: transform;
            transform: translateZ(0);
        }
        
        .photo-card.archived {
            opacity: 0.8;
            border: 2px solid var(--archived);
        }
        
        .photo-card.selected {
            box-shadow: 0 0 0 3px var(--primary);
            transform: scale(0.95);
        }
        
        .photo-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .photo-card.selected:hover {
            transform: scale(0.95);
        }
        
        .photo-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 25px;
            height: 25px;
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid var(--primary);
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        
        .photo-checkbox input {
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .photo-checkbox i {
            position: absolute;
            color: var(--primary);
            font-size: 1.2rem;
            display: none;
        }
        
        .photo-card.selected .photo-checkbox i {
            display: block;
        }
        
        .photo-card img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
        }
        
        .photo-status {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
            z-index: 2;
        }
        
        .status-live {
            background: var(--success);
            color: white;
        }
        
        .status-hidden {
            background: var(--danger);
            color: white;
        }
        
        .status-transferred {
            background: var(--info);
            color: white;
        }
        
        .status-archived {
            background: var(--archived);
            color: white;
        }
        
        .photo-time {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 5px;
            font-size: 0.7rem;
        }
        
        .photo-info {
            padding: 8px;
            background: rgba(255, 255, 255, 0.95);
            font-size: 0.75rem;
            color: #666;
        }
        
        .photo-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 10px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .photo-card:hover .photo-actions {
            opacity: 1;
        }
        
        .photo-actions .btn {
            padding: 3px 8px;
            font-size: 0.7rem;
        }
        
        /* Bulk Actions Modal */
        .bulk-actions-modal {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 30px;
            padding: 15px 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
            animation: slideUp 0.3s;
            max-width: 90%;
        }
        
        .bulk-actions-modal.active {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(100px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.2rem;
            }
            
            .filters {
                margin: 10px;
                padding: 10px;
            }
            
            .photos-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 5px;
            }
            
            .photo-info {
                display: none;
            }
            
            .photo-checkbox {
                width: 20px;
                height: 20px;
                top: 5px;
                left: 5px;
            }
            
            .photo-status {
                font-size: 0.6rem;
                padding: 2px 5px;
            }
            
            .bulk-actions-modal {
                bottom: 10px;
                padding: 10px 15px;
            }
            
            .bulk-actions-modal .btn {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
        }
        
        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Archive Badge */
        .archive-badge {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            z-index: 1;
            pointer-events: none;
        }
        
        /* تحسين الأداء أثناء التمرير */
        body.scrolling .photo-card {
            transition: none !important;
        }
        
        body.scrolling .photo-card:hover {
            transform: none !important;
        }
        
        body.scrolling .photo-actions {
            opacity: 0 !important;
            transition: none !important;
        }
        
        /* تحسين أداء الصور */
        .photo-card img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            background: #f0f0f0;
            /* منع القفزات */
            min-height: 120px;
        }
        
        @media (min-width: 768px) {
            .photo-card img {
                min-height: 180px;
            }
        }
    </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Header -->
<div class="header">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h1>
            <i class="bi bi-images"></i>
            <span>لوحة التحكم الشاملة</span>
        </h1>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> تحديث
            </button>
            <a href="../live-gallery.php" target="_blank" class="btn btn-sm btn-success">
                <i class="bi bi-broadcast"></i> البث المباشر
            </a>
        </div>
    </div>
</div>

<!-- Action Bar -->
<div class="action-bar" id="actionBar">
    <button class="btn btn-sm btn-outline-primary" id="selectAllBtn" onclick="selectAll()">
        <i class="bi bi-check-square"></i> تحديد الكل
    </button>
    <button class="btn btn-sm btn-outline-secondary" onclick="selectRange()">
        <i class="bi bi-arrows-expand"></i> تحديد نطاق
    </button>
    <button class="btn btn-sm btn-outline-info" onclick="selectByDate()">
        <i class="bi bi-calendar-check"></i> تحديد بالتاريخ
    </button>
    <button class="btn btn-sm btn-outline-warning" onclick="selectArchived()">
        <i class="bi bi-archive"></i> تحديد المؤرشف
    </button>
    <span class="selected-count" id="selectedCount" style="display: none;">
        <span id="selectedNumber">0</span> محدد
    </span>
</div>

<!-- Statistics -->
<div class="stats-container">
    <div class="stat-card">
        <i class="bi bi-images text-primary"></i>
        <h3><?= $stats['total'] ?></h3>
        <p>الإجمالي</p>
    </div>
    <div class="stat-card">
        <i class="bi bi-broadcast text-success"></i>
        <h3><?= $stats['live'] ?></h3>
        <p>في البث</p>
    </div>
    <div class="stat-card">
        <i class="bi bi-archive-fill text-secondary"></i>
        <h3><?= $stats['archived'] ?></h3>
        <p>مؤرشف</p>
    </div>
    <div class="stat-card">
        <i class="bi bi-eye-slash text-danger"></i>
        <h3><?= $stats['hidden'] ?></h3>
        <p>مخفي</p>
    </div>
    <div class="stat-card">
        <i class="bi bi-arrow-right-circle text-info"></i>
        <h3><?= $stats['transferred'] ?></h3>
        <p>منقول</p>
    </div>
</div>

<!-- Filters -->
<div class="filters">
    <form method="GET" id="filterForm">
        <div class="row g-2">
            <div class="col-md-3 col-6">
                <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>جميع الصور</option>
                    <option value="live" <?= $filter === 'live' ? 'selected' : '' ?>>في البث المباشر</option>
                    <option value="archived" <?= $filter === 'archived' ? 'selected' : '' ?>>الأرشيف</option>
                    <option value="visible" <?= $filter === 'visible' ? 'selected' : '' ?>>الظاهرة</option>
                    <option value="hidden" <?= $filter === 'hidden' ? 'selected' : '' ?>>المخفية</option>
                    <option value="transferred" <?= $filter === 'transferred' ? 'selected' : '' ?>>المنقولة</option>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <select name="groom_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">كل العرسان</option>
                    <?php foreach ($grooms as $groom): ?>
                        <option value="<?= $groom['id'] ?>" <?= $groomFilter == $groom['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($groom['groom_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <input type="date" name="date" class="form-control form-control-sm" value="<?= $dateFilter ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3 col-6">
                <button type="button" class="btn btn-sm btn-warning w-100" onclick="resetFilters()">
                    <i class="bi bi-x-circle"></i> مسح الفلاتر
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Photos Grid -->
<div class="photos-container">
    <?php if (empty($photos)): ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-info-circle"></i> لا توجد صور لعرضها
        </div>
    <?php else: ?>
        <div class="photos-grid" id="photosGrid">
            <?php foreach ($photos as $photo): ?>
                <div class="photo-card <?= $photo['is_archived'] ? 'archived' : '' ?>" 
                     data-photo-id="<?= $photo['id'] ?>" 
                     data-date="<?= date('Y-m-d', strtotime($photo['uploaded_at'])) ?>"
                     data-archived="<?= $photo['is_archived'] ?>">
                    
                    <!-- Checkbox -->
                    <div class="photo-checkbox">
                        <input type="checkbox" class="photo-select" value="<?= $photo['id'] ?>" onchange="updateSelection()">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    
                    <!-- Status Badge -->
                    <?php if ($photo['is_archived']): ?>
                        <span class="photo-status status-archived">مؤرشف</span>
                    <?php elseif ($photo['is_hidden']): ?>
                        <span class="photo-status status-hidden">مخفي</span>
                    <?php elseif ($photo['transferred_to_groom']): ?>
                        <span class="photo-status status-transferred">منقول</span>
                    <?php else: ?>
                        <span class="photo-status status-live">مباشر</span>
                    <?php endif; ?>
                    
                    <!-- Time Badge -->
                    <?php if ($photo['hours_old'] < 24): ?>
                        <span class="photo-time">
                            <?= $photo['hours_old'] < 1 ? 'جديد' : $photo['hours_old'] . ' ساعة' ?>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Archive Overlay for Archived Photos -->
                    <?php if ($photo['is_archived']): ?>
                        <div class="archive-badge">
                            <i class="bi bi-archive-fill"></i> أرشيف
                        </div>
                    <?php endif; ?>
                    
                    <!-- Image -->
                    <?php 
                    // استخدام الصورة المصغرة إذا كانت موجودة
                    $imageSrc = '/uploads/live/' . htmlspecialchars($photo['filename']);
                    $thumbSrc = str_replace(['.jpg', '.jpeg', '.png'], ['_thumb.jpg', '_thumb.jpg', '_thumb.png'], $imageSrc);
                    ?>
                    <img src="<?= $thumbSrc ?>" 
                         alt="Photo <?= $photo['id'] ?>"
                         loading="lazy"
                         onerror="this.src='<?= $imageSrc ?>'; this.onerror=function(){this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2VlZSIvPjx0ZXh0IHRleHQtYW5jaG9yPSJtaWRkbGUiIHg9IjEwMCIgeT0iMTAwIiBmaWxsPSIjOTk5IiBmb250LXNpemU9IjIwIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4=';}">
                    
                    <!-- Info -->
                    <div class="photo-info">
                        <div><?= date('Y/m/d H:i', strtotime($photo['uploaded_at'])) ?></div>
                        <?php if ($photo['groom_name']): ?>
                            <div><i class="bi bi-person"></i> <?= htmlspecialchars($photo['groom_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="photo-actions">
                        <?php if ($photo['is_archived']): ?>
                            <button class="btn btn-success btn-sm" onclick="event.stopPropagation(); quickAction('restore', <?= $photo['id'] ?>); return false;" title="استرجاع للبث">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation(); quickAction('archive', <?= $photo['id'] ?>); return false;" title="أرشفة">
                                <i class="bi bi-archive"></i>
                            </button>
                        <?php endif; ?>
                        
                        <?php if (!$photo['is_hidden']): ?>
                            <button class="btn btn-warning btn-sm" onclick="event.stopPropagation(); quickAction('hide', <?= $photo['id'] ?>); return false;" title="إخفاء">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn btn-success btn-sm" onclick="event.stopPropagation(); quickAction('show', <?= $photo['id'] ?>); return false;" title="إظهار">
                                <i class="bi bi-eye"></i>
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-danger btn-sm" onclick="event.stopPropagation(); quickAction('delete', <?= $photo['id'] ?>); return false;" title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center py-3">
        <ul class="pagination">
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>&groom_id=<?= $groomFilter ?>&date=<?= $dateFilter ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Bulk Actions Modal -->
<div class="bulk-actions-modal" id="bulkActionsModal">
    <span class="selected-count">
        <span id="bulkSelectedCount">0</span> محدد
    </span>
    <button class="btn btn-sm btn-success" onclick="bulkAction('restore')" title="استرجاع للبث">
        <i class="bi bi-arrow-counterclockwise"></i>
    </button>
    <button class="btn btn-sm btn-secondary" onclick="bulkAction('archive')" title="أرشفة">
        <i class="bi bi-archive"></i>
    </button>
    <button class="btn btn-sm btn-success" onclick="bulkAction('show')" title="إظهار">
        <i class="bi bi-eye"></i>
    </button>
    <button class="btn btn-sm btn-warning" onclick="bulkAction('hide')" title="إخفاء">
        <i class="bi bi-eye-slash"></i>
    </button>
    <button class="btn btn-sm btn-info" onclick="showTransferModal()" title="نقل">
        <i class="bi bi-arrow-right"></i>
    </button>
    <button class="btn btn-sm btn-danger" onclick="bulkAction('delete')" title="حذف">
        <i class="bi bi-trash"></i>
    </button>
    <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
        <i class="bi bi-x"></i>
    </button>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">نقل الصور المحددة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    سيتم نقل <strong id="transferCount">0</strong> صورة
                </div>
                <label class="form-label">اختر العريس:</label>
                <select id="transferGroomId" class="form-select" required>
                    <option value="">-- اختر --</option>
                    <?php foreach ($grooms as $groom): ?>
                        <option value="<?= $groom['id'] ?>">
                            <?= htmlspecialchars($groom['groom_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" onclick="executeTransfer()">نقل الصور</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let selectedPhotos = new Set();
let isRangeMode = false;
let rangeStart = null;

// Update selection state
function updateSelection() {
    selectedPhotos.clear();
    document.querySelectorAll('.photo-select:checked').forEach(checkbox => {
        selectedPhotos.add(parseInt(checkbox.value));
        checkbox.closest('.photo-card').classList.add('selected');
    });
    
    document.querySelectorAll('.photo-select:not(:checked)').forEach(checkbox => {
        checkbox.closest('.photo-card').classList.remove('selected');
    });
    
    updateUI();
}

// Update UI based on selection
function updateUI() {
    const count = selectedPhotos.size;
    
    // Update counters
    document.getElementById('selectedCount').style.display = count > 0 ? 'inline-block' : 'none';
    document.getElementById('selectedNumber').textContent = count;
    document.getElementById('bulkSelectedCount').textContent = count;
    document.getElementById('transferCount').textContent = count;
    
    // Show/hide bulk actions
    const bulkModal = document.getElementById('bulkActionsModal');
    if (count > 0) {
        bulkModal.classList.add('active');
        document.getElementById('actionBar').classList.add('active');
    } else {
        bulkModal.classList.remove('active');
        document.getElementById('actionBar').classList.remove('active');
    }
    
    // Update select all button
    const selectAllBtn = document.getElementById('selectAllBtn');
    const totalCards = document.querySelectorAll('.photo-card').length;
    if (count === totalCards && totalCards > 0) {
        selectAllBtn.innerHTML = '<i class="bi bi-square"></i> إلغاء التحديد';
    } else {
        selectAllBtn.innerHTML = '<i class="bi bi-check-square"></i> تحديد الكل';
    }
}

// Select all/none
function selectAll() {
    const checkboxes = document.querySelectorAll('.photo-select');
    const allChecked = checkboxes.length === selectedPhotos.size;
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    
    updateSelection();
}

// Select archived photos
function selectArchived() {
    document.querySelectorAll('.photo-card[data-archived="1"] .photo-select').forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelection();
    
    Swal.fire({
        icon: 'info',
        title: 'تم تحديد الصور المؤرشفة',
        text: `تم تحديد ${selectedPhotos.size} صورة مؤرشفة`,
        timer: 2000,
        showConfirmButton: false
    });
}

// Select range
function selectRange() {
    Swal.fire({
        title: 'تحديد نطاق',
        text: 'انقر على الصورة الأولى ثم الأخيرة',
        icon: 'info',
        confirmButtonText: 'فهمت'
    }).then(() => {
        isRangeMode = true;
        document.querySelectorAll('.photo-card').forEach(card => {
            card.style.cursor = 'crosshair';
            card.onclick = function() {
                if (isRangeMode) {
                    const photoId = parseInt(card.dataset.photoId);
                    if (!rangeStart) {
                        rangeStart = photoId;
                        card.style.border = '3px solid orange';
                    } else {
                        selectBetween(rangeStart, photoId);
                        isRangeMode = false;
                        rangeStart = null;
                        document.querySelectorAll('.photo-card').forEach(c => {
                            c.style.cursor = 'pointer';
                            c.style.border = '';
                            c.onclick = null;
                        });
                    }
                }
            };
        });
    });
}

// Select between two photos
function selectBetween(start, end) {
    const cards = Array.from(document.querySelectorAll('.photo-card'));
    const startIndex = cards.findIndex(c => parseInt(c.dataset.photoId) === start);
    const endIndex = cards.findIndex(c => parseInt(c.dataset.photoId) === end);
    
    const minIndex = Math.min(startIndex, endIndex);
    const maxIndex = Math.max(startIndex, endIndex);
    
    for (let i = minIndex; i <= maxIndex; i++) {
        const checkbox = cards[i].querySelector('.photo-select');
        checkbox.checked = true;
    }
    
    updateSelection();
}

// Select by date
function selectByDate() {
    const dates = [...new Set(Array.from(document.querySelectorAll('.photo-card')).map(c => c.dataset.date))];
    
    Swal.fire({
        title: 'اختر التاريخ',
        input: 'select',
        inputOptions: Object.fromEntries(dates.map(d => [d, d])),
        inputPlaceholder: 'اختر تاريخ',
        showCancelButton: true,
        confirmButtonText: 'تحديد',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            document.querySelectorAll(`.photo-card[data-date="${result.value}"] .photo-select`).forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelection();
        }
    });
}

// Clear selection
function clearSelection() {
    document.querySelectorAll('.photo-select').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelection();
}

// Quick single action
function quickAction(action, photoId) {
    // إزالة event.stopPropagation() لأنها تسبب مشاكل
    
    // تنفيذ مباشر بدون تأكيد
    executeAction(action, photoId);
}

// Bulk action
function bulkAction(action) {
    if (selectedPhotos.size === 0) {
        Swal.fire('تنبيه', 'لم تحدد أي صور', 'warning');
        return;
    }
    
    // تنفيذ مباشر بدون تأكيد
    executeAction(action + '_multiple', Array.from(selectedPhotos));
}

// Show transfer modal
function showTransferModal() {
    if (selectedPhotos.size === 0) {
        Swal.fire('تنبيه', 'لم تحدد أي صور', 'warning');
        return;
    }
    
    document.getElementById('transferCount').textContent = selectedPhotos.size;
    new bootstrap.Modal(document.getElementById('transferModal')).show();
}

// Execute transfer
function executeTransfer() {
    const groomId = document.getElementById('transferGroomId').value;
    
    if (!groomId) {
        Swal.fire('خطأ', 'يرجى اختيار العريس', 'error');
        return;
    }
    
    bootstrap.Modal.getInstance(document.getElementById('transferModal')).hide();
    executeAction('transfer_multiple', Array.from(selectedPhotos), groomId);
}

// Execute action
function executeAction(action, photoIds, groomId = null) {
    showLoading();
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('ajax', '1');
    
    // التعامل مع معرف واحد أو مصفوفة
    if (Array.isArray(photoIds)) {
        photoIds.forEach(id => formData.append('photo_ids[]', id));
    } else {
        formData.append('photo_id', photoIds);
        photoIds = [photoIds]; // تحويل لمصفوفة للمعالجة
    }
    
    if (groomId) {
        formData.append('groom_id', groomId);
    }
    
    fetch('manage-photos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            // تحديث الصور المتأثرة بدلاً من تحديث الصفحة
            photoIds.forEach(photoId => {
                const card = document.querySelector(`[data-photo-id="${photoId}"]`);
                if (card) {
                    updatePhotoCard(card, action);
                }
            });
            
            // رسالة نجاح صغيرة
            showToast(data.message, 'success');
            
            // مسح التحديد إذا كان هناك
            if (selectedPhotos.size > 0) {
                clearSelection();
            }
        } else {
            Swal.fire('خطأ', data.message || 'حدث خطأ', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error:', error);
        Swal.fire('خطأ', 'حدث خطأ في الاتصال', 'error');
    });
}

// تحديث بطاقة الصورة بناءً على الإجراء
function updatePhotoCard(card, action) {
    const statusBadge = card.querySelector('.photo-status');
    const archiveBadge = card.querySelector('.archive-badge');
    const actions = card.querySelector('.photo-actions');
    
    switch(action) {
        case 'hide':
            statusBadge.className = 'photo-status status-hidden';
            statusBadge.textContent = 'مخفي';
            // تحديث الأزرار
            updateActionButtons(card, 'hidden');
            break;
            
        case 'show':
            statusBadge.className = 'photo-status status-live';
            statusBadge.textContent = 'مباشر';
            updateActionButtons(card, 'visible');
            break;
            
        case 'archive':
            card.classList.add('archived');
            statusBadge.className = 'photo-status status-archived';
            statusBadge.textContent = 'مؤرشف';
            card.setAttribute('data-archived', '1');
            // إضافة شارة الأرشيف
            if (!archiveBadge) {
                const badge = document.createElement('div');
                badge.className = 'archive-badge';
                badge.innerHTML = '<i class="bi bi-archive-fill"></i> أرشيف';
                card.appendChild(badge);
            }
            updateActionButtons(card, 'archived');
            break;
            
        case 'restore':
            card.classList.remove('archived');
            statusBadge.className = 'photo-status status-live';
            statusBadge.textContent = 'مباشر';
            card.setAttribute('data-archived', '0');
            // إزالة شارة الأرشيف
            if (archiveBadge) {
                archiveBadge.remove();
            }
            updateActionButtons(card, 'live');
            break;
            
        case 'delete':
            // إخفاء البطاقة مع تأثير
            card.style.transition = 'opacity 0.3s, transform 0.3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.8)';
            setTimeout(() => card.remove(), 300);
            break;
    }
}

// تحديث أزرار الإجراءات
function updateActionButtons(card, state) {
    const photoId = card.dataset.photoId;
    const actionsDiv = card.querySelector('.photo-actions');
    
    if (!actionsDiv) return;
    
    let buttons = '';
    
    if (state === 'archived') {
        buttons += `<button class="btn btn-success btn-sm" onclick="quickAction('restore', ${photoId})" title="استرجاع للبث">
            <i class="bi bi-arrow-counterclockwise"></i>
        </button>`;
    } else {
        buttons += `<button class="btn btn-secondary btn-sm" onclick="quickAction('archive', ${photoId})" title="أرشفة">
            <i class="bi bi-archive"></i>
        </button>`;
    }
    
    if (state === 'hidden') {
        buttons += `<button class="btn btn-success btn-sm" onclick="quickAction('show', ${photoId})" title="إظهار">
            <i class="bi bi-eye"></i>
        </button>`;
    } else if (state !== 'hidden') {
        buttons += `<button class="btn btn-warning btn-sm" onclick="quickAction('hide', ${photoId})" title="إخفاء">
            <i class="bi bi-eye-slash"></i>
        </button>`;
    }
    
    buttons += `<button class="btn btn-danger btn-sm" onclick="quickAction('delete', ${photoId})" title="حذف">
        <i class="bi bi-trash"></i>
    </button>`;
    
    actionsDiv.innerHTML = buttons;
}

// عرض رسالة Toast صغيرة
function showToast(message, type = 'success') {
    // إنشاء Toast إذا لم يكن موجوداً
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        `;
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
    toast.style.cssText = `
        min-width: 250px;
        margin-bottom: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    `;
    toast.innerHTML = `
        <strong>${type === 'success' ? '✓' : '✗'}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    toastContainer.appendChild(toast);
    
    // إزالة تلقائياً بعد 3 ثواني
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Helper functions
function getActionText(action) {
    const actions = {
        'hide': 'إخفاء الصورة؟',
        'show': 'إظهار الصورة؟',
        'archive': 'أرشفة الصورة؟',
        'restore': 'استرجاع الصورة للبث المباشر؟',
        'delete': 'حذف الصورة نهائياً؟',
        'hide_multiple': 'إخفاء الصور المحددة؟',
        'show_multiple': 'إظهار الصور المحددة؟',
        'archive_multiple': 'أرشفة الصور المحددة؟',
        'restore_multiple': 'استرجاع الصور المحددة للبث؟',
        'delete_multiple': 'حذف الصور المحددة نهائياً؟'
    };
    return actions[action] || 'تنفيذ الإجراء؟';
}

function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('active');
}

function resetFilters() {
    window.location.href = 'manage-photos-ultimate.php';
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    updateUI();
    
    // Lazy Loading محسن للصور
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    // تحميل الصورة فقط عند الحاجة
                    if (img.dataset.src && !img.src.includes('data:image')) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px'
        });
        
        // مراقبة جميع الصور
        document.querySelectorAll('.photo-card img').forEach(img => {
            if (!img.complete) {
                imageObserver.observe(img);
            }
        });
    }
    
    // تحسين أداء التمرير
    let scrollTimer;
    window.addEventListener('scroll', () => {
        document.body.classList.add('scrolling');
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(() => {
            document.body.classList.remove('scrolling');
        }, 100);
    });
    
    // منع تحميل الصور المخفية
    document.querySelectorAll('.photo-card').forEach(card => {
        const rect = card.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > window.innerHeight) {
            const img = card.querySelector('img');
            if (img && !img.dataset.src) {
                img.dataset.src = img.src;
                img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMSIgaGVpZ2h0PSIxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjwvc3ZnPg==';
            }
        }
    });
});
</script>

</body>
</html>