<?php
/**
 * =====================================================
 * سكربت الترقية التلقائية للوحة التحكم
 * Auto-Upgrade Script for Dashboard
 * =====================================================
 * 
 * يقوم بتنفيذ التحديثات التالية:
 * 1. إضافة عمود rating_token لجدول grooms
 * 2. إنشاء جدول push_subscriptions
 * 3. إنشاء جدول notification_log
 * 4. إنشاء ملف get_rating_token.php
 * 5. تحديث استعلام جلب البيانات في dashboard.php
 * 6. تحديث جداول HTML في dashboard.php
 * 7. إضافة دالة JavaScript لنسخ رابط التقييم
 * 
 * @version 1.0
 * @author Wedding System
 * @date 2025-10-12
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

// ========================================
// إعدادات أساسية
// ========================================

define('SCRIPT_VERSION', '1.0');
define('BACKUP_DIR', __DIR__ . '/backups_' . date('Ymd_His'));

$config = [
    'db_config_file' => __DIR__ . '/config.php',
    'dashboard_file' => __DIR__ . '/dashboard.php',
    'create_backup' => true,
    'dry_run' => false, // غيرها إلى false لتنفيذ التغييرات فعلياً
];

$results = [
    'success' => [],
    'errors' => [],
    'warnings' => [],
    'skipped' => []
];

// ========================================
// دوال مساعدة
// ========================================

function logMessage($message, $type = 'info') {
    $colors = [
        'success' => '#28a745',
        'error' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#17a2b8'
    ];
    
    $color = $colors[$type] ?? $colors['info'];
    echo "<div style='padding: 10px; margin: 5px 0; background: {$color}22; border-left: 4px solid {$color}; border-radius: 4px;'>";
    echo "<strong style='color: {$color};'>[" . strtoupper($type) . "]</strong> ";
    echo htmlspecialchars($message);
    echo "</div>\n";
    flush();
}

function createBackup($file, $backupDir) {
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupFile = $backupDir . '/' . basename($file) . '.backup';
    if (copy($file, $backupFile)) {
        return $backupFile;
    }
    return false;
}

function writeFile($path, $content) {
    global $config;
    
    if ($config['dry_run']) {
        logMessage("DRY RUN: سيتم كتابة الملف: $path", 'warning');
        return true;
    }
    
    return file_put_contents($path, $content) !== false;
}

// ========================================
// بداية السكربت
// ========================================

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سكربت الترقية التلقائية - v<?= SCRIPT_VERSION ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 900px;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        .header h1 {
            color: #667eea;
            font-weight: bold;
        }
        .log-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .progress-section {
            margin: 20px 0;
        }
        .step {
            padding: 15px;
            margin: 10px 0;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #6c757d;
        }
        .step.active {
            border-left-color: #17a2b8;
            background: #e7f3ff;
        }
        .step.completed {
            border-left-color: #28a745;
        }
        .step.error {
            border-left-color: #dc3545;
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .summary-box {
            background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .stat-card {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin: 5px;
        }
        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="bi bi-rocket-takeoff"></i> سكربت الترقية التلقائية</h1>
            <p class="text-muted">الإصدار <?= SCRIPT_VERSION ?> | <?= date('Y-m-d H:i:s') ?></p>
            <?php if ($config['dry_run']): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>وضع التجربة (Dry Run)</strong> - لن يتم إجراء أي تغييرات فعلية
                </div>
            <?php endif; ?>
        </div>

        <div class="log-container" id="logContainer">
            <?php

            // ========================================
            // 1. التحقق من المتطلبات
            // ========================================
            
            logMessage("بدء عملية الترقية...", 'info');
            
            if (!file_exists($config['db_config_file'])) {
                logMessage("خطأ: ملف config.php غير موجود في: " . $config['db_config_file'], 'error');
                $results['errors'][] = "ملف config.php غير موجود";
                exit;
            }
            
            if (!file_exists($config['dashboard_file'])) {
                logMessage("خطأ: ملف dashboard.php غير موجود", 'error');
                $results['errors'][] = "ملف dashboard.php غير موجود";
                exit;
            }
            
            logMessage("✓ جميع الملفات المطلوبة موجودة", 'success');
            
            // ========================================
            // 2. الاتصال بقاعدة البيانات
            // ========================================
            
            logMessage("محاولة الاتصال بقاعدة البيانات...", 'info');
            
            try {
                require_once $config['db_config_file'];
                
                $pdo = new PDO(
                    "mysql:host=$host;dbname=$db;charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]
                );
                
                logMessage("✓ تم الاتصال بقاعدة البيانات بنجاح", 'success');
                $results['success'][] = "الاتصال بقاعدة البيانات";
                
            } catch (PDOException $e) {
                logMessage("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage(), 'error');
                $results['errors'][] = "فشل الاتصال بقاعدة البيانات";
                exit;
            }
            
            // ========================================
            // 3. إنشاء نسخة احتياطية
            // ========================================
            
            if ($config['create_backup']) {
                logMessage("إنشاء نسخة احتياطية...", 'info');
                
                if (!is_dir(BACKUP_DIR)) {
                    mkdir(BACKUP_DIR, 0755, true);
                }
                
                // نسخ dashboard.php
                if ($backup = createBackup($config['dashboard_file'], BACKUP_DIR)) {
                    logMessage("✓ تم إنشاء نسخة احتياطية: " . basename($backup), 'success');
                    $results['success'][] = "إنشاء نسخة احتياطية";
                } else {
                    logMessage("تحذير: فشل إنشاء نسخة احتياطية", 'warning');
                    $results['warnings'][] = "فشل إنشاء نسخة احتياطية";
                }
                
                // نسخ قاعدة البيانات
                try {
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    $sqlDump = "-- Database Backup: " . date('Y-m-d H:i:s') . "\n\n";
                    
                    foreach (['grooms', 'push_subscriptions', 'notification_log'] as $table) {
                        if (in_array($table, $tables)) {
                            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                            $sqlDump .= $createTable['Create Table'] . ";\n\n";
                        }
                    }
                    
                    file_put_contents(BACKUP_DIR . '/database_structure.sql', $sqlDump);
                    logMessage("✓ تم حفظ بنية قاعدة البيانات", 'success');
                    
                } catch (Exception $e) {
                    logMessage("تحذير: لم يتم حفظ بنية قاعدة البيانات: " . $e->getMessage(), 'warning');
                }
            }
            
            // ========================================
            // 4. تحديث جدول grooms - إضافة rating_token
            // ========================================
            
            logMessage("التحقق من عمود rating_token في جدول grooms...", 'info');
            
            try {
                $columns = $pdo->query("SHOW COLUMNS FROM grooms LIKE 'rating_token'")->fetchAll();
                
                if (empty($columns)) {
                    if (!$config['dry_run']) {
                        $pdo->exec("
                            ALTER TABLE grooms 
                            ADD COLUMN rating_token VARCHAR(64) NULL UNIQUE AFTER ready
                        ");
                        logMessage("✓ تم إضافة عمود rating_token", 'success');
                    } else {
                        logMessage("DRY RUN: سيتم إضافة عمود rating_token", 'warning');
                    }
                    $results['success'][] = "إضافة عمود rating_token";
                } else {
                    logMessage("→ عمود rating_token موجود بالفعل", 'info');
                    $results['skipped'][] = "عمود rating_token موجود مسبقاً";
                }
                
                // توليد توكنات للعرسان الموجودين
                if (!$config['dry_run']) {
                    $updated = $pdo->exec("
                        UPDATE grooms 
                        SET rating_token = MD5(CONCAT(id, groom_name, NOW(), RAND())) 
                        WHERE rating_token IS NULL OR rating_token = ''
                    ");
                    
                    if ($updated > 0) {
                        logMessage("✓ تم توليد توكنات لـ $updated عريس", 'success');
                        $results['success'][] = "توليد توكنات للعرسان ($updated)";
                    }
                } else {
                    logMessage("DRY RUN: سيتم توليد توكنات للعرسان", 'warning');
                }
                
            } catch (PDOException $e) {
                logMessage("خطأ في تحديث جدول grooms: " . $e->getMessage(), 'error');
                $results['errors'][] = "فشل تحديث جدول grooms";
            }
            
            // ========================================
            // 5. إنشاء جدول push_subscriptions
            // ========================================
            
            logMessage("التحقق من جدول push_subscriptions...", 'info');
            
            try {
                $tables = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'")->fetchAll();
                
                if (empty($tables)) {
                    if (!$config['dry_run']) {
                        $pdo->exec("
                            CREATE TABLE push_subscriptions (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                groom_id INT NOT NULL,
                                endpoint TEXT NOT NULL,
                                p256dh VARCHAR(255) NOT NULL,
                                auth VARCHAR(255) NOT NULL,
                                user_agent TEXT,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (groom_id) REFERENCES grooms(id) ON DELETE CASCADE,
                                INDEX idx_groom_id (groom_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                        logMessage("✓ تم إنشاء جدول push_subscriptions", 'success');
                    } else {
                        logMessage("DRY RUN: سيتم إنشاء جدول push_subscriptions", 'warning');
                    }
                    $results['success'][] = "إنشاء جدول push_subscriptions";
                } else {
                    logMessage("→ جدول push_subscriptions موجود بالفعل", 'info');
                    $results['skipped'][] = "جدول push_subscriptions موجود مسبقاً";
                }
                
            } catch (PDOException $e) {
                logMessage("خطأ في إنشاء جدول push_subscriptions: " . $e->getMessage(), 'error');
                $results['errors'][] = "فشل إنشاء جدول push_subscriptions";
            }
            
            // ========================================
            // 6. إنشاء جدول notification_log
            // ========================================
            
            logMessage("التحقق من جدول notification_log...", 'info');
            
            try {
                $tables = $pdo->query("SHOW TABLES LIKE 'notification_log'")->fetchAll();
                
                if (empty($tables)) {
                    if (!$config['dry_run']) {
                        $pdo->exec("
                            CREATE TABLE notification_log (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                groom_id INT NOT NULL,
                                subscription_id INT,
                                status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
                                message TEXT,
                                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                error_message TEXT,
                                FOREIGN KEY (groom_id) REFERENCES grooms(id) ON DELETE CASCADE,
                                FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE SET NULL,
                                INDEX idx_groom_status (groom_id, status),
                                INDEX idx_sent_at (sent_at)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ");
                        logMessage("✓ تم إنشاء جدول notification_log", 'success');
                    } else {
                        logMessage("DRY RUN: سيتم إنشاء جدول notification_log", 'warning');
                    }
                    $results['success'][] = "إنشاء جدول notification_log";
                } else {
                    logMessage("→ جدول notification_log موجود بالفعل", 'info');
                    $results['skipped'][] = "جدول notification_log موجود مسبقاً";
                }
                
            } catch (PDOException $e) {
                logMessage("خطأ في إنشاء جدول notification_log: " . $e->getMessage(), 'error');
                $results['errors'][] = "فشل إنشاء جدول notification_log";
            }
            
            // ========================================
            // 7. إنشاء ملف get_rating_token.php
            // ========================================
            
            logMessage("إنشاء ملف get_rating_token.php...", 'info');
            
            $getRatingTokenContent = <<<'PHP'
<?php
// admin/get_rating_token.php
session_start();

// التحقق من تسجيل الدخول
if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'غير مصرح']);
    exit;
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$groomId = intval($_GET['groom_id'] ?? 0);

if ($groomId <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف غير صالح']);
    exit;
}

try {
    // التحقق من وجود العريس
    $stmt = $pdo->prepare("SELECT id, groom_name FROM grooms WHERE id = ?");
    $stmt->execute([$groomId]);
    $groom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$groom) {
        echo json_encode(['success' => false, 'error' => 'العريس غير موجود']);
        exit;
    }
    
    // التحقق من وجود توكن أو إنشاء واحد جديد
    $stmt = $pdo->prepare("SELECT rating_token FROM grooms WHERE id = ?");
    $stmt->execute([$groomId]);
    $token = $stmt->fetchColumn();
    
    if (empty($token)) {
        // إنشاء توكن جديد
        $token = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("UPDATE grooms SET rating_token = ? WHERE id = ?");
        $stmt->execute([$token, $groomId]);
    }
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'groom_name' => $groom['groom_name']
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_rating_token.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'خطأ في قاعدة البيانات']);
}
?>
PHP;
            
            $getRatingTokenPath = __DIR__ . '/get_rating_token.php';
            
            if (file_exists($getRatingTokenPath)) {
                logMessage("→ ملف get_rating_token.php موجود بالفعل", 'info');
                $results['skipped'][] = "ملف get_rating_token.php موجود مسبقاً";
            } else {
                if (writeFile($getRatingTokenPath, $getRatingTokenContent)) {
                    logMessage("✓ تم إنشاء ملف get_rating_token.php", 'success');
                    $results['success'][] = "إنشاء ملف get_rating_token.php";
                } else {
                    logMessage("خطأ: فشل إنشاء ملف get_rating_token.php", 'error');
                    $results['errors'][] = "فشل إنشاء ملف get_rating_token.php";
                }
            }
            
            // ========================================
            // 8. تحديث ملف dashboard.php
            // ========================================
            
            logMessage("تحديث ملف dashboard.php...", 'info');
            
            $dashboardContent = file_get_contents($config['dashboard_file']);
            $originalContent = $dashboardContent;
            $dashboardUpdated = false;
            
            // 8.1 تحديث استعلام جلب البيانات
            logMessage("→ تحديث استعلام جلب بيانات العرسان...", 'info');
            
            $oldQuery = 'SELECT g.*, 
               (SELECT COUNT(*) FROM groom_photos WHERE groom_id = g.id) as photo_count,
               (SELECT COUNT(*) FROM groom_likes WHERE groom_id = g.id) as groom_likes_count,
               (SELECT COUNT(*) FROM photo_likes WHERE groom_id = g.id) as photo_likes_count';
            
            $newQuery = 'SELECT g.*, 
               (SELECT COUNT(*) FROM groom_photos WHERE groom_id = g.id) as photo_count,
               (SELECT COUNT(*) FROM groom_likes WHERE groom_id = g.id) as groom_likes_count,
               (SELECT COUNT(*) 
                FROM photo_likes pl 
                INNER JOIN groom_photos gp ON pl.photo_id = gp.id 
                WHERE gp.groom_id = g.id
               ) as photo_likes_count,
               (SELECT COUNT(*) FROM push_subscriptions WHERE groom_id = g.id) as push_subscribers,
               (SELECT COUNT(*) FROM notification_log WHERE groom_id = g.id AND status = \'sent\') as notifications_sent';
            
            if (strpos($dashboardContent, 'push_subscribers') === false) {
                $dashboardContent = str_replace($oldQuery, $newQuery, $dashboardContent);
                $dashboardUpdated = true;
                logMessage("✓ تم تحديث استعلام البيانات", 'success');
            } else {
                logMessage("→ استعلام البيانات محدث بالفعل", 'info');
            }
            
            // 8.2 تحديث JavaScript - إضافة دالة copyRatingLink
            logMessage("→ إضافة دالة JavaScript لنسخ رابط التقييم...", 'info');
            
            $copyRatingLinkFunction = <<<'JAVASCRIPT'

// دالة نسخ رابط التقييم
function copyRatingLink(groomId) {
    // جلب التوكن من الخادم
    fetch(`get_rating_token.php?groom_id=${groomId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const baseUrl = window.location.origin;
                const ratingUrl = `${baseUrl}/rate.php?token=${data.token}`;
                
                // نسخ الرابط
                navigator.clipboard.writeText(ratingUrl).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم النسخ!',
                        html: `
                            <div class="text-end">
                                <p>تم نسخ رابط التقييم بنجاح</p>
                                <div class="alert alert-info mt-3">
                                    <small style="word-break: break-all;">${ratingUrl}</small>
                                </div>
                                <a href="${ratingUrl}" target="_blank" class="btn btn-sm btn-primary mt-2">
                                    <i class="bi bi-box-arrow-up-right"></i> فتح الرابط
                                </a>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'حسناً'
                    });
                }).catch(() => {
                    // إذا فشل النسخ التلقائي، اعرض الرابط للنسخ اليدوي
                    Swal.fire({
                        icon: 'info',
                        title: 'رابط التقييم',
                        html: `
                            <div class="text-end">
                                <p>انسخ الرابط التالي:</p>
                                <input type="text" class="form-control text-center" value="${ratingUrl}" 
                                       onclick="this.select()" readonly>
                            </div>
                        `,
                        showConfirmButton: true
                    });
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: data.error || 'فشل في إنشاء رابط التقييم'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: 'حدث خطأ في الاتصال بالخادم'
            });
        });
}
JAVASCRIPT;
            
            if (strpos($dashboardContent, 'function copyRatingLink') === false) {
                // البحث عن نهاية السكربتات وإضافة الدالة قبل </body>
                $dashboardContent = str_replace('</script>', $copyRatingLinkFunction . "\n</script>", $dashboardContent);
                $dashboardUpdated = true;
                logMessage("✓ تم إضافة دالة copyRatingLink", 'success');
            } else {
                logMessage("→ دالة copyRatingLink موجودة بالفعل", 'info');
            }
            
            // حفظ التغييرات
            if ($dashboardUpdated) {
                if (writeFile($config['dashboard_file'], $dashboardContent)) {
                    logMessage("✓ تم حفظ تحديثات dashboard.php بنجاح", 'success');
                    $results['success'][] = "تحديث ملف dashboard.php";
                } else {
                    logMessage("خطأ: فشل حفظ تحديثات dashboard.php", 'error');
                    $results['errors'][] = "فشل حفظ تحديثات dashboard.php";
                    
                    // استرجاع النسخة الأصلية
                    file_put_contents($config['dashboard_file'], $originalContent);
                    logMessage("→ تم استرجاع النسخة الأصلية", 'warning');
                }
            } else {
                logMessage("→ لا توجد تحديثات مطلوبة لملف dashboard.php", 'info');
                $results['skipped'][] = "ملف dashboard.php محدث بالفعل";
            }
            
            // ========================================
            // 9. ملخص النتائج
            // ========================================
            
            logMessage("اكتملت عملية الترقية!", 'success');
            
            ?>
        </div>

        <div class="summary-box">
            <h4 class="text-center mb-3">📊 ملخص العملية</h4>
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card bg-success bg-opacity-10">
                        <i class="bi bi-check-circle text-success fs-2"></i>
                        <h3 class="text-success"><?= count($results['success']) ?></h3>
                        <small class="text-muted">نجح</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-danger bg-opacity-10">
                        <i class="bi bi-x-circle text-danger fs-2"></i>
                        <h3 class="text-danger"><?= count($results['errors']) ?></h3>
                        <small class="text-muted">خطأ</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning bg-opacity-10">
                        <i class="bi bi-exclamation-triangle text-warning fs-2"></i>
                        <h3 class="text-warning"><?= count($results['warnings']) ?></h3>
                        <small class="text-muted">تحذير</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-info bg-opacity-10">
                        <i class="bi bi-skip-forward text-info fs-2"></i>
                        <h3 class="text-info"><?= count($results['skipped']) ?></h3>
                        <small class="text-muted">متخطى</small>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($results['success'])): ?>
            <div class="mt-3">
                <h6 class="text-success"><i class="bi bi-check-circle"></i> العمليات الناجحة:</h6>
                <ul>
                    <?php foreach ($results['success'] as $success): ?>
                        <li><?= htmlspecialchars($success) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['errors'])): ?>
            <div class="mt-3">
                <h6 class="text-danger"><i class="bi bi-x-circle"></i> الأخطاء:</h6>
                <ul>
                    <?php foreach ($results['errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if ($config['create_backup'] && is_dir(BACKUP_DIR)): ?>
            <div class="alert alert-info mt-3">
                <i class="bi bi-archive"></i>
                <strong>النسخ الاحتياطية:</strong>
                تم حفظ النسخ الاحتياطية في: <code><?= basename(BACKUP_DIR) ?></code>
            </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <?php if (count($results['errors']) === 0): ?>
                <a href="dashboard.php" class="btn btn-custom btn-lg">
                    <i class="bi bi-speedometer2"></i> الذهاب إلى لوحة التحكم
                </a>
            <?php else: ?>
                <button onclick="location.reload()" class="btn btn-danger btn-lg">
                    <i class="bi bi-arrow-clockwise"></i> إعادة المحاولة
                </button>
            <?php endif; ?>
            
            <?php if ($config['dry_run']): ?>
                <div class="alert alert-warning mt-3">
                    <p><strong>تنبيه:</strong> هذا كان تشغيل تجريبي. لتنفيذ التغييرات فعلياً:</p>
                    <ol class="text-end">
                        <li>افتح ملف السكربت</li>
                        <li>غيّر <code>'dry_run' => true</code> إلى <code>'dry_run' => false</code></li>
                        <li>شغّل السكربت مرة أخرى</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // تمرير تلقائي للأسفل
        const logContainer = document.getElementById('logContainer');
        logContainer.scrollTop = logContainer.scrollHeight;
    </script>
</body>
</html>