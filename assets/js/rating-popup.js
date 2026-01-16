// assets/js/rating-popup.js - بسيط ورايق مثل rate.php
(function() {
    'use strict';
    
    const POPUP_DELAY = 10 * 60 * 1000; // 10 دقائق
    const SESSION_KEY = 'rating_popup_shown_';
    
    // الحصول على معرف العريس
    function getGroomId() {
        if (typeof groomId !== 'undefined') return groomId;
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('groom') || urlParams.get('id') || null;
    }
    
    // التحقق من عرض النافذة مسبقاً
    const groomIdValue = getGroomId();
    if (!groomIdValue || sessionStorage.getItem(SESSION_KEY + groomIdValue)) {
        return;
    }
    
    // الانتظار ثم العرض
    setTimeout(showRatingPopup, POPUP_DELAY);
    
    function showRatingPopup() {
        // إنشاء النافذة المنبثقة
        const popup = document.createElement('div');
        popup.id = 'ratingPopup';
        popup.innerHTML = `
            <div class="popup-overlay">
                <div class="popup-container">
                    <button class="popup-close" onclick="closeRatingPopup()">×</button>
                    
                    <div class="popup-logo-section">
                        <img src="/assets/whiti_logo_jadhlah_t.svg" alt="جذلة">
                        <h2 class="popup-title">قيّم تجربتك معنا</h2>
                        <p class="popup-subtitle">رأيك يهمنا ويساعدنا على التطور</p>
                    </div>
                    
                    <form id="quickRatingForm" class="popup-form">
                        <div class="popup-rating-container">
                            <div class="popup-rating-label">كيف كانت تجربتك معنا؟</div>
                            <div class="popup-rating-stars" id="popupStars">
                                ${generateStars()}
                            </div>
                            <div class="popup-rating-text" id="ratingText">اختر تقييمك</div>
                            <input type="hidden" name="review_rating" id="popupRating" required>
                        </div>
                        
                        <div class="popup-form-group">
                            <input type="text" 
                                   name="review_name" 
                                   id="popupName"
                                   placeholder="اسمك الكريم *" 
                                   required
                                   maxlength="100">
                        </div>
                        
                        <div class="popup-form-group">
                            <textarea name="review_message" 
                                      id="popupMessage"
                                      placeholder="رسالتك (اختياري)"
                                      maxlength="500"
                                      rows="3"></textarea>
                        </div>
                        
                        <input type="hidden" name="submit_review" value="1">
                        
                        <button type="submit" class="popup-btn" id="popupSubmitBtn" disabled>
                            ✨ إرسال التقييم
                        </button>
                        <button type="button" class="popup-btn-secondary" onclick="closeRatingPopup()">
                            ربما لاحقاً
                        </button>
                    </form>
                    
                    <!-- رسالة النجاح -->
                    <div class="popup-success" style="display: none;">
                        <div class="popup-success-icon">✨🎉✨</div>
                        <h2>شكراً لك!</h2>
                        <p>تم استلام تقييمك بنجاح</p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(popup);
        addPopupStyles();
        setupPopupStars();
        setupFormHandlers();
        
        // عرض النافذة
        setTimeout(() => popup.classList.add('show'), 10);
        document.body.style.overflow = 'hidden';
        
        // تسجيل العرض
        sessionStorage.setItem(SESSION_KEY + groomIdValue, 'true');
    }
    
    function generateStars() {
        let html = '';
        for (let i = 5; i >= 1; i--) {
            html += `
                <div class="popup-star" data-rating="${i}">
                    <svg viewBox="0 0 24 24">
                        ${i === 5 ? `
                        <defs>
                            <linearGradient id="goldGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#FFD700;stop-opacity:1" />
                                <stop offset="30%" style="stop-color:#FFC700;stop-opacity:1" />
                                <stop offset="70%" style="stop-color:#C9A651;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#8B6914;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        ` : ''}
                        <path class="star-fill" d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                    </svg>
                </div>
            `;
        }
        return html;
    }
    
    function addPopupStyles() {
        if (document.getElementById('ratingPopupStyles')) return;
        
        const style = document.createElement('style');
        style.id = 'ratingPopupStyles';
        style.textContent = `
            :root {
                --gold-dark: #8B6914;
                --gold-medium: #C9A651;
                --gold-light: #E8D5A8;
            }
            
            /* النافذة الأساسية */
            #ratingPopup {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 99999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            #ratingPopup.show {
                opacity: 1;
            }
            
            /* الخلفية - زجاجية معتمة بسيطة */
            .popup-overlay {
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(12px) saturate(150%);
                -webkit-backdrop-filter: blur(12px) saturate(150%);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            /* الحاوية - صغيرة وبسيطة */
            .popup-container {
                max-width: 400px;
                width: 100%;
                background: rgba(255, 255, 255, 0.98);
                border-radius: 20px;
                padding: 28px 24px;
                box-shadow: 
                    0 12px 40px rgba(0, 0, 0, 0.25),
                    0 0 0 1px rgba(201, 166, 81, 0.2);
                position: relative;
                backdrop-filter: blur(20px);
                transform: scale(0.95);
                animation: popupAppear 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            }
            
            @keyframes popupAppear {
                to {
                    transform: scale(1);
                }
            }
            
            /* زر الإغلاق - بسيط */
            .popup-close {
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(0, 0, 0, 0.05);
                border: none;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                font-size: 20px;
                cursor: pointer;
                color: #666;
                line-height: 1;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .popup-close:active {
                background: rgba(0, 0, 0, 0.1);
                transform: scale(0.95);
            }
            
            /* القسم العلوي - بسيط */
            .popup-logo-section {
                text-align: center;
                margin-bottom: 20px;
            }
            .popup-logo-section img {
                max-width: 100px;
                height: auto;
                margin-bottom: 10px;
            }
            .popup-title {
                color: var(--gold-dark);
                font-size: 20px;
                font-weight: 700;
                margin-bottom: 4px;
            }
            .popup-subtitle {
                color: #5D4E37;
                font-size: 13px;
                font-weight: 400;
                opacity: 0.85;
            }
            
            /* حاوية التقييم - بسيطة */
            .popup-rating-container {
                background: linear-gradient(135deg, #FFFEFB 0%, #FFFCF5 100%);
                padding: 18px 14px;
                border-radius: 16px;
                margin: 16px 0;
                border: 1.5px solid var(--gold-light);
            }
            .popup-rating-label {
                text-align: center;
                color: var(--gold-dark);
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 12px;
            }
            
            /* النجوم - بسيطة */
            .popup-rating-stars {
                display: flex;
                gap: 6px;
                justify-content: center;
                margin: 14px 0;
                direction: rtl;
            }
            .popup-star {
                width: 46px;
                height: 46px;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                flex-shrink: 0;
            }
            .popup-star svg {
                width: 100%;
                height: 100%;
                filter: drop-shadow(0 2px 6px rgba(201, 166, 81, 0.15));
            }
            
            /* النجمة غير المحددة */
            .popup-star:not(.active) .star-fill {
                fill: #F5F0E5;
                stroke: var(--gold-light);
                stroke-width: 2;
            }
            
            /* النجمة المحددة */
            .popup-star.active .star-fill {
                fill: url(#goldGradient);
                stroke: var(--gold-dark);
                stroke-width: 2.5;
            }
            
            .popup-star:active {
                transform: scale(0.9);
            }
            
            .popup-star.active {
                animation: starBounce 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            }
            
            @keyframes starBounce {
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }
            
            .popup-rating-text {
                text-align: center;
                color: var(--gold-dark);
                font-size: 13px;
                font-weight: 600;
                margin-top: 10px;
                min-height: 18px;
            }
            
            /* الحقول - بسيطة */
            .popup-form-group {
                margin-bottom: 12px;
            }
            .popup-form-group input,
            .popup-form-group textarea {
                width: 100%;
                padding: 12px 14px;
                border: 1.5px solid var(--gold-light);
                border-radius: 12px;
                font-size: 14px;
                transition: all 0.25s ease;
                font-family: 'Tajawal', Arial, sans-serif;
                background: #FAF8F3;
                box-sizing: border-box;
            }
            .popup-form-group input:focus,
            .popup-form-group textarea:focus {
                outline: none;
                border-color: var(--gold-medium);
                background: white;
                box-shadow: 0 0 0 3px rgba(201, 166, 81, 0.1);
            }
            .popup-form-group textarea {
                resize: vertical;
                min-height: 70px;
            }
            
            /* الأزرار - بسيطة */
            .popup-btn {
                width: 100%;
                padding: 13px;
                background: linear-gradient(135deg, var(--gold-medium) 0%, var(--gold-dark) 100%);
                color: white;
                border: none;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.25s ease;
                box-shadow: 
                    0 4px 16px rgba(201, 166, 81, 0.3),
                    inset 0 1px 0 rgba(255, 255, 255, 0.2);
                margin-top: 4px;
            }
            .popup-btn:active:not(:disabled) {
                transform: scale(0.98);
            }
            .popup-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            .popup-btn-secondary {
                width: 100%;
                padding: 11px;
                background: transparent;
                color: var(--gold-dark);
                border: 1.5px solid var(--gold-light);
                border-radius: 12px;
                font-size: 13px;
                cursor: pointer;
                margin-top: 8px;
                transition: all 0.2s ease;
            }
            .popup-btn-secondary:active {
                background: rgba(201, 166, 81, 0.08);
                transform: scale(0.98);
            }
            
            /* رسالة النجاح - بسيطة */
            .popup-success {
                text-align: center;
                padding: 30px 20px;
            }
            .popup-success-icon {
                font-size: 60px;
                margin-bottom: 16px;
                animation: successPulse 1s ease;
            }
            @keyframes successPulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            .popup-success h2 {
                color: var(--gold-dark);
                font-size: 22px;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .popup-success p {
                color: #5D4E37;
                font-size: 14px;
                line-height: 1.6;
            }
            
            /* الجوال - من الأسفل */
            @media (max-width: 600px) {
                .popup-overlay {
                    align-items: flex-end;
                    padding: 0;
                }
                
                .popup-container {
                    max-width: 100%;
                    border-radius: 20px 20px 0 0;
                    padding: 24px 20px;
                    max-height: 85vh;
                    overflow-y: auto;
                }
                
                .popup-logo-section img {
                    max-width: 90px;
                }
                
                .popup-title {
                    font-size: 18px;
                }
                
                .popup-subtitle {
                    font-size: 12px;
                }
                
                .popup-star {
                    width: 42px;
                    height: 42px;
                }
                
                .popup-rating-stars {
                    gap: 5px;
                }
            }
            
            /* شاشات صغيرة جداً */
            @media (max-width: 380px) {
                .popup-container {
                    padding: 22px 18px;
                }
                
                .popup-star {
                    width: 38px;
                    height: 38px;
                }
                
                .popup-rating-stars {
                    gap: 4px;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    function setupPopupStars() {
        const stars = document.querySelectorAll('.popup-star');
        const ratingInput = document.getElementById('popupRating');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('popupSubmitBtn');
        let selectedRating = 0;
        
        const labels = {
            1: 'ضعيف جداً 😞',
            2: 'ضعيف 😕',
            3: 'مقبول 😐',
            4: 'جيد 😊',
            5: 'ممتاز 🌟'
        };
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                selectedRating = parseInt(this.dataset.rating);
                ratingInput.value = selectedRating;
                
                // تحديث الشكل
                stars.forEach((s) => {
                    const starRating = parseInt(s.dataset.rating);
                    if (starRating <= selectedRating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
                
                // تحديث النص
                ratingText.textContent = labels[selectedRating];
                submitBtn.disabled = false;
                
                // Haptic feedback
                if (navigator.vibrate) {
                    navigator.vibrate(10);
                }
            });
        });
    }
    
    function setupFormHandlers() {
        const form = document.getElementById('quickRatingForm');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(form);
        });
    }
    
    function submitForm(form) {
        const submitBtn = document.getElementById('popupSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'جاري الإرسال...';
        
        const formData = new FormData(form);
        
        fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            showSuccess();
            setTimeout(() => {
                closeRatingPopup();
            }, 2500);
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.textContent = '✨ إرسال التقييم';
            alert('حدث خطأ. يرجى المحاولة مرة أخرى.');
        });
    }
    
    function showSuccess() {
        document.querySelector('.popup-form').style.display = 'none';
        document.querySelector('.popup-success').style.display = 'block';
    }
    
    window.closeRatingPopup = function() {
        const popup = document.getElementById('ratingPopup');
        if (popup) {
            popup.classList.remove('show');
            document.body.style.overflow = '';
            setTimeout(() => popup.remove(), 300);
        }
    };
    
})();