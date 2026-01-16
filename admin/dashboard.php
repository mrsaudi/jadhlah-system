<?php
// admin/dashboard.php - لوحة التحكم المحسنة والمصلحة
session_start();

// التحقق من تسجيل الدخول
if (empty($_SESSION['user'])) {
    header('Location: index.php'); 
    exit;
}

// تضمين ملف التكوين
require_once __DIR__ . '/config.php';

// معلومات المستخدم
$role = $_SESSION['role'] ?? 'employ';
$isManager = ($role === 'manager');
$canWrite = in_array($role, ['manager', 'employ']);

// معالجة الإجراءات POST للتقييمات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id']) && $isManager) {
    $id = (int) $_POST['review_id'];
    try {
        if (isset($_POST['approve_review'])) {
            $pdo->prepare("UPDATE groom_reviews SET is_approved = 1 WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = "تم نشر التقييم بنجاح.";
        } elseif (isset($_POST['delete_review'])) {
            $pdo->prepare("DELETE FROM groom_reviews WHERE id = ?")->execute([$id]);
            $_SESSION['flash'] = "تم حذف التقييم.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "حدث خطأ في معالجة التقييم";
    }
    header("Location: dashboard.php");
    exit;
}

// جلب البيانات المحسّنة
try {
    // استعلام واحد محسّن لجلب الإحصائيات
    $statsQuery = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM grooms) as totalPages,
            (SELECT COALESCE(SUM(page_views), 0) FROM grooms) as totalViews,
            (SELECT COUNT(*) FROM groom_photos) as totalPhotos,
            (SELECT COUNT(*) FROM grooms WHERE is_active = 1 AND is_blocked = 0) as activePages,
            (SELECT COUNT(*) FROM grooms WHERE is_blocked = 1) as blockedPages,
            (SELECT COUNT(*) FROM grooms WHERE is_active = 0 AND is_blocked = 0) as inactivePages,
            (SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NULL) as pendingPages,
            (SELECT COUNT(*) FROM photo_likes) as totalPhotoLikes,
            (SELECT COUNT(*) FROM groom_likes) as totalGroomLikes,
            (SELECT COUNT(DISTINCT session_id) FROM sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as activeVisitors
    ")->fetch();
    
    $stats = [
        'totalPages' => $statsQuery['totalPages'] ?? 0,
        'totalViews' => $statsQuery['totalViews'] ?? 0,
        'totalPhotos' => $statsQuery['totalPhotos'] ?? 0,
        'totalLikes' => ($statsQuery['totalPhotoLikes'] ?? 0) + ($statsQuery['totalGroomLikes'] ?? 0),
        'activePages' => $statsQuery['activePages'] ?? 0,
        'blockedPages' => $statsQuery['blockedPages'] ?? 0,
        'inactivePages' => $statsQuery['inactivePages'] ?? 0,
        'pendingPages' => $statsQuery['pendingPages'] ?? 0,
        'activeVisitors' => $statsQuery['activeVisitors'] ?? 0
    ];
    
    // جلب الصفحات المنتظرة
    $pendingGrooms = [];
    if ($stats['pendingPages'] > 0) {
        // إعدادات الترقيم للمنتظرة
$pendingPage = max(1, (int)($_GET['pending_page'] ?? 1));
$pendingLimit = 20; // زيادة العدد إلى 20
$pendingOffset = ($pendingPage - 1) * $pendingLimit;

// جلب العدد الإجمالي
$totalPendingStmt = $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NULL");
$totalPending = (int)$totalPendingStmt->fetchColumn();
$totalPendingPages = ceil($totalPending / $pendingLimit);

// جلب البيانات مع الترقيم
$pendingStmt = $pdo->prepare("
    SELECT * FROM pending_grooms 
    WHERE groom_id IS NULL 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$pendingStmt->bindValue(1, $pendingLimit, PDO::PARAM_INT);
$pendingStmt->bindValue(2, $pendingOffset, PDO::PARAM_INT);
$pendingStmt->execute();
$pendingGrooms = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // التقييمات المنتظرة
    $pendingReviews = 0;
    $pendingReviewsList = [];
    if ($isManager) {
        $pendingReviews = $pdo->query("SELECT COUNT(*) FROM groom_reviews WHERE is_approved = 0")->fetchColumn() ?: 0;
        
        if ($pendingReviews > 0) {
            $pendingReviewsList = $pdo->query("
                SELECT r.id, r.name, r.message, r.rating, r.created_at, g.groom_name
                FROM groom_reviews r
                JOIN grooms g ON g.id = r.groom_id
                WHERE r.is_approved = 0
                ORDER BY r.created_at DESC
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // جلب بيانات العرسان محسّنة
    $groomsStmt = $pdo->query("
        SELECT g.*, 
                g.expiry_days,
               (SELECT COUNT(*) FROM groom_photos WHERE groom_id = g.id) as photo_count,
               (SELECT COUNT(*) FROM groom_likes WHERE groom_id = g.id) as groom_likes_count,
               (SELECT COUNT(*) FROM photo_likes WHERE groom_id = g.id) as photo_likes_count
        FROM grooms g
        ORDER BY g.id DESC
        LIMIT 100
    ");
    $grooms = $groomsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تصنيف العرسان
    $activeGrooms = [];
    $blockedGrooms = [];
    $inactiveGrooms = [];
    
    foreach ($grooms as &$groom) {
        $groom['total_likes'] = ($groom['groom_likes_count'] ?? 0) + ($groom['photo_likes_count'] ?? 0);
        
        if ($groom['is_blocked'] == 1) {
            $blockedGrooms[] = $groom;
        } elseif ($groom['is_active'] == 0) {
            $inactiveGrooms[] = $groom;
        } else {
            $activeGrooms[] = $groom;
        }
    }
    
    // أكثر الصفحات مشاهدة
    $topPages = array_slice($grooms, 0, 10);
    usort($topPages, fn($a, $b) => $b['page_views'] - $a['page_views']);
    
    // إحصائيات الرسوم البيانية
    $monthlyStats = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as pages,
            SUM(page_views) as views
        FROM grooms
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // احصائيات الزوار المباشر
    $visitorStats = $pdo->query("
        SELECT 
            COUNT(DISTINCT session_id) as unique_visitors,
            COUNT(*) as total_page_views,
            (SELECT COUNT(DISTINCT session_id) FROM sessions WHERE device_type = 'Mobile' AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as mobile_visitors,
            (SELECT COUNT(DISTINCT session_id) FROM sessions WHERE device_type = 'Desktop' AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as desktop_visitors
        FROM sessions 
        WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ")->fetch();
    
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// دوال مساعدة
function daysSince($date) {
    return (new DateTime())->diff(new DateTime($date))->days;
}

function safeFolderSize($id) {
    $dir = dirname(__DIR__) . "/grooms/{$id}/originals";
    if (!is_dir($dir)) return '0 B';
    
    $size = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    $units = ['B','KB','MB','GB'];
    $e = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $e), 2) . ' ' . $units[$e];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - جذلة</title>
    
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <meta name="theme-color" content="#4f46e5">
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #6366f1;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
            --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --card-hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: #1f2937;
            min-height: 100vh;
        }

        /* Container */
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .dashboard-header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--card-shadow);
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .dashboard-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-bg);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dashboard-title i {
            color: var(--primary-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stat-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-4px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary-color);
        }

        .stat-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }

        .stat-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .stat-icon.info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        /* Navigation Tabs */
        .nav-tabs-modern {
            background: white;
            padding: 0.5rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }

        .nav-tabs-modern .nav-link {
            color: #6b7280;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            margin: 0 0.25rem;
            transition: all 0.3s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-tabs-modern .nav-link:hover {
            background: #f3f4f6;
            color: var(--primary-color);
        }

        .nav-tabs-modern .nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        /* Data Tables */
        .data-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .data-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .data-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        /* Modern Table */
        .modern-table {
            width: 100%;
        }

        .modern-table thead {
            background: #f9fafb;
        }

        .modern-table th {
            padding: 0.75rem 1rem;
            text-align: right;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .modern-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #1f2937;
            vertical-align: middle;
        }

        .modern-table tbody tr {
            transition: background 0.2s;
        }

        .modern-table tbody tr:hover {
            background: #f9fafb;
        }

        /* Action Buttons */
        .btn-modern {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary-modern {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary-modern:hover {
            background: var(--primary-dark);
            color: white;
        }

        /* Charts */
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            height: 400px;
            position: relative;
        }

        .chart-wrapper {
            position: relative;
            height: 350px;
        }

        /* Review Card */
        .review-card {
            background: #fff;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .review-card:hover {
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        /* Pending Pages Card */
        .pending-page-card {
            background: #f9fafb;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .notes-section {
            background: white;
            padding: 0.75rem;
            border-radius: 6px;
            margin-top: 0.5rem;
            border: 1px solid #e5e7eb;
        }

        .notes-section .note-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        /* Visitors Section */
        .visitors-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .visitor-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .visitor-item:hover {
            background: #f9fafb;
        }

        .visitor-device {
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Button Styles */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        .btn-info {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }

        .btn-info:hover {
            background-color: #2563eb;
            border-color: #2563eb;
            color: white;
        }

        .btn-warning {
            background-color: #f59e0b;
            border-color: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background-color: #d97706;
            border-color: #d97706;
            color: white;
        }

        .btn-secondary {
            background-color: #6b7280;
            border-color: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
            border-color: #4b5563;
            color: white;
        }

        .btn-success {
            background-color: #10b981;
            border-color: #10b981;
            color: white;
        }

        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
            color: white;
        }

        .btn-danger {
            background-color: #ef4444;
            border-color: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
            color: white;
        }

        /* Form Switch */
        .form-check-input {
            width: 3em;
            height: 1.5em;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .dashboard-header {
                flex-direction: column;
            }
            
            .data-card-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .modern-table {
                font-size: 0.875rem;
            }
            
            .modern-table th,
            .modern-table td {
                padding: 0.5rem;
            }
            
            .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.75rem;
            }
        }

        /* Alert Messages */
        .alert {
            border-radius: 8px;
            border: none;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loader {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f4f6;
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Live Indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .live-indicator::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--secondary-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* 
   أضف هذا CSS في نهاية <style> في dashboard.php
   أو في ملف CSS منفصل
*/

/* تحسينات زر رابط التقييم */
.btn-sm .bi-star-half {
    font-size: 1rem;
}

/* تحسينات Modal على الموبايل */
@media (max-width: 768px) {
    #ratingLinkModal .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    #ratingLinkModal .modal-body {
        padding: 1rem;
    }
    
    #ratingLinkModal .input-group {
        flex-direction: column;
    }
    
    #ratingLinkModal .input-group .form-control {
        border-radius: 8px 8px 0 0 !important;
        font-size: 0.875rem;
    }
    
    #ratingLinkModal .input-group .btn {
        border-radius: 0 0 8px 8px !important;
        width: 100%;
    }
    
    #ratingLinkModal .d-flex.gap-2 {
        flex-direction: column;
    }
    
    #ratingLinkModal .d-flex.gap-2 .btn {
        width: 100%;
    }
}

/* تحسين عرض الرابط للنسخ */
#modalRatingLink {
    font-size: 0.875rem;
    line-height: 1.5;
    padding: 0.75rem;
    word-break: break-all;
    overflow-x: auto;
    white-space: nowrap;
}

/* تحسين زر النسخ على Safari/iOS */
#modalRatingLink:focus {
    outline: 2px solid #667eea;
    outline-offset: 2px;
}

/* تحسين أزرار الواتساب */
#modalWhatsappLink {
    background: #25D366;
    border-color: #25D366;
}

#modalWhatsappLink:hover {
    background: #1da851;
    border-color: #1da851;
}

