<?php
// admin/update_page_status_clean.php - معالج نظيف لجميع إجراءات الصفحات
session_start();
header('Content-Type: application/json; charset=utf-8');

// تسجيل الأخطاء للتشخيص
ini_set('display_errors', 1);
error_reporting(E_ALL);

// دالة تسجيل الأخطاء الخاصة بهذا الملف
function writeStatusLog($message, $file = 'status_update') {
    $logFile = __DIR__ . '/logs/' . $file . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['user'] ?? 'Unknown';
    $logEntry = "[$timestamp] [$user] $message\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// دالة حذف المجلد الخاصة بهذا الملف
function removeDirectoryRecursive($dir) {
    if (!is_dir($dir)) return false;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            removeDirectoryRecursive($path);
        } else {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    writeStatusLog("محاولة وصول غير مصرح من IP: " . $_SERVER['REMOTE_ADDR']);
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح - يرجى تسجيل الدخول']));
}

// الاتصال بقاعدة البيانات مباشرة
$host = 'localhost';
$dbname = 'u709146392_jadhlah_db';
$username = 'u709146392_jad_admin';
$password = '1245@vmP';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    writeStatusLog("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'خطأ في الاتصال بقاعدة البيانات']));
}

// التحقق من المعاملات المطلوبة
if (!isset($_POST['id']) || !isset($_POST['action'])) {
    writeStatusLog("معاملات ناقصة: " . json_encode($_POST));
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'معاملات ناقصة']));
}

$id = (int)$_POST['id'];
$action = $_POST['action'];
$response = ['success' => false, 'message' => ''];

// السماح بـ action=test للاختبار
if ($action === 'test') {
    die(json_encode(['success' => true, 'message' => 'Test successful']));
}

// التحقق من صحة المعرف
if ($id <= 0) {
    writeStatusLog("معرف غير صالح: $id");
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'معرف غير صالح']));
}

// تسجيل بداية العملية
writeStatusLog("بدء تنفيذ الإجراء '$action' على العنصر #$id");

