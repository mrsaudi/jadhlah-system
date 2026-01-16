<?php
// dashboard_pending_section.php
// عرض قائمة الصفحات المنتظرة ضمن لوحة التحكم مع زر إنشاء صفحة ومحدودة بعشرة لكل صفحة مع ترقيم

require __DIR__ . '/config.php';

// إعدادات الترقيم
$page      = isset($_GET['pending_page']) ? max(1, (int)$_GET['pending_page']) : 1;
$limit     = 10;
$offset    = ($page - 1) * $limit;

// جلب العدد الإجمالي للصفحات المنتظرة
$totalStmt   = $pdo->query("SELECT COUNT(*) FROM pending_grooms WHERE groom_id IS NULL");
$total       = (int) $totalStmt->fetchColumn();
$totalPages  = ($total > 0) ? ceil($total / $limit) : 1;

// جلب البيانات للصفحة الحالية
$stmt      = $pdo->prepare(
    "SELECT * FROM pending_grooms WHERE groom_id IS NULL ORDER BY timestamp DESC LIMIT ? OFFSET ?"
);
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$pending   = $stmt->fetchAll();
?>

<div class="card mb-4 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <h5 class="mb-0">📋 الصفحات المنتظرة (<?= $total ?>)</h5>
    <?php if ($total > 0): ?>
    <nav aria-label="Pending pagination">
      <ul class="pagination mb-0">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?pending_page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if (empty($pending)): ?>
      <div class="alert alert-success text-center mb-0">لا توجد صفحات منتظرة حاليًا ✅</div>
    <?php else: ?>
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>العريس</th>
            <th>الجوال</th>
            <th>تاريخ الحجز</th>
            <th>الموقع</th>
            <th>الباقة</th>
            <th class="text-center">إجراء</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $g): ?>
          <tr>
            <td><?= htmlspecialchars($g['groom_name']) ?></td>
            <td><?= htmlspecialchars($g['phone']) ?></td>
            <td><?= htmlspecialchars($g['booking_date']) ?></td>
            <td><?= htmlspecialchars($g['location']) ?></td>
            <td><?= htmlspecialchars($g['package']) ?></td>
            <td class="text-center">
              <a href="create_from_pending.php?timestamp=<?= urlencode($g['timestamp']) ?>"
                 class="btn btn-sm btn-primary">
                ➕ إنشاء صفحة
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</div>
