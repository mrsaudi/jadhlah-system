<?php
// File: admin/user_form.php
require __DIR__ . '/auth.php';
require_permission('create_user');

$id = $_GET['id'] ?? null;
$user = ['username'=>'','email'=>'','role_id'=>''];
if($id) {
  require_permission('edit_user');
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
  $stmt->execute([$id]);
  $user = $stmt->fetch();
}
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <title><?= $id?'تعديل':'إضافة' ?> مستخدم</title>
</head>
<body class="container py-4">
  <h2><?= $id?'✏️ تعديل':'➕ إضافة' ?> مستخدم</h2>
  <form action="user_save.php" method="post">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="mb-3">
      <label>اسم المستخدم</label>
      <input name="username" class="form-control" required value="<?= htmlspecialchars($user['username']) ?>">
    </div>
    <div class="mb-3">
      <label>البريد الإلكتروني</label>
      <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
    </div>
    <?php if(!$id): ?>
    <div class="mb-3">
      <label>كلمة المرور</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <?php endif; ?>
    <div class="mb-3">
      <label>الرتبة</label>
      <select name="role_id" class="form-select" required>
        <?php foreach($roles as $r): ?>
          <option value="<?= $r['id'] ?>" <?= $r['id']==$user['role_id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-success">حفظ</button>
    <a href="users_list.php" class="btn btn-secondary">إلغاء</a>
  </form>
</body>
</html>