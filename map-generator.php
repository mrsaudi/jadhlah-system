<?php
/**
 * مولد خريطة مشروع مُلخصة - استبعاد الملفات الهامشية
 */

header('Content-Type: application/json; charset=utf-8');

// قائمة الاستبعاد
$excludeDirs = [
    'node_modules', 'vendor', '.git', '.svn', '.idea', '.vscode',
    'cache', 'tmp', 'temp', 'logs', 'backups', 'backup'
];

$excludeFiles = [
    '.DS_Store', 'Thumbs.db', '.gitignore', '.htaccess',
    'error_log', 'debug.log', '.env.example'
];

$excludeExtensions = [
    'log', 'tmp', 'cache', 'bak', 'swp', 'swo'
];

function shouldExclude($item, $isDir, $excludeDirs, $excludeFiles, $excludeExtensions) {
    // استبعاد الملفات/المجلدات المخفية
    if ($item[0] === '.') return true;
    
    if ($isDir) {
        return in_array(strtolower($item), array_map('strtolower', $excludeDirs));
    }
    
    // استبعاد الملفات المحددة
    if (in_array($item, $excludeFiles)) return true;
    
    // استبعاد حسب الامتداد
    $ext = pathinfo($item, PATHINFO_EXTENSION);
    return in_array(strtolower($ext), $excludeExtensions);
}

function scanDir($path, $basePath = null) {
    global $excludeDirs, $excludeFiles, $excludeExtensions;
    
    if ($basePath === null) $basePath = $path;
    
    $result = [];
    $items = @scandir($path);
    
    if ($items === false) return $result;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $path . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);
        
        // تطبيق الاستبعاد
        if (shouldExclude($item, $isDir, $excludeDirs, $excludeFiles, $excludeExtensions)) {
            continue;
        }
        
        if ($isDir) {
            $children = scanDir($fullPath, $basePath);
            if (!empty($children)) {
                $result[$item] = $children;
            } else {
                $result[] = $item; // مجلد فارغ
            }
        } else {
            $result[] = $item; // ملف فقط
        }
    }
    
    return $result;
}

$map = [
    'project' => basename(__DIR__),
    'date' => date('Y-m-d H:i'),
    'files' => scanDir(__DIR__)
];

echo json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>