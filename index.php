<?php
require_once 'config.php';
if (isLoggedIn()) redirect('dashboard.php');
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'ACTIVE'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

  if ($user && trim($password) == trim($user['password'])) {

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];

    redirect('dashboard.php');

} else {
    $error = "Invalid email or password.";
}


}
?>
<?php include 'partials/header.php'; ?>
<div class="auth-page">
    <div class="auth-box">
        <div class="card auth-card p-4">
            <div class="text-center mb-4">
                <h3 class="mb-1">Sign in to Hall Booking</h3>
                <p class="text-muted mb-0">Use your email and password or Google to sign in securely.</p>
            </div>
        <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
        </form>
        <button type="button" class="btn btn-google w-100 mb-3" onclick="window.location.href='google_login.php'">
            <span class="d-flex align-items-center justify-content-center gap-2">
                <span style="width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border-radius:4px;background:#fff;">G</span>
                Sign in with Google
            </span>
        </button>
        <div class="text-center mt-3">
            <small>Don&rsquo;t have an account? <a href="signup.php">Sign up</a></small>
        </div>
        <div class="text-center mt-3 text-muted">
            <small>Admin: admin@example.com / admin123</small><br>
            <small>Customer: customer@example.com / customer123</small>
        </div>
    </div>
</div>
</div>
<?php include 'partials/footer.php'; ?>
