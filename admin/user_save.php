<?php
// ===== admin/user_save.php =====
session_start();
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'manager') {
    header('Location: index.php');
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // التحقق من البيانات المطلوبة
    if (empty($username)) {
        throw new Exception('اسم المستخدم مطلوب');
    }
    
    if ($role_id <= 0) {
        throw new Exception('يجب اختيار دور للمستخدم');
    }
    
    if ($id) {
        // تعديل مستخدم موجود
        $sql = "UPDATE users SET username = ?, name = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?";
        $params = [$username, $name, $email, $role_id, $is_active, $id];
        
        // تحديث كلمة المرور إذا تم إدخالها
        if (!empty($password)) {
            $sql = "UPDATE users SET username = ?, name = ?, email = ?, password = ?, role_id = ?, is_active = ? WHERE id = ?";
            $params = [$username, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role_id, $is_active, $id];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $_SESSION['success'] = 'تم تحديث المستخدم بنجاح';
        
    } else {
        // إضافة مستخدم جديد
        if (empty($password)) {
            throw new Exception('كلمة المرور مطلوبة للمستخدم الجديد');
        }
        
        // التحقق من عدم تكرار اسم المستخدم
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetchColumn() > 0) {
            throw new Exception('اسم المستخدم موجود مسبقاً');
        }
        
        // التحقق من عدم تكرار البريد الإلكتروني
        if (!empty($email)) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('البريد الإلكتروني مستخدم مسبقاً');
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, name, email, password, role_id, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $username,
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role_id,
            $is_active
        ]);
        
        $_SESSION['success'] = 'تم إضافة المستخدم بنجاح';
    }
    
    header('Location: users_list.php');
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: users_list.php');
}
exit;

// ===== نهاية user_save.php =====
?>

<?php
// ===== admin/user_delete.php =====
session_start();
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'manager') {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // التحقق من عدم حذف المستخدم الحالي
        $currentUserId = $_SESSION['user_data']['id'] ?? 0;
        if ($id == $currentUserId) {
            $_SESSION['error'] = 'لا يمكنك حذف حسابك الحالي';
        } else {
            // حذف المستخدم
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['success'] = 'تم حذف المستخدم بنجاح';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'فشل في حذف المستخدم';
    }
}

header('Location: users_list.php');
exit;

// ===== نهاية user_delete.php =====
?>