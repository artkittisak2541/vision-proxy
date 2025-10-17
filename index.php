<?php
// กำหนด Header
header('Content-Type: application/json; charset=utf-8');

// ปิด Warning ที่ไม่จำเป็น
error_reporting(0);

// 🌐 หากเป็น GET (เปิดเว็บผ่านเบราว์เซอร์)
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
            }
            h1 { font-size: 2rem; margin-bottom: 1rem; }
            p { font-size: 1.2rem; opacity: 0.8; }
        </style>
    </head>
    <body>
        <h1>🚀 Vision Proxy API is Running!</h1>
        <p>ส่งคำขอแบบ POST พร้อมไฟล์ slip เพื่อตรวจ OCR</p>
        <p>URL: <b>https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "</b></p>
    </body>
    </html>";
    exit;
}

// 🔒 ตรวจสอบการอัปโหลดไฟล์
if (!isset($_FILES['slip'])) {
    echo json_encode(['error' => 'Missing slip file']);
    exit;
}

// 🔑 โหลด Google Vision Key
$keyFile = __DIR__ . '/google-vision-key.json';
if (!file_exists($keyFile)) {
    echo json_encode(['error' => 'Missing Google Vision key']);
    exit;
}
$keyData = json_decode(file_get_contents($keyFile), true);
if (!$keyData) {
    echo json_encode(['error' => 'Invalid key file format']);
    exit;
}

// 🔄 อ่านภาพ Base64
$image_base64 = base64_encode(file_get_contents($_FILES['slip']['tmp_name']));

// 🧠 ขอ Access Token
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
$jwt = "$jwt_header.$jwt_claim." . base64_encode($signature);
openssl_free_key($private_key);

$ch = curl_init($token_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
        "assertion" => $jwt
    ])
]);
$tokenRes = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($tokenRes['access_token'])) {
    echo json_encode(['error' => 'Vision Auth failed', 'debug' => $tokenRes]);
    exit;
}

$access_token = $tokenRes['access_token'];

// 📸 เรียก Google Vision API
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
    CURLOPT_POSTFIELDS => json_encode($vision_request)
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($response["responses"][0]["textAnnotations"])) {
    echo json_encode(['error' => 'No text found', 'debug' => $response]);
    exit;
}

$text = trim($response["responses"][0]["textAnnotations"][0]["description"] ?? '');

echo json_encode([
    'success' => true,
    'text' => $text
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
