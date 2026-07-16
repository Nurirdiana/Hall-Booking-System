<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'offline',
    'prompt' => 'select_account'
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit;
