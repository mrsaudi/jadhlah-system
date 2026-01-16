<?php
/**
 * ============================================
 * نموذج تسجيل الضيوف لإشعارات الواتساب
 * ============================================
 * 
 * الملف: components/whatsapp_registration_form.php
 * الوظيفة: نموذج HTML للضيوف للتسجيل واستلام إشعار جاهزية الصور
 * 
 * الاستخدام في صفحة الزفاف:
 * <?php 
 *   $weddingId = 'wedding_123';
 *   $groomName = 'أحمد ومنى';
 *   include 'components/whatsapp_registration_form.php'; 
 * ?>
 */

// التحقق من وجود المتغيرات المطلوبة
if (!isset($weddingId) || !isset($groomName)) {
    echo '<p style="color:red">خطأ: يجب تحديد معرف الزفاف واسم العريس</p>';
    return;
}
?>

<!-- ============================================ -->
<!-- نموذج التسجيل للإشعارات                     -->
<!-- ============================================ -->

<div class="whatsapp-registration-container" id="whatsappForm">
    <div class="whatsapp-registration-card">
        
        <!-- العنوان -->
        <div class="whatsapp-header">
            <div class="whatsapp-icon">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="#25D366">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </div>
            <h3>احصل على إشعار فوري!</h3>
            <p>سجّل رقمك وسنرسل لك رسالة واتساب فور جاهزية الصور 📸</p>
        </div>
        
        <!-- النموذج -->
        <form id="guestRegistrationForm" onsubmit="submitGuestRegistration(event)">
            <input type="hidden" name="wedding_id" value="<?php echo htmlspecialchars($weddingId); ?>">
            
            <!-- الاسم -->
            <div class="form-group">
                <label for="guestName">
                    <span class="label-icon">👤</span>
                    اسمك الكريم
                </label>
                <input 
                    type="text" 
                    id="guestName" 
                    name="guest_name" 
                    placeholder="أدخل اسمك"
                    required
                    minlength="2"
                >
            </div>
            
            <!-- رقم الهاتف -->
            <div class="form-group">
                <label for="guestPhone">
                    <span class="label-icon">📱</span>
                    رقم الواتساب
                </label>
                <div class="phone-input-wrapper">
                    <span class="country-code">966+</span>
                    <input 
                        type="tel" 
                        id="guestPhone" 
                        name="phone_number" 
                        placeholder="5XXXXXXXX"
                        required
                        pattern="^5[0-9]{8}$"
                        maxlength="9"
                        dir="ltr"
                    >
                </div>
                <small class="input-hint">أدخل رقمك بدون الصفر (مثال: 512345678)</small>
            </div>
            
            <!-- زر التسجيل -->
            <button type="submit" class="submit-btn" id="submitBtn">
                <span class="btn-text">تسجيل للإشعارات</span>
                <span class="btn-loading" style="display:none;">
                    <svg class="spinner" viewBox="0 0 24 24" width="20" height="20">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-dashoffset="10">
                            <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                        </circle>
                    </svg>
                    جاري التسجيل...
                </span>
            </button>
        </form>
        
        <!-- رسالة النجاح -->
        <div id="successMessage" class="success-message" style="display:none;">
            <div class="success-icon">✅</div>
            <h4>تم التسجيل بنجاح!</h4>
            <p>سنرسل لك إشعار على الواتساب فور جاهزية صور زفاف <strong><?php echo htmlspecialchars($groomName); ?></strong></p>
        </div>
        
        <!-- رسالة الخطأ -->
        <div id="errorMessage" class="error-message" style="display:none;">
            <div class="error-icon">❌</div>
            <p id="errorText"></p>
            <button onclick="hideError()" class="retry-btn">حاول مرة أخرى</button>
        </div>
        
    </div>
</div>

<!-- ============================================ -->
<!-- الأنماط (CSS)                               -->
<!-- ============================================ -->
<style>
.whatsapp-registration-container {
    direction: rtl;
    font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
    max-width: 400px;
    margin: 20px auto;
}

.whatsapp-registration-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e8e8e8;
}

.whatsapp-header {
    text-align: center;
    margin-bottom: 24px;
}

.whatsapp-icon {
    width: 60px;
    height: 60px;
    background: #e8f8e8;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}

.whatsapp-header h3 {
    margin: 0 0 8px;
    color: #1a1a1a;
    font-size: 1.3rem;
}

.whatsapp-header p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 0.95rem;
}

.label-icon {
    margin-left: 6px;
}

.form-group input {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-group input:focus {
    border-color: #25D366;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.15);
}

.phone-input-wrapper {
    display: flex;
    align-items: center;
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.phone-input-wrapper:focus-within {
    border-color: #25D366;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.15);
}

.country-code {
    background: #f5f5f5;
    padding: 12px 14px;
    font-weight: 600;
    color: #555;
    border-left: 1px solid #e0e0e0;
}

.phone-input-wrapper input {
    border: none;
    border-radius: 0;
    padding-right: 12px;
}

.phone-input-wrapper input:focus {
    box-shadow: none;
}

.input-hint {
    display: block;
    margin-top: 6px;
    color: #888;
    font-size: 0.8rem;
}

.submit-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
}

.submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.success-message,
.error-message {
    text-align: center;
    padding: 20px;
    border-radius: 12px;
    margin-top: 16px;
}

.success-message {
    background: #e8f8e8;
    border: 1px solid #b8e6b8;
}

.success-icon,
.error-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
}

.success-message h4 {
    color: #1e7e34;
    margin: 0 0 8px;
}

.success-message p {
    color: #155724;
    margin: 0;
}

.error-message {
    background: #fee;
    border: 1px solid #fcc;
}

.error-message p {
    color: #c00;
    margin: 0 0 12px;
}

.retry-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}
</style>

<!-- ============================================ -->
<!-- الجافاسكريبت                                -->
<!-- ============================================ -->
<script>
async function submitGuestRegistration(event) {
    event.preventDefault();
    
    const form = document.getElementById('guestRegistrationForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    // جمع البيانات
    const formData = new FormData(form);
    const data = {
        wedding_id: formData.get('wedding_id'),
        guest_name: formData.get('guest_name'),
        phone_number: '966' + formData.get('phone_number')
    };
    
    // التحقق من الرقم
    if (!/^966[5][0-9]{8}$/.test(data.phone_number)) {
        showError('يرجى إدخال رقم هاتف صحيح');
        return;
    }
    
    // إظهار التحميل
    btnText.style.display = 'none';
    btnLoading.style.display = 'flex';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('/api/whatsapp/register_guest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // إخفاء النموذج وإظهار رسالة النجاح
            form.style.display = 'none';
            document.getElementById('successMessage').style.display = 'block';
        } else {
            showError(result.error || 'حدث خطأ أثناء التسجيل');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showError('فشل الاتصال بالخادم، يرجى المحاولة لاحقاً');
    } finally {
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
        submitBtn.disabled = false;
    }
}

function showError(message) {
    document.getElementById('errorText').textContent = message;
    document.getElementById('errorMessage').style.display = 'block';
}

function hideError() {
    document.getElementById('errorMessage').style.display = 'none';
}

// تنسيق الرقم أثناء الكتابة
document.getElementById('guestPhone').addEventListener('input', function(e) {
    // إزالة أي حرف غير رقمي
    this.value = this.value.replace(/\D/g, '');
    
    // إزالة الصفر من البداية إذا وجد
    if (this.value.startsWith('0')) {
        this.value = this.value.substring(1);
    }
    
    // الحد الأقصى 9 أرقام
    if (this.value.length > 9) {
        this.value = this.value.substring(0, 9);
    }
});
</script>
