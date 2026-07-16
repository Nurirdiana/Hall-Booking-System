<?php
include 'partials/header.php';

if(!isLoggedIn()) {
    redirect('index.php');
}

/* DELETE BOOKING */
if(isset($_GET['delete'])) {

    if(isAdmin()) {

        $stmt = $pdo->prepare("
            DELETE FROM bookings
            WHERE booking_id = ?
        ");

        $stmt->execute([
            $_GET['delete']
        ]);

    } else {

        $stmt = $pdo->prepare("
            DELETE FROM bookings
            WHERE booking_id = ?
            AND user_id = ?
        ");

        $stmt->execute([
            $_GET['delete'],
            $_SESSION['user_id']
        ]);
    }

    redirect('bookings.php');
}

/* SEARCH */
$search = trim($_GET['search'] ?? '');
$filterType = $_GET['filter'] ?? 'all';
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$sql = "SELECT b.*, u.name, h.hall_name
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN halls h ON b.hall_id = h.hall_id
        WHERE 1=1";

$params = [];

if ($filterType === 'customer' && $search !== '') {
    $sql .= " AND u.name LIKE ?";
    $params[] = "%$search%";
} elseif ($filterType === 'hall' && $search !== '') {
    $sql .= " AND h.hall_name LIKE ?";
    $params[] = "%$search%";
} elseif ($filterType === 'status' && $search !== '') {
    $sql .= " AND b.booking_status LIKE ?";
    $params[] = "%$search%";
} elseif ($filterType === 'date' && $search !== '') {
    $sql .= " AND b.booking_date = ?";
    $params[] = $search;
} elseif ($search !== '') {
    $sql .= " AND (
            h.hall_name LIKE ?
            OR u.name LIKE ?
            OR b.booking_status LIKE ?
            OR b.booking_date LIKE ?
        )";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $sql .= " AND b.booking_status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter !== '') {
    $sql .= " AND b.booking_date = ?";
    $params[] = $dateFilter;
}

/* CUSTOMER ONLY SEE OWN BOOKINGS */
if(!isAdmin()) {

    $sql .= " AND b.user_id = ?";

    $params[] = $_SESSION['user_id'];
}

$sql .= " ORDER BY b.booking_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bookings = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between mb-3">

    <h2>Booking List</h2>

    <a href="booking_form.php" class="btn btn-primary">
        + New Booking
    </a>

</div>

<form class="row mb-3 search-row" method="GET">
    <div class="col-md-3">
        <select name="filter" class="form-select">
            <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All</option>
            <option value="customer" <?= $filterType === 'customer' ? 'selected' : '' ?>>Customer</option>
            <option value="hall" <?= $filterType === 'hall' ? 'selected' : '' ?>>Hall</option>
            <option value="status" <?= $filterType === 'status' ? 'selected' : '' ?>>Status</option>
            <option value="date" <?= $filterType === 'date' ? 'selected' : '' ?>>Date</option>
        </select>
    </div>

    <div class="col-md-4">
        <input
            type="text"
            name="search"
            class="form-control"
            placeholder="Search booking"
            value="<?= htmlspecialchars($search) ?>">
    </div>

    <div class="col-md-2">
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
    </div>

    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
            <option value="CONFIRMED" <?= $statusFilter === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
            <option value="CANCELLED" <?= $statusFilter === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div>

    <div class="col-md-1 d-grid">
        <button class="btn btn-secondary">
            Search
        </button>
    </div>

    <div class="col-md-12 mt-2">
        <a href="bookings.php" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>



<div class="table-responsive card p-3">

<table class="table table-bordered table-hover">

<thead>
<tr>
    <th>Customer</th>
    <th>Hall</th>
    <th>Date</th>
    <th>Time</th>
    <th>Total</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($bookings as $b): ?>

<tr>

    <td><?= htmlspecialchars($b['name']) ?></td>

    <td><?= htmlspecialchars($b['hall_name']) ?></td>

    <td><?= htmlspecialchars($b['booking_date']) ?></td>

    <td>
        <?= htmlspecialchars($b['start_time']) ?>
        -
        <?= htmlspecialchars($b['end_time']) ?>
    </td>

    <td>
        RM <?= number_format($b['total_amount'], 2) ?>
    </td>

    <td>
        <span class="badge-status <?= strtolower($b['booking_status']) ?>">
            <?= htmlspecialchars($b['booking_status']) ?>
        </span>
    </td>

    <td>
        <a href="booking_view.php?id=<?= $b['booking_id'] ?>" class="btn btn-sm btn-info mb-1">View</a>
    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php include 'partials/footer.php'; ?>