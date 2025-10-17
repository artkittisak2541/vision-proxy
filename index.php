<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
set_time_limit(30);

// =========================
// 🌐 ถ้าเปิดหน้าเว็บโดยตรง (GET)
// =========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html lang='th'>
    <head>
        <meta charset='UTF-8'>
        <title>OCR.space Proxy API</title>
        <style>
            body {
                background: linear-gradient(135deg, #00b4d8, #0077b6);
                color: white;
                font-family: 'Kanit', sans-serif;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                height: 100vh;
                text-align: center;
                margin: 0;
            }
            h1 { font-size: 2rem; margin-bottom: 0.6rem; }
            p { font-size: 1.1rem; opacity: 0.9; }
            code { background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>🚀 OCR.space Proxy API is Running!</h1>
        <p>ส่งคำขอแบบ <b>POST</b> พร้อมไฟล์ <code>slip</code> เพื่อตรวจ OCR ฟรี</p>
        <p>Endpoint: <code>https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "</code></p>
    </body>
    </html>";
    exit;
}

// =========================
// 📁 ตรวจสอบไฟล์
// =========================
if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Missing slip file']);
    exit;
}

// =========================
// 🔄 เรียก OCR.space API (ฟรี)
// =========================
$image_path = $_FILES['slip']['tmp_name'];
$api_key = "helloworld"; // 🔑 key ฟรีจาก OCR.space

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.ocr.space/parse/image",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "apikey" => $api_key,
        "language" => "tha",         // รองรับภาษาไทย
        "isOverlayRequired" => false,
        "scale" => true,             // 🔹 ขยายภาพให้ชัดก่อน OCR
        "isTable" => false,          // 🔹 ไม่โฟกัสตาราง
        "OCREngine" => 2,            // 🔹 ใช้ engine ใหม่ (แม่นกว่า)
        "file" => new CURLFile($image_path)
    ],
    CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

// =========================
// ⚠️ ตรวจสอบการเชื่อมต่อ
// =========================
if ($err) {
    echo json_encode([
        'success' => false,
        'error' => "cURL Error: $err"
    ]);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON response',
        'raw' => substr($response, 0, 500)
    ]);
    exit;
}

// =========================
// 🧠 ตรวจผล OCR
// =========================
if (
    empty($data["ParsedResults"]) ||
    empty($data["ParsedResults"][0]["ParsedText"])
) {
    echo json_encode([
        'success' => false,
        'error' => 'No text found',
        'debug' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// =========================
// ✅ ส่งผลลัพธ์กลับ
// =========================
$text = trim($data["ParsedResults"][0]["ParsedText"]);
echo json_encode([
    'success' => true,
    'text' => $text
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
