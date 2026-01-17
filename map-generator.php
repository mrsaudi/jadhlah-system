<?php
/**
 * مولد خريطة المشروع - JSON Format
 * الاستخدام: افتح الملف في المتصفح مباشرة
 */

header('Content-Type: application/json; charset=utf-8');

function scanDirectory($path, $basePath = null) {
    if ($basePath === null) {
        $basePath = $path;
    }
    
    $result = [];
    
    // التأكد من وجود المجلد وإمكانية قراءته
    if (!is_dir($path) || !is_readable($path)) {
        return $result;
    }
    
    $items = @scandir($path);
    
    if ($items === false) {
        return $result;
    }
    
    foreach ($items as $item) {
        // تجاهل . و ..
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $fullPath);
        
        $itemData = [
            'name' => $item,
            'path' => $relativePath,
            'type' => is_dir($fullPath) ? 'directory' : 'file'
        ];
        
        // إذا كان مجلد، اقرأ محتوياته
        if (is_dir($fullPath)) {
            $children = scanDirectory($fullPath, $basePath);
            if (!empty($children)) {
                $itemData['children'] = $children;
            }
        } else {
            // إضافة حجم الملف
            $itemData['size'] = @filesize($fullPath);
            
            // إضافة امتداد الملف
            $ext = pathinfo($item, PATHINFO_EXTENSION);
            if ($ext) {
                $itemData['extension'] = $ext;
            }
        }
        
        $result[] = $itemData;
    }
    
    return $result;
}

// بداية المسح من المجلد الحالي
$startPath = __DIR__;

$projectMap = [
    'generated_at' => date('Y-m-d H:i:s'),
    'root_path' => basename($startPath),
    'structure' => scanDirectory($startPath)
];

// طباعة النتيجة بصيغة JSON منسقة
echo json_encode($projectMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
```

---

### **الخطوة 3: التنفيذ**
1. احفظ الملف
2. افتح المتصفح واكتب:
```
   https://موقعك.com/map-generator.php