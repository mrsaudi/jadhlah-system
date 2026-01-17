<?php
/**
 * ============================================
 * تقويم الحجوزات - جذلة
 * ============================================
 * 
 * الملف: admin/bookings/calendar.php
 */

define('JADHLAH_APP', true);
session_start();

// الاتصال بقاعدة البيانات
$host = 'localhost';
$db   = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('خطأ في الاتصال');
}

// الشهر والسنة الحاليين
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// التأكد من صحة القيم
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

// جلب الحجوزات للشهر
$startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$endDate = date('Y-m-t', strtotime($startDate));

// جلب من جدول bookings
$stmt = $pdo->prepare("
    SELECT 
        b.*,
        p.name as package_name_db,
        p.color as package_color
    FROM bookings b
    LEFT JOIN packages p ON b.package_id = p.id
    WHERE b.wedding_date BETWEEN ? AND ?
    ORDER BY b.wedding_date, b.wedding_time
");
$stmt->execute([$startDate, $endDate]);
$bookingsFromBookings = $stmt->fetchAll();

// جلب من pending_grooms أيضاً (للحجوزات غير المزامنة)
$stmt2 = $pdo->prepare("
    SELECT 
        id, groom_name, phone, booking_date as wedding_date, 
        location as venue, package as package_name,
        time_slot, paid_amount, total_amount, groom_id
    FROM pending_grooms
    WHERE booking_date BETWEEN ? AND ?
    AND is_deleted = 0
    AND groom_name IS NOT NULL
    ORDER BY booking_date
");
$stmt2->execute([$startDate, $endDate]);
$bookingsFromPending = $stmt2->fetchAll();

// دمج وإزالة التكرار
$allBookings = [];
$seenPhones = [];

foreach ($bookingsFromBookings as $b) {
    $key = $b['phone'] . '_' . $b['wedding_date'];
    if (!isset($seenPhones[$key])) {
        $b['source'] = 'bookings';
        $allBookings[] = $b;
        $seenPhones[$key] = true;
    }
}

foreach ($bookingsFromPending as $b) {
    $key = $b['phone'] . '_' . $b['wedding_date'];
    if (!isset($seenPhones[$key])) {
        $b['source'] = 'pending';
        $b['stage'] = $b['groom_id'] ? 'delivered' : 'new_booking';
        $allBookings[] = $b;
        $seenPhones[$key] = true;
    }
}

// تجميع الحجوزات حسب اليوم
$bookingsByDay = [];
foreach ($allBookings as $booking) {
    $day = (int)date('j', strtotime($booking['wedding_date']));
    if (!isset($bookingsByDay[$day])) {
        $bookingsByDay[$day] = [];
    }
    $bookingsByDay[$day][] = $booking;
}

// أسماء الأشهر العربية
$arabicMonths = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];

$arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

// معلومات الشهر
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDayOfMonth);
$startDayOfWeek = (int)date('w', $firstDayOfMonth);
$today = (int)date('j');
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

