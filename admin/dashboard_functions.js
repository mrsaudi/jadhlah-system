// dashboard_functions.js - النسخة المحسنة مع معالجة أفضل للأخطاء
// دالة عرض الإشعارات - أضف هذا في السطر 2
function showNotification(message, type = 'success') {
    // استخدام SweetAlert2 إذا كان متوفراً
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        // استخدام alert كبديل
        alert(message);
    }
}
// دالة مساعدة لمعالجة الاستجابة
async function handleResponse(response) {
    const contentType = response.headers.get('content-type');
    
    if (!response.ok) {
        let errorMessage = `HTTP Error: ${response.status}`;
        
        if (contentType && contentType.includes('application/json')) {
            try {
                const errorData = await response.json();
                errorMessage = errorData.message || errorMessage;
            } catch (e) {
                // إذا فشل في قراءة JSON، استخدم الرسالة الافتراضية
            }
        }
        
        throw new Error(errorMessage);
    }
    
    if (contentType && contentType.includes('application/json')) {
        const text = await response.text();
        if (!text.trim()) {
            throw new Error('الاستجابة فارغة من الخادم');
        }
        
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Response text:', text);
            throw new Error('خطأ في تحليل الاستجابة JSON');
        }
    }
    
    return await response.text();
}

// دالة إرسال الطلبات مع معالجة أفضل للأخطاء
async function sendRequest(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data
        });
        
        return await handleResponse(response);
    } catch (error) {
        console.error('Request failed:', error);
        throw error;
    }
}

// Toggle Ready Status - محسنة
async function toggleReady(groomId, readyValue) {
    const checkbox = document.getElementById(`ready_${groomId}`);
    
    try {
        const data = await sendRequest('update_page_status.php', 
            `id=${groomId}&action=toggle_ready&ready=${readyValue}`
        );
        
        if (data.success) {
            showNotification(data.message || 'تم تحديث الحالة بنجاح', 'success');
        } else {
            showNotification(data.message || 'فشل في تحديث الحالة', 'error');
            // إعادة checkbox لحالته السابقة
            if (checkbox) checkbox.checked = !readyValue;
        }
    } catch (error) {
        showNotification('خطأ في الاتصال: ' + error.message, 'error');
        // إعادة checkbox لحالته السابقة
        if (checkbox) checkbox.checked = !readyValue;
    }
}

// Change Status - محسنة
function changeStatus(groomId, action) {
    const confirmMessages = {
        'block': 'هل تريد حجب هذه الصفحة؟',
        'unblock': 'هل تريد إلغاء حجب هذه الصفحة؟',
        'activate': 'هل تريد تفعيل هذه الصفحة؟',
        'deactivate': 'هل تريد تعطيل هذه الصفحة؟'
    };
    
    const confirmMessage = confirmMessages[action] || 'هل تريد تنفيذ هذا الإجراء؟';
    
    Swal.fire({
        title: 'تأكيد',
        text: confirmMessage,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'نعم',
        cancelButtonText: 'إلغاء'
    }).then(async (result) => {
        if (result.isConfirmed) {
            showLoadingOverlay();
            
            try {
                const data = await sendRequest('update_page_status.php', 
                    `id=${groomId}&action=${action}`
                );
                
                hideLoadingOverlay();
                
                if (data.success) {
                    showNotification(data.message || 'تم تحديث الحالة بنجاح', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(data.message || 'فشل في تحديث الحالة', 'error');
                }
            } catch (error) {
                hideLoadingOverlay();
                showNotification('خطأ في الاتصال: ' + error.message, 'error');
            }
        }
    });
}

// Delete Groom - محسنة
function deleteGroom(groomId) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: "سيتم حذف هذه الصفحة وجميع محتوياتها نهائياً!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'نعم، احذف!',
        cancelButtonText: 'إلغاء',
        input: 'checkbox',
        inputValue: 0,
        inputPlaceholder: 'أؤكد أنني أريد حذف هذه الصفحة نهائياً',
        inputValidator: (result) => {
            return !result && 'يجب تأكيد الحذف!'
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            showLoadingOverlay();
            
            try {
                const data = await sendRequest('update_page_status.php', 
                    `id=${groomId}&action=delete`
                );
                
                hideLoadingOverlay();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف!',
                        text: data.message || 'تم حذف الصفحة بنجاح',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(data.message || 'فشل في حذف الصفحة', 'error');
                }
            } catch (error) {
                hideLoadingOverlay();
                showNotification('خطأ في الاتصال: ' + error.message, 'error');
            }
        }
    });
}