#modalWhatsappLink .bi-whatsapp {
    font-size: 1.25rem;
}

/* تحسين Animation للـ Modal */
#ratingLinkModal.show .modal-dialog {
    animation: slideInUp 0.3s ease-out;
}

@keyframes slideInUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* تحسين Spinner */
#ratingLinkLoading .spinner-border {
    width: 3rem;
    height: 3rem;
    border-width: 0.3rem;
}

/* تحسين Alert Messages */
#ratingLinkModal .alert {
    border-radius: 10px;
    border: none;
    padding: 1rem;
}

#ratingLinkModal .alert i {
    font-size: 1.25rem;
    margin-left: 0.5rem;
}

/* Dark Mode Support (اختياري) */
@media (prefers-color-scheme: dark) {
    #ratingLinkModal .modal-content {
        background-color: #1f2937;
        color: #f9fafb;
    }
    
    #ratingLinkModal .modal-header {
        background: linear-gradient(135deg, #4338ca 0%, #6b21a8 100%);
    }
    
    #ratingLinkModal .form-control {
        background-color: #374151;
        color: #f9fafb;
        border-color: #4b5563;
    }
    
    #ratingLinkModal .alert-info {
        background-color: #1e3a5f;
        color: #bfdbfe;
    }
}

/* تحسين accessibility للقارئات الشاشة */
.btn[title] {
    position: relative;
}

.btn[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 1000;
    pointer-events: none;
}

/* تحسين للـ RTL */
[dir="rtl"] #modalRatingLink {
    text-align: left;
    direction: ltr;
}

/* تحسين Touch Targets للموبايل */
@media (hover: none) and (pointer: coarse) {
    .btn-sm {
        min-height: 44px;
        min-width: 44px;
        padding: 0.5rem;
    }
    
    #ratingLinkModal .btn {
        min-height: 48px;
        font-size: 1rem;
    }
}

/* تحسين Loading State */
#ratingLinkLoading {
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* تحسين Success Animation */
.btn-success i.bi-check-circle {
    animation: scaleIn 0.3s ease-out;
}

@keyframes scaleIn {
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.2);
    }
    100% {
        transform: scale(1);
    }
}

/* تحسين Error State */
#ratingLinkError {
    animation: shake 0.5s ease-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

/* iOS Specific Fixes */
@supports (-webkit-touch-callout: none) {
    /* تحسين للآيفون */
    #modalRatingLink {
        -webkit-user-select: all;
        user-select: all;
    }
    
    .btn {
        -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
    }
}


div#myTabContent {
    width: 100%;
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <i class="bi bi-speedometer2"></i>
                لوحة التحكم
            </h1>
            
            <div class="user-info">
                <span>مرحباً، <?= htmlspecialchars($_SESSION['user']) ?> (<?= htmlspecialchars($role) ?>)</span>
                <div class="user-avatar">
                    <?= mb_substr($_SESSION['user'], 0, 1, 'UTF-8') ?>
                </div>
                <a href="logout.php" class="btn btn-modern btn-primary-modern">
                    <i class="bi bi-box-arrow-left"></i>
                    تسجيل الخروج
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-images"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['totalPages']) ?></div>
                    <div class="stat-label">إجمالي الصفحات</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="bi bi-eye"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['totalViews']) ?></div>
                    <div class="stat-label">إجمالي المشاهدات</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="bi bi-heart"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['totalLikes']) ?></div>
                    <div class="stat-label">إجمالي الإعجابات</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['activeVisitors']) ?></div>
                    <div class="stat-label">الزوار النشطين الآن</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h5 class="mb-3">إجراءات سريعة</h5>
            <div class="d-flex gap-2 flex-wrap">
               <!-- 
ابحث في dashboard.php عن هذا الجزء:
<div class="quick-actions">
    <h5 class="mb-3">إجراءات سريعة</h5>
    <div class="d-flex gap-2 flex-wrap">
        
واستبدل كل ما بعده حتى </div></div> بهذا الكود:
-->

