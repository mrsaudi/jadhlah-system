<?php
require __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die('معرّف غير صحيح');

$folder = __DIR__ . "/../grooms/$id/";
$thumbs = $folder . "thumbs/";

if (is_dir($thumbs)) {
    foreach (glob($thumbs . "*.*") as $file) unlink($file);
}
if (is_dir($folder)) {
    foreach (glob($folder . "*.*") as $file) {
        if (basename($file) !== "banner.jpg") unlink($file);
    }
}

$stmt = $pdo->prepare("DELETE FROM groom_photos WHERE groom_id = ?");
$stmt->execute([$id]);

header("Location: edit_groom.php?id=$id");
exit;