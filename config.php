<?php
session_start();

$host = "localhost";
$dbname = "hall_booking_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// TODO: Replace these values with your Google OAuth client details.
// Create credentials at https://console.cloud.google.com/apis/credentials
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
// Redirect URI must match the Google Cloud OAuth client setting exactly.
define('GOOGLE_REDIRECT_URI', 'http://localhost/hallsystem/google_callback.php');

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';
}

function redirect($url) {
    header("Location: $url");
    exit;
}
?>
