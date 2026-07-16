<?php
include 'partials/header.php';
if(!isLoggedIn()) redirect('index.php');

$sql = "SELECT r.*, b.booking_date, h.hall_name, u.name FROM booking_receipts r
JOIN bookings b ON r.booking_id=b.booking_id
JOIN halls h ON b.hall_id=h.hall_id
JOIN users u ON b.user_id=u.user_id";

if(!isAdmin()) $sql .= " WHERE b.user_id=" . intval($_SESSION['user_id']);
$sql .= " ORDER BY r.receipt_id DESC";
$receipts = $pdo->query($sql)->fetchAll();
?>

<h2 class="mb-3">Booking Receipts</h2>
<div class="table-responsive card p-3">
<table class="table">
<thead>
<tr>
<th>Receipt No</th>
<th>Customer</th>
<th>Hall</th>
<th>Date</th>
<th>Amount</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach($receipts as $r): ?>
<tr>
<td><?= htmlspecialchars($r['receipt_number']) ?></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= htmlspecialchars($r['hall_name']) ?></td>
<td><?= htmlspecialchars($r['receipt_date']) ?></td>
<td>RM <?= number_format($r['amount_paid'],2) ?></td>
<td>
    <a href="export_pdf.php?receipt_id=<?= $r['receipt_id'] ?>" class="btn btn-sm btn-primary">
        Print Receipt
    </a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php include 'partials/footer.php'; ?>
