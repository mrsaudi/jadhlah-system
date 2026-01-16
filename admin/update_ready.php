<?php
require_once 'config.php';

$id = (int)($_POST['id'] ?? 0);
$ready = (int)($_POST['ready'] ?? 0);

if ($id <= 0) {
  echo json_encode(['success' => false]);
  exit;
}

if ($ready === 1) {
  // أول مرة يتم التفعيل: سجل التاريخ
  $stmt = $pdo->prepare("UPDATE grooms SET ready = 1, ready_at = NOW() WHERE id = ?");
} else {
  $stmt = $pdo->prepare("UPDATE grooms SET ready = 0, ready_at = NULL WHERE id = ?");
}

$stmt->execute([$id]);
echo json_encode(['success' => true]);
?>