try {
    switch ($action) {
        case 'toggle_ready':
            $ready = isset($_POST['ready']) ? (int)$_POST['ready'] : 0;
            
            if ($ready === 1) {
                $stmt = $pdo->prepare("
                    UPDATE grooms 
                    SET ready = 1, 
                        ready_at = CASE 
                            WHEN ready_at IS NULL THEN NOW() 
                            ELSE ready_at 
                        END 
                    WHERE id = ?
                ");
            } else {
                $stmt = $pdo->prepare("UPDATE grooms SET ready = 0 WHERE id = ?");
            }
            
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = $ready ? 'تم تحديد الصفحة كجاهزة' : 'تم إلغاء حالة الجاهزية';
                writeStatusLog("نجح: تحديث حالة الجاهزية للعريس #$id إلى $ready");
            } else {
                throw new Exception('لم يتم العثور على الصفحة أو لم يحدث تغيير');
            }
            break;
            
        case 'block':
            $stmt = $pdo->prepare("UPDATE grooms SET is_blocked = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'تم حجب الصفحة بنجاح';
                writeStatusLog("نجح: حجب الصفحة #$id");
            } else {
                throw new Exception('لم يتم العثور على الصفحة');
            }
            break;
            
        case 'unblock':
            $stmt = $pdo->prepare("UPDATE grooms SET is_blocked = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'تم إلغاء حجب الصفحة بنجاح';
                writeStatusLog("نجح: إلغاء حجب الصفحة #$id");
            } else {
                throw new Exception('لم يتم العثور على الصفحة');
            }
            break;
            
        case 'activate':
            $stmt = $pdo->prepare("UPDATE grooms SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'تم تفعيل الصفحة بنجاح';
                writeStatusLog("نجح: تفعيل الصفحة #$id");
            } else {
                throw new Exception('لم يتم العثور على الصفحة');
            }
            break;
            
        case 'deactivate':
            $stmt = $pdo->prepare("UPDATE grooms SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'تم تعطيل الصفحة بنجاح';
                writeStatusLog("نجح: تعطيل الصفحة #$id");
            } else {
                throw new Exception('لم يتم العثور على الصفحة');
            }
            break;
            
        case 'delete':
            // التحقق من الصلاحية
            $role = $_SESSION['role'] ?? 'employ';
            $canDelete = in_array($role, ['manager', 'work']);
            
            if (!$canDelete) {
                throw new Exception('ليس لديك صلاحية الحذف');
            }
            
            // بدء المعاملة
            $pdo->beginTransaction();
            
            try {
                // جلب معلومات العريس أولاً
                $groomStmt = $pdo->prepare("SELECT groom_name FROM grooms WHERE id = ?");
                $groomStmt->execute([$id]);
                $groomData = $groomStmt->fetch();
                
                if (!$groomData) {
                    throw new Exception('العريس غير موجود');
                }
                
                // حذف البيانات المرتبطة
                $tables = [
                    'groom_photos' => 'groom_id',
                    'groom_reviews' => 'groom_id',
                    'groom_likes' => 'groom_id',
                    'photo_likes' => 'groom_id',
                    'photo_views' => 'groom_id',
                    'upload_queue' => 'groom_id',
                    'sessions' => 'groom_id'
                ];
                
                foreach ($tables as $table => $column) {
                    try {
                        // التحقق من وجود الجدول أولاً
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount();
                        if ($checkTable > 0) {
                            $deleteStmt = $pdo->prepare("DELETE FROM $table WHERE $column = ?");
                            $deleteStmt->execute([$id]);
                            $deletedRows = $deleteStmt->rowCount();
                            if ($deletedRows > 0) {
                                writeStatusLog("حذف $deletedRows سجل من جدول $table");
                            }
                        }
                    } catch (PDOException $e) {
                        // تجاهل إذا كان الجدول غير موجود
                        writeStatusLog("تحذير: لا يمكن حذف من $table - " . $e->getMessage());
                    }
                }
                
                // حذف العريس من الجدول الرئيسي
                $deleteGroomStmt = $pdo->prepare("DELETE FROM grooms WHERE id = ?");
                $deleteGroomStmt->execute([$id]);
                
                if ($deleteGroomStmt->rowCount() === 0) {
                    throw new Exception('فشل في حذف العريس من قاعدة البيانات');
                }
                
                // إنهاء المعاملة بنجاح
                $pdo->commit();
                
                // حذف مجلد الصور
                $groomDir = dirname(__DIR__) . '/grooms/' . $id;
                if (is_dir($groomDir)) {
                    if (removeDirectoryRecursive($groomDir)) {
                        writeStatusLog("تم حذف مجلد الصور: $groomDir");
                    } else {
                        writeStatusLog("تحذير: لم يتم حذف مجلد الصور: $groomDir");
                    }
                }
                
                $response['success'] = true;
                $response['message'] = 'تم حذف الصفحة نهائياً';
                writeStatusLog("نجح: حذف العريس #$id ({$groomData['groom_name']}) نهائياً");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
case 'delete_pending':
    // حذف عريس منتظر مع التتبع
    $stmt = $pdo->prepare("SELECT * FROM pending_grooms WHERE id = ?");
    $stmt->execute([$id]);
    $pendingData = $stmt->fetch();
    
    if (!$pendingData) {
        throw new Exception('العريس المنتظر غير موجود');
    }
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    try {
        // حفظ في جدول المحذوفين
        $insertDeleted = $pdo->prepare("
            INSERT INTO deleted_pending_grooms 
            (groom_name, phone, booking_date, location, deleted_by, reason, original_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertDeleted->execute([
            $pendingData['groom_name'],
            $pendingData['phone'],
            $pendingData['booking_date'],
            $pendingData['location'],
            $_SESSION['user'] ?? 'Unknown',
            'حذف من الداشبورد',
            json_encode($pendingData)
        ]);
        
        // حذف السجل
        $deleteStmt = $pdo->prepare("DELETE FROM pending_grooms WHERE id = ?");
        $deleteStmt->execute([$id]);
        
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'تم حذف العريس المنتظر وحفظه في سلة المحذوفات';
        writeStatusLog("نجح: حذف عريس منتظر #$id ({$pendingData['groom_name']}) مع التتبع");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    break;
            
            if (!$pendingData) {
                throw new Exception('العريس المنتظر غير موجود');
            }
            
            $deleteStmt = $pdo->prepare("DELETE FROM pending_grooms WHERE id = ?");
            $deleteStmt->execute([$id]);
            
            if ($deleteStmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'تم حذف العريس المنتظر بنجاح';
                writeStatusLog("نجح: حذف عريس منتظر #$id ({$pendingData['groom_name']})");
            } else {
                throw new Exception('فشل في حذف العريس المنتظر');
            }
            break;
            
        default:
            throw new Exception('إجراء غير صحيح: ' . $action);
    }
    
    // تسجيل النجاح
    if ($response['success']) {
        writeStatusLog("✅ اكتمل الإجراء '$action' على العنصر #$id بنجاح");
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = 'خطأ في قاعدة البيانات';
    $response['error_details'] = [
        'action' => $action,
        'id' => $id,
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    ];
    
    writeStatusLog("❌ خطأ PDO في تنفيذ '$action' على #$id: " . $e->getMessage());
    http_response_code(500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['error_details'] = [
        'action' => $action,
        'id' => $id,
        'error_message' => $e->getMessage()
    ];
    
    writeStatusLog("❌ خطأ في تنفيذ '$action' على #$id: " . $e->getMessage());
    http_response_code(400);
}

// إرسال الاستجابة
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>