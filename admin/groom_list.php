<?php
require_once 'config.php';
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM grooms ORDER BY id DESC");
$grooms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>قائمة العرسان</title>
</head>
<body>

<h2>قائمة العرسان</h2>

<table border="1" cellpadding="5">
    <tr>
        <th>المعرف</th>
        <th>الاسم</th>
        <th>التاريخ</th>
        <th>رابط الصفحة</th>
        <th>تعديل</th>
    </tr>
    <?php foreach ($grooms as $groom): ?>
    <tr>
        <td><?= $groom['id'] ?></td>
        <td><?= htmlspecialchars($groom['groom_name']) ?></td>
        <td><?= $groom['wedding_date'] ?></td>
        <td><a href="../groom.php?groom=<?= $groom['folder_name'] ?>" target="_blank">عرض</a></td>
        <td><a href="edit_groom.php?id=<?= $groom['id'] ?>">تعديل</a></td>
    </tr>
    <?php endforeach; ?>
</table>

<a href="dashboard.php">⬅️ رجوع</a>

</body>
</html>
