<?php
include 'partials/header.php';
if(!isLoggedIn() || !isAdmin()) redirect('dashboard.php');

$id = $_GET['id'] ?? null;
$hall = ['hall_name'=>'','location'=>'','capacity'=>'','description'=>'','hourly_rate'=>'','status'=>'ACTIVE'];

if($id) {
    $stmt = $pdo->prepare("SELECT * FROM halls WHERE hall_id=?");
    $stmt->execute([$id]);
    $hall = $stmt->fetch();
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [$_POST['hall_name'], $_POST['location'], $_POST['capacity'], $_POST['description'], $_POST['hourly_rate'], $_POST['status']];
    if($id) {
        $stmt = $pdo->prepare("UPDATE halls SET hall_name=?, location=?, capacity=?, description=?, hourly_rate=?, status=? WHERE hall_id=?");
        $stmt->execute([...$data, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO halls (hall_name, location, capacity, description, hourly_rate, status) VALUES (?,?,?,?,?,?)");
        $stmt->execute($data);
    }
    redirect('halls.php');
}
?>
<div class="card p-4">
<h2><?= $id ? 'Edit Hall' : 'Add Hall' ?></h2>
<form method="POST">
    <label>Hall Name</label><input name="hall_name" class="form-control mb-2" value="<?= htmlspecialchars($hall['hall_name']) ?>" required>
    <label>Location</label><input name="location" class="form-control mb-2" value="<?= htmlspecialchars($hall['location']) ?>" required>
    <label>Capacity</label><input type="number" name="capacity" class="form-control mb-2" value="<?= htmlspecialchars($hall['capacity']) ?>" required>
    <label>Description</label><textarea name="description" class="form-control mb-2"><?= htmlspecialchars($hall['description']) ?></textarea>
    <label>Hourly Rate</label><input type="number" step="0.01" name="hourly_rate" class="form-control mb-2" value="<?= htmlspecialchars($hall['hourly_rate']) ?>" required>
    <label>Status</label>
    <select name="status" class="form-select mb-3">
        <option <?= $hall['status']=='ACTIVE'?'selected':'' ?>>ACTIVE</option>
        <option <?= $hall['status']=='INACTIVE'?'selected':'' ?>>INACTIVE</option>
    </select>
    <button class="btn btn-primary">Save</button>
    <a href="halls.php" class="btn btn-secondary">Back</a>
</form>
</div>
<?php include 'partials/footer.php'; ?>
