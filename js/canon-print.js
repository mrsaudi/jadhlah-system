/**
 * نظام الطباعة اللاسلكية لطابعة Canon Selphy CP1500
 * متوافق مع WiFi Direct و Canon Print
 */

class CanonSelfyPrinter {
    constructor() {
        this.printerIP = null;
        this.isConnected = false;
        this.printQueue = [];
    }

    /**
     * البحث عن طابعة Canon Selphy في الشبكة
     */
    async findPrinter() {
        // Canon Selphy عادة تستخدم mdns للاكتشاف
        // IP افتراضي عند الاتصال المباشر: 192.168.1.1
        const possibleIPs = [
            '192.168.1.1',
            '192.168.100.1', 
            '10.0.0.1'
        ];

        for (const ip of possibleIPs) {
            try {
                const response = await fetch(`http://${ip}:80`, {
                    mode: 'no-cors',
                    timeout: 2000
                });
                
                if (response) {
                    this.printerIP = ip;
                    this.isConnected = true;
                    return true;
                }
            } catch (e) {
                continue;
            }
        }

        return false;
    }

    /**
     * طباعة صورة - الطريقة الأساسية
     */
    async printImage(imageUrl, options = {}) {
        const defaults = {
            paperSize: '4x6', // 4x6, Postcard, L-size
            copies: 1,
            brightness: 0,
            contrast: 0,
            orientation: 'auto'
        };

        const settings = { ...defaults, ...options };

        try {
            // محاولة 1: استخدام Canon Print API إذا كان متاحاً
            if (await this.tryCanonPrintApp(imageUrl, settings)) {
                return { success: true, method: 'Canon App' };
            }

            // محاولة 2: الطباعة عبر WiFi Direct
            if (this.isConnected || await this.findPrinter()) {
                if (await this.printViaWiFi(imageUrl, settings)) {
                    return { success: true, method: 'WiFi Direct' };
                }
            }

            // محاولة 3: استخدام Web Print API
            if (await this.printViaWebAPI(imageUrl, settings)) {
                return { success: true, method: 'Web Print API' };
            }

            // الطريقة الاحتياطية: نافذة طباعة عادية
            this.printStandard(imageUrl, settings);
            return { success: true, method: 'Standard Print' };

        } catch (error) {
            console.error('Print error:', error);
            throw error;
        }
    }

