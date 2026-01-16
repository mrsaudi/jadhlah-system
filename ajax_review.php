<?php
header('Content-Type: application/json; charset=utf-8');
require 'config.php'; // اتصال PDO
$groomId = isset($_GET['groom']) ? (int)$_GET['groom'] : 0;
if ($_SERVER['REQUEST_METHOD']==='POST' && $groomId>0) {
  $name    = trim($_POST['review_name']);
  $rating  = (int)$_POST['review_rating'];
  $message = trim($_POST['review_message']);
  if ($name && $rating && $message) {
    $stmt = $pdo->prepare("INSERT INTO groom_reviews (groom_id,name,rating,message) VALUES (?,?,?,?)");
    $stmt->execute([$groomId,$name,$rating,$message]);
    echo json_encode(['success'=>true]);
    exit;
  }
}
echo json_encode(['success'=>false]);
