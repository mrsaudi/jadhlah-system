<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// إعداد اتصال قاعدة البيانات
$host    = 'localhost';
$db      = 'u709146392_jadhlah_db';
$user    = 'u709146392_jad_admin';
$pass    = '1245@vmP';
$charset = 'utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
} catch (PDOException $e) {
    die('DB Connection Error: ' . $e->getMessage());
}


$groomId = isset($_GET['groom']) ? (int)$_GET['groom'] : 0;
if ($groomId <= 0) die('Missing or invalid groom ID.');
// جلب معرف العريس أولاً (حتى نستخدمه لاحقًا)
$groomId = isset($_GET['groom']) ? (int)$_GET['groom'] : 0;

// إذا تم إرسال تقييم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $name    = trim($_POST['review_name']);
    $rating  = (int) $_POST['review_rating'];
    $message = trim($_POST['review_message']);

    if ($groomId > 0 && $name && $rating && $message) {
        $stmt = $pdo->prepare("INSERT INTO groom_reviews (groom_id, name, rating, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$groomId, $name, $rating, $message]);
        header("Location: groom.php?groom=$groomId&review=success");
        exit;
    }
}
// جلب بيانات العريس من قاعدة البيانات
$stmt = $pdo->prepare('SELECT *, page_views, created_at FROM grooms WHERE id = ?');
$stmt->execute([$groomId]);
$groomData = $stmt->fetch();

if (!$groomData) {
    die("❌ هذه الصفحة غير موجودة.");
}

// ✅ التحقق من حالة التفعيل
if ((int)$groomData['is_active'] !== 1) {
    die("❌ هذه الصفحة غير متاحة حالياً.");
}

// حساب عدد الأيام منذ الإنشاء
$created   = new DateTime($groomData['created_at']);
$now       = new DateTime();
$diff      = $now->diff($created);
$daysElapsed = $diff->days;
$maxDays     = 30;
$isStale     = ($daysElapsed >= $maxDays);
$daysLeft    = max(0, $maxDays - $daysElapsed);

