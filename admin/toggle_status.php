<?php
header('Content-Type: application/json');
$id = $_POST['id'] ?? null;
if (!$id || !is_numeric($id)) {
  echo json_encode(['success'=>false, 'error'=>'معرّف غير صحيح']); exit;
}
require 'config.php';
// استعلام الحالة الحالية
$current = $pdo->prepare("SELECT blocked FROM grooms WHERE id = ?");
$current->execute([$id]);
$row = $current->fetch();
$newBlocked = $row['blocked'] ? 0 : 1;
// حدّث العمود
$stmt = $pdo->prepare("UPDATE grooms SET blocked = ? WHERE id = ?");
$ok   = $stmt->execute([$newBlocked, $id]);
echo json_encode(['success'=> (bool)$ok]);

?>