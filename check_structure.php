<?php
// check_structure.php
echo "<h3>📁 Current File Structure - Kibenes eBirth</h3>";
echo "<pre>";

function listFiles($dir = '.', $indent = 0) {
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        $icon = is_dir($path) ? '📁' : '📄';
        
        echo str_repeat('  ', $indent) . $icon . ' ' . $item . "\n";
        
        if (is_dir($path) && $indent < 3) { // Limit depth to 3 levels
            listFiles($path, $indent + 1);
        }
    }
}

listFiles('.');
echo "</pre>";

// Specific check for dompdf
echo "<h3>🔍 Specific Dompdf Check:</h3>";
$dompdf_paths = [
    'includes/dompdf/',
    'vendor/dompdf/dompdf/', 
    'dompdf/',
    '../includes/dompdf/'
];

foreach ($dompdf_paths as $path) {
    if (file_exists($path)) {
        echo "✅ FOUND: $path<br>";
        // Check important files
        $autoload = $path . 'autoload.inc.php';
        $src = $path . 'src/';
        if (file_exists($autoload)) echo "&nbsp;&nbsp;✅ autoload.inc.php exists<br>";
        if (file_exists($src)) echo "&nbsp;&nbsp;✅ src/ folder exists<br>";
    } else {
        echo "❌ NOT FOUND: $path<br>";
    }
}
?>