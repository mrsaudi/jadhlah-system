<?php
/**
 * مولد خريطة المشروع - نسخة آمنة
 */

// إيقاف عرض الأخطاء للمستخدم (لكن تسجيلها)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// زيادة حدود التنفيذ
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '60');

header('Content-Type: application/json; charset=utf-8');

try {
    // قائمة الاستبعاد
    $exclude = [
        'dirs' => ['node_modules', 'vendor', '.git', 'cache', 'tmp', 'logs', 'backups'],
        'files' => ['.DS_Store', 'Thumbs.db', '.gitignore', 'error_log'],
        'ext' => ['log', 'tmp', 'cache', 'bak']
    ];

    function scan($path, &$exclude, $base = null) {
        if (!$base) $base = $path;
        
        $result = [];
        
        if (!is_readable($path)) return $result;
        
        $items = @scandir($path);
        if (!$items) return $result;
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if ($item[0] === '.') continue; // تجاهل المخفي
            
            $full = $path . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($full);
            
            // فحص الاستبعاد
            if ($isDir && in_array(strtolower($item), $exclude['dirs'])) continue;
            if (!$isDir && in_array($item, $exclude['files'])) continue;
            
            $ext = pathinfo($item, PATHINFO_EXTENSION);
            if ($ext && in_array(strtolower($ext), $exclude['ext'])) continue;
            
            if ($isDir) {
                $sub = scan($full, $exclude, $base);
                if (!empty($sub)) {
                    $result[$item] = $sub;
                } else {
                    $result[] = $item;
                }
            } else {
                $result[] = $item;
            }
        }
        
        return $result;
    }

    $data = [
        'project' => basename(__DIR__),
        'date' => date('Y-m-d H:i'),
        'tree' => scan(__DIR__, $exclude)
    ];

    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'فشل في قراءة المجلدات',
        'details' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>