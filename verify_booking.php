<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('dashboard.php');
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $booking_id = $_GET['id'];
    $status = $_GET['status'];

    if ($status == 'CONFIRMED' || $status == 'CANCELLED') {
        $stmt = $pdo->prepare("UPDATE bookings SET booking_status=? WHERE booking_id=?");
        $stmt->execute([$status, $booking_id]);
    }
}

redirect('bookings.php');
?>