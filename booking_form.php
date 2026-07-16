<?php
include 'partials/header.php';

if(!isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$id = $_GET['id'] ?? null;
$selectedHall = $_GET['hall_id'] ?? '';

$booking = [
    'hall_id' => $selectedHall,
    'booking_date' => '',
    'start_time' => '',
    'end_time' => '',
    'purpose' => '',
    'booking_status' => 'PENDING'
];

$halls = $pdo->query("SELECT * FROM halls WHERE status='ACTIVE'")->fetchAll();

$error = '';
$bookedDates = [];
$bookingsQuery = $pdo->prepare("SELECT hall_id, booking_date FROM bookings WHERE booking_status IN ('PENDING','CONFIRMED') AND booking_date >= CURDATE()" . ($id ? " AND booking_id != ?" : "") . " ORDER BY booking_date");
$paramsForDates = $id ? [$id] : [];
$bookingsQuery->execute($paramsForDates);
foreach ($bookingsQuery->fetchAll() as $booked) {
    $bookedDates[$booked['hall_id']][] = $booked['booking_date'];
}

/* GET BOOKING DATA FOR EDIT */
if($id) {

    if(isAdmin()) {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
    }

    $booking = $stmt->fetch();

    if(!$booking) {
        echo "<div class='alert alert-danger'>Booking not found or access denied.</div>";
        include 'partials/footer.php';
        exit;
    }
}

/* SAVE BOOKING */
if($_SERVER['REQUEST_METHOD'] === 'POST') {

    $hall_id = $_POST['hall_id'];
    $booking_date = $_POST['booking_date'];
    $minBookingDate = date('Y-m-d', strtotime('+1 day'));

if ($booking_date < $minBookingDate) {
    $error = "Booking date must be from tomorrow onwards. You cannot book today or past dates.";
}
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    if ($start_time < '08:00' || $start_time > '23:00') {
    $error = 'Booking start time must be between 8:00 AM and 11:00 PM only.';
}
    $purpose = trim($_POST['purpose']);

    if (strtotime($start_time) >= strtotime($end_time)) {
        $error = 'End time must be later than start time.';
    }

    $stmtHall = $pdo->prepare("SELECT hourly_rate FROM halls WHERE hall_id = ?");
    $stmtHall->execute([$hall_id]);
    $rate = $stmtHall->fetchColumn();

    $total = max(1, (strtotime($end_time) - strtotime($start_time)) / 3600) * $rate;

    $status = isAdmin() ? $_POST['booking_status'] : 'PENDING';

    if ($error === '') {
        $stmtCheck = $pdo->prepare(
    "SELECT COUNT(*) 
     FROM bookings 
     WHERE hall_id = ? 
     AND booking_date = ? 
     AND booking_id <> ?
     AND booking_status IN ('PENDING','CONFIRMED')"
);
       $stmtCheck->execute([
    $hall_id,
    $booking_date,
    $id ?: 0
]);

        if ($stmtCheck->fetchColumn() > 0) {
            $error = 'The selected date and time slot is already booked. Please choose another time.';
        }
    }

    if ($error === '') {
        if($id) {

            if(isAdmin()) {
                $stmt = $pdo->prepare("
                    UPDATE bookings 
                    SET hall_id = ?, 
                        booking_date = ?, 
                        start_time = ?, 
                        end_time = ?, 
                        purpose = ?, 
                        total_amount = ?, 
                        booking_status = ? 
                    WHERE booking_id = ?
                ");

                $stmt->execute([
                    $hall_id,
                    $booking_date,
                    $start_time,
                    $end_time,
                    $purpose,
                    $total,
                    $status,
                    $id
                ]);

            } else {
                $stmt = $pdo->prepare("
                    UPDATE bookings 
                    SET hall_id = ?, 
                        booking_date = ?, 
                        start_time = ?, 
                        end_time = ?, 
                        purpose = ?, 
                        total_amount = ?, 
                        booking_status = ? 
                    WHERE booking_id = ? 
                    AND user_id = ?
                ");

                $stmt->execute([
                    $hall_id,
                    $booking_date,
                    $start_time,
                    $end_time,
                    $purpose,
                    $total,
                    $status,
                    $id,
                    $_SESSION['user_id']
                ]);
            }

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO bookings 
                (user_id, hall_id, booking_date, start_time, end_time, purpose, total_amount, booking_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                $hall_id,
                $booking_date,
                $start_time,
                $end_time,
                $purpose,
                $total,
                $status
            ]);
        }

        redirect('bookings.php');
    }
}
?>

<div class="card p-4">

<h2><?= $id ? 'Edit Booking' : 'New Booking' ?></h2>

<?php if($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

    <label>Hall</label>
    <select name="hall_id" id="hall-select" class="form-select mb-2" required>
        <option value="">-- Select Hall --</option>

        <?php foreach($halls as $h): ?>
            <option value="<?= $h['hall_id'] ?>" 
                <?= $booking['hall_id'] == $h['hall_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($h['hall_name']) ?> 
                - RM<?= number_format($h['hourly_rate'], 2) ?>/hour
            </option>
        <?php endforeach; ?>

    </select>

    <label>Booking Date</label>
    <label>Booking Date</label>
    <input
    type="date"
    name="booking_date"
    id="booking-date"
    class="form-control mb-2"
    min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
    value="<?= htmlspecialchars($booking['booking_date']) ?>"
    required>
    <small class="text-muted d-block mb-3" id="blocked-dates-info">
        Select a hall to see unavailable dates.
    </small>

    <<label>Start Time</label>
<input 
    type="time" 
    name="start_time" 
    class="form-control mb-2" 
    min="08:00"
    max="23:00"
    value="<?= htmlspecialchars($booking['start_time']) ?>" 
    required>

    <label>End Time</label>
<input 
    type="time" 
    name="end_time" 
    class="form-control mb-2" 
    min="08:00"
    max="23:00"
    value="<?= htmlspecialchars($booking['end_time']) ?>" 
    required>

    <label>Purpose</label>
    <input 
        name="purpose" 
        class="form-control mb-2" 
        value="<?= htmlspecialchars($booking['purpose']) ?>" 
        required>

    <?php if(isAdmin()): ?>

        <label>Status</label>
        <select name="booking_status" class="form-select mb-3">
            <option value="PENDING" <?= $booking['booking_status'] == 'PENDING' ? 'selected' : '' ?>>
                PENDING
            </option>
            <option value="CONFIRMED" <?= $booking['booking_status'] == 'CONFIRMED' ? 'selected' : '' ?>>
                CONFIRMED
            </option>
            <option value="CANCELLED" <?= $booking['booking_status'] == 'CANCELLED' ? 'selected' : '' ?>>
                CANCELLED
            </option>
        </select>

    <?php endif; ?>

    <button class="btn btn-primary">Save</button>

    <a href="bookings.php" class="btn btn-secondary">Back</a>

</form>

</div>

<script>
    const bookedDates = <?= json_encode($bookedDates) ?>;
    const hallSelect = document.getElementById('hall-select');
    const dateInput = document.getElementById('booking-date');
    const blockedInfo = document.getElementById('blocked-dates-info');

    function updateBlockedDates() {
        const selectedHall = hallSelect.value;
        const dates = bookedDates[selectedHall] || [];

        if (!selectedHall) {
            blockedInfo.textContent = 'Select a hall to see unavailable dates.';
            return;
        }

        blockedInfo.textContent = dates.length
            ? 'Unavailable dates for selected hall: ' + dates.join(', ')
            : 'No booked dates yet for this hall.';

        if (dates.includes(dateInput.value)) {
            blockedInfo.textContent += ' The selected date is unavailable.';
        }
    }

    hallSelect.addEventListener('change', updateBlockedDates);
    dateInput.addEventListener('change', updateBlockedDates);

    updateBlockedDates();
</script>

<?php include 'partials/footer.php'; ?>