<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// === معالجة رفع الصور عبر Uppy وإنتاج thumbs ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['photos'])) {
    $groomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($groomId <= 0) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Invalid groom_id']);
        exit;
    }

    $baseDir     = __DIR__ . "/grooms/{$groomId}";
    $originalDir = "{$baseDir}/originals";
    $thumbDir    = "{$baseDir}/modal_thumbs";
    if (!is_dir($originalDir)) mkdir($originalDir, 0777, true);
    if (!is_dir($thumbDir))    mkdir($thumbDir,    0777, true);
    $uploaded = 0;
    foreach ($_FILES['photos']['tmp_name'] as $i => $tmpPath) {
        $name     = basename($_FILES['photos']['name'][$i]);
        $origPath = "{$originalDir}/{$name}";
        $thumbPath= "{$thumbDir}/{$name}";
        move_uploaded_file($tmpPath, $origPath);
        list($w,$h,$type)= getimagesize($origPath);
        $newW=1500; $newH=intval($h*($newW/$w));
        $dst=imagecreatetruecolor($newW,$newH);
        switch($type){
          case IMAGETYPE_JPEG:
            $src=imagecreatefromjpeg($origPath);
            imagecopyresampled($dst,$src,0,0,0,0,$newW,$newH,$w,$h);
            imagejpeg($dst,$thumbPath,85);
            imagedestroy($src);break;
          case IMAGETYPE_PNG:
            $src=imagecreatefrompng($origPath);
            imagealphablending($dst,false);
            imagesavealpha($dst,true);
            imagecopyresampled($dst,$src,0,0,0,0,$newW,$newH,$w,$h);
            imagepng($dst,$thumbPath);
            imagedestroy($src);break;
          default:
            copy($origPath,$thumbPath);
        }
        imagedestroy($dst);
        // سجل في DB
        $stmt = $pdo->prepare("INSERT INTO groom_photos (groom_id, filename) VALUES (?,?)");
        $stmt->execute([$groomId,$name]);
        $uploaded++;
    }
    header('Content-Type: application/json');
    echo json_encode(['success'=>true,'uploaded'=>$uploaded]);
    exit;
}


session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user'])) {
  header('Location: login.php');
  exit;
}

$role = $_SESSION['role'] ?? '';
$canWrite = in_array($role, ['manager', 'work']);
if (!$canWrite) {
  die('ليس لديك صلاحية التعديل.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $id           = (int) $_POST['id'];
    $groomName    = $_POST['groom_name'] ?? '';
    $weddingDate  = $_POST['wedding_date'] ?? '';
    $hallName     = $_POST['hall_name'] ?? '';
    $notes        = $_POST['notes'] ?? '';
    $eventName = trim($_POST['event_name'] ?? '') ?: 'حفل زواج';
    $youtubeLinks = [];
    for ($i = 1; $i <= 7; $i++) {
      $youtubeLinks[] = $_POST["youtube$i"] ?? '';
    }

    $stmt = $pdo->prepare("UPDATE grooms SET
  event_name = ?, groom_name = ?, wedding_date = ?, hall_name = ?, notes = ?,
  youtube1 = ?, youtube2 = ?, youtube3 = ?, youtube4 = ?, youtube5 = ?, youtube6 = ?, youtube7 = ?
  WHERE id = ?");
 
 
    $stmt->execute([
  $eventName, $groomName, $weddingDate, $hallName, $notes,
  $youtubeLinks[0], $youtubeLinks[1], $youtubeLinks[2],
  $youtubeLinks[3], $youtubeLinks[4], $youtubeLinks[5], $youtubeLinks[6],
  $id
]);

    // تحديث البنر (إن وجد)
if (!empty($_FILES['banner']['tmp_name'])) {
  $folder = $id;
  $bannerName = 'banner.jpg';
  $baseDir = __DIR__ . "/../grooms/$folder";

  $bannerOriginalPath = "$baseDir/$bannerName";
  $bannerThumbDir = "$baseDir/modal_thumb";
  $bannerThumbPath = "$bannerThumbDir/$bannerName";

  if (!is_dir($bannerThumbDir)) mkdir($bannerThumbDir, 0777, true);

  move_uploaded_file($_FILES['banner']['tmp_name'], $bannerOriginalPath);

  // توليد نسخة مصغّرة بعرض 1500
  $info = getimagesize($bannerOriginalPath);
  if ($info !== false) {
    [$width, $height, $type] = $info;
    $newWidth = 1500;
    $newHeight = intval($height * ($newWidth / $width));
    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    switch ($type) {
      case IMAGETYPE_JPEG:
        $src = imagecreatefromjpeg($bannerOriginalPath);
        break;
      case IMAGETYPE_PNG:
        $src = imagecreatefrompng($bannerOriginalPath);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        break;
      default:
        $src = null;
    }

    if ($src) {
      imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
      imagejpeg($thumb, $bannerThumbPath, 85);
      imagedestroy($src);
      imagedestroy($thumb);
    }
  }

  // تحديث قاعدة البيانات
  $stmt = $pdo->prepare("UPDATE grooms SET banner = ? WHERE id = ?");
  $stmt->execute([$bannerName, $id]);
}

    // تحديث الصور المميزة والمخفية
    $featured = json_decode($_POST['featured'] ?? '[]', true);
    $hidden   = json_decode($_POST['hidden'] ?? '[]', true);

    if (is_array($featured)) {
      $stmt = $pdo->prepare("UPDATE groom_photos SET is_featured = 0 WHERE groom_id = ?");
      $stmt->execute([$id]);
      $stmt = $pdo->prepare("UPDATE groom_photos SET is_featured = 1 WHERE groom_id = ? AND filename = ?");
      foreach ($featured as $file) {
        $stmt->execute([$id, $file]);
      }
    }

    if (is_array($hidden)) {
      $stmt = $pdo->prepare("UPDATE groom_photos SET hidden = 0 WHERE groom_id = ?");
      $stmt->execute([$id]);
      $stmt = $pdo->prepare("UPDATE groom_photos SET hidden = 1 WHERE groom_id = ? AND filename = ?");
      foreach ($hidden as $file) {
        $stmt->execute([$id, $file]);
      }
    }

    // إعادة توليد قائمة الانتظار للترتيب في الكرون
    $queueDir = __DIR__ . '/queue/';
    if (!file_exists($queueDir)) mkdir($queueDir, 0777, true);
    file_put_contents("$queueDir/queue_$id.json", json_encode([
      'groom_id'     => $id,
      'folder'       => $id,
      'photo_order'  => [], // يمكن إضافته لاحقًا من JS إذا أردت الترتيب أيضًا
      'featured'     => $featured,
      'hidden'       => $hidden
    ]));

    header("Location: groom.php?groom=$id");
    exit;
  } catch (Exception $e) {
    die("حدث خطأ أثناء التعديل: " . $e->getMessage());
  }
} else {
  die("طلب غير صالح.");
}