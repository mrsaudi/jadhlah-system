<?php
// test_subscribe.php - ضعه في الجذر واختبر
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار API الإشعارات</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
        }
        .test-box {
            background: #2d2d2d;
            border: 2px solid #00ff00;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        button {
            background: #00ff00;
            color: #000;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
        }
        .result {
            white-space: pre-wrap;
            background: #000;
            padding: 15px;
            margin-top: 10px;
            border-radius: 5px;
        }
        .error { color: #ff0000; }
        .success { color: #00ff00; }
    </style>
</head>
<body>
    <h1>🔍 اختبار API الإشعارات</h1>
    
    <div class="test-box">
        <h3>1️⃣ اختبار subscribe_push.php</h3>
        <p>سيرسل طلب تجريبي للـ API</p>
        <label>رقم العريس: <input type="number" id="groomIdSub" value="1109"></label>
        <button onclick="testSubscribe()">▶ اختبار</button>
        <div id="subscribeResult" class="result"></div>
    </div>
    
    <div class="test-box">
        <h3>2️⃣ اختبار check_groom_ready.php</h3>
        <input type="number" id="groomIdCheck" placeholder="رقم العريس" value="1">
        <button onclick="testCheckReady()">▶ اختبار</button>
        <div id="checkResult" class="result"></div>
    </div>
    
    <div class="test-box">
        <h3>3️⃣ قائمة العرسان من قاعدة البيانات</h3>
        <button onclick="listGrooms()">▶ عرض</button>
        <div id="groomsResult" class="result"></div>
    </div>

    <script>
        async function testSubscribe() {
            const resultDiv = document.getElementById('subscribeResult');
            resultDiv.innerHTML = '⏳ جاري الاختبار...';
            
            const testData = {
                groom_id: 1,
                subscription: {
                    endpoint: 'https://fcm.googleapis.com/fcm/send/test',
                    keys: {
                        p256dh: 'test_key',
                        auth: 'test_auth'
                    }
                }
            };
            
            try {
                const response = await fetch('/api/subscribe_push.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(testData)
                });
                
                const text = await response.text();
                resultDiv.innerHTML = '📥 الاستجابة الخام:\n' + text;
                
                try {
                    const json = JSON.parse(text);
                    resultDiv.innerHTML += '\n\n✅ JSON صحيح:\n' + JSON.stringify(json, null, 2);
                    resultDiv.className = 'result success';
                } catch (e) {
                    resultDiv.innerHTML += '\n\n❌ خطأ في JSON:\n' + e.message;
                    resultDiv.className = 'result error';
                }
            } catch (error) {
                resultDiv.innerHTML = '❌ خطأ في الاتصال:\n' + error.message;
                resultDiv.className = 'result error';
            }
        }
        
        async function testCheckReady() {
            const resultDiv = document.getElementById('checkResult');
            const groomId = document.getElementById('groomIdCheck').value;
            resultDiv.innerHTML = '⏳ جاري الاختبار...';
            
            try {
                const response = await fetch(`/api/check_groom_ready.php?groom_id=${groomId}`);
                const text = await response.text();
                
                resultDiv.innerHTML = '📥 الاستجابة:\n' + text;
                
                try {
                    const json = JSON.parse(text);
                    resultDiv.innerHTML = '✅ JSON:\n' + JSON.stringify(json, null, 2);
                    resultDiv.className = 'result success';
                } catch (e) {
                    resultDiv.innerHTML += '\n\n❌ خطأ في JSON:\n' + e.message;
                    resultDiv.className = 'result error';
                }
            } catch (error) {
                resultDiv.innerHTML = '❌ خطأ:\n' + error.message;
                resultDiv.className = 'result error';
            }
        }
        
        async function listGrooms() {
            const resultDiv = document.getElementById('groomsResult');
            resultDiv.innerHTML = '⏳ جاري التحميل...';
            
            try {
                const response = await fetch('/api/list_grooms.php');
                const text = await response.text();
                
                try {
                    const json = JSON.parse(text);
                    resultDiv.innerHTML = 'قائمة العرسان:\n' + JSON.stringify(json, null, 2);
                    resultDiv.className = 'result success';
                } catch (e) {
                    resultDiv.innerHTML = '❌ خطأ:\n' + text;
                    resultDiv.className = 'result error';
                }
            } catch (error) {
                resultDiv.innerHTML = '❌ خطأ:\n' + error.message;
                resultDiv.className = 'result error';
            }
        }
    </script>
</body>
</html>