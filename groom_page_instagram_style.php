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
        .modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .modal img {
            width: 100%;
            height: auto;
            border-radius: 8px;
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
        }
        .close-btn {
            margin-top: 20px;
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
        @media (max-width: 600px) {
            .info h1 { font-size: 22px; }
            .info p { font-size: 14px; }
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
                 onclick="openModal('<?= $dir ?>/<?= $photo ?>')"
                 data-photo="<?= $photo ?>">
        </div>
    <?php endforeach; ?>
</div>

<button class="share-btn" onclick="copyLink()">📎 انسخ رابط هذه الصفحة</button>

<div class="modal" id="imageModal" onclick="closeModal()">
    <div class="modal-content" onclick="event.stopPropagation();">
        <img id="modalImg" src="">
        <div class="heart-animation" id="heartAnimation">❤️</div>
        <div class="like-display">
            <span id="modalLikeCount">0 إعجاب</span>
        </div>
    </div>
    <button class="close-btn">إغلاق</button>
</div>

<script>
function openModal(src) {
    let photoId = src.split('/').pop();
    document.getElementById('modalImg').src = src;
    document.getElementById('imageModal').style.display = 'flex';
    document.getElementById('imageModal').dataset.photoId = photoId;

    let liked = localStorage.getItem('liked_' + photoId);
    let count = liked ? 1 : 0;
    document.getElementById('modalLikeCount').innerText = count + ' إعجاب';
}

function closeModal() {
    document.getElementById('imageModal').style.display = 'none';
}

document.getElementById('modalImg').addEventListener('click', () => {
    const heart = document.getElementById('heartAnimation');
    const photoId = document.getElementById('imageModal').dataset.photoId;

    if (!localStorage.getItem('liked_' + photoId)) {
        heart.classList.add('show');
        setTimeout(() => heart.classList.remove('show'), 800);

        let countEl = document.getElementById('modalLikeCount');
        let current = parseInt(countEl.innerText) || 0;
        countEl.innerText = (current + 1) + ' إعجاب';

        localStorage.setItem('liked_' + photoId, true);
    }
});

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert("تم نسخ الرابط!");
    });
}
</script>

</body>
</html>