<div class="quick-actions">
    <h5 class="mb-3">إجراءات سريعة</h5>
    
    <!-- الصف الأول: إدارة العرسان -->
    <div class="mb-3">
        <h6 class="text-muted mb-2">
            <i class="bi bi-people-fill"></i> إدارة العرسان
        </h6>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($canWrite): ?>
            <a href="add_groom.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-plus-circle"></i>
                إضافة عريس جديد
            </a>
            
            <a href="pending_pages.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-clock-history"></i>
                العرسان المنتظرين
                <?php if ($stats['pendingPages'] > 0): ?>
                    <span class="badge bg-warning"><?= $stats['pendingPages'] ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            
            <?php if ($isManager): ?>
            <button onclick="importFromSheets()" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-cloud-download"></i>
                استيراد من Google Sheets
            </button>
            
            <a href="users_list.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-people"></i>
                إدارة المستخدمين
            </a>
            
            <a href="tools/manage_deleted.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-trash"></i>
                المحذوفين
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- الصف الثاني: التقييمات والإشعارات -->
    <div class="mb-3">
        <h6 class="text-muted mb-2">
            <i class="bi bi-star-fill"></i> التقييمات والإشعارات
        </h6>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($isManager): ?>
            <a href="reviews.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-star"></i>
                إدارة التقييمات
                <?php if ($pendingReviews > 0): ?>
                    <span class="badge bg-danger"><?= $pendingReviews ?></span>
                <?php endif; ?>
            </a>
            
            <a href="generate_rating_link.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-link-45deg"></i>
                إنشاء رابط تقييم
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- الصف الثالث: المعارض والصفحات الحية -->
    <div class="mb-3">
        <h6 class="text-muted mb-2">
            <i class="bi bi-images"></i> المعارض والصفحات
        </h6>
        <div class="d-flex gap-2 flex-wrap">
            <a href="../gallery_admin.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-grid-3x3"></i>
                معرض الأعمال
            </a>
            
            <a href="../landing.php" class="btn btn-modern btn-primary-modern" target="_blank">
                <i class="bi bi-qr-code"></i>
                صفحة الهبوط (QR)
            </a>
            
            <a href="../live-gallery.php" class="btn btn-modern btn-primary-modern" target="_blank">
                <i class="bi bi-camera-video"></i>
                الصور الحية (24 ساعة)
            </a>
            
            <a href="../rate.php" class="btn btn-modern btn-primary-modern" target="_blank">
                <i class="bi bi-star-half"></i>
                صفحة التقييم
            </a>
        </div>
    </div>
    
    <!-- الصف الرابع: أدوات وإحصائيات -->
    <?php if ($isManager): ?>
    <div class="mb-3">
        <h6 class="text-muted mb-2">
            <i class="bi bi-tools"></i> أدوات وإحصائيات
        </h6>
        <div class="d-flex gap-2 flex-wrap">
            <a href="debug_charts.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-bar-chart"></i>
                إحصائيات الصفحات
            </a>
            
            <a href="diagnostic.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-bug"></i>
                تشخيص الأخطاء
            </a>
            
            <a href="../scripts/ftp_watcher.php" class="btn btn-modern btn-primary-modern" target="_blank">
                <i class="bi bi-folder-check"></i>
                مراقب FTP
            </a>
            
            <a href="tools/import_manager.php" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-cloud-upload"></i>
                مدير الاستيراد
            </a>
            
            <button onclick="viewApiDocs()" class="btn btn-modern btn-primary-modern">
                <i class="bi bi-file-code"></i>
                توثيق API
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- أضف هذا السكريبت قبل </body> -->
<script>
function viewApiDocs() {
    Swal.fire({
        title: 'توثيق API والصفحات',
        html: `
            <div style="text-align: right; max-height: 500px; overflow-y: auto;">
                <h6 class="text-primary mb-3">🔔 API الإشعارات</h6>
                <div class="alert alert-info text-start mb-2">
                    <strong>POST</strong> /api/subscribe_push.php<br>
                    <small>تسجيل اشتراك إشعارات المتصفح</small>
                </div>
                <div class="alert alert-secondary text-start mb-3">
                    <strong>POST</strong> /api/subscribe_sms.php<br>
                    <small>تسجيل اشتراك SMS (قريباً)</small>
                </div>
                
                <h6 class="text-primary mb-3">⭐ API التقييمات</h6>
                <div class="alert alert-info text-start mb-3">
                    <strong>POST</strong> /api/submit_rating.php<br>
                    <small>إرسال تقييم جديد من الزوار</small>
                </div>
                
                <h6 class="text-primary mb-3">📸 صفحات العرض العامة</h6>
                <div class="alert alert-success text-start mb-2">
                    <strong>GET</strong> /landing.php<br>
                    <small>صفحة الهبوط - QR Code للحفلات</small>
                </div>
                <div class="alert alert-success text-start mb-2">
                    <strong>GET</strong> /live-gallery.php<br>
                    <small>معرض الصور الحية (آخر 24 ساعة)</small>
                </div>
                <div class="alert alert-success text-start mb-2">
                    <strong>GET</strong> /rate.php?token=XXX<br>
                    <small>صفحة التقييم العامة للعرسان</small>
                </div>
                <div class="alert alert-success text-start mb-3">
                    <strong>GET</strong> /gallery_admin.php<br>
                    <small>معرض الأعمال العام</small>
                </div>
                
                <hr>
                
                <h6 class="text-warning mb-3">⚙️ صفحات الإدارة</h6>
                <div class="alert alert-warning text-start mb-2">
                    <strong>GET</strong> /admin/dashboard.php<br>
                    <small>لوحة التحكم الرئيسية</small>
                </div>
                <div class="alert alert-warning text-start mb-2">
                    <strong>GET</strong> /admin/pending_pages.php<br>
                    <small>العرسان المنتظرين من الاستيراد</small>
                </div>
                <div class="alert alert-warning text-start mb-2">
                    <strong>GET</strong> /admin/generate_rating_link.php<br>
                    <small>إنشاء روابط التقييم للعرسان</small>
                </div>
                <div class="alert alert-warning text-start mb-2">
                    <strong>GET</strong> /admin/tools/manage_deleted.php<br>
                    <small>إدارة العرسان المحذوفين</small>
                </div>
                <div class="alert alert-warning text-start mb-2">
                    <strong>GET</strong> /admin/debug_charts.php<br>
                    <small>إحصائيات وتحليل الصفحات</small>
                </div>
                <div class="alert alert-warning text-start mb-2">
                    <strong>GET</strong> /admin/diagnostic.php<br>
                    <small>تشخيص الأخطاء والمشاكل</small>
                </div>
                
                <hr>
                
                <h6 class="text-danger mb-3">🔧 سكريبتات الخلفية</h6>
                <div class="alert alert-danger text-start mb-2">
                    <strong>CLI</strong> /scripts/ftp_watcher.php<br>
                    <small>مراقب FTP لرفع الصور من الكاميرا</small>
                </div>
                <div class="alert alert-secondary text-start mb-2">
                    <strong>JS</strong> /assets/js/rating-popup.js<br>
                    <small>نافذة التقييم المنبثقة للزوار</small>
                </div>
            </div>
        `,
        width: 800,
        confirmButtonText: 'إغلاق',
        customClass: {
            popup: 'text-end'
        }
    });
}
</script>

<!-- CSS إضافي للأزرار -->
<style>
.quick-actions h6 {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
}

.quick-actions .btn-modern {
    font-size: 0.875rem;
    white-space: nowrap;
}

.quick-actions .badge {
    font-size: 0.7rem;
    padding: 0.25em 0.5em;
    margin-right: 0.25rem;
    vertical-align: middle;
}

.quick-actions > div {
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.quick-actions > div:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .quick-actions .btn-modern {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
    }
}
</style>
    <!-- Navigation Tabs -->
    <nav class="nav-tabs-modern">
        <div class="nav nav-tabs" id="myTab" role="tablist">
            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button">
                <i class="bi bi-check-circle"></i>
                النشطة (<?= $stats['activePages'] ?>)
            </button>
            <button class="nav-link" id="blocked-tab" data-bs-toggle="tab" data-bs-target="#blocked" type="button">
                <i class="bi bi-x-circle"></i>
                المحجوبة (<?= $stats['blockedPages'] ?>)
            </button>
            <button class="nav-link" id="inactive-tab" data-bs-toggle="tab" data-bs-target="#inactive" type="button">
                <i class="bi bi-pause-circle"></i>
                الخاملة (<?= $stats['inactivePages'] ?>)
            </button>
            <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button">
                <i class="bi bi-clock"></i>
        المنتظرة (<?= $totalPending ?>) - صفحة <?= $pendingPage ?>/<?= $totalPendingPages ?>            </button>
            <?php if ($isManager): ?>
            <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                <i class="bi bi-star"></i>
                التقييمات (<?= $pendingReviews ?>)
            </button>
            <?php endif; ?>
            <button class="nav-link" id="visitors-tab" data-bs-toggle="tab" data-bs-target="#visitors" type="button">
                <i class="bi bi-people"></i>
                الزوار
                <span class="live-indicator">مباشر</span>
            </button>
            <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button">
                <i class="bi bi-graph-up"></i>
                الإحصائيات
            </button>
        </div>
    </nav>

    <!-- Tab Content -->
    <div class="tab-content" id="myTabContent">
        <!-- Active Pages Tab -->
        <div class="tab-pane fade show active" id="active" role="tabpanel">
            <div class="data-card">
                <div class="data-card-header">
                    <h5 class="data-card-title">الصفحات النشطة</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>اسم العريس</th>
                                <th>القاعة</th>
                                <th>التاريخ</th>
                                <th>المشاهدات</th>
                                <th>الإعجابات</th>
                                <th>الصور</th>
                                <th>جاهز</th>
                                <th>الحجم</th>
                                <th width="120">مدة الصلاحية</th>
                                <th width="100">الأيام المتبقية</th>
                                <th width="200">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeGrooms as $groom): ?>
                            <?php
// حساب الأيام المتبقية
$startDate = !empty($groom['ready_at']) 
    ? new DateTime($groom['ready_at']) 
    : new DateTime($groom['created_at']);
$now = new DateTime();
$daysElapsed = $now->diff($startDate)->days;
$expiryDays = isset($groom['expiry_days']) && $groom['expiry_days'] > 0 
    ? (int)$groom['expiry_days'] 
    : 90;
