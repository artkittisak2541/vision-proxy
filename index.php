<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid method']);
    exit;
}

// ตรวจสอบการอัปโหลด
if (!isset($_FILES['slip'])) {
    echo json_encode(['error' => 'Missing slip file']);
    exit;
}

// โหลด Key
$keyFile = __DIR__ . '/google-vision-key.json';
if (!file_exists($keyFile)) {
    echo json_encode(['error' => 'Missing Google Vision key']);
    exit;
}
$keyData = json_decode(file_get_contents($keyFile), true);

// อ่านภาพ base64
$image_base64 = base64_encode(file_get_contents($_FILES['slip']['tmp_name']));

// ขอ Token
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

// เรียก Vision API
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

$text = $response["responses"][0]["textAnnotations"][0]["description"] ?? '';
echo json_encode([
    'success' => true,
    'text' => $text
]);
