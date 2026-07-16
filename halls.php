<?php
include 'partials/header.php';
if(!isLoggedIn()) redirect('index.php');

if(isset($_GET['delete']) && isAdmin()) {
    $stmt = $pdo->prepare("DELETE FROM halls WHERE hall_id=?");
    $stmt->execute([$_GET['delete']]);
    redirect('halls.php');
}

$search = $_GET['search'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM halls WHERE hall_name LIKE ? OR location LIKE ? ORDER BY hall_id DESC");
$stmt->execute(["%$search%", "%$search%"]);
$halls = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Hall List</h2>
    <?php if(isAdmin()): ?><a href="hall_form.php" class="btn btn-primary">+ Add Hall</a><?php endif; ?>
</div>

<form class="row mb-3">
    <div class="col-md-10"><input name="search" class="form-control" placeholder="Search hall or location" value="<?= htmlspecialchars($search) ?>"></div>
    <div class="col-md-2"><button class="btn btn-secondary w-100">Search</button></div>
</form>

<div class="table-responsive card p-3">
<table class="table align-middle">
    <thead><tr><th>Name</th><th>Location</th><th>Capacity</th><th>Rate/Hour</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($halls as $hall): ?>
        <tr>
            <td><?= htmlspecialchars($hall['hall_name']) ?></td>
            <td><?= htmlspecialchars($hall['location']) ?></td>
            <td><?= $hall['capacity'] ?></td>
            <td>RM <?= number_format($hall['hourly_rate'],2) ?></td>
            <td><?= $hall['status'] ?></td>
            <td>
                <?php if(isAdmin()): ?>
                    <a href="hall_form.php?id=<?= $hall['hall_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <a onclick="return confirm('Delete this hall?')" href="halls.php?delete=<?= $hall['hall_id'] ?>" class="btn btn-sm btn-danger">Delete</a>
                <?php else: ?>
                    <a href="booking_form.php?hall_id=<?= $hall['hall_id'] ?>" class="btn btn-sm btn-primary">Book</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php include 'partials/footer.php'; ?>