// إذا الصفحة خاملة، نظهر رسالة ونوقف التنفيذ
if ($isStale) {
    echo '<!DOCTYPE html>
    <html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>انتهت الصفحة</title>
    <style>body{font-family:Tajawal,sans-serif;text-align:center;padding:50px;} .msg{font-size:24px;color:#555;}</style>
    </head><body>
      <div class="msg">عذراً، هذه الصفحة منتهية ولن تكون متاحة للعرض.</div>
    </body></html>';
    exit;
}

// الصفحة نشطة: زيادة عداد زياراتها
$update = $pdo->prepare("UPDATE `grooms` SET `page_views` = `page_views` + 1 WHERE `id` = ?");
$update->execute([$groomId]);


// مسارات الملفات والويب
$webBase    = "grooms/$groomId";
$thumbsPath = "$webBase/thumbs";
$origPath   = "$webBase/originals";
$scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// جلب الصور
$stmt = $pdo->prepare(
    'SELECT id, filename, likes, views, is_featured
     FROM groom_photos
     WHERE groom_id = ? AND hidden = 0
     ORDER BY is_featured DESC, likes DESC'
);
$stmt->execute([$groomId]);
$photos = $stmt->fetchAll();
// جلب التقييمات الموافق عليها للعريس
$stmt = $pdo->query(
  "SELECT name, message, rating, created_at
   FROM groom_reviews
   WHERE is_approved = 1
   ORDER BY created_at DESC"
);
$approvedReviews = $stmt->fetchAll();


?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>حفل <?= htmlspecialchars($groomData['groom_name']) ?></title>
  <!-- خط Lateef -->
<link href="https://fonts.googleapis.com/css2?family=Lateef&display=swap" rel="stylesheet">

<!-- خط Tajawal (للاحتياط) -->
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@700;800&display=swap" rel="stylesheet">

  <style>
    body { margin:0; font-family:'Tajawal',sans-serif; background:#f7f7f7; color:#222; }
    .banner { width:100%; max-height:400px; object-fit:cover; }
    .info { padding:20px; text-align:center; }
    .info h1 { margin:0; font-size:28px; color:#333; }
    .info p { color:#666; font-size:16px; }
    .gallery {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0; /* ← لا فراغ بين الصور */
}

.photo-box {
  position: relative;
  width: 100%;
  aspect-ratio: 1 / 1;
  overflow: hidden;
}

.photo-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

    .share-btn { display:block; margin:20px auto; padding:10px 20px; background:#0077cc; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:16px; }
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000; }
    .modal-content { position:relative; width:100%; height:100%; display:flex; flex-direction:column; }
    .modal-close { position: fixed; top:20px; right:20px; z-index:1001; background:rgba(0,0,0,0.5); color:#fff; border:none; font-size:24px; cursor:pointer; border-radius:50%; width:40px; height:40px; }
    .modal-scroll { flex:1; overflow-y:auto; display:flex; flex-direction:column; align-items:center; padding:60px 0 80px; }
    .modal-photo { width:90%; margin-bottom:30px; position:relative; }
    .modal-photo img { width:100%; border-radius:10px; cursor:pointer; }
    .heart-animation { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%) scale(0); font-size:60px; color:red; opacity:0; transition:transform .4s ease, opacity .4s ease; pointer-events:none; }
    .heart-animation.show { transform:translate(-50%,-50%) scale(1); opacity:1; }
    .modal-actions { display:flex; justify-content:space-around; background:#ffffffcc; border-radius:0 0 30px 30px; padding:10px; }
    .modal-actions button, .modal-actions a { background:none; border:none; font-size:20px; cursor:pointer; color:#888; }
    .modal-actions a { text-decoration:none; }
    .modal-actions span { margin-left:5px; }
    .page-views { text-align:center; margin:20px 0; font-size:16px; color:#555; }
    .countdown { text-align:center; font-size:16px; color:#333; margin-bottom:20px; }
   .youtube-wrapper {
  display: flex;
  flex-direction: column;
  gap: 30px;
  padding: 20px 0;
  align-items: center;
}

.youtube-card {
  background: #fff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  width: 75%;
  aspect-ratio: 3 / 1;
  transition: transform 0.3s ease;
}

.youtube-card:hover {
  transform: translateY(-4px);
}

.youtube-card iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}

    
   .star-rating {
  display: flex;
  flex-direction: row-reverse;
  justify-content: center;
  font-size: 30px;
  cursor: pointer;
  margin: 10px 0;
}
.star-rating span {
  color: #ccc;
  transition: color 0.2s;
}
.star-rating span:hover,
.star-rating span:hover ~ span {
  color: #f5b301;
}
.star-rating .selected,
.star-rating .selected ~ span {
  color: #f5b301;
}
.review-form-wrapper {
  max-width: 70%;
  margin: 40px auto;
  background: #ffffff;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.07);
}


.review-title {
  text-align: center;
  font-size: 24px;
  margin-bottom: 20px;
  color: #333;
}

.review-success {
  background: #e0ffe0;
  color: #226622;
  padding: 12px;
  border-radius: 6px;
  text-align: center;
  margin-bottom: 15px;
  font-weight: bold;
}

.review-form label {
  display: block;
  margin-top: 15px;
  margin-bottom: 5px;
  color: #555;
  font-size: 15px;
}

.review-form input[type="text"],
.review-form textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
}

.review-form button {
  margin-top: 20px;
  padding: 12px 25px;
  background: #0077cc;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  width: 100%;
}

.review-form button:hover {
  background: #005fa3;
}
.review-form button,
.share-btn {
  background: #ffbf00 !important;
  color: #000 !important;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
  padding: 10px 20px;
}

.review-form button:hover,
.share-btn:hover {
  background: #e6ac00 !important;
}
.swiper-button-prev,
.swiper-button-next {
  color: #ffbf00 !important;
}
.navbar {
  position: sticky;
  top: 0;
  width: 100%;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(6px);
  box-shadow: 0 1px 6px rgba(0,0,0,0.05);
  z-index: 1000;
  padding: 6px 0;
}

.navbar-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  height: 36px;
}

.nav-buttons {
  background: #fff;
  border-radius: 999px;
  display: flex;
  box-shadow: 0 1px 5px rgba(0,0,0,0.07);
  overflow: hidden;
}

.nav-btn {
  padding: 6px 12px;
  font-size: 13px;
  color: #333;
  font-weight: 500;
  text-decoration: none;
  border-left: 1px solid #eee;
  transition: background 0.2s, color 0.2s;
}

.nav-btn:first-child {
  border-left: none;
}

.nav-btn:hover {
  background: #ffbf00;
  color: #000;
}

/* الجوال: نبقي التصميم أفقي تمامًا */
@media (max-width: 600px) {
  .navbar-container {
    padding: 0 12px;
  }
  .logo {
    height: 32px;
  }
  .nav-btn {
    padding: 5px 10px;
    font-size: 12px;
  }
}
.info {
  padding: 30px 20px 20px;
  text-align: center;
  background: #fff;
  border-bottom: 1px solid #eee;
}

.groom-name {
  margin: 0;
  font-size: 36px;
  font-family: 'Lateef', cursive;
  color: #333;
  line-height: 1.3;
}

.wedding-date {
  color: #888;
  font-size: 16px;
  margin: 8px 0;
}

.groom-notes {
  font-size: 15px;
  color: #555;
  margin-top: 10px;
  white-space: pre-line;
}

/* للجوال */
@media (max-width: 600px) {
  .groom-name {
    font-size: 28px;
  }
  .wedding-date, .groom-notes {
    font-size: 14px;
  }
}
.info-stats {
  display: flex;
  justify-content: center;
  gap: 30px;
  margin: 40px 0;
  flex-wrap: wrap;
}

.stat-box {
  background: #fff;
  border-radius: 16px;
  padding: 20px 30px;
  text-align: center;
  box-shadow: 0 4px 10px rgba(0,0,0,0.06);
  transition: transform 0.3s ease;
  min-width: 140px;
}

.stat-box:hover {
  transform: translateY(-4px);
}

.stat-icon svg {
  display: block;
  margin: 0 auto 8px;
}


.stat-label {
  font-size: 14px;
  color: #888;
}

.stat-value {
  font-size: 20px;
  font-weight: bold;
  color: #333;
  margin-top: 5px;
}

/* للجوال */
@media (max-width: 600px) {
  .info-stats {
    flex-direction: column;
    align-items: center;
  }
}

.promo-section {
  text-align: center;
  padding: 60px 20px;
  background: #fafafa;
}

.promo-logo {
  width: 200px;
  margin-bottom: 20px;
}

.promo-text {
  font-size: 20px;
  color: #333;
  margin-bottom: 20px;
  font-weight: bold;
  font-family: 'Tajawal', sans-serif;
}

.promo-btn {
  display: inline-block;
  background: #25D366;
  color: white;
  padding: 12px 24px;
  border-radius: 999px;
  text-decoration: none;
  font-size: 15px;
  font-weight: bold;
  box-shadow: 0 3px 8px rgba(0,0,0,0.1);
  transition: background 0.3s;
}

.promo-btn:hover {
  background: #1ebc59;
}
.social-section {
  padding: 60px 20px;
  text-align: center;
  background: #f7f7f7;
}

.social-title {
  font-size: 20px;
  font-weight: bold;
  color: #333;
  margin-bottom: 25px;
}

.social-icons {
  display: flex;
  justify-content: center;
  gap: 25px;
  flex-wrap: wrap;
}

.social-link {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: #eaeaea;
  transition: background 0.3s, transform 0.3s;
}

.social-link img {
  width: 24px;
  height: 24px;
  filter: brightness(0.3);
  transition: filter 0.3s;
}

.social-link:hover {
  transform: scale(1.1);
}

.social-link:hover img {
  filter: brightness(0) saturate(100%) sepia(100%) hue-rotate(20deg) brightness(1.1);
}

/* ألوان تمييز عند المرور */
.social-link.tiktok:hover    { background: #000; }
.social-link.instagram:hover { background: #E1306C; }
.social-link.snapchat:hover  { background: #FFFC00; }
.social-link.x:hover         { background: #1DA1F2; }

.copy-section {
  text-align: center;
  margin: 50px 0 20px;
}

.copy-btn {
  padding: 10px 22px;
  background: #ffbf00;
  border: none;
  border-radius: 999px;
  font-size: 14px;
  font-weight: bold;
  color: #000;
  cursor: pointer;
  transition: background 0.3s;
}

.copy-btn:hover {
  background: #e0a900;
}

.copy-msg {
  margin-top: 10px;
  font-size: 14px;
  color: green;
  display: none;
}
.site-footer {
  background: #1e1e1e;
  color: #ccc;
  padding: 40px 20px;
  text-align: center;
}

.footer-logo {
  width: 120px;
  margin-bottom: 15px;
}

.footer-links {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-bottom: 15px;
  flex-wrap: wrap;
}

.footer-link {
  color: #ccc;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.3s;
}

.footer-link:hover {
  color: #ffbf00;
}

.footer-copy {
  font-size: 13px;
  color: #888;
}

/* للجوال */
@media (max-width: 600px) {
  .footer-links {
    flex-direction: column;
    gap: 10px;
  }
}
.banner-wrapper {
  position: relative;
  width: 100%;
  max-height: 400px;
  overflow: hidden;
}

.banner {
  width: 100%;
  object-fit: cover;
  display: block;
}

.banner-logo-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 80%;
  opacity: 0.30;
  pointer-events: none;
}

.floating-btn {
  position: fixed;
  bottom: 20px;
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 50%;
  background: rgba(50, 50, 50, 0.6);
  backdrop-filter: blur(4px);
  color: white;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  transition: background 0.3s ease, transform 0.3s ease;
}

.floating-btn:hover {
  background: rgba(70, 70, 70, 0.8);
  transform: scale(1.08);
}

.right-btn {
  right: 20px;
}

.left-btn {
  left: 20px;
}

.floating-btn svg {
  width: 22px;
  height: 22px;
}
.whatsapp-float {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 46px;
  height: 46px;
  background: #25D366; /* لون واتساب */
  border-radius: 50%;
  box-shadow: 0 2px 6px rgba(0,0,0,0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  z-index: 9999;
  transition: background 0.3s, transform 0.3s;
}

.whatsapp-float:hover {
  background: #1ebe5d;
  transform: scale(1.08);
}

.whatsapp-float img {
  width: 22px;
  height: 22px;
}
.fade-in-up {
  opacity: 0;
  transform: translateY(30px);
  animation: fadeInUp 2s ease-out forwards;
}

@keyframes fadeInUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.toast {
  position: fixed;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  background: rgba(0,0,0,0.8);
  color: #fff;
  padding: 12px 20px;
  border-radius: 6px;
  font-size: 16px;
  opacity: 0;
  pointer-events: none;
  transition: opacity .3s ease;
  z-index: 2000;
}
.toast.show {
  opacity: 1;
}
/* خلفية شفافة تغطي الشاشة */
.welcome-modal {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.6);
  display: none; /* مخفية في البداية */
  align-items: center;
  justify-content: center;
  z-index: 5000;
}

/* صندوق المحتوى */
.welcome-content {
  background: #fff;
  padding: 30px 20px;
  border-radius: 12px;
  text-align: center;
  position: relative;
  max-width: 300px;
  animation: fadeIn 0.5s ease-out;
}

/* زر الإغلاق */
.welcome-close {
  position: absolute;
  top: 8px; right: 8px;
  background: transparent;
  border: none;
  font-size: 18px;
  cursor: pointer;
}

/* الشعار */
.welcome-logo {
  width: 100px;
  margin-bottom: 15px;
}

/* النص */
.welcome-text {
  font-size: 16px;
  color: #333;
  margin-bottom: 20px;
}

/* الإنيميشن الخفيف (spinner) */
.spinner {
  width: 40px; height: 40px;
  border: 4px solid #eee;
  border-top-color: #ffbf00;
  border-radius: 50%;
  margin: 0 auto;
  animation: spin 1s linear infinite;
}

/* keyframes */
@keyframes spin {
  to { transform: rotate(360deg); }
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-20px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeOut {
  from { opacity: 1; }
  to   { opacity: 0; }
}
<style>
  .download-all-section {
    text-align: center;
    margin: 40px 0;
  }
  .download-all-btn {
    background: linear-gradient(135deg, #ffbf00 0%, #ff9a00 100%);
    border: none;
    padding: 14px 28px;
    border-radius: 8px;
    color: #222;
    font-size: 18px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform .2s, box-shadow .2s;
  }
  .download-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.15);
  }
</style>

</style>

  </style>
  <!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<!-- لتحزيم الملفات في المتصفح -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<!-- لحفظ الباكيت الناتج -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

</head>
<body>
    <!-- نافذة الترحيب -->
<div id="welcomeModal" class="welcome-modal">
  <div class="welcome-content">
    <button id="welcomeClose" class="welcome-close">✖</button>
    <img src="/assets/whiti_logo_jadhlah_t.svg" alt="شعار جذلة" class="welcome-logo">
    <p class="welcome-text">لقطاتنا تعيش أطول من لحظاتها</p>
    <div class="spinner"></div>
  </div>
</div>

<header class="navbar">
  <div class="navbar-container">
    <img src="/assets/whiti_logo_jadhlah_t.svg" alt="شعار جذلة" class="logo">
    <div class="nav-buttons">
      <a href="/index.php" class="nav-btn">الرئيسية</a>
      <a href="https://wa.me/966544705859" target="_blank" class="nav-btn">تواصل معنا</a>
    </div>
  </div>
</header>



 <div class="banner-wrapper">
  <?php if (!empty($groomData['banner'])): ?>
    <img src="<?= htmlspecialchars("$webBase/{$groomData['banner']}") ?>" class="banner">
    <img src="/assets/black_logo_jadhlah_t.svg" class="banner-logo-overlay" alt="شعار جذلة على البنر">
  <?php endif; ?>
</div>


 <div class="info fade-in-up">
  <h1 class="groom-name"><?= htmlspecialchars($groomData['groom_name']) ?></h1>
  <?php if (!empty($groomData['wedding_date'])): ?>
    <p class="wedding-date"> <?= htmlspecialchars($groomData['wedding_date']) ?></p>
  <?php endif; ?>
  <?php if (!empty($groomData['notes'])): ?>
    <p class="groom-notes"><?= nl2br(htmlspecialchars($groomData['notes'])) ?></p>
  <?php endif; ?>
</div>

  
<div class="youtube-wrapper ">
<?php
for ($i = 1; $i <= 7; $i++) {
    $field = "youtube$i";
    $link = $groomData[$field] ?? '';
    if (!empty($link)) {
        $videoId = '';
       if (preg_match(
    '/(?:v=|youtu\.be\/|embed\/|shorts\/|v\/)([A-Za-z0-9_-]+)/',
    $link,
    $matches
)) {
    $videoId = $matches[1];
}

        if ($videoId) {
            echo "
            <div class='youtube-card fade-in-up'>
              <iframe src='https://www.youtube.com/embed/$videoId'
                      allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                      allowfullscreen></iframe>
            </div>";
        }
    }
}
?>
</div>




  <div class="gallery">
    <?php foreach ($photos as $photo): ?>
      <div class="photo-box fade-in-up" data-id="<?= $photo['id'] ?>">
        <img src="<?= htmlspecialchars("{$thumbsPath}/{$photo['filename']}") ?>" loading="lazy" decoding="async">
      </div>
      


<!-- مودال تأكيد وتحميل ZIP -->
<div id="downloadModal" style="
  display:none;
  position:fixed; top:0; left:0;
  width:100%; height:100%;
  background:rgba(0,0,0,0.6);
  align-items:center; justify-content:center;
  z-index:2000;
">
  <div style="
    background:#fff;
    border-radius:12px;
    padding:20px;
    max-width:320px;
    text-align:center;
    box-shadow:0 4px 20px rgba(0,0,0,0.2);
    position:relative;
    font-family:'Tajawal',sans-serif;
  ">
    <span id="downloadModalClose" style="
      position:absolute; top:10px; right:12px;
      font-size:24px; cursor:pointer; color:#666;
    ">&times;</span>

    <img src="/assets/whiti_logo_jadhlah_t.svg"
         alt="جذلة" style="width:80px; margin:0 auto 15px; display:block;"/>

    <p style="margin-bottom:15px; color:#333; font-size:16px;">
      سيُحمَّل ZIP يحوي جميع الصور الأصلية
    </p>

    <div style="display:flex; gap:10px; justify-content:center; margin-bottom:15px;">
      <button id="confirmDownload" style="
        background:#25D366; color:#fff; border:none;
        padding:8px 16px; border-radius:6px;
        font-size:14px; cursor:pointer;
      ">موافق</button>
      <button id="cancelDownload" style="
        background:#ccc; color:#333; border:none;
        padding:8px 16px; border-radius:6px;
        font-size:14px; cursor:pointer;
      ">إلغاء</button>
    </div>

    <progress id="downloadProgress" value="0" max="100" style="
      width:100%; display:none; height:12px;
      accent-color:#25D366;
    "></progress>
  </div>
</div>

    <?php endforeach; ?>
  </div>
<section class="promo-section">
  <img src="/assets/whiti_logo_jadhlah_t.svg" alt="شعار جذلة" class="promo-logo">
  <h2 class="promo-text">لأن القلب يعيش هذه الليلة ألف مرة بعدستنا</h2>
  <a href="https://wa.me/966544705859" target="_blank" class="promo-btn">تواصل معنا عبر واتساب</a>
</section>



  <div id="imageModal" class="modal">
    <div class="modal-content">
      <button class="modal-close">✖️</button>
      <div class="modal-scroll">
        <?php foreach ($photos as $photo): ?>
          <div class="modal-photo" data-id="<?= $photo['id'] ?>">
            <div class="heart-animation"></div>
            <img src="<?= htmlspecialchars("{$thumbsPath}/{$photo['filename']}") ?>">
            <div class="modal-actions">
              <button class="like-btn" data-id="<?= $photo['id'] ?>">❤️ <span><?= $photo['likes'] ?></span></button>
              <span>👁️ <span id="views-<?= $photo['id'] ?>"><?= $photo['views'] ?></span></span>
        <a
          href="/<?= htmlspecialchars($webBase . '/originals/' . $photo['filename']) ?>"
          download
          style="font-size:20px; text-decoration:none; color:#888;"
        >
          ⬇️
        </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if ($approvedReviews): ?>
  <div style="max-width:800px;margin:40px auto;padding:20px;">
    <h3 style="text-align:center;color:#444;">قالوا عن جذلة</h3>
    
    <div class="swiper mySwiper">
      <div class="swiper-wrapper">
        <?php foreach ($approvedReviews as $rev): ?>
        <div class="swiper-slide" style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);max-width:300px;">
          <div style="font-size:16px;color:#f5b301;margin-bottom:8px;">
            <?= str_repeat("⭐", $rev['rating']) ?>
          </div>
          <div style="color:#333;margin-bottom:10px;"><?= nl2br(htmlspecialchars($rev['message'])) ?></div>
          <div style="text-align:right;font-size:14px;color:#888;">— <?= htmlspecialchars($rev['name']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <!-- أزرار التمرير -->
      <div class="swiper-button-next"></div>
      <div class="swiper-button-prev"></div>
    </div>
  </div>
<?php endif; ?>


  <div class="review-form-wrapper">
  <h3 class="review-title">💬 اترك تقييمك</h3>

  <?php if (isset($_GET['review']) && $_GET['review'] === 'success'): ?>
    <div class="review-success">✅ شكرًا على تقييمك! سيتم مراجعته من قبل الإدارة.</div>
  <?php endif; ?>

  <form method="POST" class="review-form">
    <label>اسمك</label>
    <input type="text" name="review_name" required>

    <label>عدد النجوم</label>
    <div class="star-rating">
      <span data-value="5">★</span>
      <span data-value="4">★</span>
      <span data-value="3">★</span>
      <span data-value="2">★</span>
      <span data-value="1">★</span>
    </div>
    <input type="hidden" name="review_rating" id="review_rating" required>

    <label>رسالتك</label>
    <textarea name="review_message" rows="4" required></textarea>

    <button type="submit" name="submit_review">إرسال التقييم</button>
  </form>
</div>
<section class="social-section">
  <h3 class="social-title">تابعنا على السوشال ميديا</h3>
  <div class="social-icons">
    <a href="https://www.tiktok.com/@jadhlah" target="_blank" class="social-link tiktok" aria-label="TikTok">
      <img src="/assets/icons/tiktok.svg" alt="TikTok">
    </a>
    <a href="https://www.instagram.com/jadhlah" target="_blank" class="social-link instagram" aria-label="Instagram">
      <img src="/assets/icons/instagram.svg" alt="Instagram">
    </a>
    <a href="https://www.snapchat.com/add/vmp.pro" target="_blank" class="social-link snapchat" aria-label="Snapchat">
      <img src="/assets/icons/snapchat.svg" alt="Snapchat">
    </a>
    <a href="https://x.com/jadhlah" target="_blank" class="social-link x" aria-label="X">
      <img src="/assets/icons/x.svg" alt="X">
    </a>
  </div>
</section>

  <div class="info-stats">
  <div class="stat-box">
    <div class="stat-icon">
      <!-- Eye SVG -->
      <svg width="28" height="28" fill="#444" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M1.293 12.707a1 1 0 0 1 0-1.414C3.84 8.746 7.655 6 12 6s8.16 2.746 10.707 5.293a1 1 0 0 1 0 1.414C20.16 15.254 16.345 18 12 18s-8.16-2.746-10.707-5.293Zm10.707 3.293a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
      </svg>
    </div>
    <div class="stat-label">زيارات أحباب العريس</div>
    <div class="stat-value"><?= htmlspecialchars($groomData['page_views']) ?></div>
  </div>

  <div class="stat-box">
    <div class="stat-icon">
      <!-- Hourglass SVG -->
      <svg width="28" height="28" fill="#444" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M6 2a1 1 0 1 0 0 2h.243A6.97 6.97 0 0 0 9 10c0 1.726-.628 3.3-1.657 4.5A6.97 6.97 0 0 0 6.243 20H6a1 1 0 1 0 0 2h12a1 1 0 1 0 0-2h-.243a6.97 6.97 0 0 0-1.1-5.5A6.97 6.97 0 0 0 15 10c0-1.726.628-3.3 1.657-4.5A6.97 6.97 0 0 0 17.757 4H18a1 1 0 1 0 0-2H6Zm2.243 2h7.514A4.97 4.97 0 0 1 14 10c0 .795.185 1.548.514 2.243H9.486A4.97 4.97 0 0 1 10 10c0-1.64-.659-3.13-1.757-4ZM9.486 16h5.028A4.97 4.97 0 0 1 14 20H10a4.97 4.97 0 0 1-.514-4Z"/>
      </svg>
    </div>
    <div class="stat-label">إغلاق الصفحة بعد</div>
    <div class="stat-value"><?= $daysLeft ?> يوم<?= $daysLeft !== 1 ? 'ًا' : '' ?></div>
  </div>
</div>
    <section class="download-all-section">
      <button id="downloadAllBtn" class="download-all-btn">
        ⬇️ تحميل جميع الصور
      </button>
    </section>

  <div class="copy-section">
  <button onclick="copyPageLink()" class="copy-btn">📎 نسخ رابط هذه الصفحة</button>
  <div id="copy-msg" class="copy-msg">✅ تم نسخ الرابط</div>
</div>
<div id="toast" class="toast">✅ تم النسخ</div>

<footer class="site-footer">
  <div class="footer-content">
    <img src="/assets/black_logo_jadhlah_t.svg" alt="شعار جذلة" class="footer-logo">
    <div class="footer-links">
      <a href="/index.php" class="footer-link">الرئيسية</a>
      <a href="/about.php" class="footer-link">من نحن</a>
      <a href="https://wa.me/966544705859" target="_blank" class="footer-link">تواصل معنا</a>
    </div>
    <p class="footer-copy">© <?= date('Y') ?> جذلة. جميع الحقوق محفوظة.</p>
  </div>
</footer>


<a href="https://wa.me/966544705859" target="_blank" class="whatsapp-float" title="تواصل معنا عبر واتساب">
  <img src="/assets/icons/whatsapp.png" alt="واتساب" width="22" height="22">
</a>


<!-- زر صعود/نزول -->
<button id="scrollToggleBtn" class="floating-btn left-btn" title="انتقال">
  <svg id="scrollIcon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
    <path d="M12 4a1 1 0 0 1 .7.3l6 6a1 1 0 1 1-1.4 1.4L12 6.4 6.7 11.7a1 1 0 0 1-1.4-1.4l6-6A1 1 0 0 1 12 4Z"/>
  </svg>
</button>


<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('imageModal');
  const closeBtn = modal.querySelector('.modal-close');

  // فتح المودال وعرض الصورة
  document.querySelectorAll('.photo-box').forEach(box => {
    box.addEventListener('click', () => {
      const id = box.dataset.id;
      modal.style.display = 'block';
      const target = modal.querySelector(`.modal-photo[data-id='${id}']`);
      if (target) target.scrollIntoView({behavior:'smooth',block:'start'});
    });
    // عد المشاهدات في العرض المصغر
    box.addEventListener('mouseenter', () => {
      const id = box.dataset.id;
      fetch('reaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `photo_id=${id}&action=view`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) document.getElementById(`views-${id}`).textContent = data.count;
      });
    });
  });

  // إغلاق المودال
  closeBtn.addEventListener('click', () => modal.style.display = 'none');
  modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.style.display = 'none'; });

  // عدّ المشاهدات في العرض العمودي
  document.querySelectorAll('.modal-photo').forEach(photoDiv => {
    photoDiv.addEventListener('mouseenter', () => {
      const id = photoDiv.dataset.id;
      fetch('reaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `photo_id=${id}&action=view`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) document.getElementById(`views-${id}`).textContent = data.count;
      });
    });
  });

  // زر الإعجاب وأنيميشن القلب
  document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const id = btn.dataset.id;
      const parent = btn.closest('.modal-photo');
      const heart = parent.querySelector('.heart-animation');
      heart.textContent = '❤️';
      heart.classList.add('show');
      setTimeout(() => heart.classList.remove('show'), 600);
      fetch('reaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `photo_id=${id}&action=like`
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) btn.querySelector('span').textContent = data.count;
      });
    });
  });

  // النقر على الصورة داخل المودال يعادل الإعجاب
  document.querySelectorAll('.modal-photo img').forEach(img => {
    img.addEventListener('click', e => {
      e.stopPropagation();
      const btn = img.closest('.modal-photo').querySelector('.like-btn');
      btn.click();
    });
  });
});
</script>
<script>
document.querySelectorAll('.star-rating span').forEach(star => {
  star.addEventListener('click', function() {
    const value = this.getAttribute('data-value');
    document.getElementById('review_rating').value = value;

    document.querySelectorAll('.star-rating span').forEach(s => s.classList.remove('selected'));
    this.classList.add('selected');
    let next = this.nextElementSibling;
    while (next) {
      next.classList.add('selected');
      next = next.nextElementSibling;
    }
  });
});
</script>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
  const swiper = new Swiper(".mySwiper", {
    slidesPerView: 1.2,
    spaceBetween: 20,
    navigation: {
      nextEl: ".swiper-button-next",
      prevEl: ".swiper-button-prev"
    },
    breakpoints: {
      640: { slidesPerView: 2.2 },
      1024: { slidesPerView: 3 }
    }
  });