$daysLeft = max(0, $expiryDays - $daysElapsed);
?>
                            <tr data-id="<?= $groom['id'] ?>">
                                <td><?= $groom['id'] ?></td>
                                <td><?= htmlspecialchars($groom['groom_name']) ?></td>
                                <td><?= htmlspecialchars($groom['hall_name'] ?? '-') ?></td>
                                <td><?= date('Y-m-d', strtotime($groom['created_at'])) ?></td>
                                <td>
                                    <i class="bi bi-eye text-muted me-1"></i>
                                    <?= number_format($groom['page_views']) ?>
                                </td>
                                <td>
                                    <i class="bi bi-heart text-danger me-1"></i>
                                    <?= number_format($groom['total_likes']) ?>
                                </td>
                                <td>
                                    <i class="bi bi-images text-muted me-1"></i>
                                    <?= $groom['photo_count'] ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" 
                                               id="ready_<?= $groom['id'] ?>"
                                               <?= $groom['ready'] ? 'checked' : '' ?>
                                               onchange="toggleReady(<?= $groom['id'] ?>, this.checked ? 1 : 0)">
                                    </div>
                                </td>
                                <td><?= safeFolderSize($groom['id']) ?></td>
                                <!-- عمود مدة الصلاحية -->
<td>
    <div class="d-flex align-items-center gap-1">
        <input type="number" 
               class="form-control form-control-sm" 
               id="expiry_<?= $groom['id'] ?>"
               value="<?= $expiryDays ?>" 
               min="7" 
               max="365"
               style="width: 60px; text-align: center;"
               title="اضغط مرتين للتعديل"
               ondblclick="this.select()">
        <button class="btn btn-sm btn-primary" 
                onclick="updateExpiryDays(<?= $groom['id'] ?>)"
                title="حفظ">
            <i class="bi bi-check"></i>
        </button>
    </div>
    <small class="text-muted">يوم</small>
</td>

<!-- عمود الأيام المتبقية -->
<td>
    <?php if ($daysLeft > 7): ?>
        <span class="badge bg-success"><?= $daysLeft ?> يوم</span>
    <?php elseif ($daysLeft > 0): ?>
        <span class="badge bg-warning text-dark">⚠️ <?= $daysLeft ?> يوم</span>
    <?php else: ?>
        <span class="badge bg-danger">منتهية</span>
    <?php endif; ?>
</td>

                                <td>
    <div class="d-flex gap-1 justify-content-center">
        <!-- زر العرض -->
        <a href="../groom.php?groom=<?= $groom['id'] ?>" 
           class="btn btn-sm btn-info" target="_blank" title="عرض">
            <i class="bi bi-eye"></i>
        </a>
        
        <!-- زر رابط التقييم -->
        <button class="btn btn-sm btn-warning" 
                onclick="generateRatingLink(<?= $groom['id'] ?>, '<?= htmlspecialchars($groom['groom_name'], ENT_QUOTES) ?>')" 
                title="رابط التقييم">
            <i class="bi bi-star-half"></i>
        </button>
        
        <?php if ($canWrite): ?>
        <a href="edit_groom.php?id=<?= $groom['id'] ?>" 
           class="btn btn-sm btn-warning" title="تعديل">
            <i class="bi bi-pencil"></i>
        </a>
        
        <button class="btn btn-sm btn-secondary" 
                onclick="changeStatus(<?= $groom['id'] ?>, 'block')" 
                title="حجب">
            <i class="bi bi-shield-x"></i>
        </button>
        
        <button class="btn btn-sm btn-secondary" 
                onclick="changeStatus(<?= $groom['id'] ?>, 'deactivate')" 
                title="تعطيل">
            <i class="bi bi-pause-circle"></i>
        </button>
        
        <?php if ($isManager): ?>
        <button class="btn btn-sm btn-danger" 
                onclick="deleteGroom(<?= $groom['id'] ?>)" 
                title="حذف">
            <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($activeGrooms)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>لا توجد صفحات نشطة</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Blocked Pages Tab -->
        <div class="tab-pane fade" id="blocked" role="tabpanel">
            <div class="data-card">
                <div class="data-card-header">
                    <h5 class="data-card-title">الصفحات المحجوبة</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>اسم العريس</th>
                                <th>القاعة</th>
                                <th>التاريخ</th>
                                <th>المشاهدات</th>
                                <th>الإعجابات</th>
                                <th>الصور</th>
                                <th>جاهز</th>
                                <th>الحجم</th>
                               
                                <th width="200">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blockedGrooms as $groom): ?>
                            <tr data-id="<?= $groom['id'] ?>">
                                <td><?= $groom['id'] ?></td>
                                <td><?= htmlspecialchars($groom['groom_name']) ?></td>
                                <td><?= htmlspecialchars($groom['hall_name'] ?? '-') ?></td>
                                <td><?= date('Y-m-d', strtotime($groom['created_at'])) ?></td>
                                <td><?= number_format($groom['page_views']) ?></td>
                                <td><?= number_format($groom['total_likes']) ?></td>
                                <td><?= $groom['photo_count'] ?></td>
                                <td>
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" 
                                               id="ready_<?= $groom['id'] ?>"
                                               <?= $groom['ready'] ? 'checked' : '' ?>
                                               onchange="toggleReady(<?= $groom['id'] ?>, this.checked ? 1 : 0)">
                                    </div>
                                </td>
                                <td><?= safeFolderSize($groom['id']) ?></td>
                                <td>
    <div class="d-flex gap-1 justify-content-center">
        <!-- زر العرض -->
        <a href="../groom.php?groom=<?= $groom['id'] ?>" 
           class="btn btn-sm btn-info" target="_blank" title="عرض">
            <i class="bi bi-eye"></i>
        </a>
        
        <!-- زر رابط التقييم -->
        <button class="btn btn-sm btn-warning" 
                onclick="generateRatingLink(<?= $groom['id'] ?>, '<?= htmlspecialchars($groom['groom_name'], ENT_QUOTES) ?>')" 
                title="رابط التقييم">
            <i class="bi bi-star-half"></i>
        </button>
        
        <?php if ($canWrite): ?>
        <a href="edit_groom.php?id=<?= $groom['id'] ?>" 
           class="btn btn-sm btn-warning" title="تعديل">
            <i class="bi bi-pencil"></i>
        </a>
        
        <button class="btn btn-sm btn-success" 
                onclick="changeStatus(<?= $groom['id'] ?>, 'unblock')" 
                title="إلغاء الحجب">
            <i class="bi bi-shield-check"></i>
        </button>
        
        <?php if ($isManager): ?>
        <button class="btn btn-sm btn-danger" 
                onclick="deleteGroom(<?= $groom['id'] ?>)" 
                title="حذف">
            <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($blockedGrooms)): ?>
                    <div class="empty-state">
                        <i class="bi bi-shield-check"></i>
                        <p>لا توجد صفحات محجوبة</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Inactive Pages Tab -->
        <div class="tab-pane fade" id="inactive" role="tabpanel">
            
            <div class="data-card">
                <div class="data-card-header">
                    <h5 class="data-card-title">الصفحات الخاملة</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th>اسم العريس</th>
                                <th>القاعة</th>
                                <th>التاريخ</th>
                                <th>المشاهدات</th>
                                <th>الإعجابات</th>
                                <th>الصور</th>
                                <th>جاهز</th>
                                <th>الحجم</th>
                                <th width="200">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inactiveGrooms as $groom): ?>
                            <tr data-id="<?= $groom['id'] ?>">
                                <td><?= $groom['id'] ?></td>
                                <td><?= htmlspecialchars($groom['groom_name']) ?></td>
                                <td><?= htmlspecialchars($groom['hall_name'] ?? '-') ?></td>
                                <td><?= date('Y-m-d', strtotime($groom['created_at'])) ?></td>
                                <td><?= number_format($groom['page_views']) ?></td>
                                <td><?= number_format($groom['total_likes']) ?></td>
                                <td><?= $groom['photo_count'] ?></td>
                                <td>
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input" type="checkbox" 
                                               id="ready_<?= $groom['id'] ?>"
                                               <?= $groom['ready'] ? 'checked' : '' ?>
                                               onchange="toggleReady(<?= $groom['id'] ?>, this.checked ? 1 : 0)">
                                    </div>
                                </td>
                                <td><?= safeFolderSize($groom['id']) ?></td>
                                <td>
    <div class="d-flex gap-1 justify-content-center">
        <!-- زر العرض -->
        <a href="../groom.php?groom=<?= $groom['id'] ?>" 
           class="btn btn-sm btn-info" target="_blank" title="عرض">
            <i class="bi bi-eye"></i>
        </a>
        
        <!-- زر رابط التقييم -->
        <button class="btn btn-sm btn-warning" 
                onclick="generateRatingLink(<?= $groom['id'] ?>, '<?= htmlspecialchars($groom['groom_name'], ENT_QUOTES) ?>')" 
                title="رابط التقييم">
            <i class="bi bi-star-half"></i>
        </button>
        
        <?php if ($canWrite): ?>
        <a href="edit_groom.php?id=<?= $groom['id'] ?>" 
           class="btn btn-sm btn-warning" title="تعديل">
            <i class="bi bi-pencil"></i>
        </a>
        
        <button class="btn btn-sm btn-success" 
                onclick="changeStatus(<?= $groom['id'] ?>, 'activate')" 
                title="تفعيل">
            <i class="bi bi-play-circle"></i>
        </button>
        
        <?php if ($isManager): ?>
        <button class="btn btn-sm btn-danger" 
                onclick="deleteGroom(<?= $groom['id'] ?>)" 
                title="حذف">
            <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($inactiveGrooms)): ?>
                    <div class="empty-state">
                        <i class="bi bi-pause-circle"></i>
                        <p>لا توجد صفحات خاملة</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pending Pages Tab -->
        <div class="tab-pane fade" id="pending" role="tabpanel">
            <div class="data-card">
                <div class="data-card-header">
                    <h5 class="data-card-title">الصفحات المنتظرة للمعالجة</h5>
                </div>
                <div class="p-3">
                    <?php foreach ($pendingGrooms as $pending): ?>
                    <div class="pending-page-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <i class="bi bi-person-circle me-2"></i>
                                    <?= htmlspecialchars($pending['groom_name']) ?>
                                </h6>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-building me-2"></i>
                                    <?= htmlspecialchars($pending['location'] ?? 'غير محدد') ?>
                                </p>
                                
                                <?php if (!empty($pending['phone'])): ?>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-telephone me-2"></i>
                                    <?= htmlspecialchars($pending['phone']) ?>
                                </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($pending['booking_date'])): ?>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-calendar-event me-2"></i>
                                    <?= htmlspecialchars($pending['booking_date']) ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <?php if ($canWrite): ?>
                                <button class="btn btn-sm btn-success" 
                                        onclick="createFromPending('<?= $pending['timestamp'] ?>')">
                                    <i class="bi bi-check-circle"></i> إنشاء الصفحة
                                </button>
                                
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deletePending(<?= $pending['id'] ?>)">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($pendingGrooms)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle"></i>
                        <p>لا توجد صفحات منتظرة</p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- أزرار التنقل للمنتظرة -->
