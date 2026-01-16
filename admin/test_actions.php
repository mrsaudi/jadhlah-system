<?php
// admin/test_actions.php - أداة اختبار إجراءات الداشبورد
session_start();

// التحقق من تسجيل الدخول
if (empty($_SESSION['user'])) {
    die('يرجى تسجيل الدخول أولاً');
}

require_once __DIR__ . '/config.php';

// جلب عريس للاختبار
$testGroom = $pdo->query("SELECT id, groom_name FROM grooms ORDER BY id DESC LIMIT 1")->fetch();
$testPending = $pdo->query("SELECT id, groom_name FROM pending_grooms WHERE groom_id IS NULL LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار الإجراءات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container mt-5">
    <h2>🧪 اختبار إجراءات الداشبورد</h2>
    
    <div class="alert alert-info">
        <h5>معلومات النظام:</h5>
        <ul>
            <li>المستخدم: <?= htmlspecialchars($_SESSION['user']) ?></li>
            <li>الصلاحية: <?= htmlspecialchars($_SESSION['role'] ?? 'غير محدد') ?></li>
            <li>عريس الاختبار: <?= $testGroom ? $testGroom['groom_name'] . ' (#' . $testGroom['id'] . ')' : 'لا يوجد' ?></li>
            <li>منتظر للاختبار: <?= $testPending ? $testPending['groom_name'] . ' (#' . $testPending['id'] . ')' : 'لا يوجد' ?></li>
        </ul>
    </div>
    
    <?php if ($testGroom): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h5>اختبار إجراءات العريس</h5>
        </div>
        <div class="card-body">
            <div class="btn-group" role="group">
                <button class="btn btn-primary" onclick="testAction('toggle_ready', <?= $testGroom['id'] ?>, {ready: 1})">
                    تفعيل الجاهزية
                </button>
                <button class="btn btn-secondary" onclick="testAction('toggle_ready', <?= $testGroom['id'] ?>, {ready: 0})">
                    إلغاء الجاهزية
                </button>
                <button class="btn btn-warning" onclick="testAction('block', <?= $testGroom['id'] ?>)">
                    حجب
                </button>
                <button class="btn btn-success" onclick="testAction('unblock', <?= $testGroom['id'] ?>)">
                    إلغاء الحجب
                </button>
                <button class="btn btn-info" onclick="testAction('activate', <?= $testGroom['id'] ?>)">
                    تفعيل
                </button>
                <button class="btn btn-dark" onclick="testAction('deactivate', <?= $testGroom['id'] ?>)">
                    تعطيل
                </button>
                <button class="btn btn-danger" onclick="testDelete(<?= $testGroom['id'] ?>)">
                    حذف
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($testPending): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h5>اختبار إجراءات المنتظر</h5>
        </div>
        <div class="card-body">
            <button class="btn btn-danger" onclick="testAction('delete_pending', <?= $testPending['id'] ?>)">
                حذف المنتظر
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5>نتائج الاختبار</h5>
        </div>
        <div class="card-body">
            <div id="results" style="max-height: 400px; overflow-y: auto;">
                <p class="text-muted">سيتم عرض النتائج هنا...</p>
            </div>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="dashboard.php" class="btn btn-secondary">العودة للداشبورد</a>
        <button class="btn btn-warning" onclick="checkLogs()">فحص السجلات</button>
        <button class="btn btn-info" onclick="checkDatabase()">فحص قاعدة البيانات</button>
    </div>
</div>

<script>
function addResult(message, type = 'info') {
    const results = document.getElementById('results');
    const time = new Date().toLocaleTimeString('ar-SA');
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    results.innerHTML += `
        <div class="alert ${alertClass} mb-2">
            <small class="text-muted">[${time}]</small>
            ${message}
        </div>
    `;
    results.scrollTop = results.scrollHeight;
}

async function testAction(action, id, extraData = {}) {
    addResult(`🔄 بدء اختبار: ${action} على #${id}`, 'info');
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', action);
    
    // إضافة البيانات الإضافية
    for (const [key, value] of Object.entries(extraData)) {
        formData.append(key, value);
    }
    
    try {
        // استخدام الملف النظيف
        const response = await fetch('update_page_status_clean.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('Raw response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            addResult(`❌ خطأ في تحليل الاستجابة: ${responseText}`, 'error');
            return;
        }
        
        if (data.success) {
            addResult(`✅ نجح: ${data.message}`, 'success');
        } else {
            addResult(`❌ فشل: ${data.message}`, 'error');
            if (data.error_details) {
                console.error('Error details:', data.error_details);
                addResult(`تفاصيل: ${JSON.stringify(data.error_details)}`, 'warning');
            }
        }
    } catch (error) {
        addResult(`❌ خطأ في الاتصال: ${error.message}`, 'error');
        console.error('Fetch error:', error);
    }
}

async function testDelete(id) {
    const result = await Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هذا اختبار حقيقي - سيتم حذف العريس فعلاً!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف!',
        cancelButtonText: 'إلغاء'
    });
    
    if (result.isConfirmed) {
        testAction('delete', id);
    }
}