// ألوان المراحل
$stageColors = [
    'new_booking' => '#6366f1',
    'coordination' => '#8b5cf6',
    'team_assigned' => '#a855f7',
    'guidelines_sent' => '#ec4899',
    'reminder_sent' => '#f97316',
    'wedding_day' => '#eab308',
    'processing' => '#22c55e',
    'delivered' => '#14b8a6',
    'review_requested' => '#06b6d4',
    'closed' => '#64748b',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقويم الحجوزات | جذلة</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
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
            --border-color: rgba(255,255,255,0.08);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }
        
        .header {
            background: var(--bg-secondary);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-gold), #f4d03f);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .month-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: var(--bg-card);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .nav-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-hover);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .nav-btn:hover {
            background: var(--accent-gold);
            color: var(--bg-primary);
        }
        
        .current-month {
            font-size: 1.25rem;
            font-weight: 700;
            min-width: 180px;
            text-align: center;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
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
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-gold), #b8960c);
            color: var(--bg-primary);
        }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        /* التقويم */
        .calendar-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .calendar {
            background: var(--bg-secondary);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
        }
        
        .day-name {
            padding: 1rem;
            text-align: center;
            font-weight: 700;
            color: var(--accent-gold);
            font-size: 0.9rem;
        }
        
        .day-name.weekend {
            color: #ef4444;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
        }
        
        .calendar-day {
            min-height: 140px;
            border: 1px solid var(--border-color);
            padding: 0.5rem;
            position: relative;
            transition: all 0.2s;
        }
        
        .calendar-day:hover {
            background: var(--bg-hover);
        }
        
        .calendar-day.empty {
            background: rgba(0,0,0,0.2);
        }
        
        .calendar-day.today {
            background: rgba(212, 175, 55, 0.1);
            border-color: var(--accent-gold);
        }
        
        .calendar-day.has-events {
            background: rgba(99, 102, 241, 0.05);
        }
        
        .day-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        
        .calendar-day.today .day-number {
            background: var(--accent-gold);
            color: var(--bg-primary);
        }
        
        .calendar-day.weekend .day-number {
            color: #ef4444;
        }
        
        .day-events {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            max-height: 100px;
            overflow-y: auto;
        }
        
        .day-events::-webkit-scrollbar {
            width: 4px;
        }
        
        .day-events::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 2px;
        }
        
        .event-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.5rem;
            background: var(--bg-card);
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            border-right: 3px solid var(--event-color, var(--accent-gold));
        }
        
        .event-item:hover {
            background: var(--bg-hover);
            transform: translateX(-2px);
        }
        
        .event-item .name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 600;
        }
        
        .event-item .time {
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        
        .event-item.delivered {
            opacity: 0.6;
        }
        
        .event-count {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            background: var(--accent-gold);
            color: var(--bg-primary);
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
        }
        
        /* الإحصائيات */
        .stats-bar {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem 2rem;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon.gold { background: rgba(212, 175, 55, 0.15); color: var(--accent-gold); }
        .stat-icon.green { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .stat-icon.blue { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .stat-icon.orange { background: rgba(249, 115, 22, 0.15); color: #f97316; }
        
        .stat-info .value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .stat-info .label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: var(--bg-secondary);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow: hidden;
        }
        
        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .modal-close {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-card);
            border: none;
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
        }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(80vh - 70px);
        }
        
        .booking-detail {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            align-items: flex-start;
        }
        
        .booking-detail i {
            width: 20px;
            color: var(--accent-gold);
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .booking-detail .label {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .booking-detail .value {
            font-weight: 600;
        }
        
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .modal-actions .btn {
            flex: 1;
            justify-content: center;
        }
        
        /* Legend */
        .legend {
            display: flex;
            gap: 1.5rem;
            padding: 1rem 2rem;
            background: var(--bg-card);
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        @media (max-width: 768px) {
            .calendar-day {
                min-height: 80px;
                padding: 0.25rem;
            }
            
            .day-number {
                width: 24px;
                height: 24px;
                font-size: 0.8rem;
            }
            
            .event-item {
                padding: 0.25rem;
                font-size: 0.65rem;
            }
            
            .event-item .time {
                display: none;
            }
            
            .header {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-title">
            <a href="pipeline.php" class="nav-btn">
                <i data-lucide="arrow-right"></i>
            </a>
            <h1><i data-lucide="calendar"></i> تقويم الحجوزات</h1>
        </div>
        
        <div class="nav-controls">
            <div class="month-nav">
                <a href="?month=<?= $month - 1 ?>&year=<?= $year ?>" class="nav-btn">
                    <i data-lucide="chevron-right"></i>
                </a>
                <span class="current-month"><?= $arabicMonths[$month] ?> <?= $year ?></span>
                <a href="?month=<?= $month + 1 ?>&year=<?= $year ?>" class="nav-btn">
                    <i data-lucide="chevron-left"></i>
                </a>
            </div>
            
            <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-secondary">
                <i data-lucide="calendar-check"></i>
                اليوم
            </a>
        </div>
        
        <div class="header-actions">
            <a href="pipeline.php" class="btn btn-secondary">
                <i data-lucide="kanban"></i>
                Pipeline
            </a>
            <button class="btn btn-primary" onclick="syncBookings()">
                <i data-lucide="refresh-cw"></i>
                مزامنة
            </button>
        </div>
    </header>
    
    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-icon gold">
                <i data-lucide="calendar-days"></i>
            </div>
            <div class="stat-info">
                <div class="value"><?= count($allBookings) ?></div>
                <div class="label">حجوزات الشهر</div>
            </div>
        </div>
        
        <?php
        $upcoming = array_filter($allBookings, function($b) {
            return strtotime($b['wedding_date']) >= strtotime('today');
        });
        $completed = array_filter($allBookings, function($b) {
            return ($b['stage'] ?? '') === 'delivered' || !empty($b['groom_id']);
        });
        ?>
        
        <div class="stat-item">
            <div class="stat-icon blue">
                <i data-lucide="clock"></i>
            </div>
            <div class="stat-info">
                <div class="value"><?= count($upcoming) ?></div>
                <div class="label">قادمة</div>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon green">
                <i data-lucide="check-circle"></i>
            </div>
            <div class="stat-info">
                <div class="value"><?= count($completed) ?></div>
                <div class="label">مكتملة</div>
            </div>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="legend">
        <div class="legend-item">
            <div class="legend-color" style="background: #6366f1;"></div>
            <span>حجز جديد</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #f97316;"></div>
            <span>تذكير</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #eab308;"></div>
            <span>يوم الزفاف</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #22c55e;"></div>
            <span>معالجة</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background: #14b8a6;"></div>
            <span>تم التسليم</span>
        </div>
    </div>
    
    <!-- Calendar -->
    <div class="calendar-container">
        <div class="calendar">
            <div class="calendar-header">
                <?php foreach ($arabicDays as $index => $dayName): ?>
                <div class="day-name <?= ($index == 5 || $index == 6) ? 'weekend' : '' ?>">
                    <?= $dayName ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="calendar-grid">
                <?php
                // أيام فارغة في البداية
                for ($i = 0; $i < $startDayOfWeek; $i++):
                ?>
                <div class="calendar-day empty"></div>
                <?php endfor; ?>
                
                <?php
                // أيام الشهر
                for ($day = 1; $day <= $daysInMonth; $day++):
                    $dayOfWeek = ($startDayOfWeek + $day - 1) % 7;
                    $isToday = ($day == $today && $month == $currentMonth && $year == $currentYear);
                    $isWeekend = ($dayOfWeek == 5 || $dayOfWeek == 6);
                    $hasEvents = isset($bookingsByDay[$day]) && count($bookingsByDay[$day]) > 0;
                    $events = $bookingsByDay[$day] ?? [];
                ?>
                <div class="calendar-day <?= $isToday ? 'today' : '' ?> <?= $isWeekend ? 'weekend' : '' ?> <?= $hasEvents ? 'has-events' : '' ?>">
                    <div class="day-number"><?= $day ?></div>
                    
                    <?php if (count($events) > 0): ?>
                    <div class="event-count"><?= count($events) ?></div>
                    <?php endif; ?>
                    
                    <div class="day-events">
                        <?php foreach (array_slice($events, 0, 3) as $event): 
                            $stage = $event['stage'] ?? 'new_booking';
                            $color = $stageColors[$stage] ?? '#d4af37';
                            $isDelivered = ($stage === 'delivered' || !empty($event['groom_id']));
                        ?>
                        <div class="event-item <?= $isDelivered ? 'delivered' : '' ?>" 
                             style="--event-color: <?= $color ?>"
                             onclick='showBookingDetails(<?= json_encode($event, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <span class="name"><?= htmlspecialchars($event['groom_name']) ?></span>
                            <span class="time">7م</span>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($events) > 3): ?>
                        <div class="event-item" style="--event-color: #666; text-align: center;">
                            +<?= count($events) - 3 ?> المزيد
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
                
                <?php
                // أيام فارغة في النهاية
                $remainingDays = 7 - (($startDayOfWeek + $daysInMonth) % 7);
                if ($remainingDays < 7):
                    for ($i = 0; $i < $remainingDays; $i++):
                ?>
                <div class="calendar-day empty"></div>
                <?php 
                    endfor;
                endif;
                ?>
            </div>
        </div>
    </div>
    
    <!-- Modal -->
    <div class="modal-overlay" id="bookingModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">تفاصيل الحجز</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- سيتم ملؤه بـ JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
        
        function showBookingDetails(booking) {
            const modal = document.getElementById('bookingModal');
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            
            title.textContent = booking.groom_name;
            
            const date = new Date(booking.wedding_date);
            const formattedDate = date.toLocaleDateString('ar-SA', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            body.innerHTML = `
                <div class="booking-detail">
                    <i data-lucide="calendar"></i>
                    <div>
                        <div class="label">التاريخ</div>
                        <div class="value">${formattedDate}</div>
                    </div>
                </div>
                
                <div class="booking-detail">
                    <i data-lucide="clock"></i>
                    <div>
                        <div class="label">الوقت</div>
                        <div class="value">${booking.time_slot || '7:00 م - 12:00 ص'}</div>
                    </div>
                </div>
                
                <div class="booking-detail">
                    <i data-lucide="map-pin"></i>
                    <div>
                        <div class="label">المكان</div>
                        <div class="value">${booking.venue || 'غير محدد'}</div>
                    </div>
                </div>
                
                <div class="booking-detail">
                    <i data-lucide="phone"></i>
                    <div>
                        <div class="label">الهاتف</div>
                        <div class="value" dir="ltr">${booking.phone || '-'}</div>
                    </div>
                </div>
                
                <div class="booking-detail">
                    <i data-lucide="package"></i>
                    <div>
                        <div class="label">الباقة</div>
                        <div class="value">${booking.package_name || booking.package || 'غير محدد'}</div>
                    </div>
                </div>
                
                ${booking.total_amount ? `
                <div class="booking-detail">
                    <i data-lucide="wallet"></i>
                    <div>
                        <div class="label">المبلغ</div>
                        <div class="value">${Number(booking.total_amount || booking.total_price || 0).toLocaleString()} ريال</div>
                    </div>
                </div>
                ` : ''}
                
                <div class="modal-actions">
                    ${booking.phone ? `
                    <a href="https://wa.me/${booking.phone.replace(/[^0-9]/g, '')}" target="_blank" class="btn btn-secondary" style="background: #25d366; border: none;">
                        <i data-lucide="message-circle"></i>
                        واتساب
                    </a>
                    ` : ''}
                    <a href="pipeline.php" class="btn btn-primary">
                        <i data-lucide="kanban"></i>
                        Pipeline
                    </a>
                </div>
            `;
            
            lucide.createIcons();
            modal.classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }
        
        document.getElementById('bookingModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
        
        async function syncBookings() {
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin"></i> جاري المزامنة...';
            btn.disabled = true;
            
            try {
                const response = await fetch('../tools/sync_to_bookings.php');
                const data = await response.json();
                
                if (data.success) {
                    alert(`تمت المزامنة!\n${data.message}`);
                    location.reload();
                } else {
                    alert('خطأ: ' + data.error);
                }
            } catch (error) {
                alert('خطأ في الاتصال');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
