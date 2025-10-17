<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== Render ENV Test ===\n\n";

$key = getenv('GOOGLE_VISION_KEY');

if (!$key) {
    echo "❌ ไม่พบ Environment Variable: GOOGLE_VISION_KEY\n";
} else {
    echo "✅ พบ GOOGLE_VISION_KEY แล้ว\n";
    echo substr($key, 0, 200) . "...\n"; // แสดงแค่บางส่วน
}
?>