// // Delete Pending - محسنة
// function deletePending(pendingId) {
//     Swal.fire({
//         title: 'هل أنت متأكد؟',
//         text: "سيتم حذف هذه الصفحة المنتظرة!",
//         icon: 'warning',
//         showCancelButton: true,
//         confirmButtonColor: '#ef4444',
//         cancelButtonColor: '#6b7280',
//         confirmButtonText: 'نعم، احذف!',
//         cancelButtonText: 'إلغاء'
//     }).then(async (result) => {
//         if (result.isConfirmed) {
//             try {
//                 const data = await sendRequest('update_page_status.php', 
//                     `id=${pendingId}&action=delete_pending`
//                 );
                
//                 if (data.success) {
//                     showNotification(data.message || 'تم حذف الصفحة المنتظرة', 'success');
//                     setTimeout(() => location.reload(), 1500);
//                 } else {
//                     showNotification(data.message || 'فشل في الحذف', 'error');
//                 }
//             } catch (error) {
//                 showNotification('خطأ في الاتصال: ' + error.message, 'error');
//             }
//         }
//     });
// }

// Create Groom from Pending
function createFromPending(timestamp) {
    window.location.href = `create_from_pending.php?timestamp=${encodeURIComponent(timestamp)}`;
}
// إضافة في dashboard_functions.js - استبدل دالة importFromSheets بهذه

async function importFromSheets() {
    const btn = event.target || event.currentTarget;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> جاري استيراد جميع السجلات...';
    
    try {
        // استخدام الملف الجديد الذي يستورد جميع السجلات
        const response = await fetch('tools/import_pending_grooms.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم الاستيراد بنجاح',
                html: `
                    <div class="text-right">
                        <p><strong>✅ تم استيراد:</strong> ${data.imported} صفحة جديدة</p>
                        <p><strong>⏭️ تم تخطي:</strong> ${data.skipped} صفحة (موجودة أو معالجة)</p>
                        ${data.total_errors > 0 ? 
                            `<p class="text-danger"><strong>⚠️ أخطاء:</strong> ${data.total_errors}</p>` : ''}
                    </div>
                `,
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: 'حسناً'
            });
            
            // إعادة تحميل الصفحة بعد 3 ثواني
            setTimeout(() => location.reload(), 3000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'فشل الاستيراد',
                text: data.error || 'حدث خطأ غير متوقع',
                confirmButtonText: 'حسناً'
            });
        }
    } catch (error) {
        console.error('Import error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ في الاتصال',
            text: 'تعذر الاتصال بالخادم. تحقق من اتصالك بالإنترنت.',
            confirmButtonText: 'حسناً'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

// تحديث دالة حذف الصفحة المنتظرة
// دالة حذف الصفحة المنتظرة - محدثة وتعمل
function deletePending(pendingId) {
    // استخدام SweetAlert للتأكيد
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: "سيتم حذف هذه الصفحة المنتظرة نهائياً!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'نعم، احذف!',
        cancelButtonText: 'إلغاء'
    }).then(async (result) => {
        if (result.isConfirmed) {
            // إظهار مؤشر التحميل
            Swal.fire({
                title: 'جاري الحذف...',
                text: 'يرجى الانتظار',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            try {
                // إرسال الطلب باستخدام URLSearchParams
                const params = new URLSearchParams();
                params.append('id', pendingId);
                params.append('action', 'delete_pending');
                
                const response = await fetch('update_page_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: params.toString()
                });
                
                // قراءة الاستجابة كنص أولاً
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let data;
                try {
                    // محاولة تحويل النص إلى JSON
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    throw new Error('استجابة غير صحيحة من الخادم');
                }
                
                if (data.success) {
                    // نجح الحذف
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف!',
                        text: data.message || 'تم حذف الصفحة المنتظرة بنجاح',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    // إعادة تحميل الصفحة بعد ثانيتين
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                    
                } else {
                    // فشل الحذف
                    Swal.fire({
                        icon: 'error',
                        title: 'فشل الحذف',
                        text: data.message || 'حدث خطأ أثناء حذف الصفحة المنتظرة',
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#4f46e5'
                    });
                    
                    // عرض تفاصيل الخطأ في console للتشخيص
                    if (data.error_details) {
                        console.error('Error details:', data.error_details);
                    }
                }
                
            } catch (error) {
                console.error('Delete error:', error);
                
                // عرض رسالة خطأ مفصلة
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال',
                    html: `
                        <p>${error.message}</p>
                        <small class="text-muted">تحقق من الاتصال بالإنترنت أو اتصل بالدعم الفني</small>
                    `,
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#4f46e5'
                });
            }
        }
    });
}