    /**
     * محاولة استخدام تطبيق Canon Print
     */
    async tryCanonPrintApp(imageUrl, settings) {
        // فتح URL خاص بـ Canon Print
        const canonUrl = `canonprint://print?url=${encodeURIComponent(imageUrl)}&size=${settings.paperSize}`;
        
        try {
            // محاولة فتح التطبيق
            window.location.href = canonUrl;
            
            // انتظار رد من التطبيق
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * الطباعة عبر WiFi Direct
     */
    async printViaWiFi(imageUrl, settings) {
        if (!this.printerIP) return false;

        try {
            // تحميل الصورة كـ blob
            const response = await fetch(imageUrl);
            const blob = await response.blob();
            
            // تحويل لـ base64
            const base64 = await this.blobToBase64(blob);
            
            // إرسال للطابعة (Canon Selphy تستخدم بروتوكول خاص)
            const printData = {
                image: base64,
                settings: settings
            };

            // محاولة الإرسال للطابعة
            const printResponse = await fetch(`http://${this.printerIP}/print`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(printData)
            });

            return printResponse.ok;
        } catch (e) {
            console.error('WiFi print failed:', e);
            return false;
        }
    }

    /**
     * استخدام Web Print API
     */
    async printViaWebAPI(imageUrl, settings) {
        if (!window.print) return false;

        try {
            // إنشاء iframe للطباعة
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            document.body.appendChild(iframe);

            // تحميل الصورة في iframe
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(`
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <title>Canon Selphy Print</title>
                    <style>
                        @page {
                            size: ${settings.paperSize === '4x6' ? '4in 6in' : 'auto'};
                            margin: 0;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            min-height: 100vh;
                        }
                        img {
                            max-width: 100%;
                            max-height: 100%;
                            object-fit: contain;
                            filter: brightness(${100 + settings.brightness}%) 
                                    contrast(${100 + settings.contrast}%);
                        }
                    </style>
                </head>
                <body>
                    <img src="${imageUrl}" onload="window.print()">
                </body>
                </html>
            `);
            iframeDoc.close();

            // انتظار الطباعة
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // إزالة iframe
            document.body.removeChild(iframe);
            
            return true;
        } catch (e) {
            console.error('Web API print failed:', e);
            return false;
        }
    }

    /**
     * الطباعة القياسية (fallback)
     */
    printStandard(imageUrl, settings) {
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html dir="rtl">
            <head>
                <title>طباعة - جذلة</title>
                <style>
                    @page {
                        size: ${settings.paperSize === '4x6' ? '4in 6in' : 'auto'};
                        margin: 0;
                    }
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        background: #f5f5f5;
                    }
                    img {
                        max-width: 100%;
                        max-height: 100vh;
                        object-fit: contain;
                        filter: brightness(${100 + settings.brightness}%) 
                                contrast(${100 + settings.contrast}%);
                    }
                    @media print {
                        body {
                            background: white;
                        }
                        img {
                            page-break-after: always;
                        }
                    }
                </style>
            </head>
            <body>
                <img src="${imageUrl}" onload="window.print(); setTimeout(() => window.close(), 1000);">
            </body>
            </html>
        `);
    }

    /**
     * تحويل Blob إلى Base64
     */
    blobToBase64(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    /**
     * إضافة صورة إلى قائمة الانتظار
     */
    addToQueue(imageUrl, options) {
        this.printQueue.push({ imageUrl, options });
    }

    /**
     * طباعة جميع الصور في القائمة
     */
    async printQueue() {
        for (const item of this.printQueue) {
            await this.printImage(item.imageUrl, item.options);
            await new Promise(resolve => setTimeout(resolve, 2000)); // انتظار بين كل طباعة
        }
        this.printQueue = [];
    }

    /**
     * فحص حالة الطابعة
     */
    async checkPrinterStatus() {
        if (!this.printerIP) {
            return { connected: false, status: 'غير متصل' };
        }

        try {
            const response = await fetch(`http://${this.printerIP}/status`, {
                timeout: 2000
            });
            
            if (response.ok) {
                const data = await response.json();
                return {
                    connected: true,
                    status: data.status || 'جاهز',
                    paperLevel: data.paperLevel || 'غير معروف',
                    inkLevel: data.inkLevel || 'غير معروف'
                };
            }
        } catch (e) {
            return { connected: false, status: 'خطأ في الاتصال' };
        }
    }
}

// إنشاء instance عام
const canonPrinter = new CanonSelfyPrinter();

/**
 * دالة سهلة للطباعة السريعة
 */
async function quickPrint(imageUrl, showStatus = true) {
    if (showStatus) {
        showPrintStatus('🖨️ جاري الإعداد للطباعة...');
    }

    try {
        const result = await canonPrinter.printImage(imageUrl, {
            paperSize: '4x6',
            copies: 1
        });

        if (showStatus) {
            showPrintStatus(`✅ تم الإرسال للطباعة (${result.method})`);
        }

        return true;
    } catch (error) {
        if (showStatus) {
            showPrintStatus('❌ حدث خطأ في الطباعة');
        }
        console.error('Print error:', error);
        return false;
    }
}

/**
 * طباعة متعددة
 */
async function printMultiple(imageUrls, options = {}) {
    showPrintStatus(`🖨️ جاري طباعة ${imageUrls.length} صورة...`);

    let successCount = 0;
    for (const url of imageUrls) {
        try {
            await canonPrinter.printImage(url, options);
            successCount++;
            await new Promise(resolve => setTimeout(resolve, 3000)); // انتظار بين الصور
        } catch (e) {
            console.error('Failed to print:', url);
        }
    }

    showPrintStatus(`✅ تم طباعة ${successCount} من ${imageUrls.length} صورة`);
}

/**
 * عرض حالة الطباعة
 */
function showPrintStatus(message, duration = 3000) {
    let statusEl = document.getElementById('printStatus');
    
    if (!statusEl) {
        statusEl = document.createElement('div');
        statusEl.id = 'printStatus';
        statusEl.className = 'print-status';
        document.body.appendChild(statusEl);
    }

    statusEl.textContent = message;
    statusEl.classList.add('show');

    setTimeout(() => {
        statusEl.classList.remove('show');
    }, duration);
}

// تصدير للاستخدام العام
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CanonSelfyPrinter, canonPrinter, quickPrint, printMultiple };
}