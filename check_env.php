<?php
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Render Environment Test ===\n\n";

$key = getenv('GOOGLE_VISION_KEY');

if (!$key) {
    echo "❌ ไม่พบ Environment Variable: GOOGLE_VISION_KEY\n";
    echo "กรุณาไปที่ Render → Environment แล้วเพิ่มค่าใหม่:\n";
    echo "KEY = GOOGLE_VISION_KEY\n";
    echo "VALUE = (เนื้อหาไฟล์ service account JSON)\n";
} else {
    echo "✅ พบ GOOGLE_VISION_KEY แล้ว!\n";
    echo "📄 ตัวอย่างเนื้อหาบางส่วน:\n";
    echo substr($key, 0, 400) . "...\n";
}
?>
