<?php
// live-gallery.php - نسخة محسّنة مع تحديث تلقائي وتمرير
require_once 'config/database.php';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// AJAX Request للصور
if (isset($_GET['ajax'])) {
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = 20;
    $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
    $lastId = isset($_GET['lastId']) ? (int)$_GET['lastId'] : 0;
    
    // إذا كان lastId موجود، نجيب الصور الأحدث فقط
    if ($lastId > 0) {
        $stmt = $conn->prepare("
            SELECT * FROM live_gallery_photos 
            WHERE is_expired = 0 
            AND expires_at > NOW()
            AND is_hidden = 0
            AND id > ?
            ORDER BY uploaded_at DESC
            LIMIT 20
        ");
        $stmt->bind_param("i", $lastId);
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM live_gallery_photos 
            WHERE is_expired = 0 
            AND expires_at > NOW()
            AND is_hidden = 0
            ORDER BY uploaded_at $order
            LIMIT ?, ?
        ");
        $stmt->bind_param("ii", $offset, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $photos = [];
    while ($row = $result->fetch_assoc()) {
        $time = strtotime($row['uploaded_at']);
        $diff = time() - $time;
        
        if ($diff < 60) {
            $timeText = "الآن";
        } elseif ($diff < 3600) {
            $timeText = floor($diff / 60) . " دقيقة";
        } elseif ($diff < 86400) {
            $timeText = floor($diff / 3600) . " ساعة";
        } else {
            $timeText = floor($diff / 86400) . " يوم";
        }
        
        $photos[] = [
            'id' => $row['id'],
            'filename' => $row['filename'],
            'thumb' => preg_replace('/\.(jpg|jpeg|JPG|JPEG)$/i', '_thumb.jpg', $row['filename']),
            'timeText' => $timeText,
            'width' => $row['width'] ?? 0,
            'height' => $row['height'] ?? 0
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($photos);
    exit;
}

$photoCount = $conn->query("
    SELECT COUNT(*) as count FROM live_gallery_photos 
    WHERE is_expired = 0 AND expires_at > NOW() AND is_hidden = 0
")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البث المباشر - جذلة للتصوير</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap');
        
        :root {
            --gold: #D4AF37;
            --dark-gold: #B8941E;
            --light-gold: #F4E5C2;
            --black: #1a1a1a;
            --dark-gray: #2d2d2d;
            --white: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--black);
            color: var(--white);
            overflow-x: hidden;
        }
        
        .luxury-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%);
            z-index: -1;
        }
        
        .top-bar {
            background: rgba(45, 45, 45, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 0;
            border-bottom: 2px solid var(--gold);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .top-bar-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-img {
            height: 50px;
            width: auto;
        }
        
        .logo-text p {
            font-size: 14px;
            color: var(--light-gold);
            margin: 0;
            font-weight: 500;
        }
        
        .back-btn {
            background: var(--gold);
            color: var(--black);
            padding: 10px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }
        
        .header {
            text-align: center;
            padding: 40px 30px;
        }
        
        .header h1 {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gold), var(--light-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 68, 68, 0.2);
            border: 2px solid #ff4444;
            padding: 10px 25px;
            border-radius: 30px;
            color: #ff4444;
            font-weight: 700;
            font-size: 16px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        
        .live-dot {
            width: 12px;
            height: 12px;
            background: #ff4444;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .toolbar {
            max-width: 1400px;
            margin: 0 auto 30px;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats {
            display: flex;
            align-items: center;
            gap: 20px;
            color: var(--light-gold);
            font-size: 16px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .view-btn, .sort-btn {
            width: 45px;
            height: 45px;
            background: rgba(45, 45, 45, 0.8);
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 10px;
            color: var(--light-gold);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
        }
        
        .view-btn:hover,
        .view-btn.active,
        .sort-btn:hover,
        .sort-btn.active {
            background: var(--gold);
            color: var(--black);
            border-color: var(--gold);
        }
        
        .gallery {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px 50px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            min-height: 400px;
        }
        
        .gallery.single-view {
            grid-template-columns: 1fr;
            max-width: 900px;
        }
        
        .photo-card {
            background: rgba(45, 45, 45, 0.6);
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            opacity: 0;
            animation: fadeIn 0.5s forwards;
        }
        
        .photo-card.new-photo {
            animation: slideInDown 0.5s forwards;
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .photo-card:hover {
            border-color: var(--gold);
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.3);
        }
        
        .photo-card img {
            width: 100%;
            height: 350px;
            object-fit: cover;
            display: block;
            background: rgba(45, 45, 45, 0.8);
        }
        
        .gallery.single-view .photo-card img {
            height: auto;
            max-height: 70vh;
        }
        
        .photo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.8));
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 20px;
        }
        
        .photo-card:hover .photo-overlay {
            opacity: 1;
        }
        
        .photo-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .action-btn {
            flex: 1;
            padding: 10px;
            background: var(--gold);
            color: var(--black);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 13px;
        }
        
        .action-btn:hover {
            background: var(--light-gold);
            transform: translateY(-2px);
        }
        
        .photo-info {
            padding: 15px;
        }
        
        .photo-time {
            color: var(--light-gold);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .lightbox.active {
            display: flex;
        }
        
        .lightbox-content {
            position: relative;
            max-width: 95%;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .lightbox img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 10px;
        }
        
        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 60px;
            height: 60px;
            background: rgba(212, 175, 55, 0.9);
            border: none;
            border-radius: 50%;
            color: var(--black);
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .lightbox-nav:hover {
            background: var(--gold);
            transform: translateY(-50%) scale(1.1);
        }
        
        .lightbox-nav.prev {
            right: -80px;
        }
        
        .lightbox-nav.next {
            left: -80px;
        }
        
        .lightbox-controls {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .lightbox-btn {
            padding: 12px 25px;
            background: var(--gold);
            color: var(--black);
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .lightbox-btn:hover {
            background: var(--light-gold);
            transform: translateY(-2px);
        }
        
        .lightbox-close {
            position: absolute;
            top: -50px;
            left: 0;
            width: 45px;
            height: 45px;
            background: var(--gold);
            color: var(--black);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .lightbox-close:hover {
            transform: rotate(90deg);
            background: var(--light-gold);
        }
        
        .lightbox-counter {
            position: absolute;
            top: -50px;
            right: 0;
            background: rgba(212, 175, 55, 0.9);
            color: var(--black);
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--light-gold);
            font-size: 18px;
        }
        
        .loading i {
            font-size: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 100px 30px;
        }
        
        .empty-icon {
            font-size: 100px;
            margin-bottom: 30px;
        }
        
        .empty-state h2 {
            font-size: 32px;
            color: var(--gold);
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: var(--light-gold);
            font-size: 18px;
        }

        .print-status {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--gold);
            color: var(--black);
            padding: 15px 25px;
            border-radius: 10px;
            font-weight: 600;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            z-index: 2000;
        }

        .print-status.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .new-photos-badge {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--gold);
            color: var(--black);
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(212, 175, 55, 0.5);
            z-index: 999;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
        }
        
        .new-photos-badge.show {
            opacity: 1;
            pointer-events: all;
        }
        
        .new-photos-badge:hover {
            transform: translateX(-50%) translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.7);
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 28px;
            }
            
            .gallery {
                grid-template-columns: repeat(3, 1fr);
                gap: 5px;
                padding: 0 15px 50px;
            }
            
            .gallery.single-view {
                grid-template-columns: 1fr;
            }
            
            .toolbar {
                flex-direction: column;
            }
            
            .top-bar-content {
                flex-direction: column;
                text-align: center;
            }
            
            .photo-card img {
                        height: auto;
        aspect-ratio: 1;
            }

            .lightbox-controls {
                padding: 0 15px;
            }

            .lightbox-btn {
                font-size: 13px;
                padding: 10px 15px;
            }
            
            .lightbox-nav {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .lightbox-nav.prev {
                right: 10px;
            }
            
            .lightbox-nav.next {
                left: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="luxury-bg"></div>
    
    <div class="top-bar">
        <div class="top-bar-content">
            <div class="logo-section">
                <img src="/assets/black_logo_jadhlah_t.svg" alt="جذلة" class="logo-img">
                <div class="logo-text">
                    <p>متخصص تصوير الزواجات</p>
                </div>
            </div>
            
            <a href="landing.php" class="back-btn">
                <i class="fas fa-arrow-right"></i>
                العودة
            </a>
        </div>
    </div>
    
    <div class="header">
        <h1>🔴 البث المباشر للصور</h1>
        <span class="live-badge">
            <span class="live-dot"></span>
            مباشر الآن • تحديث تلقائي
        </span>
    </div>
    
    <div class="toolbar">
        <div class="stats">
            <div class="stat-item">
                <i class="fas fa-images"></i>
                <span id="photoCount"><?php echo $photoCount; ?> صورة</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-clock"></i>
                <span>آخر 24 ساعة</span>
            </div>
        </div>
        
        <div class="view-controls">
            <button class="sort-btn" id="sortBtn" onclick="toggleSort()" title="ترتيب">
                <i class="fas fa-sort-amount-down"></i>
            </button>
            <button class="view-btn active" onclick="switchView('grid')" title="عرض شبكي">
                <i class="fas fa-th"></i>
            </button>
            <button class="view-btn" onclick="switchView('single')" title="عرض فردي">
                <i class="fas fa-square"></i>
            </button>
        </div>
    </div>
    
    <div class="new-photos-badge" id="newPhotosBadge" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
        <span id="newPhotosCount">0</span> صورة جديدة
    </div>
    
    <div class="gallery" id="gallery"></div>
    
    <div class="loading" id="loading" style="display: none;">
        <i class="fas fa-spinner"></i>
        <p>جاري التحميل...</p>
    </div>
    
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <span class="lightbox-close" onclick="closeLightbox()">×</span>
            <span class="lightbox-counter" id="lightboxCounter">1 / 1</span>
            <button class="lightbox-nav prev" onclick="event.stopPropagation(); prevImage()">
                <i class="fas fa-chevron-right"></i>
            </button>
            <img src="" id="lightboxImage" alt="صورة">
            <button class="lightbox-nav next" onclick="event.stopPropagation(); nextImage()">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="lightbox-controls">
                <button class="lightbox-btn" onclick="downloadCurrentImage()">
                    <i class="fas fa-download"></i>
                    تحميل
                </button>
                <button class="lightbox-btn" onclick="printCurrentImage()">
                    <i class="fas fa-print"></i>
                    طباعة
                </button>
                <button class="lightbox-btn" onclick="shareCurrentImage()" id="shareBtn">
                    <i class="fas fa-share-alt"></i>
                    مشاركة
                </button>
            </div>
        </div>
    </div>
    
    <script>
let currentImageSrc = '';
let currentImageIndex = 0;
let allPhotos = [];
let offset = 0;
let isLoading = false;
let hasMore = true;
let currentOrder = 'desc';
let currentViewMode = 'grid';
let lastPhotoId = 0;
let newPhotosCount = 0;

loadPhotos();
startAutoRefresh();

window.addEventListener('scroll', () => {
    if (isLoading || !hasMore) return;
    
    const scrollPosition = window.innerHeight + window.scrollY;
    const threshold = document.body.offsetHeight - 500;
    
    if (scrollPosition >= threshold) {
        loadPhotos();
    }
});

async function loadPhotos() {
    if (isLoading || !hasMore) return;
    
    isLoading = true;
    document.getElementById('loading').style.display = 'block';
    
    try {
        const response = await fetch(`?ajax=1&offset=${offset}&order=${currentOrder}`);
        const photos = await response.json();
        
        if (photos.length === 0) {
            hasMore = false;
            document.getElementById('loading').style.display = 'none';
            
            if (offset === 0) {
                showEmptyState();
            }
            return;
        }
        
        const gallery = document.getElementById('gallery');
        
        photos.forEach(photo => {
            allPhotos.push(photo);
            const card = createPhotoCard(photo);
            gallery.appendChild(card);
            
            if (photo.id > lastPhotoId) {
                lastPhotoId = photo.id;
            }
        });
        
        offset += photos.length;
        observeImages();
        
    } catch (error) {
        console.error('Error loading photos:', error);
        showStatus('❌ حدث خطأ في تحميل الصور');
    } finally {
        isLoading = false;
        document.getElementById('loading').style.display = 'none';
    }
}

async function checkForNewPhotos() {
    if (currentOrder !== 'desc') return;
    
    try {
        const response = await fetch(`?ajax=1&lastId=${lastPhotoId}`);
        const newPhotos = await response.json();
        
        if (newPhotos.length > 0) {
            newPhotosCount = newPhotos.length;
            
            const badge = document.getElementById('newPhotosBadge');
            document.getElementById('newPhotosCount').textContent = newPhotosCount;
            badge.classList.add('show');
            
            setTimeout(() => {
                badge.classList.remove('show');
            }, 10000);
            
            newPhotos.reverse().forEach(photo => {
                allPhotos.unshift(photo);
                
                if (photo.id > lastPhotoId) {
                    lastPhotoId = photo.id;
                }
            });
            
            const photoCount = document.getElementById('photoCount');
            const currentCount = parseInt(photoCount.textContent);
            photoCount.textContent = (currentCount + newPhotos.length) + ' صورة';
        }
    } catch (error) {
        console.error('Error checking for new photos:', error);
    }
}

function scrollToTop() {
    const gallery = document.getElementById('gallery');
    const newPhotosToAdd = allPhotos.slice(0, newPhotosCount);
    
    newPhotosToAdd.forEach((photo, index) => {
        setTimeout(() => {
            const card = createPhotoCard(photo, true);
            gallery.insertBefore(card, gallery.firstChild);
            observeImages();
        }, index * 100);
    });
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
    document.getElementById('newPhotosBadge').classList.remove('show');
    newPhotosCount = 0;
}

function startAutoRefresh() {
    setInterval(checkForNewPhotos, 15000);
}

function createPhotoCard(photo, isNew = false) {
    const card = document.createElement('div');
    card.className = 'photo-card' + (isNew ? ' new-photo' : '');
    card.dataset.photoId = photo.id;
    card.dataset.fullImage = `/uploads/live/${photo.filename}`;
    card.onclick = () => openLightbox(photo.id);
    
    const isSingleView = currentViewMode === 'single';
    const displayImage = isSingleView ? photo.filename : photo.thumb;
    
    card.innerHTML = `
        <img 
            data-src="/uploads/live/${displayImage}" 
            data-full="/uploads/live/${photo.filename}"
            alt="صورة حية" 
            class="lazy"
        >
        <div class="photo-overlay">
            <div class="photo-actions">
                <button class="action-btn" onclick="event.stopPropagation(); quickDownload('/uploads/live/${photo.filename}')">
                    <i class="fas fa-download"></i>
                    تحميل
                </button>
                <button class="action-btn" onclick="event.stopPropagation(); quickPrint('/uploads/live/${photo.filename}')">
                    <i class="fas fa-print"></i>
                    طباعة
                </button>
                <button class="action-btn" onclick="event.stopPropagation(); quickShare('/uploads/live/${photo.filename}')">
                    <i class="fas fa-share-alt"></i>
                    مشاركة
                </button>
            </div>
        </div>
        <div class="photo-info">
            <div class="photo-time">
                <i class="fas fa-clock"></i>
                ${photo.timeText}
            </div>
        </div>
    `;
    
    return card;
}

function observeImages() {
    const images = document.querySelectorAll('img.lazy');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

function toggleSort() {
    currentOrder = currentOrder === 'desc' ? 'asc' : 'desc';
    
    const icon = document.querySelector('#sortBtn i');
    icon.className = currentOrder === 'desc' 
        ? 'fas fa-sort-amount-down' 
        : 'fas fa-sort-amount-up';
    
    offset = 0;
    hasMore = true;
    allPhotos = [];
    lastPhotoId = 0;
    document.getElementById('gallery').innerHTML = '';
    loadPhotos();
}

function openLightbox(photoId) {
    currentImageIndex = allPhotos.findIndex(p => p.id === photoId);
    if (currentImageIndex === -1) currentImageIndex = 0;
    
    showImageAtIndex(currentImageIndex);
    document.getElementById('lightbox').classList.add('active');
}

function showImageAtIndex(index) {
    if (index < 0 || index >= allPhotos.length) return;
    
    currentImageIndex = index;
    const photo = allPhotos[index];
    currentImageSrc = `/uploads/live/${photo.filename}`;
    
    document.getElementById('lightboxImage').src = currentImageSrc;
    document.getElementById('lightboxCounter').textContent = `${index + 1} / ${allPhotos.length}`;
}

function nextImage() {
    if (currentImageIndex < allPhotos.length - 1) {
        showImageAtIndex(currentImageIndex + 1);
    }
}

function prevImage() {
    if (currentImageIndex > 0) {
        showImageAtIndex(currentImageIndex - 1);
    }
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}

function switchView(mode) {
    currentViewMode = mode;
    const gallery = document.getElementById('gallery');
    const buttons = document.querySelectorAll('.view-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    
    if (mode === 'grid') {
        gallery.classList.remove('single-view');
        buttons[0].classList.add('active');
    } else {
        gallery.classList.add('single-view');
        buttons[1].classList.add('active');
    }
    
    offset = 0;
    hasMore = true;
    allPhotos = [];
    lastPhotoId = 0;
    gallery.innerHTML = '';
    loadPhotos();
}

function quickDownload(imageUrl) {
    downloadImage(imageUrl);
}

function downloadImage(imageSrc) {
    const link = document.createElement('a');
    link.href = imageSrc;
    link.download = 'jathlah_' + Date.now() + '.jpg';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showStatus('✅ تم بدء التحميل');
}

function downloadCurrentImage() {
    downloadImage(currentImageSrc);
}

function quickPrint(imageUrl) {
    printImage(imageUrl);
}

function printImage(imageUrl) {
    showStatus('🖨️ جاري فتح نافذة الطباعة...');
    
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
            <title>طباعة - جذلة</title>
            <style>
                @page {
                    size: 4in 6in;
                    margin: 0;
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    background: #f5f5f5;
                }
                img {
                    max-width: 100%;
                    max-height: 100vh;
                    object-fit: contain;
                }
                @media print {
                    body {
                        background: white;
                    }
                }
            </style>
        </head>
        <body>
            <img src="${imageUrl}" onload="setTimeout(() => { window.print(); }, 500);">
        </body>
        </html>
    `);
    printWindow.document.close();
}

function printCurrentImage() {
    printImage(currentImageSrc);
}

async function quickShare(imageUrl) {
    await shareImage(imageUrl);
}

async function shareImage(imageUrl) {
    if (!navigator.share && !navigator.canShare) {
        showStatus('⚠️ المشاركة غير مدعومة، جاري فتح نافذة الطباعة...');
        printImage(imageUrl);
        return;
    }

    try {
        showStatus('📤 جاري تحضير الصورة...');
        
        const response = await fetch(imageUrl);
        const blob = await response.blob();
        const file = new File([blob], 'jathlah_photo.jpg', { type: 'image/jpeg' });
        
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: 'صورة من جذلة للتصوير',
                text: 'شاهد هذه الصورة من حفلتنا'
            });
            showStatus('✅ تمت المشاركة بنجاح');
        } else {
            await navigator.share({
                title: 'صورة من جذلة للتصوير',
                text: 'شاهد هذه الصورة من حفلتنا',
                url: imageUrl
            });
            showStatus('✅ تمت المشاركة بنجاح');
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            showStatus('⚠️ فشلت المشاركة، جاري فتح نافذة الطباعة...');
            printImage(imageUrl);
        }
    }
}

async function shareCurrentImage() {
    await shareImage(currentImageSrc);
}

function showStatus(message, duration = 3000) {
    let statusEl = document.getElementById('printStatus');
    
    if (!statusEl) {
        statusEl = document.createElement('div');
        statusEl.id = 'printStatus';
        statusEl.className = 'print-status';
        document.body.appendChild(statusEl);
    }

    statusEl.textContent = message;
    statusEl.classList.add('show');

    setTimeout(() => {
        statusEl.classList.remove('show');
    }, duration);
}

function showEmptyState() {
    document.getElementById('gallery').innerHTML = `
        <div class="empty-state" style="grid-column: 1/-1;">
            <div class="empty-icon">📷</div>
            <h2>لا توجد صور حية حالياً</h2>
            <p>الصور ستظهر هنا مباشرة من الحفلات الجارية</p>
        </div>
    `;
}

document.addEventListener('keydown', (e) => {
    if (document.getElementById('lightbox').classList.contains('active')) {
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowLeft') {
            nextImage();
        } else if (e.key === 'ArrowRight') {
            prevImage();
        }
    }
});

if (!navigator.share) {
    const shareBtn = document.getElementById('shareBtn');
    if (shareBtn) shareBtn.style.display = 'none';
}
    </script>
</body>
</html>