<?php
// admin/users_list.php - نظام إدارة المستخدمين المحسن
session_start();
require_once __DIR__ . '/config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// التحقق من الصلاحيات (المدير فقط)
$role = $_SESSION['role'] ?? 'employ';
if ($role !== 'manager') {
    die('غير مصرح لك بالدخول لهذه الصفحة');
}

// إعدادات الصفحات
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // إنشاء الجداول إذا لم تكن موجودة
    createUserTables($pdo);
    
    // جلب العدد الإجمالي للمستخدمين
    $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPages = ceil($total / $limit);
    
    // جلب المستخدمين
    $stmt = $pdo->prepare("
        SELECT u.*, r.name AS role_name 
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        ORDER BY u.id DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    // جلب الأدوار المتاحة
    $roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    $error = "خطأ في قاعدة البيانات: " . $e->getMessage();
}

// دالة إنشاء الجداول
function createUserTables($pdo) {
    try {
        // جدول الأدوار
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // إضافة الأدوار الافتراضية
        $pdo->exec("
            INSERT IGNORE INTO roles (name, description) VALUES
            ('manager', 'مدير النظام - صلاحيات كاملة'),
            ('employ', 'موظف - صلاحيات محدودة'),
            ('viewer', 'مشاهد - قراءة فقط')
        ");
        
        // جدول المستخدمين
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE,
                name VARCHAR(100),
                role_id INT,
                is_active TINYINT(1) DEFAULT 1,
                last_login DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول الصلاحيات
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول ربط الأدوار بالصلاحيات
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
    } catch (Exception $e) {
        // تجاهل الأخطاء إذا كانت الجداول موجودة
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - جذلة</title>
    
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #6366f1;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
            --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --card-hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border-radius: 16px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-bg);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            color: var(--primary-color);
        }

        .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #9ca3af;
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-4px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary-color);
        }

        /* User Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-bg);
            margin: 0;
        }

        /* Search Box */
        .search-box {
            position: relative;
            min-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.875rem;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        /* Modern Buttons */
        .btn-modern {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            color: white;
        }

        /* Modern Table */
        .modern-table {
            width: 100%;
        }

        .modern-table thead {
            background: #f9fafb;
        }

        .modern-table th {
            padding: 1rem 1.5rem;
            text-align: right;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .modern-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            color: #1f2937;
            vertical-align: middle;
        }

        .modern-table tbody tr {
            transition: background 0.2s;
        }

        .modern-table tbody tr:hover {
            background: #f9fafb;
        }

        /* User Avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 0.75rem;
        }

        /* Role Badge */
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .role-badge.manager {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary-color);
        }

        .role-badge.employ {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }

        .role-badge.viewer {
            background: rgba(156, 163, 175, 0.1);
            color: #6b7280;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
        }

        .action-btn:hover {
            background: #f3f4f6;
            color: #1f2937;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 16px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-custom">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">
                        <i class="bi bi-people-fill"></i>
                        إدارة المستخدمين
                    </h1>
                    <nav aria-label="breadcrumb" class="mt-2">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">لوحة التحكم</a></li>
                            <li class="breadcrumb-item active">إدارة المستخدمين</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <button class="btn btn-modern btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                        <i class="bi bi-person-plus"></i>
                        إضافة مستخدم جديد
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3 class="h5 mb-1"><?= $total ?></h3>
                    <p class="text-muted mb-0">إجمالي المستخدمين</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3 class="h5 mb-1"><?= count($roles) ?></h3>
                    <p class="text-muted mb-0">عدد الأدوار</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <h3 class="h5 mb-1"><?= array_sum(array_map(fn($u) => $u['is_active'] ?? 1, $users)) ?></h3>
                    <p class="text-muted mb-0">مستخدمين نشطين</p>
                </div>
            </div>
        </div>

        <!-- Error Alert -->
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="table-card">
            <div class="table-header">
                <h5 class="table-title">قائمة المستخدمين</h5>
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="بحث في المستخدمين...">
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="modern-table" id="usersTable">
                    <thead>
                        <tr>
                            <th width="60">#</th>
                            <th>المستخدم</th>
                            <th>البريد الإلكتروني</th>
                            <th>الدور</th>
                            <th>الحالة</th>
                            <th>آخر دخول</th>
                            <th>تاريخ الإنشاء</th>
                            <th width="120">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted d-block mb-3"></i>
                                <p class="text-muted">لا يوجد مستخدمين حتى الآن</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar">
                                            <?= mb_substr($user['username'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($user['username']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($user['name'] ?? '') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                <td>
                                    <span class="role-badge <?= $user['role_name'] ?? 'viewer' ?>">
                                        <?= htmlspecialchars($user['role_name'] ?? 'غير محدد') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['is_active'] ?? 1): ?>
                                        <span class="status-badge active">نشط</span>
                                    <?php else: ?>
                                        <span class="status-badge inactive">معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يسجل دخول' ?></td>
                                <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button class="action-btn" title="تعديل" 
                                                onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="action-btn text-danger" title="حذف" 
                                                onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
            <div class="d-flex justify-content-center p-3 border-top">
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>">السابق</a>
                        </li>
                        <?php for($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>">التالي</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">إضافة مستخدم جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm" action="user_save.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="userId">
                        
                        <div class="mb-3">
                            <label class="form-label">اسم المستخدم *</label>
                            <input type="text" class="form-control" name="username" id="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل</label>
                            <input type="text" class="form-control" name="name" id="name">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" name="email" id="email">
                        </div>
                        
                        <div class="mb-3" id="passwordGroup">
                            <label class="form-label">كلمة المرور *</label>
                            <input type="password" class="form-control" name="password" id="password">
                            <small class="text-muted">اتركها فارغة للاحتفاظ بكلمة المرور الحالية (عند التعديل)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الدور *</label>
                            <select class="form-select" name="role_id" id="role_id" required>
                                <option value="">اختر الدور</option>
                                <?php foreach($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                                <label class="form-check-label" for="is_active">
                                    مستخدم نشط
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Edit User
        function editUser(user) {
            document.getElementById('modalTitle').textContent = 'تعديل المستخدم';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('name').value = user.name || '';
            document.getElementById('email').value = user.email || '';
            document.getElementById('role_id').value = user.role_id || '';
            document.getElementById('is_active').checked = user.is_active == 1;
            document.getElementById('password').removeAttribute('required');
            
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        }
        
        // Reset form when modal is closed
        document.getElementById('userModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('userForm').reset();
            document.getElementById('modalTitle').textContent = 'إضافة مستخدم جديد';
            document.getElementById('userId').value = '';
            document.getElementById('password').setAttribute('required', 'required');
        });
        
        // Delete User
        async function deleteUser(id, username) {
            const result = await Swal.fire({
                title: 'هل أنت متأكد؟',
                text: `سيتم حذف المستخدم "${username}" نهائياً`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'نعم، احذف!',
                cancelButtonText: 'إلغاء'
            });
            
            if (result.isConfirmed) {
                window.location.href = `user_delete.php?id=${id}`;
            }
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>