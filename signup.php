<?php
require_once 'config.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    $approval_status = ($role == 'ADMIN') ? 'PENDING' : 'APPROVED';

    $stmt = $pdo->prepare("INSERT INTO users 
    (name, email, phone, password, role, approval_status) 
    VALUES (?, ?, ?, ?, ?, ?)");

    if ($stmt->execute([$name, $email, $phone, $password, $role, $approval_status])) {
        $success = "Account registered successfully.";
    } else {
        $error = "Registration failed.";
    }
}
?>

<?php include 'partials/header.php'; ?>

<div class="auth-box">
    <div class="card p-4">
        <h3 class="text-center mb-3">Sign Up</h3>

        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control mb-3" required>

            <label>Email</label>
            <input type="email" name="email" class="form-control mb-3" required>

            <label>Phone Number</label>
            <input type="text" name="phone" class="form-control mb-3">

            <label>Password</label>
            <input type="password" name="password" class="form-control mb-3" required>

            <label>Role</label>
            <select name="role" class="form-select mb-3" required>
                <option value="CUSTOMER">Customer</option>
                <option value="ADMIN">Admin</option>
            </select>

            <button class="btn btn-primary w-100">Register</button>
        </form>

        <div class="text-center mt-3">
            <p>Already have an account?<a href="index.php"> Login</a><p>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>