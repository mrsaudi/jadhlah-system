/**
 * ============================================
 * WhatsApp API Helper - جذلة
 * ============================================
 * 
 * الملف: assets/js/whatsapp.js
 * الوظيفة: دوال JavaScript للتعامل مع WhatsApp API
 */

const JadhlaWhatsApp = {
    
    // رابط API
    apiBaseUrl: '/api/whatsapp',
    
    /**
     * إرسال إشعار جاهزية الصور
     * 
     * @param {string|array} phones - رقم أو أرقام الهواتف
     * @param {string} groomName - اسم العريس
     * @param {string} pageUrl - رابط صفحة الصور
     * @returns {Promise}
     */
    async sendPhotosReady(phones, groomName, pageUrl) {
        const data = {
            groom_name: groomName,
            page_url: pageUrl
        };
        
        // تحديد إذا كان رقم واحد أو عدة أرقام
        if (Array.isArray(phones)) {
            data.phones = phones;
        } else {
            data.phone = phones;
        }
        
        return this.request('/send_notification.php', data);
    },
    
    /**
     * إرسال رسالة نصية (للمستقبل)
     */
    async sendTextMessage(phone, message) {
        return this.request('/send_message.php', {
            phone: phone,
            message: message
        });
    },
    
    /**
     * إرسال الطلب للـ API
     */
    async request(endpoint, data) {
        try {
            const response = await fetch(this.apiBaseUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            return result;
            
        } catch (error) {
            console.error('WhatsApp API Error:', error);
            return {
                success: false,
                error: 'فشل الاتصال بالخادم'
            };
        }
    },
    
    /**
     * تنسيق رقم الهاتف السعودي
     */
    formatSaudiNumber(phone) {
        // إزالة كل الأحرف غير الرقمية
        phone = phone.replace(/\D/g, '');
        
        // إذا بدأ بـ 0، أزل الصفر
        if (phone.startsWith('0')) {
            phone = phone.substring(1);
        }
        
        // إذا لم يبدأ بـ 966
        if (!phone.startsWith('966')) {
            phone = '966' + phone;
        }
        
        return phone;
    },
    
    /**
     * التحقق من صحة رقم الهاتف السعودي
     */
    isValidSaudiNumber(phone) {
        const formatted = this.formatSaudiNumber(phone);
        // يجب أن يكون 12 رقم (966 + 9 أرقام)
        return /^966[5][0-9]{8}$/.test(formatted);
    }
};

// ============================================
// دوال مساعدة للواجهة
// ============================================

/**
 * إظهار رسالة نجاح
 */
function showWhatsAppSuccess(message) {
    // يمكنك تخصيص هذه الدالة حسب تصميم موقعك
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'تم بنجاح!',
            text: message,
            confirmButtonText: 'حسناً'
        });
    } else {
        alert('✅ ' + message);
    }
}

/**
 * إظهار رسالة خطأ
 */
function showWhatsAppError(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'خطأ!',
            text: message,
            confirmButtonText: 'حسناً'
        });
    } else {
        alert('❌ ' + message);
    }
}

/**
 * إظهار حالة التحميل
 */
function showWhatsAppLoading(show = true) {
    // يمكنك تخصيص هذه الدالة
    const loader = document.getElementById('whatsapp-loader');
    if (loader) {
        loader.style.display = show ? 'block' : 'none';
    }
}

// ============================================
// مثال على الاستخدام في زر الإرسال
// ============================================

/**
 * إرسال إشعار الجاهزية من لوحة التحكم
 * 
 * استخدمها مع زر مثل:
 * <button onclick="sendGroomsReadyNotification('wedding_id_123')">
 *     إرسال إشعار للضيوف
 * </button>
 */
async function sendGroomsReadyNotification(weddingId) {
    // الحصول على بيانات الزفاف (عدّل حسب طريقة تخزينك)
    const weddingData = await getWeddingData(weddingId);
    
    if (!weddingData) {
        showWhatsAppError('لم يتم العثور على بيانات الزفاف');
        return;
    }
    
    // التأكيد قبل الإرسال
    const confirmMessage = `سيتم إرسال إشعار لـ ${weddingData.guests.length} ضيف.\nهل تريد المتابعة؟`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    showWhatsAppLoading(true);
    
    try {
        const result = await JadhlaWhatsApp.sendPhotosReady(
            weddingData.guestPhones,
            weddingData.groomName,
            weddingData.pageUrl
        );
        
        showWhatsAppLoading(false);
        
        if (result.success) {
            showWhatsAppSuccess(`تم إرسال ${result.sent} رسالة بنجاح!`);
        } else {
            showWhatsAppError(`فشل إرسال ${result.failed} رسالة. ${result.error || ''}`);
        }
        
        // عرض التفاصيل في الكونسول
        console.log('WhatsApp Send Result:', result);
        
    } catch (error) {
        showWhatsAppLoading(false);
        showWhatsAppError('حدث خطأ أثناء الإرسال');
        console.error(error);
    }
}

/**
 * دالة مساعدة للحصول على بيانات الزفاف
 * عدّل هذه الدالة حسب طريقة تخزين البيانات في موقعك
 */
async function getWeddingData(weddingId) {
    // مثال: جلب من API
    try {
        const response = await fetch(`/api/wedding/${weddingId}`);
        return await response.json();
    } catch (error) {
        console.error('Error fetching wedding data:', error);
        return null;
    }
}
