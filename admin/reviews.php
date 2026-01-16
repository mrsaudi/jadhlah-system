<?php
// admin/reviews.php - إدارة التقييمات
session_start();
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'manager') {
    header('Location: index.php');
    exit;
}

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reviewId = (int)($_POST['review_id'] ?? 0);
    
    if ($reviewId > 0) {
        try {
            if (isset($_POST['approve'])) {
                $stmt = $pdo->prepare("UPDATE groom_reviews SET is_approved = 1 WHERE id = ?");
                $stmt->execute([$reviewId]);
                $_SESSION['flash'] = 'تم نشر التقييم بنجاح';
            } elseif (isset($_POST['delete'])) {
                $stmt = $pdo->prepare("DELETE FROM groom_reviews WHERE id = ?");
                $stmt->execute([$reviewId]);
                $_SESSION['flash'] = 'تم حذف التقييم';
            } elseif (isset($_POST['block'])) {
                $stmt = $pdo->prepare("UPDATE groom_reviews SET blocked = 1 WHERE id = ?");
                $stmt->execute([$reviewId]);
                $_SESSION['flash'] = 'تم حظر التقييم';
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'حدث خطأ في معالجة الطلب';
        }
    }
    
    header('Location: reviews.php');
    exit;
}

// جلب التقييمات
$filter = $_GET['filter'] ?? 'pending';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// بناء الاستعلام حسب الفلتر
$whereClause = '';
switch ($filter) {
    case 'pending':
        $whereClause = 'WHERE r.is_approved = 0 AND (r.blocked = 0 OR r.blocked IS NULL)';
        break;
    case 'approved':
        $whereClause = 'WHERE r.is_approved = 1';
        break;
    case 'blocked':
        $whereClause = 'WHERE r.blocked = 1';
        break;
    default:
        $whereClause = '';
}

// عد التقييمات
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM groom_reviews r 
    JOIN grooms g ON g.id = r.groom_id 
    $whereClause
");
$countStmt->execute();
$totalReviews = $countStmt->fetchColumn();
$totalPages = ceil($totalReviews / $limit);

// جلب التقييمات
$stmt = $pdo->prepare("
    SELECT r.*, g.groom_name, g.wedding_date,
           (SELECT COUNT(*) FROM groom_reviews WHERE groom_id = r.groom_id) as groom_total_reviews,
           (SELECT AVG(rating) FROM groom_reviews WHERE groom_id = r.groom_id AND is_approved = 1) as groom_avg_rating
    FROM groom_reviews r
    JOIN grooms g ON g.id = r.groom_id
    $whereClause
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();

// إحصائيات
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM groom_reviews")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM groom_reviews WHERE is_approved = 0 AND (blocked = 0 OR blocked IS NULL)")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM groom_reviews WHERE is_approved = 1")->fetchColumn(),
    'blocked' => $pdo->query("SELECT COUNT(*) FROM groom_reviews WHERE blocked = 1")->fetchColumn(),
    'avg_rating' => $pdo->query("SELECT AVG(rating) FROM groom_reviews WHERE is_approved = 1")->fetchColumn() ?: 0
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التقييمات - جذلة</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border-radius: 16px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .review-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
        }

        .review-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .rating-stars {
            color: #f59e0b;
            font-size: 1.1rem;
        }

        .filter-tabs {
            background: white;
            padding: 0.5rem;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .filter-tabs .nav-link {
            color: #6b7280;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            margin: 0 0.25rem;
        }

        .filter-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-badge.approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }

        .status-badge.blocked {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="bi bi-star-fill text-warning"></i>
                        إدارة التقييمات
                    </h1>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">لوحة التحكم</a></li>
                            <li class="breadcrumb-item active">إدارة التقييمات</li>
                        </ol>
                    </nav>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right"></i>
                    رجوع
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($_SESSION['flash']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <h4 class="text-primary"><?= number_format($stats['total']) ?></h4>
                    <p class="text-muted mb-0">إجمالي التقييمات</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <h4 class="text-warning"><?= number_format($stats['pending']) ?></h4>
                    <p class="text-muted mb-0">بانتظار الموافقة</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <h4 class="text-success"><?= number_format($stats['approved']) ?></h4>
                    <p class="text-muted mb-0">تقييمات منشورة</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <h4 class="text-warning">
                        <i class="bi bi-star-fill"></i>
                        <?= number_format($stats['avg_rating'], 1) ?>
                    </h4>
                    <p class="text-muted mb-0">متوسط التقييم</p>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <nav class="filter-tabs">
            <ul class="nav nav-tabs border-0">
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'pending' ? 'active' : '' ?>" 
                       href="?filter=pending">
                        بانتظار الموافقة (<?= $stats['pending'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'approved' ? 'active' : '' ?>" 
                       href="?filter=approved">
                        منشورة (<?= $stats['approved'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'blocked' ? 'active' : '' ?>" 
                       href="?filter=blocked">
                        محظورة (<?= $stats['blocked'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" 
                       href="?filter=all">
                        جميع التقييمات
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Reviews List -->
        <?php if (empty($reviews)): ?>
        <div class="text-center py-5 bg-white rounded">
            <i class="bi bi-star display-1 text-muted"></i>
            <p class="text-muted mt-3">لا توجد تقييمات في هذا القسم</p>
        </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <div class="review-card">
                <div class="row">
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($review['name']) ?></h5>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-calendar3"></i>
                                    <?= date('Y-m-d H:i', strtotime($review['created_at'])) ?>
                                </p>
                            </div>
                            <div>
                                <?php if ($review['is_approved']): ?>
                                    <span class="status-badge approved">منشور</span>
                                <?php elseif ($review['blocked']): ?>
                                    <span class="status-badge blocked">محظور</span>
                                <?php else: ?>
                                    <span class="status-badge pending">بانتظار الموافقة</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="rating-stars mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill' : '' ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted ms-2">(<?= $review['rating'] ?>/5)</span>
                        </div>
                        
                        <p class="mb-3"><?= nl2br(htmlspecialchars($review['message'])) ?></p>
                        
                        <div class="text-muted small">
                            <i class="bi bi-person-heart"></i>
                            العريس: <a href="../groom.php?groom=<?= $review['groom_id'] ?>" target="_blank">
                                <?= htmlspecialchars($review['groom_name']) ?>
                            </a>
                            <span class="mx-2">•</span>
                            <i class="bi bi-chat-dots"></i>
                            <?= $review['groom_total_reviews'] ?> تقييم
                            <span class="mx-2">•</span>
                            <i class="bi bi-star"></i>
                            متوسط: <?= number_format($review['groom_avg_rating'], 1) ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4 text-end">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                            
                            <?php if (!$review['is_approved'] && !$review['blocked']): ?>
                                <button type="submit" name="approve" class="btn btn-success btn-sm mb-1">
                                    <i class="bi bi-check-circle"></i> نشر
                                </button>
                            <?php endif; ?>
                            
                            <?php if (!$review['blocked']): ?>
                                <button type="submit" name="block" class="btn btn-warning btn-sm mb-1">
                                    <i class="bi bi-x-circle"></i> حظر
                                </button>
                            <?php endif; ?>
                            
                            <button type="submit" name="delete" class="btn btn-danger btn-sm mb-1"
                                    onclick="return confirm('هل أنت متأكد من حذف هذا التقييم نهائياً؟')">
                                <i class="bi bi-trash"></i> حذف
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>">السابق</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>">التالي</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>