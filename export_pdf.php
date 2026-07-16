<?php
require_once 'config.php';

if(!isLoggedIn()) {
    redirect('index.php');
}

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

$receipt_id = $_GET['receipt_id'] ?? 0;

$sql = "SELECT r.*, b.booking_id, b.booking_date, b.start_time, b.end_time, 
               b.purpose, b.total_amount, b.booking_status,
               h.hall_name, h.location,
               u.name, u.email, u.phone
        FROM booking_receipts r
        JOIN bookings b ON r.booking_id = b.booking_id
        JOIN halls h ON b.hall_id = h.hall_id
        JOIN users u ON b.user_id = u.user_id
        WHERE r.receipt_id = ?";

$params = [$receipt_id];

if(!isAdmin()) {
    $sql .= " AND b.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receipt = $stmt->fetch();

if(!$receipt) {
    die("Receipt not found.");
}

$html = '
<!DOCTYPE html>
<html>
<head>
<title>Receipt PDF</title>
<style>
@page {
    margin: 0px 0px 0px 0px !important;
    padding: 0px 0px 0px 0px !important;
}
body {
    font-family: DejaVu Sans, sans-serif;
    color: #223830;
    margin: 0;
}
.header {
    background: #271F56;
    color: white;
    padding: 30px;
}
.header h1 {
    margin: 0;
    font-size: 28px;
}
.header p {
    margin: 5px 0 0 0;
}
.content {
    margin: 30px;
}
.receipt-box {
    border: 2px solid #271F56;
    border-radius: 10px;
    padding: 20px;
}
.title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}
.label {
    font-weight: bold;
    width: 35%;
}
.total {
    background: #FFFDF1;
    font-size: 20px;
    font-weight: bold;
}
.footer {
    margin-top: 35px;
    text-align: center;
    font-size: 12px;
    color: #555;
}
</style>
</head>
<body>

<div class="header">
    <h1>Hall Booking System</h1>
    <p>Official Payment Receipt</p>
</div>

<div class="content">
    <div class="receipt-box">
        <div class="title">BOOKING RECEIPT</div>

        <table>
            <tr>
                <td class="label">Receipt No</td>
                <td>'.htmlspecialchars($receipt['receipt_number']).'</td>
            </tr>
            <tr>
                <td class="label">Receipt Date</td>
                <td>'.date('d F Y, h:i A', strtotime($receipt['receipt_date'])).'</td>
            </tr>
            <tr>
                <td class="label">Customer Name</td>
                <td>'.htmlspecialchars($receipt['name']).'</td>
            </tr>
            <tr>
                <td class="label">Email</td>
                <td>'.htmlspecialchars($receipt['email']).'</td>
            </tr>
            <tr>
                <td class="label">Phone</td>
                <td>'.htmlspecialchars($receipt['phone']).'</td>
            </tr>
            <tr>
                <td class="label">Hall</td>
                <td>'.htmlspecialchars($receipt['hall_name']).'</td>
            </tr>
            <tr>
                <td class="label">Location</td>
                <td>'.htmlspecialchars($receipt['location']).'</td>
            </tr>
            <tr>
                <td class="label">Booking Date</td>
                <td>'.date('d F Y', strtotime($receipt['booking_date'])).'</td>
            </tr>
            <tr>
                <td class="label">Booking Time</td>
                <td>'.date('h:i A', strtotime($receipt['start_time'])).' - '.date('h:i A', strtotime($receipt['end_time'])).'</td>
            </tr>
            <tr>
                <td class="label">Purpose</td>
                <td>'.htmlspecialchars($receipt['purpose']).'</td>
            </tr>
            <tr>
                <td class="label">Booking Status</td>
                <td>'.htmlspecialchars($receipt['booking_status']).'</td>
            </tr>
            <tr class="total">
                <td class="label">Amount Paid</td>
                <td>RM '.number_format($receipt['amount_paid'], 2).'</td>
            </tr>
        </table>

        <div class="footer">
            This receipt is computer generated and does not require a signature.<br>
            Thank you for using Hall Booking System.
        </div>
    </div>
</div>

</body>
</html>
';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("receipt_".$receipt['receipt_number'].".pdf", ["Attachment" => false]);
?>