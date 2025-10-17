<?php
// ========================
// 🚀 Vision Proxy API (Render)
// ========================

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
set_time_limit(20);

// ========================
// 🌐 ถ้าเปิดหน้าเว็บโดยตรง (GET)
// ========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html lang='th'>
    <head>
        <meta charset='UTF-8'>
        <title>Vision Proxy API</title>
        <style>
            body {
                background: linear-gradient(135deg, #00b4d8, #0077b6);
                color: white;
                font-family: 'Kanit', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                flex-direction: column;
                text-align: center;
            }
            h1 { font-size: 2rem; margin-bottom: 0.5rem; }
            p { font-size: 1.1rem; opacity: 0.85; }
            code { background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>🚀 Vision Proxy API is Running!</h1>
        <p>ส่งคำขอแบบ <b>POST</b> พร้อมไฟล์ <code>slip</code> เพื่อตรวจ OCR</p>
        <p>Endpoint: <code>https://" . $_SERVER['HTTP_HOST'] . "/ocr</code></p>
    </body>
    </html>";
    exit;
}

// ========================
// 📁 ตรวจสอบไฟล์สลิป
// ========================
if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Missing slip file']);
    exit;
}

// ========================
// 🔑 โหลด Google Vision Key
// ========================
$keyJson = getenv('GOOGLE_VISION_KEY'); // ✅ อ่านจาก Environment Variable ก่อน

if ($keyJson) {
    $keyData = json_decode($keyJson, true);
} else {
    $keyFile = __DIR__ . '/google-vision-key.json';
    if (!file_exists($keyFile)) {
        echo json_encode(['success' => false, 'error' => 'Missing Google Vision key']);
        exit;
    }
    $keyData = json_decode(file_get_contents($keyFile), true);
}

if (!$keyData || !isset($keyData['private_key'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid key format']);
    exit;
}

// ========================
// 🧠 สร้าง JWT สำหรับ OAuth2
// ========================
$token_url = "https://oauth2.googleapis.com/token";

$jwt_header = base64_encode(json_encode(["alg" => "RS256", "typ" => "JWT"]));
$jwt_claim = base64_encode(json_encode([
    "iss" => $keyData["client_email"],
    "scope" => "https://www.googleapis.com/auth/cloud-vision",
    "aud" => $token_url,
    "exp" => time() + 3600,
    "iat" => time()
]));

$private_key = openssl_pkey_get_private($keyData["private_key"]);
openssl_sign("$jwt_header.$jwt_claim", $signature, $private_key, 'sha256WithRSAEncryption');
$jwt = "$jwt_header.$jwt_claim." . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
openssl_free_key($private_key);

// ========================
// 🔑 ขอ Access Token
// ========================
$ch = curl_init($token_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
        "assertion" => $jwt
    ]),
    CURLOPT_TIMEOUT => 15,
]);
$tokenRes = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($tokenRes['access_token'])) {
    echo json_encode(['success' => false, 'error' => 'Vision Auth failed', 'debug' => $tokenRes]);
    exit;
}
$access_token = $tokenRes['access_token'];

// ========================
// 📸 เรียก Google Vision API
// ========================
$image_base64 = base64_encode(file_get_contents($_FILES['slip']['tmp_name']));

$vision_request = [
    "requests" => [[
        "image" => ["content" => $image_base64],
        "features" => [["type" => "TEXT_DETECTION"]]
    ]]
];

$ch = curl_init("https://vision.googleapis.com/v1/images:annotate");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $access_token"
    ],
    CURLOPT_POSTFIELDS => json_encode($vision_request),
    CURLOPT_TIMEOUT => 15,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

// ========================
// 🧾 ประมวลผลผลลัพธ์ OCR
// ========================
if (empty($response["responses"][0]["textAnnotations"])) {
    echo json_encode(['success' => false, 'error' => 'No text found', 'debug' => $response]);
    exit;
}

$text = trim($response["responses"][0]["textAnnotations"][0]["description"] ?? '');

echo json_encode([
    'success' => true,
    'text' => $text
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
