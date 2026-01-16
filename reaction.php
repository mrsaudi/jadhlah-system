<?php
// **هذا الكود كله يجب أن يحل محل أي محتوى سابق في reaction.php**
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=u709146392_jadhlah_db;charset=utf8',
        'u709146392_jad_admin',
        '1245@vmP',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || empty($_POST['photo_id'])
        || empty($_POST['action'])
    ) {
        throw new Exception('Invalid request');
    }

    $photoId = (int) $_POST['photo_id'];
    $action  = $_POST['action']; // 'like' أو 'view'

    if ($action === 'like') {
        $col = 'likes';
    } elseif ($action === 'view') {
        $col = 'views';
    } else {
        throw new Exception('Invalid action');
    }

    // تحديث العداد
    $stmt = $pdo->prepare("UPDATE `groom_photos` 
                           SET `{$col}` = `{$col}` + 1 
                           WHERE `id` = ?");
    $stmt->execute([$photoId]);

    // جلب العدد الجديد
    $stmt = $pdo->prepare("SELECT `{$col}` FROM `groom_photos` WHERE `id` = ?");
    $stmt->execute([$photoId]);
    $newCount = (int) $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'action'  => $action,
        'count'   => $newCount
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