<?php if ($totalPendingPages > 1): ?>
<div class="d-flex justify-content-center mt-3">
    <nav>
        <ul class="pagination mb-0">
            <li class="page-item <?= $pendingPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?pending_page=<?= $pendingPage - 1 ?>#pending">السابق</a>
            </li>
            <?php for ($p = 1; $p <= $totalPendingPages; $p++): ?>
                <li class="page-item <?= $p == $pendingPage ? 'active' : '' ?>">
                    <a class="page-link" href="?pending_page=<?= $p ?>#pending"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $pendingPage >= $totalPendingPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?pending_page=<?= $pendingPage + 1 ?>#pending">التالي</a>
            </li>
        </ul>
    </nav>
</div>
<div class="text-center mt-2">
    <small class="text-muted">عرض صفحة <?= $pendingPage ?> من <?= $totalPendingPages ?> (إجمالي: <?= $totalPending ?> صفحة منتظرة)</small>
</div>
<?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Reviews Tab -->
        <?php if ($isManager): ?>
        <div class="tab-pane fade" id="reviews" role="tabpanel">
            <div class="data-card">
                <div class="data-card-header">
                    <h5 class="data-card-title">
                        <i class="bi bi-star text-warning"></i>
                        التقييمات المنتظرة
                    </h5>
                    <a href="reviews.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-right"></i>
                        عرض جميع التقييمات
                    </a>
                </div>
                <div class="p-3">
                    <?php foreach ($pendingReviewsList as $rev): ?>
                    <div class="review-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?= htmlspecialchars($rev['name']) ?></strong>
                                <span class="text-muted mx-2">•</span>
                                <span class="text-primary"><?= htmlspecialchars($rev['groom_name']) ?></span>
                            </div>
                            <div class="text-warning">
                                <?= str_repeat("⭐", (int)$rev['rating']) ?>
                            </div>
                        </div>
                        <p class="mb-2"><?= nl2br(htmlspecialchars($rev['message'])) ?></p>
                        <div class="d-flex gap-2">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                <button name="approve_review" class="btn btn-sm btn-success">
                                    <i class="bi bi-check-circle"></i> نشر
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                <button name="delete_review" class="btn btn-sm btn-danger" 
                                        onclick="return confirm('هل أنت متأكد من حذف التقييم؟')">
                                    <i class="bi bi-trash"></i> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($pendingReviewsList)): ?>
                    <div class="empty-state">
                        <i class="bi bi-star"></i>
                        <p>لا توجد تقييمات منتظرة</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Visitors Tab -->
        <div class="tab-pane fade" id="visitors" role="tabpanel">
            <div class="row">
                <!-- Live Visitors -->
                <div class="col-lg-8 mb-4">
                    <div class="data-card">
                        <div class="data-card-header">
                            <h5 class="data-card-title">
                                <i class="bi bi-people-fill text-info"></i>
                                الزوار النشطين الآن
                                <span class="live-indicator">مباشر</span>
                            </h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshVisitors()">
                                <i class="bi bi-arrow-clockwise"></i> تحديث
                            </button>
                        </div>
                        <div class="p-3">
                            <div id="visitorsContainer" class="visitors-container">
                                <div class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">جاري التحميل...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Visitor Stats -->
                <div class="col-lg-4 mb-4">
                    <div class="data-card">
                        <div class="data-card-header">
                            <h5 class="data-card-title">إحصائيات الزوار</h5>
                        </div>
                        <div class="p-3">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>الزوار الحاليين</span>
                                    <strong><?= $visitorStats['unique_visitors'] ?? 0 ?></strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: 100%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><i class="bi bi-phone"></i> موبايل</span>
                                    <strong><?= $visitorStats['mobile_visitors'] ?? 0 ?></strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?= ($visitorStats['mobile_visitors'] ?? 0) / max(($visitorStats['unique_visitors'] ?? 1), 1) * 100 ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><i class="bi bi-laptop"></i> كمبيوتر</span>
                                    <strong><?= $visitorStats['desktop_visitors'] ?? 0 ?></strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?= ($visitorStats['desktop_visitors'] ?? 0) / max(($visitorStats['unique_visitors'] ?? 1), 1) * 100 ?>%"></div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <h6 class="text-muted mb-2">إجمالي مشاهدات الصفحات</h6>
                                <h3 class="text-primary"><?= number_format($visitorStats['total_page_views'] ?? 0) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Tab -->
        <div class="tab-pane fade" id="stats" role="tabpanel">
            <div class="row">
                <!-- المشاهدات والإعجابات -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-3">إحصائيات المشاهدات والإعجابات</h5>
                        <div class="chart-wrapper">
                            <canvas id="viewsLikesChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- الأكثر مشاهدة -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-3">أكثر 10 صفحات مشاهدة</h5>
                        <div class="chart-wrapper">
                            <canvas id="topPagesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- معدل النمو -->
            <div class="row">
                <div class="col-lg-12 mb-4">
                    <div class="chart-container">
                        <h5 class="mb-3">معدل النمو الشهري</h5>
                        <div class="chart-wrapper">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- جدول أفضل الصفحات -->
            <div class="data-card">
                <div class="data-card-header">
                    <h5 class="data-card-title">أفضل الصفحات أداءً</h5>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>الترتيب</th>
                                <th>اسم العريس</th>
                                <th>المشاهدات</th>
                                <th>الإعجابات</th>
                                <th>معدل التفاعل</th>
                                <th>الصور</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $topPagesTable = array_slice($topPages, 0, 10);
                            foreach ($topPagesTable as $index => $page):
                                $engagementRate = $page['page_views'] > 0 ? 
                                    round(($page['total_likes'] / $page['page_views']) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <a href="../groom.php?groom=<?= $page['id'] ?>" target="_blank">
                                        <?= htmlspecialchars($page['groom_name']) ?>
                                    </a>
                                </td>
                                <td><?= number_format($page['page_views']) ?></td>
                                <td><?= number_format($page['total_likes']) ?></td>
                                <td>
                                    <span class="badge <?= $engagementRate > 5 ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $engagementRate ?>%
                                    </span>
                                </td>
                                <td><?= $page['photo_count'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
<!-- Modal لعرض رابط التقييم -->

واستبدل Modal بالكامل بهذا الكود المحسّن:
-->

<div class="modal fade" id="ratingLinkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-star-half"></i>
                    رابط التقييم والإشعارات
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Loading State -->
                <div id="ratingLinkLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                    <p class="mt-3 text-muted">جاري إنشاء رابط التقييم...</p>
                </div>
                
                <!-- Content State -->
                <div id="ratingLinkContent" style="display: none;">
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle"></i>
                        <strong>تم إنشاء رابط التقييم بنجاح!</strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">العريس:</label>
                        <p class="mb-0" id="modalGroomName"></p>
                        <input type="hidden" id="modalGroomId">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">الرابط:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="modalRatingLink" readonly 
                                   style="direction: ltr; text-align: left; font-family: monospace;">
                            <button class="btn btn-primary" onclick="copyModalLink(this)">
                                <i class="bi bi-clipboard"></i> نسخ
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <a id="modalWhatsappLink" href="#" class="btn btn-success" target="_blank">
                            <i class="bi bi-whatsapp"></i>
                            إرسال عبر واتساب
                        </a>
                        
                        <a id="modalOpenLink" href="#" class="btn btn-info" target="_blank">
                            <i class="bi bi-box-arrow-up-right"></i>
                            فتح الرابط
                        </a>
                    </div>
                    
                    <!-- قسم إرسال الإشعارات - الجديد! -->
                    <div class="alert alert-primary border-start border-4 border-primary">
                        <h6 class="alert-heading">
                            <i class="bi bi-bell-fill"></i>
                            إرسال إشعارات للمشتركين
                        </h6>
                        <p class="mb-2 small">سيتم إرسال إيميل لكل المشتركين بأن صور العريس جاهزة</p>
                        <button id="sendNotificationsBtn" 
                                class="btn btn-primary" 
                                onclick="sendEmailNotifications()">
                            <i class="bi bi-send"></i>
                            إرسال الإشعارات الآن
                        </button>
                    </div>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        صلاحية الرابط: 30 يوم من تاريخ الإنشاء
                    </div>
                </div>
                
                <!-- Error State -->
                <div id="ratingLinkError" style="display: none;">
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>خطأ:</strong> <span id="errorMessage"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Dashboard Functions -->
<script src="dashboard_functions.js"></script>

<!-- Main Dashboard Script -->
<script>
// ==============================================
// 1. تحميل البيانات عند بدء الصفحة
// ==============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard loaded successfully');
    
    // تحميل الزوار
    if (typeof loadVisitors === 'function') {
        loadVisitors();
        setInterval(loadVisitors, 30000); // تحديث كل 30 ثانية
    }
    
    // تحميل الرسوم البيانية
    loadChartData();
});

