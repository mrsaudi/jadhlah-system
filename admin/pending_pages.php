<?php
session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// جلب كل البيانات
$stmt = $pdo->query("SELECT * FROM pending_grooms ORDER BY timestamp DESC");
$allPending = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>الصفحات المنتظرة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f9f9f9; }
        h2 { color: #333; margin-bottom: 20px; }
        .table td, .table th { vertical-align: middle !important; }
        .copy-link-btn { font-size: 13px; padding: 3px 6px; }
    </style>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert("تم نسخ الرابط: " + text);
            });
        }
    </script>
</head>
<body>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📋 قائمة الصفحات المنتظرة من Google Sheets</h2>
        <a href="dashboard.php" class="btn btn-secondary">🔙 العودة للوحة التحكم</a>
    </div>

    <table class="table table-bordered table-striped bg-white">
        <thead class="table-light">
            <tr>
                <th>اسم العريس</th>
                <th>رقم الجوال</th>
                <th>تاريخ الحجز</th>
                <th>الموقع</th>
                <th>الباقة</th>
                <th>الإجراء</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($allPending as $groom): ?>
            <tr>
                <td><?= htmlspecialchars($groom['groom_name']) ?></td>
                <td><?= htmlspecialchars($groom['phone']) ?></td>
                <td><?= htmlspecialchars($groom['booking_date']) ?></td>
                <td><?= htmlspecialchars($groom['location']) ?></td>
                <td><?= htmlspecialchars($groom['package']) ?></td>
                <td>
                    <?php if (empty($groom['groom_id'])): ?>
                        <a href="create_from_pending.php?timestamp=<?= urlencode($groom['timestamp']) ?>"
                           class="btn btn-sm btn-primary">➕ إنشاء صفحة</a>
                    <?php else: ?>
                        <a href="../groom.php?groom=<?= $groom['groom_id'] ?>" class="btn btn-sm btn-success" target="_blank">👁️ عرض الصفحة</a>
                        <button class="btn btn-sm btn-outline-secondary copy-link-btn"
                                onclick="copyToClipboard('https://www.jadhlah.com/groom.php?groom=<?= $groom['groom_id'] ?>')">📋 نسخ الرابط</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>