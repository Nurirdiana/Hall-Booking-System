<?php
require_once 'config.php';

if (isset($_GET['error'])) {
    redirect('index.php');
}

$code = $_GET['code'] ?? null;
if (!$code) {
    redirect('index.php');
}

$tokenUrl = 'https://oauth2.googleapis.com/token';
$response = fetchAccessToken($tokenUrl, [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
]);

if (!$response || !isset($response['access_token'])) {
    redirect('index.php');
}

$accessToken = $response['access_token'];
$userInfo = fetchGoogleUserInfo($accessToken);
if (!$userInfo || !isset($userInfo['email'])) {
    redirect('index.php');
}

$email = $userInfo['email'];
$name = $userInfo['name'] ?? $userInfo['email'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, '', 'CUSTOMER', 'ACTIVE')");
    $stmt->execute([$name, $email]);
    $user_id = $pdo->lastInsertId();
    $role = 'CUSTOMER';
} else {
    $user_id = $user['user_id'];
    $role = $user['role'];
}

$_SESSION['user_id'] = $user_id;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;

redirect('dashboard.php');

function fetchAccessToken($url, $params) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function fetchGoogleUserInfo($accessToken) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}
