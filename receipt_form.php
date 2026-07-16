<?php
include 'partials/header.php';
if(!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

$booking_id = $_GET['booking_id'] ?? '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receipt_no = "RCPT-" . date("YmdHis");
    $stmt = $pdo->prepare("INSERT INTO booking_receipts (booking_id, amount_paid, receipt_number) VALUES (?,?,?)");
    $stmt->execute([$_POST['booking_id'], $_POST['amount_paid'], $receipt_no]);
    redirect('receipts.php');
}

$stmt = $pdo->prepare("SELECT total_amount FROM bookings WHERE booking_id=?");
$stmt->execute([$booking_id]);
$total = $stmt->fetchColumn();
?>
<div class="card p-4">
<h2>Generate Booking Receipt</h2>
<form method="POST">
    <label>Booking ID</label>
    <input name="booking_id" class="form-control mb-2" value="<?= htmlspecialchars($booking_id) ?>" required>
    <label>Amount Paid</label>
    <input type="number" step="0.01" name="amount_paid" class="form-control mb-3" value="<?= htmlspecialchars($total) ?>" required>
    <button class="btn btn-success">Generate Receipt</button>
</form>
</div>
<?php include 'partials/footer.php'; ?>
