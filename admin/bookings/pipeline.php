<?php
/**
 * صفحة إدارة الحجوزات - Pipeline View
 * جذلة للتصوير
 * 
 * المسار: /admin/bookings/pipeline.php
 */

// منع الوصول المباشر
define('JADHLAH_APP', true);

// التحقق من تسجيل الدخول
session_start();
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: /admin/login.php');
//     exit;
// }

// تحميل الإعدادات
require_once __DIR__ . '/../../config/database.php';

// التأكد من وجود الاتصال (database.php ينشئ $pdo)
if (!isset($pdo)) {
    die('خطأ في الاتصال بقاعدة البيانات');
}

// تعيين الخصائص الإضافية
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// جلب الإحصائيات
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE stage != 'closed'");
$stats['active'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE MONTH(wedding_date) = MONTH(CURRENT_DATE()) AND YEAR(wedding_date) = YEAR(CURRENT_DATE())");
$stats['this_month'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status != 'paid' AND stage != 'closed'");
$stats['pending_payments'] = $stmt->fetchColumn();

// جلب الباقات
$packages = $pdo->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY display_order")->fetchAll();

// المراحل
$stages = [
    ['key' => 'new_booking', 'name' => 'حجز جديد', 'color' => '#6366f1', 'icon' => 'plus-circle'],
    ['key' => 'coordination', 'name' => 'تنسيق', 'color' => '#8b5cf6', 'icon' => 'message-square'],
    ['key' => 'team_assigned', 'name' => 'تعيين الفريق', 'color' => '#a855f7', 'icon' => 'users'],
    ['key' => 'guidelines_sent', 'name' => 'إرشادات', 'color' => '#ec4899', 'icon' => 'file-text'],
    ['key' => 'reminder_sent', 'name' => 'تذكير', 'color' => '#f97316', 'icon' => 'bell'],
    ['key' => 'wedding_day', 'name' => 'يوم الزواج', 'color' => '#eab308', 'icon' => 'calendar-heart'],
    ['key' => 'processing', 'name' => 'معالجة', 'color' => '#22c55e', 'icon' => 'loader'],
    ['key' => 'delivered', 'name' => 'تسليم', 'color' => '#14b8a6', 'icon' => 'check-circle'],
    ['key' => 'review_requested', 'name' => 'تقييم', 'color' => '#06b6d4', 'icon' => 'star'],
    ['key' => 'closed', 'name' => 'مغلق', 'color' => '#64748b', 'icon' => 'archive'],
];

// جلب الحجوزات
$bookings = [];
foreach ($stages as $stage) {
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            p.name as package_name_db,
            p.color as package_color,
            (SELECT COUNT(*) FROM booking_team bt WHERE bt.booking_id = b.id) as team_count,
            (SELECT SUM(amount) FROM booking_payments bp WHERE bp.booking_id = b.id AND bp.is_paid = 1) as total_paid
        FROM bookings b
        LEFT JOIN packages p ON b.package_id = p.id
        WHERE b.stage = :stage
        ORDER BY b.wedding_date ASC
    ");
    $stmt->execute([':stage' => $stage['key']]);
    $bookings[$stage['key']] = $stmt->fetchAll();
}

// دالة تنسيق التاريخ
function formatDate($date) {
    if (!$date) return '';
    $months = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
    $ts = strtotime($date);
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// دالة تنسيق المبلغ
function formatPrice($amount) {
    return number_format($amount, 0) . ' ر.س';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الحجوزات | جذلة</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #1a1a24;
            --bg-hover: #22222e;
            --text-primary: #ffffff;
            --text-secondary: #a0a0b0;
            --text-muted: #606070;
            --accent-gold: #d4af37;
            --accent-gold-light: #f4d03f;
            --accent-gold-dark: #b8960c;
            --stage-1: #6366f1;
            --stage-2: #8b5cf6;
            --stage-3: #a855f7;
            --stage-4: #ec4899;
            --stage-5: #f97316;
            --stage-6: #eab308;
            --stage-7: #22c55e;
            --stage-8: #14b8a6;
            --stage-9: #06b6d4;
            --stage-10: #64748b;
            --paid: #22c55e;
            --partial: #f97316;
            --unpaid: #ef4444;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.5);
            --shadow-glow: 0 0 20px rgba(212, 175, 55, 0.3);
            --border-color: rgba(255,255,255,0.08);
            --border-radius: 12px;
            --transition-fast: 0.15s ease;
            --transition-normal: 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: auto;
        }
        
        /* Header */
        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--bg-primary);
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stats-bar {
            display: flex;
            gap: 2rem;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-card);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent-gold);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-dark));
            color: var(--bg-primary);
        }
        
        .btn-primary:hover {
            box-shadow: var(--shadow-glow);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--bg-hover);
            border-color: var(--accent-gold);
        }
        
        .btn-success { background: var(--paid); color: white; }
        .btn-warning { background: var(--partial); color: white; }
        .btn-danger { background: var(--unpaid); color: white; }
        
        /* Filters */
        .filters-bar {
            background: var(--bg-secondary);
            padding: 1rem 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.9rem;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--accent-gold);
        }
        
        .search-box input::placeholder { color: var(--text-muted); }
        
        .search-box i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            width: 18px;
            height: 18px;
        }
        
        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .filter-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .filter-select {
            padding: 0.6rem 2rem 0.6rem 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--accent-gold);
        }
        
        /* Pipeline */
        .pipeline-container {
            padding: 1.5rem;
            overflow-x: auto;
            min-height: calc(100vh - 180px);
        }
        
        .pipeline-board {
            display: flex;
            gap: 1rem;
            min-width: max-content;
        }
        
        .stage-column {
            width: 300px;
            min-width: 300px;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 220px);
        }
        
        .stage-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            background: var(--bg-secondary);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .stage-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .stage-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .stage-name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .stage-count {
            background: var(--bg-card);
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .stage-cards {
            padding: 0.75rem;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 100px;
        }
        
        .stage-cards::-webkit-scrollbar { width: 6px; }
        .stage-cards::-webkit-scrollbar-track { background: transparent; }
        .stage-cards::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 3px; }
        
        /* Booking Card */
        .booking-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 1rem;
            cursor: grab;
            transition: all var(--transition-normal);
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .booking-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: var(--card-accent, var(--accent-gold));
            border-radius: 0 10px 10px 0;
        }
        
        .booking-card:hover {
            border-color: var(--border-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .booking-card.dragging { opacity: 0.5; cursor: grabbing; }
        .booking-card.drag-over { border-color: var(--accent-gold); box-shadow: var(--shadow-glow); }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .groom-name {
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .groom-name i { width: 16px; height: 16px; color: var(--accent-gold); }
        
        .booking-id {
            font-size: 0.75rem;
            color: var(--text-muted);
            background: var(--bg-primary);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        
        .card-body {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .card-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .card-row i { width: 14px; height: 14px; opacity: 0.7; }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }
        
        .card-badges { display: flex; gap: 0.5rem; }
        
        .badge {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge i { width: 12px; height: 12px; }
        
        .badge-paid { background: rgba(34, 197, 94, 0.15); color: var(--paid); }
        .badge-partial { background: rgba(249, 115, 22, 0.15); color: var(--partial); }
        .badge-unpaid { background: rgba(239, 68, 68, 0.15); color: var(--unpaid); }
        .badge-team { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
        .badge-whatsapp { background: rgba(37, 211, 102, 0.15); color: #25d366; }
        
        .card-price { font-weight: 700; color: var(--accent-gold); font-size: 0.9rem; }
        
        .card-actions {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            display: flex;
            gap: 0.25rem;
            opacity: 0;
            transition: opacity var(--transition-fast);
        }
        
        .booking-card:hover .card-actions { opacity: 1; }
        
        .card-action-btn {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .card-action-btn:hover {
            background: var(--accent-gold);
            color: var(--bg-primary);
            border-color: var(--accent-gold);
        }
        
        .card-action-btn i { width: 14px; height: 14px; }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            color: var(--text-muted);
        }
        
        .empty-state i { width: 40px; height: 40px; margin-bottom: 0.75rem; opacity: 0.3; }
        .empty-state p { font-size: 0.85rem; }
        
        /* Drop Zone Highlight */
        .stage-cards.drag-over {
            background: rgba(212, 175, 55, 0.05);
            border: 2px dashed var(--accent-gold);
            border-radius: 8px;
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all var(--transition-normal);
        }
        
        .modal-overlay.active { opacity: 1; visibility: visible; }
        
        .modal {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: transform var(--transition-normal);
        }
        
        .modal-overlay.active .modal { transform: scale(1) translateY(0); }
        
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .modal-title i { color: var(--accent-gold); }
        
        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .modal-close:hover { background: var(--unpaid); color: white; }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        /* Forms */
        .form-group { margin-bottom: 1.25rem; }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 0.95rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-gold);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-color);
            border-top-color: var(--accent-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--bg-card);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            z-index: 2000;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success { border-color: var(--paid); }
        .toast.error { border-color: var(--unpaid); }
        
        @media (max-width: 1200px) {
            .stats-bar { display: none; }
        }
        
        @media (max-width: 768px) {
            .filters-bar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .stage-column { width: 280px; min-width: 280px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">ج</div>
                <span class="logo-text">جذلة</span>
            </div>
            
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-value" id="statActive"><?= $stats['active'] ?></span>
                    <span class="stat-label">حجز نشط</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="statMonth"><?= $stats['this_month'] ?></span>
                    <span class="stat-label">هذا الشهر</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="statPending"><?= $stats['pending_payments'] ?></span>
                    <span class="stat-label">دفعات معلقة</span>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="/admin/bookings/calendar.php" class="btn btn-secondary">
                    <i data-lucide="calendar"></i>
                    التقويم
                </a>
                <button class="btn btn-primary" onclick="openNewBooking()">
                    <i data-lucide="plus"></i>
                    حجز جديد
                </button>
            </div>
        </div>
    </header>
    
    <!-- Filters -->
    <div class="filters-bar">
        <div class="search-box">
            <i data-lucide="search"></i>
            <input type="text" placeholder="بحث عن حجز، عريس، رقم جوال..." id="searchInput">
        </div>
        
        <div class="filter-group">
            <span class="filter-label">الشهر:</span>
            <select class="filter-select" id="monthFilter">
                <option value="">الكل</option>
                <?php
                $months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
                $currentMonth = date('n');
                foreach ($months as $i => $m) {
                    $selected = ($i + 1 == $currentMonth) ? 'selected' : '';
                    echo "<option value='" . ($i + 1) . "' {$selected}>{$m}</option>";
                }
                ?>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">الباقة:</span>
            <select class="filter-select" id="packageFilter">
                <option value="">الكل</option>
                <?php foreach ($packages as $pkg): ?>
                <option value="<?= $pkg['id'] ?>"><?= $pkg['name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="filter-label">الدفع:</span>
            <select class="filter-select" id="paymentFilter">
                <option value="">الكل</option>
                <option value="paid">مدفوع</option>
                <option value="partial">جزئي</option>
                <option value="unpaid">غير مدفوع</option>
            </select>
        </div>
    </div>
    
    <!-- Pipeline Board -->
    <div class="pipeline-container">
        <div class="pipeline-board" id="pipelineBoard">
            <?php foreach ($stages as $index => $stage): ?>
            <div class="stage-column" data-stage="<?= $stage['key'] ?>">
                <div class="stage-header">
                    <div class="stage-info">
                        <div class="stage-indicator" style="background: <?= $stage['color'] ?>"></div>
                        <span class="stage-name"><?= $stage['name'] ?></span>
                    </div>
                    <span class="stage-count"><?= count($bookings[$stage['key']]) ?></span>
                </div>
                <div class="stage-cards" data-stage="<?= $stage['key'] ?>">
                    <?php if (empty($bookings[$stage['key']])): ?>
                    <div class="empty-state">
                        <i data-lucide="<?= $stage['icon'] ?>"></i>
                        <p>لا توجد حجوزات</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($bookings[$stage['key']] as $booking): ?>
                        <div class="booking-card" 
                             draggable="true" 
                             data-id="<?= $booking['id'] ?>"
                             data-stage="<?= $stage['key'] ?>"
                             style="--card-accent: <?= $stage['color'] ?>">
                            
                            <div class="card-actions">
                                <button class="card-action-btn" title="عرض" onclick="viewBooking(<?= $booking['id'] ?>)">
                                    <i data-lucide="eye"></i>
                                </button>
                                <button class="card-action-btn" title="واتساب" onclick="sendWhatsApp(<?= $booking['id'] ?>, '<?= $booking['phone'] ?>')">
                                    <i data-lucide="message-circle"></i>
                                </button>
                                <button class="card-action-btn" title="تعديل" onclick="editBooking(<?= $booking['id'] ?>)">
                                    <i data-lucide="edit"></i>
                                </button>
                            </div>
                            
                            <div class="card-header">
                                <span class="groom-name">
                                    <i data-lucide="user"></i>
                                    <?= htmlspecialchars($booking['groom_name']) ?>
                                </span>
                                <span class="booking-id">#<?= $booking['id'] ?></span>
                            </div>
                            
                            <div class="card-body">
                                <div class="card-row">
                                    <i data-lucide="calendar"></i>
                                    <span><?= formatDate($booking['wedding_date']) ?></span>
                                </div>
                                <?php if ($booking['venue']): ?>
                                <div class="card-row">
                                    <i data-lucide="map-pin"></i>
                                    <span><?= htmlspecialchars($booking['venue']) ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="card-row">
                                    <i data-lucide="package"></i>
                                    <span><?= htmlspecialchars($booking['package_name'] ?? $booking['package_name_db'] ?? 'غير محدد') ?></span>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <div class="card-badges">
                                    <?php
                                    $paymentClass = 'badge-unpaid';
                                    $paymentIcon = 'alert-circle';
                                    $paymentText = 'غير مدفوع';
                                    
                                    if ($booking['payment_status'] === 'paid') {
                                        $paymentClass = 'badge-paid';
                                        $paymentIcon = 'check-circle';
                                        $paymentText = 'مدفوع';
                                    } elseif ($booking['payment_status'] === 'partial' || $booking['deposit_paid']) {
                                        $paymentClass = 'badge-partial';
                                        $paymentIcon = 'wallet';
                                        $paymentText = 'عربون';
                                    }
                                    ?>
                                    <span class="badge <?= $paymentClass ?>">
                                        <i data-lucide="<?= $paymentIcon ?>"></i>
                                        <?= $paymentText ?>
                                    </span>
                                    
                                    <?php if ($booking['team_count'] > 0): ?>
                                    <span class="badge badge-team">
                                        <i data-lucide="users"></i>
                                        <?= $booking['team_count'] ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <span class="card-price"><?= formatPrice($booking['total_price']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- New Booking Modal -->
    <div class="modal-overlay" id="newBookingModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i data-lucide="plus-circle"></i>
                    حجز جديد
                </h2>
                <button class="modal-close" onclick="closeNewBooking()">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <form id="newBookingForm" onsubmit="submitNewBooking(event)">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">اسم العريس *</label>
                            <input type="text" class="form-input" name="groom_name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الجوال *</label>
                            <input type="tel" class="form-input" name="phone" placeholder="05xxxxxxxx" dir="ltr" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">تاريخ الزواج *</label>
                            <input type="date" class="form-input" name="wedding_date" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">وقت البدء</label>
                            <input type="time" class="form-input" name="wedding_time">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">المدينة</label>
                            <input type="text" class="form-input" name="city" placeholder="المدينة المنورة">
                        </div>
                        <div class="form-group">
                            <label class="form-label">القاعة / المكان</label>
                            <input type="text" class="form-input" name="venue" placeholder="اسم القاعة">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">الباقة *</label>
                            <select class="form-input" name="package_id" required>
                                <option value="">اختر الباقة</option>
                                <?php foreach ($packages as $pkg): ?>
                                <option value="<?= $pkg['id'] ?>" data-price="<?= $pkg['price'] ?>">
                                    <?= $pkg['name'] ?> - <?= formatPrice($pkg['price']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">العربون</label>
                            <input type="number" class="form-input" name="deposit_amount" placeholder="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea class="form-input" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeNewBooking()">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="check"></i>
                        حفظ الحجز
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast -->
    <div class="toast" id="toast"></div>
    
    <script>
        // Initialize Lucide
        lucide.createIcons();
        
        // API Base URL
        const API_URL = '/api/bookings/';
        
        // ═══════════════════════════════════════════════════════════
        // DRAG AND DROP
        // ═══════════════════════════════════════════════════════════
        
        let draggedCard = null;
        
        function initDragAndDrop() {
            document.querySelectorAll('.booking-card').forEach(card => {
                card.addEventListener('dragstart', handleDragStart);
                card.addEventListener('dragend', handleDragEnd);
            });
            
            document.querySelectorAll('.stage-cards').forEach(column => {
                column.addEventListener('dragover', handleDragOver);
                column.addEventListener('drop', handleDrop);
                column.addEventListener('dragleave', handleDragLeave);
            });
        }
        
        function handleDragStart(e) {
            draggedCard = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.id);
        }
        
        function handleDragEnd() {
            this.classList.remove('dragging');
            document.querySelectorAll('.stage-cards').forEach(col => col.classList.remove('drag-over'));
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        }
        
        function handleDragLeave() {
            this.classList.remove('drag-over');
        }
        
        async function handleDrop(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            if (!draggedCard) return;
            
            const bookingId = draggedCard.dataset.id;
            const oldStage = draggedCard.dataset.stage;
            const newStage = this.dataset.stage;
            
            if (oldStage === newStage) return;
            
            // إزالة الحالة الفارغة إن وجدت
            const emptyState = this.querySelector('.empty-state');
            if (emptyState) emptyState.remove();
            
            // نقل البطاقة
            this.appendChild(draggedCard);
            draggedCard.dataset.stage = newStage;
            
            // تحديث اللون
            const stageColors = {
                'new_booking': '#6366f1', 'coordination': '#8b5cf6', 'team_assigned': '#a855f7',
                'guidelines_sent': '#ec4899', 'reminder_sent': '#f97316', 'wedding_day': '#eab308',
                'processing': '#22c55e', 'delivered': '#14b8a6', 'review_requested': '#06b6d4', 'closed': '#64748b'
            };
            draggedCard.style.setProperty('--card-accent', stageColors[newStage]);
            
            // تحديث العدادات
            updateStageCounts();
            
            // إرسال للسيرفر
            try {
                const response = await fetch(API_URL + '?action=update_stage', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        new_stage: newStage
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('تم تحديث المرحلة', 'success');
                    
                    // سؤال عن إرسال واتساب
                    const stageNames = {
                        'coordination': 'طلب التنسيق',
                        'team_assigned': 'إشعار الفريق',
                        'guidelines_sent': 'إرشادات التصوير',
                        'reminder_sent': 'تذكير',
                        'processing': 'بدء المعالجة',
                        'delivered': 'جاهزية الصور',
                        'review_requested': 'طلب التقييم'
                    };
                    
                    if (stageNames[newStage]) {
                        Swal.fire({
                            title: 'إرسال واتساب؟',
                            text: `هل تريد إرسال "${stageNames[newStage]}" للعميل؟`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'نعم، أرسل',
                            cancelButtonText: 'لاحقاً',
                            confirmButtonColor: '#d4af37'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                sendStageWhatsApp(bookingId, newStage);
                            }
                        });
                    }
                } else {
                    showToast('فشل التحديث: ' + result.error, 'error');
                }
            } catch (error) {
                showToast('خطأ في الاتصال', 'error');
                console.error(error);
            }
        }
        
        function updateStageCounts() {
            document.querySelectorAll('.stage-column').forEach(column => {
                const cards = column.querySelectorAll('.booking-card').length;
                column.querySelector('.stage-count').textContent = cards;
                
                const cardsContainer = column.querySelector('.stage-cards');
                if (cards === 0 && !cardsContainer.querySelector('.empty-state')) {
                    cardsContainer.innerHTML = `
                        <div class="empty-state">
                            <i data-lucide="inbox"></i>
                            <p>لا توجد حجوزات</p>
                        </div>
                    `;
                    lucide.createIcons();
                }
            });
        }
        
        // ═══════════════════════════════════════════════════════════
        // MODALS
        // ═══════════════════════════════════════════════════════════
        
        function openNewBooking() {
            document.getElementById('newBookingModal').classList.add('active');
        }
        
        function closeNewBooking() {
            document.getElementById('newBookingModal').classList.remove('active');
            document.getElementById('newBookingForm').reset();
        }
        
        async function submitNewBooking(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch(API_URL + '?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('تم إنشاء الحجز بنجاح', 'success');
                    closeNewBooking();
                    
                    // إعادة تحميل الصفحة لعرض الحجز الجديد
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('فشل: ' + result.error, 'error');
                }
            } catch (error) {
                showToast('خطأ في الاتصال', 'error');
            }
        }
        
        // ═══════════════════════════════════════════════════════════
        // ACTIONS
        // ═══════════════════════════════════════════════════════════
        
        function viewBooking(id) {
            window.location.href = `/admin/bookings/view.php?id=${id}`;
        }
        
        function editBooking(id) {
            window.location.href = `/admin/bookings/edit.php?id=${id}`;
        }
        
        function sendWhatsApp(bookingId, phone) {
            Swal.fire({
                title: 'إرسال رسالة واتساب',
                html: `
                    <select id="templateSelect" class="swal2-select" style="width:100%;padding:10px;margin-top:10px;">
                        <option value="booking_confirmation">تأكيد الحجز</option>
                        <option value="coordination_request">طلب التنسيق</option>
                        <option value="photo_guidelines">إرشادات التصوير</option>
                        <option value="reminder_groom">تذكير</option>
                        <option value="processing_start">بدء المعالجة</option>
                        <option value="grooms_ready">جاهزية الصور</option>
                        <option value="review_request">طلب التقييم</option>
                        <option value="thank_you">شكر وتوديع</option>
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: 'إرسال',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#25d366',
                preConfirm: () => {
                    return document.getElementById('templateSelect').value;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    sendWhatsAppTemplate(bookingId, result.value);
                }
            });
        }
        
        async function sendWhatsAppTemplate(bookingId, templateKey) {
            try {
                const response = await fetch('/api/whatsapp/send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        booking_id: bookingId,
                        template_key: templateKey
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('تم إرسال الرسالة بنجاح ✓', 'success');
                } else {
                    showToast('فشل الإرسال: ' + result.error, 'error');
                }
            } catch (error) {
                showToast('خطأ في الاتصال', 'error');
            }
        }
        
        async function sendStageWhatsApp(bookingId, stage) {
            const stageTemplates = {
                'coordination': 'coordination_request',
                'guidelines_sent': 'photo_guidelines',
                'reminder_sent': 'reminder_groom',
                'processing': 'processing_start',
                'delivered': 'grooms_ready',
                'review_requested': 'review_request'
            };
            
            if (stageTemplates[stage]) {
                await sendWhatsAppTemplate(bookingId, stageTemplates[stage]);
            }
        }
        
        // ═══════════════════════════════════════════════════════════
        // SEARCH & FILTERS
        // ═══════════════════════════════════════════════════════════
        
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            
            document.querySelectorAll('.booking-card').forEach(card => {
                const name = card.querySelector('.groom-name').textContent.toLowerCase();
                const id = card.querySelector('.booking-id').textContent.toLowerCase();
                
                card.style.display = (name.includes(term) || id.includes(term)) ? '' : 'none';
            });
        });
        
        // ═══════════════════════════════════════════════════════════
        // UTILITIES
        // ═══════════════════════════════════════════════════════════
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('active');
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeNewBooking();
            if (e.ctrlKey && e.key === 'n') { e.preventDefault(); openNewBooking(); }
            if (e.ctrlKey && e.key === 'k') { e.preventDefault(); document.getElementById('searchInput').focus(); }
        });
        
        // Initialize
        initDragAndDrop();
    </script>
</body>
</html>
