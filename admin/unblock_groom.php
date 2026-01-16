<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    require 'config.php';
    $stmt = $pdo->prepare("UPDATE grooms SET is_blocked = 0 WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['flash'] = "تم إلغاء الحجب بنجاح.";
}
header("Location: dashboard.php");
exit;
