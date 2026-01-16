<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/admin/config.php';

echo "<h2>Database Test</h2><pre>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM grooms");
    $count = $stmt->fetchColumn();
    echo "✅ Database Connection: WORKING!\n";
    echo "Total Grooms: $count\n\n";
    
    echo "=== Push Subscriptions Table ===\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'push_subscriptions'");
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Table EXISTS!\n";
        $stmt = $pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE is_active = 1");
        echo "Active Subscriptions: " . $stmt->fetchColumn() . "\n";
    } else {
        echo "❌ Table NOT FOUND! Creating...\n";
        $pdo->exec("
            CREATE TABLE `push_subscriptions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `endpoint` text NOT NULL,
              `p256dh` varchar(255) NOT NULL,
              `auth` varchar(255) NOT NULL,
              `is_active` tinyint(1) DEFAULT 1,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ Table created!\n";
    }
    
    echo "\n🎉 DATABASE TEST PASSED!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>