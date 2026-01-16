<?php
// admin/login_process.php - معالج تسجيل الدخول المحسن

session_start();
header('Content-Type: application/json; charset=utf-8');

// تضمين ملف التكوين
require_once __DIR__ . '/config.php';

// المستخدمون المؤقتون (سيتم نقلهم لقاعدة البيانات لاحقاً)
$temporaryUsers = [
    'saud' => ['password' => '1245', 'role' => 'manager', 'name' => 'سعود'],
    'work' => ['password' => '1231', 'role' => 'employ', 'name' => 'موظف']
];

// دالة للتحقق من المدخلات
function validateInput($data) {
    return htmlspecialchars(trim($data));
}

// دالة لتسجيل محاولات الدخول
function logLoginAttempt($username, $success, $ip) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, success, attempt_time) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $ip, $success ? 1 : 0]);
    } catch (Exception $e) {
        // Log error but don't stop the login process
        error_log("Login attempt logging failed: " . $e->getMessage());
    }
}

// دالة للتحقق من عدد المحاولات الفاشلة
function checkLoginAttempts($username, $ip) {
    global $pdo;
    try {
        // حذف المحاولات القديمة (أكثر من 24 ساعة)
        $stmt = $pdo->prepare("
            DELETE FROM login_attempts 
            WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        
        // التحقق من عدد المحاولات الفاشلة في آخر 15 دقيقة
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$username, $ip]);
        $result = $stmt->fetch();
        
        return $result['attempts'] < 5; // السماح بـ 5 محاولات كحد أقصى
    } catch (Exception $e) {
        // في حالة الخطأ، نسمح بالمحاولة
        return true;
    }
}

// الاستجابة الافتراضية
$response = [
    'success' => false,
    'message' => 'حدث خطأ غير متوقع'
];

try {
    // التحقق من طريقة الإرسال
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الإرسال غير صحيحة');
    }
    
    // الحصول على البيانات المرسلة
    $username = validateInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
    
    // الحصول على IP المستخدم
    $userIP = $_SERVER['REMOTE_ADDR'];
    
    // التحقق من البيانات المطلوبة
    if (empty($username) || empty($password)) {
        throw new Exception('يرجى إدخال اسم المستخدم وكلمة المرور');
    }
    
    // التحقق من عدد المحاولات
    if (!checkLoginAttempts($username, $userIP)) {
        throw new Exception('تم تجاوز عدد المحاولات المسموحة. يرجى المحاولة بعد 15 دقيقة.');
    }
    
    // محاولة تسجيل الدخول من قاعدة البيانات أولاً
    $loginSuccess = false;
    $userData = null;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->execute([$username]);
        $dbUser = $stmt->fetch();
        
        if ($dbUser && password_verify($password, $dbUser['password'])) {
            $loginSuccess = true;
            $userData = [
                'id' => $dbUser['id'],
                'username' => $dbUser['username'],
                'name' => $dbUser['name'] ?? $dbUser['username'],
                'email' => $dbUser['email'],
                'role' => $dbUser['role_name'],
                'role_id' => $dbUser['role_id']
            ];
            
            // تحديث آخر تسجيل دخول
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$dbUser['id']]);
        }
    } catch (Exception $e) {
        // في حالة عدم وجود جدول المستخدمين، نستخدم المستخدمين المؤقتين
        error_log("Database users table not available: " . $e->getMessage());
    }
    
    // إذا فشل من قاعدة البيانات، جرب المستخدمين المؤقتين
    if (!$loginSuccess && isset($temporaryUsers[$username])) {
        if ($temporaryUsers[$username]['password'] === $password) {
            $loginSuccess = true;
            $userData = [
                'id' => 0, // معرف مؤقت
                'username' => $username,
                'name' => $temporaryUsers[$username]['name'],
                'role' => $temporaryUsers[$username]['role'],
                'is_temporary' => true
            ];
        }
    }
    
    // تسجيل المحاولة
    logLoginAttempt($username, $loginSuccess, $userIP);
    
    if ($loginSuccess) {
        // تسجيل الدخول بنجاح
        $_SESSION['user'] = $userData['username'];
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['user_data'] = $userData;
        $_SESSION['role'] = $userData['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // إعداد الكوكيز إذا تم اختيار "تذكرني"
        if ($remember) {
            $cookieLifetime = 30 * 24 * 60 * 60; // 30 يوم
            $cookieParams = [
                'lifetime' => $cookieLifetime,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ];
            
            // إنشاء رمز تذكر آمن
            $rememberToken = bin2hex(random_bytes(32));
            setcookie('remember_token', $rememberToken, time() + $cookieLifetime, 
                     $cookieParams['path'], $cookieParams['domain'], 
                     $cookieParams['secure'], $cookieParams['httponly']);
            
            // حفظ الرمز في قاعدة البيانات (إذا كان المستخدم من قاعدة البيانات)
            if (!isset($userData['is_temporary'])) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users SET remember_token = ?, remember_token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                        WHERE id = ?
                    ");
                    $stmt->execute([$rememberToken, $userData['id']]);
                } catch (Exception $e) {
                    // لا نوقف العملية في حالة فشل حفظ الرمز
                    error_log("Failed to save remember token: " . $e->getMessage());
                }
            }
        }
        
        // تسجيل نشاط تسجيل الدخول
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, username, action, details, ip_address, created_at) 
                VALUES (?, ?, 'login', 'تسجيل دخول ناجح', ?, NOW())
            ");
            $stmt->execute([$userData['id'], $username, $userIP]);
        } catch (Exception $e) {
            // لا نوقف العملية
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        $response = [
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'redirect' => 'dashboard.php',
            'user' => [
                'name' => $userData['name'],
                'role' => $userData['role']
            ]
        ];
    } else {
        throw new Exception('اسم المستخدم أو كلمة المرور غير صحيحة');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    // تسجيل الخطأ
    error_log("Login error: " . $e->getMessage());
}

// إرسال الاستجابة
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// إنشاء الجداول المطلوبة إذا لم تكن موجودة
function createRequiredTables($pdo) {
    try {
        // جدول محاولات تسجيل الدخول
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50),
                ip_address VARCHAR(45),
                success TINYINT(1) DEFAULT 0,
                attempt_time DATETIME,
                INDEX idx_username (username),
                INDEX idx_ip (ip_address),
                INDEX idx_time (attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول سجل النشاط
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                username VARCHAR(50),
                action VARCHAR(50),
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // إضافة حقول إضافية لجدول المستخدمين إذا لم تكن موجودة
        $pdo->exec("
            ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS last_login DATETIME,
            ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64),
            ADD COLUMN IF NOT EXISTS remember_token_expires DATETIME,
            ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1,
            ADD COLUMN IF NOT EXISTS name VARCHAR(100)
        ");
        
    } catch (Exception $e) {
        // لا نفعل شيء إذا كانت الجداول موجودة بالفعل
        error_log("Table creation notice: " . $e->getMessage());
    }
}

// استدعاء دالة إنشاء الجداول عند الحاجة
createRequiredTables($pdo);
?>