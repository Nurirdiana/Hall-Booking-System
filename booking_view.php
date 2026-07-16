<?php
include 'partials/header.php';

if(!isLoggedIn()) {
    redirect('index.php');
}

$id = $_GET['id'] ?? 0;

$sql = "SELECT b.*, u.name, u.email, h.hall_name, h.location 
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN halls h ON b.hall_id = h.hall_id
        WHERE b.booking_id = ?";

$params = [$id];

if(!isAdmin()) {
    $sql .= " AND b.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$b = $stmt->fetch();

if(!$b) {
    echo "<div class='alert alert-danger'>Booking not found or you are not allowed to view this booking.</div>";
    include 'partials/footer.php';
    exit;
}
?>

<div class="card p-4">
    <h2>Booking Detail</h2>

    <p><strong>Customer:</strong> 
        <?= htmlspecialchars($b['name']) ?> 
        (<?= htmlspecialchars($b['email']) ?>)
    </p>

    <p><strong>Hall:</strong> 
        <?= htmlspecialchars($b['hall_name']) ?>, 
        <?= htmlspecialchars($b['location']) ?>
    </p>

    <p><strong>Date:</strong> 
        <?= htmlspecialchars($b['booking_date']) ?>
    </p>

    <p><strong>Time:</strong> 
        <?= htmlspecialchars($b['start_time']) ?> - 
        <?= htmlspecialchars($b['end_time']) ?>
    </p>

    <p><strong>Purpose:</strong> 
        <?= htmlspecialchars($b['purpose']) ?>
    </p>

    <p><strong>Total Amount:</strong> 
        RM <?= number_format($b['total_amount'], 2) ?>
    </p>

    <p><strong>Status:</strong> 
        <span class="badge-status <?= strtolower($b['booking_status']) ?>">
            <?= htmlspecialchars($b['booking_status']) ?>
        </span>
    </p>

    <div class="d-flex flex-wrap gap-2">
        <a href="bookings.php" class="btn btn-secondary">Back</a>

        <?php if(isAdmin()): ?>
            <?php if($b['booking_status'] === 'PENDING'): ?>
                <a href="verify_booking.php?id=<?= $b['booking_id'] ?>&status=CONFIRMED" class="btn btn-success">Approve</a>
                <a href="verify_booking.php?id=<?= $b['booking_id'] ?>&status=CANCELLED" class="btn btn-danger">Reject</a>
            <?php endif; ?>

            <?php if($b['booking_status'] === 'CONFIRMED'): ?>
                <a href="receipt_form.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-primary">Generate Receipt</a>
            <?php endif; ?>

            <?php if($b['booking_status'] !== 'CONFIRMED'): ?>
                <a href="booking_form.php?id=<?= $b['booking_id'] ?>" class="btn btn-warning">Edit</a>
                <a href="bookings.php?delete=<?= $b['booking_id'] ?>" onclick="return confirm('Delete booking?')" class="btn btn-danger">Delete</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'partials/footer.php'; ?>