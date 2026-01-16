<?php
$groom = isset($_GET['groom']) ? $_GET['groom'] : null;
$dir = "grooms/$groom";

if (!$groom || !file_exists("$dir/data.json")) {
    echo "لم يتم العثور على صفحة العريس المطلوبة.";
    exit;
}

$data = json_decode(file_get_contents("$dir/data.json"), true);
$photos = $data['photos'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>حفل <?= htmlspecialchars($data['groom_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: 'Tajawal', sans-serif;
            background: #f7f7f7;
            color: #222;
            text-align: center;
        }
        .banner {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
        }
        .info {
            margin-top: 15px;
        }
        .info h1 {
            margin: 0;
            font-size: 28px;
            color: #333;
        }
        .info p {
            color: #666;
            font-size: 16px;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
        }
        .photo-box {
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
        }
        .gallery img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            cursor: pointer;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.9);
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .modal-scroll {
            overflow-y: scroll;
            height: 90vh;
            width: 100%;
        }
        .modal-photo {
            margin: 20px auto;
            max-width: 90%;
            position: relative;
        }
        .modal-photo img {
            width: 100%;
            height: auto;
            border-radius: 10px;
        }
        .heart-animation {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            font-size: 60px;
            color: red;
            opacity: 0;
            transition: all 0.4s ease;
            pointer-events: none;
        }
        .heart-animation.show {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        .like-display {
            color: white;
            margin-top: 10px;
            font-size: 16px;
            text-align: center;
        }
        .close-btn {
            margin-top: 10px;
            background: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
        }
        .share-btn {
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #0077cc;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>
<body>

<img src="<?= $dir ?>/banner.jpg" class="banner" alt="بنر الحفل">

<div class="info">
    <h1><?= htmlspecialchars($data['groom_name']) ?></h1>
    <p><?= htmlspecialchars($data['event_date']) ?></p>
    <?php if (!empty($data['notes'])): ?>
        <p><?= htmlspecialchars($data['notes']) ?></p>
    <?php endif; ?>
</div>

<div class="gallery">
    <?php foreach ($photos as $photo): ?>
        <div class="photo-box">
            <img src="<?= $dir ?>/thumbs/<?= $photo ?>" 
                 alt="صورة" 
                 loading="lazy"
                 onclick="openModal('<?= $dir ?>/<?= $photo ?>', '<?= $photo ?>')">
        </div>
    <?php endforeach; ?>
</div>

<button class="share-btn" onclick="copyLink()">📎 انسخ رابط هذه الصفحة</button>

<div class="modal" id="imageModal" onclick="closeModal()">
    <div class="modal-scroll" onclick="event.stopPropagation();">
        <?php foreach ($photos as $photo): ?>
            <div class="modal-photo" data-photo="<?= $photo ?>">
                <img src="<?= $dir ?>/<?= $photo ?>" onclick="likePhoto('<?= $photo ?>', this)">
                <div class="heart-animation">❤️</div>
                <div class="like-display" id="count-<?= md5($photo) ?>">0 إعجاب</div>
            </div>
        <?php endforeach; ?>
    </div>
    <button class="close-btn">إغلاق</button>
</div>

<script>
const groomId = <?= json_encode($groom) ?>;

function openModal(src, photoName) {
    document.getElementById('imageModal').style.display = 'flex';

    // التمرير التلقائي إلى الصورة المطلوبة
    const target = document.querySelector('.modal-photo[data-photo="' + photoName + '"]');
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function closeModal() {
    document.getElementById('imageModal').style.display = 'none';
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert("تم نسخ الرابط!");
    });
}

function likePhoto(photoId, imgEl) {
    const key = 'liked_' + photoId;
    if (localStorage.getItem(key)) return;

    const heart = imgEl.parentElement.querySelector('.heart-animation');
    heart.classList.add('show');
    setTimeout(() => heart.classList.remove('show'), 800);

    const countEl = document.getElementById('count-' + md5(photoId));
    let current = parseInt(countEl.innerText) || 0;
    countEl.innerText = (current + 1) + ' إعجاب';

    localStorage.setItem(key, true);

    fetch('like_photo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            groom_id: groomId,
            photo: photoId
        })
    });
}

// دالة md5 بسيطة عبر JS إذا كانت ضرورية لاحقًا (أو استبدالها لاحقًا)
function md5(str) {
    return str.split('').reduce((s, c) =>
        s + c.charCodeAt(0).toString(16), '');
}
</script>

</body>
</html>