</script>
<script>
function copyPageLink() {
  navigator.clipboard.writeText(window.location.href)
    .then(() => {
      const msg = document.getElementById('copy-msg');
      msg.style.display = 'block';
      setTimeout(() => msg.style.display = 'none', 2000);
    });
}
</script>
<script>
const scrollBtn = document.getElementById('scrollToggleBtn');

function updateScrollButton() {
  const scrollY = window.scrollY;
  const maxScroll = document.documentElement.scrollHeight - window.innerHeight;

  if (scrollY < maxScroll / 2) {
    // نعرض سهم للأسفل
    scrollBtn.innerHTML = `
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 20a1 1 0 0 1-.7-.3l-6-6a1 1 0 1 1 1.4-1.4L12 17.6l5.3-5.3a1 1 0 1 1 1.4 1.4l-6 6a1 1 0 0 1-.7.3Z"/>
      </svg>
    `;
    scrollBtn.onclick = () => window.scrollTo({ top: maxScroll, behavior: 'smooth' });
  } else {
    // نعرض سهم للأعلى
    scrollBtn.innerHTML = `
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 4a1 1 0 0 1 .7.3l6 6a1 1 0 1 1-1.4 1.4L12 6.4 6.7 11.7a1 1 0 0 1-1.4-1.4l6-6A1 1 0 0 1 12 4Z"/>
      </svg>
    `;
    scrollBtn.onclick = () => window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

window.addEventListener('scroll', updateScrollButton);
window.addEventListener('load', updateScrollButton);
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('welcomeModal');
  const btnClose = document.getElementById('welcomeClose');

  // إذا لم يُعرض من قبل
  if (!localStorage.getItem('welcomeShown')) {
    modal.style.display = 'flex';
    localStorage.setItem('welcomeShown', 'true');

    // إغلاق تلقائي بعد 5 ثواني
    const timeoutId = setTimeout(hideModal, 5000);

    // إغلاق بالزر
    btnClose.addEventListener('click', () => {
      clearTimeout(timeoutId);
      hideModal();
    });
  }

  function hideModal() {
    // أنيميشن اختفاء ثم إخفاء نهائي
    modal.querySelector('.welcome-content')
         .style.animation = 'fadeOut 0.5s ease-out forwards';
    setTimeout(() => modal.style.display = 'none', 500);
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const downloadAllBtn   = document.getElementById('downloadAllBtn');
  const downloadModal    = document.getElementById('downloadModal');
  const closeModalBtn    = document.getElementById('downloadModalClose');
  const confirmBtn       = document.getElementById('confirmDownload');
  const cancelBtn        = document.getElementById('cancelDownload');
  const progressBar      = document.getElementById('downloadProgress');
  const groomId          = <?= json_encode($groomId) ?>;

  // فتح المودال
  downloadAllBtn.addEventListener('click', () => {
    progressBar.style.display = 'none';
    progressBar.value = 0;
    downloadModal.style.display = 'flex';
  });

  // إغلاق المودال
  [closeModalBtn, cancelBtn].forEach(btn =>
    btn.addEventListener('click', () => { downloadModal.style.display = 'none'; })
  );

  // عند الضغط على "موافق" يبدأ تجميع الصور وضغطها
  confirmBtn.addEventListener('click', async () => {
    downloadAllBtn.disabled = true;
    progressBar.style.display = 'block';

    // اجمع كل روابط الصور الأصلية
    const imgs = Array.from(document.querySelectorAll('.photo-box img'))
      .map(img => {
        // src الصور المصغرة يتضمن thumbs؛ نريد originals
        const thumbUrl  = img.getAttribute('src');
        return {
          url: thumbUrl.replace('/thumbs/', '/originals/'),
          name: img.closest('.photo-box').dataset.filename
        };
      });

    const zip = new JSZip();
    let count = 0;

    // أضف كل صورة إلى الأرشيف
    for (const {url, name} of imgs) {
      try {
        const res  = await fetch(url);
        const blob = await res.blob();
        zip.file(name, blob);
      } catch (e) {
        console.warn('فشل تحميل', url);
      }
      count++;
      progressBar.value = (count / imgs.length) * 100;
    }

    // توليد ملف الـ ZIP مع مؤشر تقدّم داخلي
    const content = await zip.generateAsync({ type: 'blob' }, meta => {
      progressBar.value = meta.percent;
    });

    // حفظ الملف لدى المستخدم
    saveAs(content, `groom_${groomId}_photos.zip`);

    downloadModal.style.display = 'none';
    downloadAllBtn.disabled = false;
  });
});
</script>




</body>
</html>
