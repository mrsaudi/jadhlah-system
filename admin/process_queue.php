<?php
require __DIR__.'/config.php';
require __DIR__.'/functions.php';
set_time_limit(0);

$stmt = $pdo->prepare(
  "SELECT * FROM upload_queue 
   WHERE status = 'pending' 
   ORDER BY created_at 
   LIMIT 10"
);
$stmt->execute();
while ($row = $stmt->fetch()) {
  $pdo->prepare(
    "UPDATE upload_queue SET status='processing' WHERE id=?"
  )->execute([$row['id']]);

  $groomId = $row['groom_id'];
  $file    = $row['filename'];
  $uploadDir     = __DIR__."/../uploads_final/$groomId";
  $groomDir      = __DIR__."/../grooms/$groomId";
  $originalsDir  = "$groomDir/originals";
  $thumbsDir     = "$groomDir/thumbs";

  // تأكد من وجود المجلدات
  foreach ([$originalsDir, $thumbsDir] as $d) {
    if (!file_exists($d)) mkdir($d, 0777, true);
  }

  $src = "$uploadDir/$file";
  $dstO = "$originalsDir/$file";
  $dstT = "$thumbsDir/$file";

  if (is_file($src) && copy($src, $dstO)) {
    createThumbnail($dstO, $dstT, 300);
    // إدخال في جدول الصور
    $isFeat = /* هنا يمكن استعلامك عن الصور المميزة حسب حاجتك */;
    $stmt2 = $pdo->prepare(
      "INSERT INTO groom_photos (groom_id, filename, is_featured) 
       VALUES (?, ?, ?)"
    );
    $stmt2->execute([$groomId, $file, $isFeat]);
    // بعد النجاح
    $pdo->prepare(
      "UPDATE upload_queue SET status='done' WHERE id=?"
    )->execute([$row['id']]);
    // حذف الملف المؤقت
    unlink($src);
  } else {
    // فشل المعالجة
    $pdo->prepare(
      "UPDATE upload_queue SET status='failed' WHERE id=?"
    )->execute([$row['id']]);
  }
}
