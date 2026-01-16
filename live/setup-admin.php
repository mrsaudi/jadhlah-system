<?php
// admin/setup-admin.php - إنشاء المستخدم الإداري الأول
// احذف هذا الملف بعد إنشاء المستخدم!

require_once '../config/database.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// إنشاء جدول المسؤولين إذا لم يكن موجوداً
$createTableSQL = "
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100),
    `email` VARCHAR(100),
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($createTableSQL)) {
    echo "✅ تم إنشاء جدول المسؤولين بنجاح<br>";
} else {
    echo "❌ خطأ في إنشاء الجدول: " . $conn->error . "<br>";
}

// إنشاء مستخدم إداري افتراضي
$defaultAdmin = [
    'username' => 'admin',
    'password' => 'Admin@123456', // غيّر كلمة المرور فوراً!
    'full_name' => 'المسؤول العام',
    'email' => 'admin@jadhlah.com'
];

// التحقق من عدم وجود المستخدم
$checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
$checkStmt->bind_param("s", $defaultAdmin['username']);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    // تشفير كلمة المرور
    $hashedPassword = password_hash($defaultAdmin['password'], PASSWORD_DEFAULT);
    
    // إدراج المستخدم
    $stmt = $conn->prepare("
        INSERT INTO admins (username, password, full_name, email, is_active) 
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("ssss", 
        $defaultAdmin['username'], 
        $hashedPassword, 
        $defaultAdmin['full_name'], 
        $defaultAdmin['email']
    );
    
    if ($stmt->execute()) {
        echo "✅ تم إنشاء المستخدم الإداري بنجاح!<br><br>";
        echo "📋 <strong>معلومات الدخول:</strong><br>";
        echo "اسم المستخدم: <code>{$defaultAdmin['username']}</code><br>";
        echo "كلمة المرور: <code>{$defaultAdmin['password']}</code><br><br>";
        echo "⚠️ <strong style='color: red;'>تحذير مهم:</strong><br>";
        echo "1. غيّر كلمة المرور فوراً بعد أول دخول<br>";
        echo "2. احذف هذا الملف (setup-admin.php) من السيرفر فوراً<br>";
        echo "3. قم بإنشاء مستخدمين إضافيين من لوحة التحكم<br><br>";
        echo "🔗 <a href='login.php'>الذهاب لصفحة تسجيل الدخول</a>";
    } else {
        echo "❌ خطأ في إنشاء المستخدم: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "ℹ️ المستخدم الإداري موجود بالفعل<br>";
    echo "🔗 <a href='login.php'>الذهاب لصفحة تسجيل الدخول</a>";
}

$checkStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد المسؤول</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f5f5f5;
            padding: 50px;
            text-align: center;
        }
        code {
            background: #333;
            color: #fff;
            padding: 3px 8px;
            border-radius: 3px;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        a:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
</body>
</html>