// ==============================================
// 2. دالة تحميل بيانات الرسوم البيانية
// ==============================================
function loadChartData() {
    fetch('get_chart_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createCharts(data.chartData);
            } else {
                createDemoCharts();
            }
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
            createDemoCharts();
        });
}

// ==============================================
// 3. دالة إنشاء الرسوم البيانية
// ==============================================
function createCharts(chartData) {
    // رسم المشاهدات والإعجابات
    const viewsLikesCtx = document.getElementById('viewsLikesChart');
    if (viewsLikesCtx) {
        new Chart(viewsLikesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: [{
                    label: 'المشاهدات',
                    data: chartData.views || [],
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'الإعجابات',
                    data: chartData.likes || [],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // رسم أكثر الصفحات مشاهدة
    const topPagesCtx = document.getElementById('topPagesChart');
    if (topPagesCtx) {
        new Chart(topPagesCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.topLabels || [],
                datasets: [{
                    label: 'المشاهدات',
                    data: chartData.topViews || [],
                    backgroundColor: [
                        '#4f46e5', '#7c3aed', '#db2777', '#dc2626', '#ea580c',
                        '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ec4899'
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            callback: function(value, index, values) {
                                const label = this.getLabelForValue(value);
                                return label && label.length > 10 ? label.substr(0, 10) + '...' : label;
                            }
                        }
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // رسم معدل النمو
    const growthCtx = document.getElementById('growthChart');
    if (growthCtx) {
        new Chart(growthCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.labels || [],
                datasets: [{
                    label: 'صفحات جديدة',
                    data: chartData.pages || [],
                    backgroundColor: '#6366f1',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
}

// ==============================================
// 4. دالة إنشاء رسوم بيانية تجريبية
// ==============================================
function createDemoCharts() {
    const demoData = {
        labels: ['يناير 2025', 'فبراير 2025', 'مارس 2025', 'أبريل 2025', 'مايو 2025', 'يونيو 2025'],
        views: [1200, 1900, 1500, 2500, 2000, 3000],
        likes: [80, 120, 100, 180, 150, 220],
        pages: [12, 19, 15, 25, 18, 22],
        topLabels: ['أحمد محمد', 'خالد علي', 'محمد حسن', 'عبدالله سالم', 'يوسف أحمد'],
        topViews: [1250, 980, 750, 620, 450]
    };
    
    createCharts(demoData);
}

// ==============================================
// 5. دالة إنشاء صفحة من المنتظرة
// ==============================================
function createFromPending(timestamp) {
    window.location.href = `create_from_pending.php?timestamp=${encodeURIComponent(timestamp)}`;
}

// ==============================================
// 6. دوال الاستيراد من Google Sheets
// ==============================================
function importFromSheets() {
    Swal.fire({
        title: 'استيراد العرسان',
        html: `
            <div style="text-align: right;">
                <p>اختر طريقة الاستيراد:</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" onclick="openAdvancedImport()">
                        <i class="bi bi-window"></i> واجهة الاستيراد المتقدمة
                    </button>
                    <button class="btn btn-success btn-lg" onclick="quickImport()">
                        <i class="bi bi-lightning"></i> استيراد سريع
                    </button>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'إلغاء',
        width: 500
    });
}

function openAdvancedImport() {
    Swal.close();
    const width = 1200;
    const height = 800;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    const importWindow = window.open(
        'tools/import_manager.php',
        'ImportManager',
        `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,scrollbars=yes,resizable=yes`
    );
    
    if (importWindow) {
        importWindow.focus();
    }
}

function quickImport() {
    Swal.close();
    
    Swal.fire({
        title: 'جاري الاستيراد...',
        text: 'يرجى الانتظار',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('tools/import_pending_grooms.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let resultHtml = `
                    <div class="text-right">
                        <h5>نتائج الاستيراد:</h5>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> <strong>تم استيراد:</strong> ${data.stats.imported} سجل جديد</li>
                            <li><i class="bi bi-arrow-repeat text-warning"></i> <strong>تم تحديث:</strong> ${data.stats.updated} سجل</li>
                            <li><i class="bi bi-skip-forward text-info"></i> <strong>تم تخطي:</strong> ${data.stats.skipped} سجل</li>
                        </ul>
                    </div>
                `;
                
                Swal.fire({
                    icon: 'success',
                    title: 'اكتمل الاستيراد',
                    html: resultHtml,
                    confirmButtonText: 'ممتاز',
                    timer: 5000,
                    timerProgressBar: true
                });
                
                setTimeout(() => location.reload(), 3000);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'فشل الاستيراد',
                    text: data.error || 'حدث خطأ غير متوقع',
                    confirmButtonText: 'حسناً'
                });
            }
        })
        .catch(error => {
            console.error('Import error:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ في الاتصال',
                text: error.message,
                confirmButtonText: 'حسناً'
            });
        });
}

function viewApiDocs() {
    Swal.fire({
        title: 'توثيق API والصفحات',
        html: `
            <div style="text-align: right; max-height: 500px; overflow-y: auto;">
                <h6 class="text-primary mb-3">🔔 API الإشعارات</h6>
                <div class="alert alert-info text-start mb-2">
                    <strong>POST</strong> /api/subscribe_push.php<br>
                    <small>تسجيل اشتراك إشعارات المتصفح</small>
                </div>
                
                <h6 class="text-primary mb-3">⭐ API التقييمات</h6>
                <div class="alert alert-info text-start mb-3">
                    <strong>POST</strong> /api/submit_rating.php<br>
                    <small>إرسال تقييم جديد من الزوار</small>
                </div>
            </div>
        `,
        width: 800,
        confirmButtonText: 'إغلاق'
    });
}

// ==============================================
// 7. دوال روابط التقييم - الأهم!
// ==============================================
function generateRatingLink(groomId, groomName) {
    console.log('generateRatingLink called with:', groomId, groomName);
    
    const modal = new bootstrap.Modal(document.getElementById('ratingLinkModal'));
    
    // إعادة تعيين المحتوى
    document.getElementById('ratingLinkLoading').style.display = 'block';
    document.getElementById('ratingLinkContent').style.display = 'none';
    document.getElementById('ratingLinkError').style.display = 'none';
    
    modal.show();
    
    // طلب إنشاء الرابط
    fetch('generate_rating_token.php?groom_id=' + groomId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('ratingLinkLoading').style.display = 'none';
            
            if (data.success) {
                const protocol = window.location.protocol;
                const domain = window.location.host;
                const ratingLink = `${protocol}//${domain}/rate.php?token=${data.token}`;
                
                // حفظ معلومات العريس
                document.getElementById('modalGroomName').textContent = groomName;
                document.getElementById('modalGroomId').value = groomId;
                document.getElementById('modalRatingLink').value = ratingLink;
                document.getElementById('modalOpenLink').href = ratingLink;
                
                const whatsappMessage = 
                    "السلام عليكم ورحمة الله وبركاته\n\n" +
                    "نشكرك على اختيارنا لتصوير زواجك. نتمنى أن تشاركنا رأيك في خدماتنا من خلال هذا الرابط:\n\n" +
                    ratingLink + "\n\n" +
                    "⏰ صلاحية الرابط: 30 يوم\n\n" +
                    "فريق جذلة للتصوير 📸";
                
                document.getElementById('modalWhatsappLink').href = 
                    'https://wa.me/?text=' + encodeURIComponent(whatsappMessage);
                
                document.getElementById('ratingLinkContent').style.display = 'block';
            } else {
                document.getElementById('errorMessage').textContent = data.error || 'حدث خطأ غير متوقع';
                document.getElementById('ratingLinkError').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Rating link error:', error);
            document.getElementById('ratingLinkLoading').style.display = 'none';
            document.getElementById('errorMessage').textContent = 'فشل الاتصال بالخادم: ' + error.message;
            document.getElementById('ratingLinkError').style.display = 'block';
        });
}

// ==============================================
// دالة إرسال الإشعارات - جديدة!
// ==============================================
function sendEmailNotifications() {
    const groomId = document.getElementById('modalGroomId').value;
    const groomName = document.getElementById('modalGroomName').textContent;
    const btn = document.getElementById('sendNotificationsBtn');
    
    if (!groomId) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: 'لم يتم العثور على معرف العريس'
        });
        return;
    }
    
    // تأكيد الإرسال
    Swal.fire({
        title: 'إرسال الإشعارات؟',
        html: `
            <div style="text-align: right;">
                <p>سيتم إرسال إيميل لجميع المشتركين بأن صور العريس:</p>
                <p class="fw-bold text-primary">${groomName}</p>
                <p>أصبحت جاهزة الآن.</p>
                <p class="text-muted small">⚠️ لن يتم الإرسال للمشتركين الذين تم إشعارهم مسبقاً</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، أرسل الآن',
        cancelButtonText: 'إلغاء',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            // تعطيل الزر أثناء الإرسال
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>جاري الإرسال...';
            
            return fetch(`../api/send_email_notifications_simple.php?groom_id=${groomId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('خطأ في الاتصال بالخادم');
                    }
                    return response.json();
                })
                .then(data => {
                    // إعادة تفعيل الزر
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send"></i> إرسال الإشعارات الآن';
                    
                    if (!data.success) {
                        throw new Error(data.error || 'حدث خطأ غير متوقع');
                    }
                    return data;
                })
                .catch(error => {
                    // إعادة تفعيل الزر
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send"></i> إرسال الإشعارات الآن';
                    
                    Swal.showValidationMessage(`خطأ: ${error.message}`);
                });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const data = result.value;
            
            // إذا لم يكن هناك مشتركين
            if (data.sent === 0 && data.failed === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'لا يوجد مشتركين',
                    text: data.message || 'لا يوجد مشتركين لم يتم إشعارهم بعد',
                    confirmButtonText: 'حسناً'
                });
                return;
            }
            
            // عرض نتيجة الإرسال
            let resultHtml = `
                <div style="text-align: right;">
                    <h5 class="mb-3">تم إرسال الإشعارات بنجاح! 🎉</h5>
                    <div class="alert alert-success text-start">
                        <h6 class="alert-heading">نتائج الإرسال:</h6>
                        <hr>
                        <ul class="list-unstyled mb-0">
                            <li><i class="bi bi-check-circle-fill text-success"></i> <strong>تم الإرسال:</strong> ${data.sent} إيميل</li>
                            ${data.failed > 0 ? `<li><i class="bi bi-x-circle-fill text-danger"></i> <strong>فشل:</strong> ${data.failed} إيميل</li>` : ''}
                            <li><i class="bi bi-envelope-fill text-info"></i> <strong>إجمالي المشتركين:</strong> ${data.total}</li>
                        </ul>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-info-circle"></i>
                        تم وضع علامة "تم الإشعار" على جميع المشتركين الذين تم إرسال الإيميل لهم بنجاح
                    </p>
                </div>
            `;
            
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح',
                html: resultHtml,
                confirmButtonText: 'ممتاز',
                width: 600
            });
        }
    });
}

function copyModalLink(button) {
    const linkInput = document.getElementById('modalRatingLink');
    const linkText = linkInput.value.trim();
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(linkText)
            .then(() => showModalCopySuccess(button))
            .catch(() => fallbackModalCopy(linkInput, button));
    } else {
        fallbackModalCopy(linkInput, button);
    }
}

function fallbackModalCopy(input, button) {
    input.select();
    input.setSelectionRange(0, 99999);
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showModalCopySuccess(button);
        } else {
            alert('فشل النسخ. يرجى المحاولة يدوياً');
        }
    } catch (err) {
        alert('فشل النسخ. يرجى المحاولة يدوياً');
    }
}

