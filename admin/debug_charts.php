<?php
// admin/debug_charts.php - تشخيص مشاكل الرسوم البيانية
header('Content-Type: text/html; charset=utf-8');

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تشخيص الرسوم البيانية</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .test-chart { height: 300px; margin: 20px 0; }
        .debug-info { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { color: #dc3545; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h1>🔍 تشخيص مشاكل الرسوم البيانية</h1>
    
    <div class="debug-info">
        <h3>1. فحص البيانات في قاعدة البيانات</h3>
        <?php
        try {
            // فحص جدول grooms
            $groomsCount = $pdo->query("SELECT COUNT(*) FROM grooms")->fetchColumn();
            echo "<p class='success'>✅ عدد العرسان في قاعدة البيانات: <strong>$groomsCount</strong></p>";
            
            if ($groomsCount > 0) {
                $viewsSum = $pdo->query("SELECT SUM(page_views) FROM grooms")->fetchColumn();
                echo "<p class='success'>✅ إجمالي المشاهدات: <strong>" . number_format($viewsSum ?: 0) . "</strong></p>";
                
                // أحدث 5 عرسان
                $recent = $pdo->query("SELECT groom_name, page_views, created_at FROM grooms ORDER BY created_at DESC LIMIT 5")->fetchAll();
                echo "<p class='success'>✅ أحدث العرسان:</p><ul>";
                foreach ($recent as $groom) {
                    echo "<li>{$groom['groom_name']} - مشاهدات: {$groom['page_views']} - تاريخ: {$groom['created_at']}</li>";
                }
                echo "</ul>";
            } else {
                echo "<p class='warning'>⚠️ لا توجد بيانات عرسان في قاعدة البيانات</p>";
            }
            
            // فحص جدول الإعجابات
            try {
                $likesCount = $pdo->query("SELECT COUNT(*) FROM groom_likes")->fetchColumn();
                echo "<p class='success'>✅ عدد إعجابات العرسان: <strong>$likesCount</strong></p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ جدول groom_likes غير موجود</p>";
            }
            
            try {
                $photoLikesCount = $pdo->query("SELECT COUNT(*) FROM photo_likes")->fetchColumn();
                echo "<p class='success'>✅ عدد إعجابات الصور: <strong>$photoLikesCount</strong></p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ جدول photo_likes غير موجود</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ خطأ في فحص قاعدة البيانات: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="debug-info">
        <h3>2. اختبار get_chart_data.php</h3>
        <button onclick="testChartData()" class="btn btn-primary">اختبار جلب البيانات</button>
        <div id="chartDataResult" class="mt-3"></div>
    </div>
    
    <div class="debug-info">
        <h3>3. اختبار Chart.js</h3>
        <p>اختبار أساسي لمكتبة Chart.js:</p>
        <div class="test-chart">
            <canvas id="testChart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <div class="debug-info">
        <h3>4. معلومات JavaScript</h3>
        <div id="jsInfo"></div>
    </div>
    
    <div class="debug-info">
        <h3>5. اختبار البيانات المباشرة</h3>
        <?php
        // جلب بيانات الأشهر الستة الماضية
        echo "<h5>البيانات الشهرية:</h5>";
        try {
            $monthlyData = $pdo->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as pages,
                    COALESCE(SUM(page_views), 0) as views
                FROM grooms
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY month
                ORDER BY month
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($monthlyData)) {
                echo "<table class='table table-sm'>";
                echo "<thead><tr><th>الشهر</th><th>عدد الصفحات</th><th>المشاهدات</th></tr></thead><tbody>";
                foreach ($monthlyData as $row) {
                    echo "<tr><td>{$row['month']}</td><td>{$row['pages']}</td><td>{$row['views']}</td></tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p class='warning'>⚠️ لا توجد بيانات شهرية</p>";
            }
            
            // أكثر الصفحات مشاهدة
            echo "<h5>أكثر الصفحات مشاهدة:</h5>";
            $topPages = $pdo->query("
                SELECT groom_name, page_views 
                FROM grooms 
                WHERE page_views > 0 
                ORDER BY page_views DESC 
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($topPages)) {
                echo "<table class='table table-sm'>";
                echo "<thead><tr><th>اسم العريس</th><th>المشاهدات</th></tr></thead><tbody>";
                foreach ($topPages as $page) {
                    echo "<tr><td>{$page['groom_name']}</td><td>{$page['page_views']}</td></tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<p class='warning'>⚠️ لا توجد صفحات لها مشاهدات</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ خطأ في جلب البيانات: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
</div>

<script>
// معلومات JavaScript
document.getElementById('jsInfo').innerHTML = `
    <p><strong>Chart.js متاح:</strong> ${typeof Chart !== 'undefined' ? '✅ نعم' : '❌ لا'}</p>
    <p><strong>إصدار المتصفح:</strong> ${navigator.userAgent}</p>
    <p><strong>يدعم ES6:</strong> ${typeof Promise !== 'undefined' ? '✅ نعم' : '❌ لا'}</p>
`;

// اختبار Chart.js الأساسي
if (typeof Chart !== 'undefined') {
    const ctx = document.getElementById('testChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو'],
                datasets: [{
                    label: 'اختبار البيانات',
                    data: [12, 19, 3, 5, 2],
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        
        document.getElementById('jsInfo').innerHTML += '<p class="success">✅ Chart.js يعمل بشكل صحيح</p>';
    }
} else {
    document.getElementById('jsInfo').innerHTML += '<p class="error">❌ Chart.js غير محمل</p>';
}

// اختبار get_chart_data.php
function testChartData() {
    const resultDiv = document.getElementById('chartDataResult');
    resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> جاري الاختبار...';
    
    fetch('get_chart_data.php')
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>✅ نجح جلب البيانات!</strong><br>
                        <strong>الحالة:</strong> ${data.success ? 'نجح' : 'فشل'}<br>
                        <strong>عدد التسميات:</strong> ${data.chartData?.labels?.length || 0}<br>
                        <strong>عدد المشاهدات:</strong> ${data.chartData?.views?.length || 0}<br>
                        <strong>عدد أكثر الصفحات:</strong> ${data.chartData?.topLabels?.length || 0}
                    </div>
                    <details>
                        <summary>عرض البيانات الكاملة</summary>
                        <pre class="mt-2">${JSON.stringify(data, null, 2)}</pre>
                    </details>
                `;
            } catch (e) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>❌ خطأ في تحليل JSON:</strong> ${e.message}<br>
                        <strong>الاستجابة:</strong><br>
                        <pre>${text}</pre>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <strong>❌ خطأ في الطلب:</strong> ${error.message}
                </div>
            `;
        });
}

// اختبار تلقائي عند تحميل الصفحة
window.addEventListener('load', function() {
    setTimeout(() => {
        testChartData();
    }, 1000);
});
</script>
</body>
</html>