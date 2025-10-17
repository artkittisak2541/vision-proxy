<?php
// ======================================
// 🚀 Vision Proxy API — Render Edition (Final)
// ======================================

// เปิด output buffer ให้ PHP จัดการ header ได้อย่างปลอดภัย
if (ob_get_level() == 0) ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(25);

// ================================
// 🌐 ถ้าเปิดผ่านเว็บโดยตรง (GET)
// ================================
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
                flex-direction: column;
                justify-content: center;
                align-items: center;
                height: 100vh;
                text-align: center;
            }
            h1 { font-size: 2rem; margin-bottom: 0.4rem; }
            p { font-size: 1.1rem; opacity: 0.9; }
            code { background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>🚀 Vision Proxy API is Running!</h1>
        <p>ส่งคำขอแบบ <b>POST</b> พร้อมไฟล์ <code>slip</code> เพื่อตรวจ OCR</p>
        <p>Endpoint: <code>https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "</code></p>
    </body>
    </html>";
    ob_end_flush();
    exit;
}

// ตั้ง Content-Type สำหรับ API Response
header('Content-Type: application/json; charset=utf-8');

// ================================
// 📁 ตรวจสอบไฟล์สลิป
// ================================
if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Missing slip file']);
    ob_end_flush();
    exit;
}

// ================================
// 🔑 โหลด Google Vision Key
// ================================
$keyJson = getenv('GOOGLE_VISION_KEY');
$keyData = null;

if ($keyJson) {
    $keyData = json_decode($keyJson, true);
} else {
    $keyFile = __DIR__ . '/google-vision-key.json';
    if (file_exists($keyFile)) {
        $keyData = json_decode(file_get_contents($keyFile), true);
    }
}

if (!$keyData || !isset($keyData['private_key'])) {
    echo json_encode(['success' => false, 'error' => 'Missing or invalid Google Vision key']);
    ob_end_flush();
    exit;
}

// ================================
// 🧠 สร้าง JWT สำหรับ OAuth2
// ================================
$token_url = "https://oauth2.googleapis.com/token";
$jwt_header = rtrim(strtr(base64_encode(json_encode(["alg" => "RS256", "typ" => "JWT"])), '+/', '-_'), '=');
$jwt_claim = rtrim(strtr(base64_encode(json_encode([
    "iss" => $keyData["client_email"],
    "scope" => "https://www.googleapis.com/auth/cloud-vision",
    "aud" => $token_url,
    "exp" => time() + 3600,
    "iat" => time()
])), '+/', '-_'), '=');

$private_key = openssl_pkey_get_private($keyData["private_key"]);
if (!$private_key) {
    echo json_encode(['success' => false, 'error' => 'Invalid private key']);
    ob_end_flush();
    exit;
}

openssl_sign("$jwt_header.$jwt_claim", $signature, $private_key, 'sha256WithRSAEncryption');
$jwt = "$jwt_header.$jwt_claim." . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
openssl_free_key($private_key);

// ================================
// 🔑 ขอ Access Token
// ================================
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
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'error' => 'cURL error: '.$err]);
    ob_end_flush();
    exit;
}
if (empty($tokenRes['access_token'])) {
    echo json_encode(['success' => false, 'error' => 'Vision Auth failed', 'debug' => $tokenRes]);
    ob_end_flush();
    exit;
}
$access_token = $tokenRes['access_token'];

// ================================
// 📸 เรียก Google Vision API
// ================================
$image_base64 = base64_encode(file_get_contents($_FILES['slip']['tmp_name']));
$vision_request = [
    "requests" => [[
        "image" => ["content" => $image_base64],
        "features" => [["type" => "DOCUMENT_TEXT_DETECTION"]]
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
    CURLOPT_TIMEOUT => 20,
]);
$response = json_decode(curl_exec($ch), true);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['success' => false, 'error' => 'Vision API connection error: '.$err]);
    ob_end_flush();
    exit;
}

// ================================
// 🧾 ตรวจผล OCR
// ================================
if (empty($response["responses"][0]["textAnnotations"])) {
    echo json_encode(['success' => false, 'error' => 'No text found', 'debug' => $response]);
    ob_end_flush();
    exit;
}

$text = trim($response["responses"][0]["textAnnotations"][0]["description"] ?? '');

echo json_encode([
    'success' => true,
    'text' => $text
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ปิด output buffer อย่างปลอดภัย
ob_end_flush();
?>
