
<?php
function getNextGroomId() {
    $groomFolder = "../grooms";
    $ids = [];
    foreach (scandir($groomFolder) as $file) {
        if (is_dir("$groomFolder/$file") && is_numeric($file)) {
            $ids[] = (int)$file;
        }
    }
    return $ids ? max($ids) + 1 : 1001;
}

$groom_id = getNextGroomId();
$groom_name = $_POST['groom_name'];
$wedding_date = $_POST['wedding_date'];
$hall_name = $_POST['hall_name'];
$notes = $_POST['notes'];
$youtube = $_POST['youtube'];
$eventName = trim($_POST['event_name'] ?? '') ?: 'حفل زواج';

$dir = "../grooms/$groom_id";
mkdir($dir, 0775, true);
mkdir("$dir/thumbs", 0775, true);

move_uploaded_file($_FILES['banner']['tmp_name'], "$dir/banner.jpg");

$photos = [];
foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
    $name = basename($_FILES['photos']['name'][$i]);
    $target = "$dir/$name";
    move_uploaded_file($tmp, $target);
    copy($target, "$dir/thumbs/$name");
    $photos[] = ["name" => $name, "featured" => false, "hidden" => false];
}

$data = [
    'groom_name' => $groom_name,
    'event_name' => $eventName,
    'wedding_date' => $wedding_date,
    'hall_name' => $hall_name,
    'notes' => $notes,
    'youtube' => $youtube,
    'photos' => $photos
];

file_put_contents("$dir/data.json", json_encode($data, JSON_UNESCAPED_UNICODE));

header("Location: add_groom.php?link=https://jadhlah.com/groom.php?groom=$groom_id");
exit;
?>