async function checkLogs() {
    addResult('📋 فحص ملفات السجل...', 'info');
    
    try {
        const response = await fetch('check_logs.php');
        const data = await response.json();
        
        if (data.logs) {
            addResult(`وجدت ${data.logs.length} ملف سجل`, 'info');
            data.logs.forEach(log => {
                addResult(`📄 ${log.name}: ${log.size} - آخر تعديل: ${log.modified}`, 'info');
            });
        }
    } catch (error) {
        addResult('لا يمكن الوصول لملفات السجل', 'warning');
    }
}

async function checkDatabase() {
    addResult('🗄️ فحص قاعدة البيانات...', 'info');
    
    try {
        const response = await fetch('check_database_simple.php');
        const text = await response.text();
        console.log('Database check response:', text);
        
        const data = JSON.parse(text);
        
        if (data.status === 'connected') {
            addResult(`✅ الاتصال بقاعدة البيانات: ${data.database}`, 'success');
            addResult(`📊 عدد الجداول: ${data.tables_count}`, 'info');
            
            if (data.grooms_count !== undefined) {
                addResult(`👥 عدد العرسان: ${data.grooms_count}`, 'info');
                addResult(`✅ نشط: ${data.active_grooms} | ⏸️ خامل: ${data.inactive_grooms} | 🚫 محجوب: ${data.blocked_grooms}`, 'info');
            }
            
            if (data.pending_count !== undefined) {
                addResult(`⏳ عدد المنتظرين: ${data.pending_count}`, 'info');
                addResult(`✅ تم معالجة: ${data.processed_pending}`, 'info');
            }
            
            if (data.photos_count !== undefined) {
                addResult(`📷 عدد الصور: ${data.photos_count}`, 'info');
            }
            
            if (data.columns_status) {
                addResult(`✅ ${data.columns_status}`, 'success');
            }
            
            if (data.missing_columns) {
                addResult(`⚠️ أعمدة ناقصة: ${data.missing_columns.join(', ')}`, 'warning');
            }
        } else {
            addResult(`❌ خطأ في الاتصال: ${data.error}`, 'error');
        }
    } catch (error) {
        addResult(`❌ خطأ في فحص قاعدة البيانات: ${error.message}`, 'error');
        console.error('Database check error:', error);
    }
}

// فحص تلقائي عند التحميل
window.addEventListener('load', () => {
    addResult('🚀 بدء الاختبار التلقائي...', 'info');
    
    // اختبار AJAX مع الملف النظيف
    fetch('update_page_status_clean.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=0&action=test'
    })
    .then(response => response.text())
    .then(text => {
        addResult('✅ الاتصال بـ update_page_status_clean.php يعمل', 'success');
    })
    .catch(error => {
        addResult('❌ خطأ في الاتصال بـ update_page_status_clean.php', 'error');
    });
});
</script>
</body>
</html>