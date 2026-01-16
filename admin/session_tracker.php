<?php
// session_tracker.php
// تتبّع الزوار الحاليين عبر الجلسات

// 1. بدء أو استئناف الجلسة
session_start();

// 2. الاتصال بقاعدة البيانات (تأكد من المسار الصحيح لملف config.php)
require_once __DIR__ . '/config.php';

// 3. الحصول على معرّف الجلسة والمسار الحالي
$sessId = session_id();
$page   = $_SERVER['REQUEST_URI']; // يخزن URI كامل الصفحة

// 4. إدخال سجل الجلسة أو تحديثه إذا كان موجوداً
$sql = "
  INSERT INTO sessions (session_id, page, last_activity)
  VALUES (:sessId, :page, NOW())
  ON DUPLICATE KEY UPDATE
    page = VALUES(page),
    last_activity = NOW()
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':sessId' => $sessId,
  ':page'   => $page,
]);
