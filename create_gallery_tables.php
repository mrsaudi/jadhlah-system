// ======================================
// 1. create_gallery_tables.php - إنشاء الجداول المطلوبة
// ======================================
<?php
$host = 'localhost';
$db = 'u709146392_jadhlah_db';
$user = 'u709146392_jad_admin';
$pass = '1245@vmP';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // إنشاء جدول الصور المرفوعة يدوياً
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gallery_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            title VARCHAR(255),
            category VARCHAR(50) DEFAULT 'general',
            is_featured BOOLEAN DEFAULT 0,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_featured (is_featured),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // إضافة حقل is_featured لجدول groom_photos إن لم يكن موجوداً
    $pdo->exec("
        ALTER TABLE groom_photos 
        ADD COLUMN IF NOT EXISTS is_featured BOOLEAN DEFAULT 0,
        ADD INDEX IF NOT EXISTS idx_featured (is_featured)
    ");
    
    echo "تم إنشاء الجداول بنجاح!";
    
} catch (PDOException $e) {
    echo "خطأ: " . $e->getMessage();
}
?>
