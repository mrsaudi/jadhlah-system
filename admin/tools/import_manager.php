<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة استيراد العرسان - جذلة</title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
        }
        
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #6366f1);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .card-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .card-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
        .stat-icon.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }
        .stat-icon.info { background: rgba(59, 130, 246, 0.1); color: var(--info-color); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1f2937;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin: 2rem 0;
        }
        
        .btn-action {
            padding: 1rem 2rem;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .btn-import {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }
        
        .btn-check {
            background: linear-gradient(135deg, var(--info-color), #2563eb);
            color: white;
        }
        
        .btn-clear {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }
        
        .btn-export {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
        }
        
        .progress-container {
            display: none;
            margin: 2rem 0;
        }
        
        .progress {
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            background: #e5e7eb;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .log-container {
            background: #1f2937;
            color: #10b981;
            border-radius: 12px;
            padding: 1rem;
            height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            display: none;
            margin-top: 2rem;
        }
        
        .log-entry {
            margin: 0.25rem 0;
            padding: 0.25rem;
        }
        
        .log-entry.error { color: #ef4444; }
        .log-entry.success { color: #10b981; }
        .log-entry.warning { color: #f59e0b; }
        .log-entry.info { color: #3b82f6; }
        
        .settings-panel {
            background: #f9fafb;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .setting-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-container {
            margin: 2rem 0;
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f3f4f6;
            padding: 0.75rem;
            text-align: right;
            font-weight: 600;
            color: #4b5563;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }
        .badge-info { background: rgba(59, 130, 246, 0.1); color: var(--info-color); }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="card-header">
                <h1>
                    <i class="bi bi-cloud-download"></i>
                    نظام استيراد العرسان المتقدم
                </h1>
                <p>استيراد البيانات من Google Sheets بأمان وكفاءة</p>
            </div>
            
            <div class="card-body p-4">
                <!-- الإحصائيات -->
                <div class="stats-grid" id="statsGrid">
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="bi bi-database"></i>
                        </div>
                        <div class="stat-value" id="statTotal">0</div>
                        <div class="stat-label">إجمالي السجلات</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-value" id="statImported">0</div>
                        <div class="stat-label">تم الاستيراد</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <div class="stat-value" id="statUpdated">0</div>
                        <div class="stat-label">تم التحديث</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="bi bi-skip-forward"></i>
                        </div>
                        <div class="stat-value" id="statSkipped">0</div>
                        <div class="stat-label">تم التخطي</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="stat-value" id="statFailed">0</div>
                        <div class="stat-label">فشل</div>
                    </div>
                </div>
                
                <!-- الإعدادات -->
                <div class="settings-panel">
                    <h5 class="mb-3">إعدادات الاستيراد</h5>
                    <div class="settings-grid">
                        <div class="setting-item">
                            <input type="checkbox" id="dryRun" class="form-check-input">
                            <label for="dryRun">وضع التجربة (بدون حفظ)</label>
                        </div>
                        <div class="setting-item">
                            <input type="checkbox" id="updateExisting" class="form-check-input" checked>
                            <label for="updateExisting">تحديث السجلات الموجودة</label>
                        </div>
                        <div class="setting-item">
                            <input type="checkbox" id="skipErrors" class="form-check-input" checked>
                            <label for="skipErrors">تخطي الأخطاء والمتابعة</label>
                        </div>
                        <div class="setting-item">
                            <input type="checkbox" id="showLog" class="form-check-input">
                            <label for="showLog">عرض السجل المباشر</label>
                        </div>
                    </div>
                </div>
                
                <!-- أزرار الإجراءات -->
                <div class="action-buttons">
                    <button class="btn-action btn-check" onclick="checkConnection()">
                        <i class="bi bi-wifi"></i>
                        فحص الاتصال
                    </button>
                    
                    <button class="btn-action btn-import" onclick="startImport()">
                        <i class="bi bi-cloud-download"></i>
                        بدء الاستيراد
                    </button>
                    
                    <button class="btn-action btn-export" onclick="exportData()">
                        <i class="bi bi-file-earmark-excel"></i>
                        تصدير البيانات
                    </button>
                    
                    <button class="btn-action btn-clear" onclick="clearPending()">
                        <i class="bi bi-trash"></i>
                        مسح المنتظرين
                    </button>
                </div>
                
                <!-- شريط التقدم -->
                <div class="progress-container" id="progressContainer">
                    <h5 class="mb-2">التقدم</h5>
                    <div class="progress">
                        <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
                    </div>
                    <p class="text-center mt-2" id="progressText">جاري الاستيراد...</p>
                </div>
                
                <!-- سجل العمليات -->
                <div class="log-container" id="logContainer"></div>
                
                <!-- جدول البيانات المستوردة -->
                <div class="table-container" id="tableContainer" style="display: none;">
                    <h5 class="mb-3">البيانات المستوردة حديثاً</h5>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>اسم العريس</th>
                                <th>الهاتف</th>
                                <th>التاريخ</th>
                                <th>الموقع</th>
                                <th>الباقة</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody"></tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-footer text-center p-3">
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right"></i>
                    العودة للوحة التحكم
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // متغيرات عامة
        let isImporting = false;
        let importStats = {
            total: 0,
            imported: 0,
            updated: 0,
            skipped: 0,
            failed: 0
        };
        
        // فحص الاتصال
        async function checkConnection() {
            Swal.fire({
                title: 'جاري فحص الاتصال...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            try {
                const response = await fetch('check_sheets_connection.php');
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'الاتصال ناجح',
                        html: `
                            <p>تم الاتصال بـ Google Sheets بنجاح</p>
                            <p>عدد الصفوف: ${data.rows_count}</p>
                            <p>آخر تحديث: ${data.last_update}</p>
                        `,
                        confirmButtonText: 'ممتاز'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'فشل الاتصال',
                        text: data.error || 'لا يمكن الاتصال بـ Google Sheets',
                        confirmButtonText: 'حسناً'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: 'حدث خطأ في فحص الاتصال',
                    confirmButtonText: 'حسناً'
                });
            }
        }
        
        // بدء الاستيراد
        async function startImport() {
            if (isImporting) {
                Swal.fire({
                    icon: 'warning',
                    title: 'تنبيه',
                    text: 'هناك عملية استيراد جارية بالفعل',
                    confirmButtonText: 'حسناً'
                });
                return;
            }
            
            const result = await Swal.fire({
                title: 'تأكيد الاستيراد',
                text: 'هل تريد بدء استيراد البيانات من Google Sheets؟',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، ابدأ',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280'
            });
            
            if (!result.isConfirmed) return;
            
            isImporting = true;
            resetStats();
            showProgress();
            
            if (document.getElementById('showLog').checked) {
                document.getElementById('logContainer').style.display = 'block';
                clearLog();
            }
            
            try {
                const params = new URLSearchParams({
                    dry_run: document.getElementById('dryRun').checked ? '1' : '0',
                    update_existing: document.getElementById('updateExisting').checked ? '1' : '0',
                    skip_errors: document.getElementById('skipErrors').checked ? '1' : '0'
                });
                
                const response = await fetch('tools/import_pending_grooms.php?' + params);
                const data = await response.json();
                
                if (data.success) {
                    updateStats(data.stats);
                    hideProgress();
                    showSuccessMessage(data);
                    
                    if (data.stats.imported > 0 || data.stats.updated > 0) {
                        loadRecentData();
                    }
                } else {
                    hideProgress();
                    showErrorMessage(data.error);
                }
            } catch (error) {
                hideProgress();
                showErrorMessage('حدث خطأ في الاستيراد: ' + error.message);
            } finally {
                isImporting = false;
            }
        }
        
        // تصدير البيانات
        function exportData() {
            Swal.fire({
                title: 'تصدير البيانات',
                text: 'سيتم تصدير البيانات المنتظرة إلى ملف Excel',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'تصدير',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'export_pending.php';
                }
            });
        }
        
        // مسح المنتظرين
        async function clearPending() {
            const result = await Swal.fire({
                title: 'تحذير!',
                text: 'سيتم حذف جميع السجلات المنتظرة غير المعالجة. هذا الإجراء لا يمكن التراجع عنه!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، احذف الكل',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#ef4444',
                input: 'checkbox',
                inputValue: 0,
                inputPlaceholder: 'أؤكد حذف جميع السجلات المنتظرة'
            });
            
            if (result.isConfirmed && result.value) {
                try {
                    const response = await fetch('clear_pending.php', {
                        method: 'POST'
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم الحذف',
                            text: `تم حذف ${data.deleted} سجل`,
                            confirmButtonText: 'حسناً'
                        });
                        resetStats();
                    } else {
                        throw new Error(data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: error.message,
                        confirmButtonText: 'حسناً'
                    });
                }
            }
        }
        
        // تحديث الإحصائيات
        function updateStats(stats) {
            importStats = stats;
            document.getElementById('statTotal').textContent = stats.total || 0;
            document.getElementById('statImported').textContent = stats.imported || 0;
            document.getElementById('statUpdated').textContent = stats.updated || 0;
            document.getElementById('statSkipped').textContent = stats.skipped || 0;
            document.getElementById('statFailed').textContent = stats.failed || 0;
        }
        
        // إعادة تعيين الإحصائيات
        function resetStats() {
            updateStats({
                total: 0,
                imported: 0,
                updated: 0,
                skipped: 0,
                failed: 0
            });
        }
        
        // عرض التقدم
        function showProgress() {
            document.getElementById('progressContainer').style.display = 'block';
            updateProgress(0);
        }
        
        // إخفاء التقدم
        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
        }
        
        // تحديث التقدم
        function updateProgress(percent) {
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }
        
        // إضافة إلى السجل
        function addLog(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.textContent = `[${new Date().toLocaleTimeString('ar-SA')}] ${message}`;
            logContainer.appendChild(entry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // مسح السجل
        function clearLog() {
            document.getElementById('logContainer').innerHTML = '';
        }
        
        // عرض رسالة النجاح
        function showSuccessMessage(data) {
            let html = '<div style="text-align: right;">';
            html += `<p><strong>✅ تم الاستيراد:</strong> ${data.stats.imported}</p>`;
            html += `<p><strong>🔄 تم التحديث:</strong> ${data.stats.updated}</p>`;
            html += `<p><strong>⏭️ تم التخطي:</strong> ${data.stats.skipped}</p>`;
            
            if (data.stats.failed > 0) {
                html += `<p class="text-danger"><strong>❌ فشل:</strong> ${data.stats.failed}</p>`;
            }
            
            if (data.errors && data.errors.length > 0) {
                html += '<hr><p><strong>الأخطاء:</strong></p><ul>';
                data.errors.forEach(error => {
                    html += `<li>${error}</li>`;
                });
                html += '</ul>';
            }
            
            html += '</div>';
            
            Swal.fire({
                icon: 'success',
                title: 'اكتمل الاستيراد',
                html: html,
                confirmButtonText: 'حسناً',
                width: 600
            });
        }
        
        // عرض رسالة الخطأ
        function showErrorMessage(error) {
            Swal.fire({
                icon: 'error',
                title: 'فشل الاستيراد',
                text: error,
                confirmButtonText: 'حسناً'
            });
        }
        
        // تحميل البيانات الحديثة
        async function loadRecentData() {
            try {
                const response = await fetch('get_recent_pending.php');
                const data = await response.json();
                
                if (data.success && data.records.length > 0) {
                    displayDataTable(data.records);
                }
            } catch (error) {
                console.error('Error loading recent data:', error);
            }
        }
        
        // عرض جدول البيانات
        function displayDataTable(records) {
            const tableContainer = document.getElementById('tableContainer');
            const tableBody = document.getElementById('dataTableBody');
            
            tableBody.innerHTML = '';
            
            records.forEach(record => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${record.groom_name}</td>
                    <td>${record.phone || '-'}</td>
                    <td>${record.booking_date || '-'}</td>
                    <td>${record.location || '-'}</td>
                    <td>${record.package || '-'}</td>
                    <td>
                        ${record.groom_id ? 
                            '<span class="badge badge-success">معالج</span>' : 
                            '<span class="badge badge-warning">منتظر</span>'}
                    </td>
                    <td>
                        ${!record.groom_id ? 
                            `<button class="btn btn-sm btn-primary" onclick="createGroom(${record.id})">
                                <i class="bi bi-plus"></i> إنشاء
                            </button>` : 
                            `<a href="../groom.php?groom=${record.groom_id}" class="btn btn-sm btn-success" target="_blank">
                                <i class="bi bi-eye"></i> عرض
                            </a>`}
                    </td>
                `;
                tableBody.appendChild(row);
            });
            
            tableContainer.style.display = 'block';
        }
        
        // إنشاء صفحة عريس
        function createGroom(pendingId) {
            window.location.href = `../create_from_pending.php?id=${pendingId}`;
        }
        
        // تحميل الإحصائيات عند التحميل
        window.addEventListener('load', async () => {
            try {
                const response = await fetch('get_import_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    updateStats(data.stats);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        });
    </script>
</body>
</html>