function showModalCopySuccess(button) {
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check-circle"></i> تم النسخ!';
    button.classList.add('btn-success');
    button.classList.remove('btn-primary');
    
    setTimeout(() => {
        button.innerHTML = originalHTML;
        button.classList.add('btn-primary');
        button.classList.remove('btn-success');
    }, 2000);
}

// تسجيل الدوال للتشخيص
console.log('Notifications functions loaded:', {
    generateRatingLink: typeof generateRatingLink,
    sendEmailNotifications: typeof sendEmailNotifications
});

// ==============================================
// دالة تفعيل/تعطيل "جاهز" مع إرسال الإشعارات
// أضف هذا الكود في نهاية <script> في dashboard.php
// ==============================================

function toggleReady(groomId, readyValue) {
    // إذا كان التفعيل (من 0 إلى 1)
    if (readyValue === 1) {
        // إظهار نافذة تأكيد مع خيار إرسال الإشعارات
        Swal.fire({
            title: 'تفعيل الصفحة كـ "جاهزة"',
            html: `
                <div style="text-align: right;">
                    <p class="mb-3">سيتم تفعيل الصفحة كـ "جاهزة"</p>
                    <div class="form-check text-start">
                        <input class="form-check-input" type="checkbox" id="sendNotificationsCheck" checked>
                        <label class="form-check-label fw-bold" for="sendNotificationsCheck">
                            📧 إرسال إشعارات للمشتركين
                        </label>
                        <p class="text-muted small mb-0 mt-1">سيتم إرسال إيميل لجميع المشتركين بأن الصور جاهزة</p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'تفعيل',
            cancelButtonText: 'إلغاء',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                const sendNotifications = document.getElementById('sendNotificationsCheck').checked;
                
                // تحديث حالة Ready في قاعدة البيانات
                return fetch('update_ready_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `groom_id=${groomId}&ready=${readyValue}`
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'فشل تحديث الحالة');
                    }
                    
                    // إذا اختار المستخدم إرسال الإشعارات
                    if (sendNotifications) {
                        return fetch(`../api/send_email_notifications_simple.php?groom_id=${groomId}`)
                            .then(res => res.json())
                            .then(notifData => ({
                                statusUpdated: true,
                                notificationsSent: true,
                                notifData: notifData
                            }));
                    }
                    
                    return { statusUpdated: true, notificationsSent: false };
                })
                .catch(error => {
                    Swal.showValidationMessage(`خطأ: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                const data = result.value;
                
                if (data.notificationsSent && data.notifData) {
                    // عرض نتائج الإرسال
                    const notifData = data.notifData;
                    
                    if (notifData.success) {
                        let resultHtml = `
                            <div style="text-align: right;">
                                <h5 class="mb-3">تم بنجاح! ✅</h5>
                                <div class="alert alert-success text-start">
                                    <h6 class="alert-heading">✓ تم تفعيل الصفحة كـ "جاهزة"</h6>
                                    <hr>
                                    <h6 class="alert-heading">📧 نتائج إرسال الإشعارات:</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><i class="bi bi-check-circle-fill text-success"></i> <strong>تم الإرسال:</strong> ${notifData.sent} إيميل</li>
                                        ${notifData.failed > 0 ? `<li><i class="bi bi-x-circle-fill text-danger"></i> <strong>فشل:</strong> ${notifData.failed} إيميل</li>` : ''}
                                        <li><i class="bi bi-envelope-fill text-info"></i> <strong>إجمالي المشتركين:</strong> ${notifData.total}</li>
                                    </ul>
                                </div>
                            </div>
                        `;
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'تم بنجاح',
                            html: resultHtml,
                            confirmButtonText: 'ممتاز',
                            width: 600
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'تم التفعيل',
                            html: `
                                <p>تم تفعيل الصفحة بنجاح ✅</p>
                                <p class="text-muted">لكن حدث خطأ في إرسال الإشعارات: ${notifData.error || 'خطأ غير معروف'}</p>
                            `,
                            confirmButtonText: 'حسناً'
                        });
                    }
                } else {
                    // تم التفعيل بدون إرسال إشعارات
                    Swal.fire({
                        icon: 'success',
                        title: 'تم التفعيل',
                        text: 'تم تفعيل الصفحة كـ "جاهزة" بنجاح',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                // إذا ألغى المستخدم، أعد الـ checkbox إلى حالته السابقة
                const checkbox = document.getElementById(`ready_${groomId}`);
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
        });
        
    } else {
        // إذا كان التعطيل (من 1 إلى 0) - بدون إشعارات
        Swal.fire({
            title: 'تعطيل "جاهز"',
            text: 'هل تريد تعطيل هذه الصفحة من حالة "جاهزة"؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'نعم، عطّل',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('update_ready_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `groom_id=${groomId}&ready=${readyValue}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم التعطيل',
                            text: 'تم تعطيل حالة "جاهز" بنجاح',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        throw new Error(data.error || 'فشل التعطيل');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: error.message
                    });
                    
                    // إعادة الـ checkbox إلى حالته السابقة
                    const checkbox = document.getElementById(`ready_${groomId}`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            } else {
                // إذا ألغى المستخدم، أعد الـ checkbox إلى حالته السابقة
                const checkbox = document.getElementById(`ready_${groomId}`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            }
        });
    }
}

console.log('toggleReady function loaded successfully');

// ==============================================
// دالة تفعيل/تعطيل "جاهز" مع إرسال الإشعارات
// ==============================================

// ==============================================
// دالة تفعيل/تعطيل "جاهز" مع إرسال الإشعارات
// ==============================================

// ==============================================
// دالة تفعيل/تعطيل "جاهز" مباشرة مع إرسال الإشعارات (بدون تأكيد)
// ==============================================

function toggleReady(groomId, readyValue) {
    const checkbox = document.getElementById(`ready_${groomId}`);
    
    // إذا كان التفعيل (من 0 إلى 1)
    if (readyValue === 1) {
        
        // إظهار Loading
        Swal.fire({
            title: 'جاري التفعيل وإرسال الإشعارات...',
            html: 'يرجى الانتظار',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        // تنفيذ العملية مباشرة
        (async () => {
            try {
                // 1. تحديث حالة Ready
                const statusResponse = await fetch('update_page_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${groomId}&action=toggle_ready&ready=${readyValue}`
                });
                
                const statusData = await statusResponse.json();
                
                if (!statusData.success) {
                    throw new Error(statusData.message || 'فشل تحديث الحالة');
                }
                
                let pushResult = { sent: 0, failed: 0, total: 0 };
                let emailResult = { sent: 0, failed: 0, total: 0 };
                
                // 2. إرسال Push Notifications
                try {
                    const pushResponse = await fetch(`../api/send_notifications.php?groom_id=${groomId}`);
                    const pushData = await pushResponse.json();
                    
                    if (pushData.success) {
                        pushResult = {
                            sent: pushData.sent || 0,
                            failed: pushData.failed || 0,
                            total: (pushData.sent || 0) + (pushData.failed || 0)
                        };
                    }
                } catch (pushError) {
                    console.error('Push notification error:', pushError);
                }
                
                // 3. إرسال Email Notifications
                try {
                    const emailResponse = await fetch(`../api/send_email_notifications_simple.php?groom_id=${groomId}`);
                    const emailData = await emailResponse.json();
                    
                    if (emailData.success) {
                        emailResult = {
                            sent: emailData.sent || 0,
                            failed: emailData.failed || 0,
                            total: emailData.total || 0
                        };
                    }
                } catch (emailError) {
                    console.error('Email notification error:', emailError);
                }
                
                // عرض النتائج
                const totalSent = pushResult.sent + emailResult.sent;
                const totalFailed = pushResult.failed + emailResult.failed;
                
                let resultHtml = `
                    <div style="text-align: right;">
                        <h5 class="mb-3">تم بنجاح! 🎉</h5>
                        <div class="alert alert-success text-start">
                            <h6 class="alert-heading">✓ تم تفعيل الصفحة كـ "جاهزة"</h6>
                            <hr>
                            <h6 class="alert-heading">📊 نتائج إرسال الإشعارات:</h6>
                            
                            ${pushResult.total > 0 ? `
                            <div class="mb-3">
                                <strong>🔔 إشعارات المتصفح (Push):</strong>
                                <ul class="list-unstyled mb-0 ms-3">
                                    <li><i class="bi bi-check-circle-fill text-success"></i> تم الإرسال: ${pushResult.sent}</li>
                                    ${pushResult.failed > 0 ? `<li><i class="bi bi-x-circle-fill text-danger"></i> فشل: ${pushResult.failed}</li>` : ''}
                                    <li><i class="bi bi-people-fill text-info"></i> إجمالي المشتركين: ${pushResult.total}</li>
                                </ul>
                            </div>
                            ` : '<p class="text-muted small">لا يوجد مشتركين في إشعارات المتصفح</p>'}
                            
                            ${emailResult.total > 0 ? `
                            <div class="mb-3">
                                <strong>📧 إشعارات الإيميل:</strong>
                                <ul class="list-unstyled mb-0 ms-3">
                                    <li><i class="bi bi-check-circle-fill text-success"></i> تم الإرسال: ${emailResult.sent}</li>
                                    ${emailResult.failed > 0 ? `<li><i class="bi bi-x-circle-fill text-danger"></i> فشل: ${emailResult.failed}</li>` : ''}
                                    <li><i class="bi bi-envelope-fill text-info"></i> إجمالي المشتركين: ${emailResult.total}</li>
                                </ul>
                            </div>
                            ` : '<p class="text-muted small">لا يوجد مشتركين في إشعارات الإيميل</p>'}
                            
                            <hr>
                            <div class="text-center">
                                <strong class="text-success">إجمالي الإشعارات المرسلة: ${totalSent}</strong>
                                ${totalFailed > 0 ? `<br><span class="text-danger">فشل: ${totalFailed}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                
                Swal.fire({
                    icon: totalSent > 0 ? 'success' : 'info',
                    title: totalSent > 0 ? 'تم بنجاح' : 'تم التفعيل',
                    html: resultHtml,
                    confirmButtonText: 'ممتاز',
                    width: 700
                });
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: error.message || 'حدث خطأ غير متوقع',
                    confirmButtonText: 'حسناً'
                });
                
                // إعادة الـ checkbox
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
        })();
        
    } else {
        // التعطيل (من 1 إلى 0) - مباشرة بدون سؤال
        fetch('update_page_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${groomId}&action=toggle_ready&ready=${readyValue}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // إشعار صغير بالنجاح
                Swal.fire({
                    icon: 'success',
                    title: 'تم التعطيل',
                    text: 'تم تعطيل حالة "جاهز" بنجاح',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                throw new Error(data.message || 'فشل التعطيل');
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: error.message,
                timer: 2000
            });
            
            // إعادة الـ checkbox إلى حالته السابقة
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
}

console.log('✅ toggleReady loaded - Direct mode (no confirmation)');


// دالة تحديث مدة الصلاحية
function updateExpiryDays(groomId) {
    const input = document.getElementById(`expiry_${groomId}`);
    const newValue = parseInt(input.value);
    
    if (!newValue || newValue < 7 || newValue > 365) {
        Swal.fire({
            icon: 'error',
            title: 'قيمة غير صحيحة',
            text: 'المدة يجب أن تكون بين 7 و 365 يوم',
            timer: 3000
        });
        return;
    }
    
    Swal.fire({
        title: 'جاري التحديث...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => Swal.showLoading()
    });
    
    fetch('update_expiry_days.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `groom_id=${groomId}&expiry_days=${newValue}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم التحديث',
                html: `
                    <div style="text-align: right;">
                        <p>✅ تم تحديث مدة الصلاحية بنجاح</p>
                        <p><strong>المدة الجديدة:</strong> ${data.data.expiry_days} يوم</p>
                        <p><strong>الأيام المتبقية:</strong> ${data.data.days_left} يوم</p>
                    </div>
                `,
                timer: 3000,
                showConfirmButton: false
            });
            setTimeout(() => location.reload(), 2000);
        } else {
            throw new Error(data.error || 'حدث خطأ');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.message
        });
    });
}

// اختصار Enter للحفظ
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[id^="expiry_"]').forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const groomId = this.id.replace('expiry_', '');
                updateExpiryDays(groomId);
            }
        });
    });
});

console.log('✅ Expiry system loaded');

</script>


</body>
</html>