// Load Visitors Data - محسنة
async function loadVisitors() {
    try {
        const response = await fetch('../get_visitors.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            updateVisitorsDisplay(data);
        } else {
            document.getElementById('visitorsContainer').innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    ${data.error || 'لا توجد بيانات متاحة'}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading visitors:', error);
        document.getElementById('visitorsContainer').innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                نظام تتبع الزوار غير مفعل حالياً
            </div>
        `;
    }
}

// Update Visitors Display
function updateVisitorsDisplay(data) {
    const container = document.getElementById('visitorsContainer');
    
    if (!data.data || data.data.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-person-x"></i>
                <p>لا يوجد زوار نشطين حالياً</p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <span class="badge bg-primary fs-6">
                <i class="bi bi-people"></i> 
                إجمالي الزوار: ${data.stats?.total_visitors || data.data.length}
            </span>
            <span class="text-muted small">
                آخر تحديث: ${new Date().toLocaleTimeString('ar-SA')}
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>الصفحة</th>
                        <th>الزوار</th>
                        <th>الجهاز</th>
                        <th>آخر نشاط</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.data.forEach(item => {
        const deviceIcon = item.device_type === 'Mobile' ? 'bi-phone' : 'bi-laptop';
        const deviceClass = item.device_type === 'Mobile' ? 'text-info' : 'text-success';
        
        html += `
            <tr>
                <td>
                    <i class="bi ${item.page_icon || 'bi-file-person'} me-1"></i>
                    ${item.groom_id ? 
                        `<a href="../groom.php?groom=${item.groom_id}" target="_blank">${item.page_name}</a>` : 
                        `<span>${item.page_name}</span>`
                    }
                </td>
                <td>
                    <span class="badge bg-info">${item.visitor_count || 1}</span>
                </td>
                <td>
                    <i class="bi ${deviceIcon} ${deviceClass}"></i>
                    <span class="small text-muted">${item.browser || 'غير محدد'}</span>
                </td>
                <td class="text-muted small">${item.last_seen || 'الآن'}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    // إضافة أكثر الصفحات زيارة إذا كانت متوفرة
    if (data.stats?.top_pages && data.stats.top_pages.length > 0) {
        html += `
            <div class="mt-3">
                <h6 class="text-muted mb-2">أكثر الصفحات زيارة (24 ساعة)</h6>
                <div class="list-group list-group-flush">
                    ${data.stats.top_pages.map(page => `
                        <div class="list-group-item d-flex justify-content-between align-items-center px-2">
                            <a href="../groom.php?groom=${page.id}" target="_blank" class="text-decoration-none">
                                ${page.groom_name}
                            </a>
                            <div>
                                <span class="badge bg-primary">${page.unique_visitors} زائر</span>
                                <span class="badge bg-secondary">${page.page_views} مشاهدة</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Refresh Visitors
function refreshVisitors() {
    const container = document.getElementById('visitorsContainer');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div></div>';
    loadVisitors();
}

// Loading Overlay Functions
function showLoadingOverlay() {
    let overlay = document.querySelector('.loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = '<div class="loader"></div>';
        document.body.appendChild(overlay);
    }
    overlay.classList.add('active');
}

function hideLoadingOverlay() {
    const overlay = document.querySelector('.loading-overlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

// تشغيل الدوال عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // تحميل الزوار
    if (document.getElementById('visitorsContainer')) {
        loadVisitors();
        // تحديث كل 30 ثانية
        setInterval(loadVisitors, 30000);
    }
});