<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (empty($_SESSION['user']) || $_SESSION['role'] !== 'manager') exit;
header('Content-Type: application/json;charset=utf-8');
try {
    $pdo = new PDO('mysql:host=localhost;dbname=u709146392_jadhlah_db;charset=utf8',
                   'u709146392_jad_admin', '1245@vmP',
                   [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pages = $pdo->query('SELECT id, groom_name, page_views FROM grooms')->fetchAll();
    $labels = $views = [];
    foreach ($pages as $p) { $labels[] = $p['groom_name']; $views[] = (int)$p['page_views']; }
    $likesStmt = $pdo->query('SELECT groom_id, SUM(likes) AS total FROM groom_photos GROUP BY groom_id')->fetchAll();
    $likesMap = [];
    foreach ($likesStmt as $l) $likesMap[$l['groom_id']] = (int)$l['total'];
    $likesArr = [];
    foreach ($pages as $p) $likesArr[] = $likesMap[$p['id']] ?? 0;
    echo json_encode(['labels'=>$labels,'views'=>$views,'likes'=>$likesArr]);
} catch (Exception $e) {
    echo json_encode([]);
}
