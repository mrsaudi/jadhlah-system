<?php
// admin/tools/manage_deleted.php
// واجهة إدارة المحذوفين
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: ../../index.php');
    exit;
}

// الاتصال بقاعدة البيانات
$host = 'localhost';
$dbname = 'u709146392_jadhlah_db';
$username = 'u709146392_jad_admin';
$password = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في الاتصال: " . $e->getMessage());
}

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['restore'])) {
        $id = (int)$_POST['id'];
        
        // جلب البيانات من سلة المحذوفات
        $stmt = $pdo->prepare("SELECT * FROM deleted_pending_grooms WHERE id = ?");
        $stmt->execute([$id]);
        $deleted = $stmt->fetch();
        
        if ($deleted) {
            $originalData = json_decode($deleted['original_data'], true);
            
            try {
                $pdo->beginTransaction();
                
                // إعادة الإدراج في pending_grooms
                $insertStmt = $pdo->prepare("
                    INSERT INTO pending_grooms (
                        timestamp, groom_name, phone, booking_date, location, 
                        package, services, equipment, time_slot, delivery_method,
                        paid_amount, remaining_amount, total_amount,
                        employee_name, invoice_number, created_at, is_deleted
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0
                    )
                ");
                
                $insertStmt->execute([
                    $originalData['timestamp'] ?? null,
                    $originalData['groom_name'],
                    $originalData['phone'] ?? null,
                    $originalData['booking_date'] ?? null,
                    $originalData['location'] ?? null,
                    $originalData['package'] ?? null,
                    $originalData['services'] ?? null,
                    $originalData['equipment'] ?? null,
                    $originalData['time_slot'] ?? null,
                    $originalData['delivery_method'] ?? null,
                    $originalData['paid_amount'] ?? '0.00',
                    $originalData['remaining_amount'] ?? '0.00',
                    $originalData['total_amount'] ?? '0.00',
                    $originalData['employee_name'] ?? null,
                    $originalData['invoice_number'] ?? null
                ]);
                
                // حذف من سلة المحذوفات
                $pdo->prepare("DELETE FROM deleted_pending_grooms WHERE id = ?")->execute([$id]);
                
                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'message' => "تم استرجاع العريس بنجاح"];
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash'] = ['type' => 'danger', 'message' => "خطأ في الاسترجاع: " . $e->getMessage()];
            }
        }
    } elseif (isset($_POST['delete_forever'])) {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM deleted_pending_grooms WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'warning', 'message' => "تم الحذف النهائي"];
    } elseif (isset($_POST['clear_all'])) {
        $pdo->exec("TRUNCATE TABLE deleted_pending_grooms");
        $_SESSION['flash'] = ['type' => 'warning', 'message' => "تم تفريغ سلة المحذوفات"];
    }
    
    header('Location: manage_deleted.php');
    exit;
}

// جلب المحذوفين
$deletedStmt = $pdo->query("
    SELECT * FROM deleted_pending_grooms 
    ORDER BY deleted_at DESC
");
$deletedGrooms = $deletedStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المحذوفين - جذلة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        .btn-action {
            margin: 2px;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <!-- عنوان الصفحة -->
    <div class="row mb-4">
        <div class="col">
            <h2 class="text-danger">
                <i class="bi bi-trash3"></i> سلة المحذوفات
            </h2>
        </div>
    </div>
    
    <!-- إحصائيات -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <h5>إجمالي المحذوفين</h5>
                <h2><?= count($deletedGrooms) ?></h2>
            </div>
        </div>
    </div>
    
    <!-- رسائل التنبيه -->
    <?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show">
        <?= $_SESSION['flash']['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <!-- البطاقة الرئيسية -->
    <div class="card shadow">
        <div class="card-header bg-danger text-white">
            <div class="row">
                <div class="col">
                    <h4>العرسان المحذوفين</h4>
                </div>
                <div class="col-auto">
                    <?php if (!empty($deletedGrooms)): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من تفريغ سلة المحذوفات نهائياً؟ لا يمكن التراجع عن هذا الإجراء!')">
                        <button type="submit" name="clear_all" class="btn btn-dark btn-sm">
                            <i class="bi bi-trash3"></i> تفريغ السلة نهائياً
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (empty($deletedGrooms)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle fs-1"></i>
                <h5 class="mt-3">سلة المحذوفات فارغة</h5>
                <p>لا توجد عرسان محذوفين حالياً</p>
            </div>
            <?php else: ?>
            
            <div class="table-responsive table-container">
                <table class="table table-hover">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th width="5%">#</th>
                            <th width="20%">اسم العريس</th>
                            <th width="15%">الهاتف</th>
                            <th width="15%">الموقع</th>
                            <th width="10%">حذف بواسطة</th>
                            <th width="15%">تاريخ الحذف</th>
                            <th width="10%">السبب</th>
                            <th width="10%">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deletedGrooms as $index => $deleted): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($deleted['groom_name']) ?></strong>
                                <?php if ($deleted['booking_date']): ?>
                                <br><small class="text-muted">الحجز: <?= htmlspecialchars($deleted['booking_date']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($deleted['phone']): ?>
                                <a href="https://wa.me/<?= htmlspecialchars($deleted['phone']) ?>" target="_blank" class="text-success">
                                    <i class="bi bi-whatsapp"></i>
                                    <?= htmlspecialchars($deleted['phone']) ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($deleted['location'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= htmlspecialchars($deleted['deleted_by'] ?? 'Unknown') ?>
                                </span>
                            </td>
                            <td>
                                <?= date('Y/m/d', strtotime($deleted['deleted_at'])) ?>
                                <br>
                                <small class="text-muted"><?= date('h:i A', strtotime($deleted['deleted_at'])) ?></small>
                            </td>
                            <td>
                                <?php if ($deleted['reason']): ?>
                                <span class="text-truncate" title="<?= htmlspecialchars($deleted['reason']) ?>">
                                    <?= htmlspecialchars(mb_substr($deleted['reason'], 0, 20)) ?>...
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="id" value="<?= $deleted['id'] ?>">
                                        <button type="submit" name="restore" class="btn btn-success btn-sm btn-action" 
                                                onclick="return confirm('استرجاع هذا العريس إلى قائمة المنتظرين؟')"
                                                title="استرجاع">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="id" value="<?= $deleted['id'] ?>">
                                        <button type="submit" name="delete_forever" class="btn btn-danger btn-sm btn-action"
                                                onclick="return confirm('حذف نهائي؟ لا يمكن التراجع عن هذا الإجراء!')"
                                                title="حذف نهائي">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a href="../dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-right"></i> العودة للداشبورد
                    </a>
                </div>
                <div class="col-auto">
                    <a href="import_manager.php" class="btn btn-primary">
                        <i class="bi bi-cloud-download"></i> إدارة الاستيراد
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// إخفاء التنبيهات تلقائياً بعد 5 ثواني
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>
</body>
</html>