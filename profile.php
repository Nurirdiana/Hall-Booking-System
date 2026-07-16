<?php
include 'partials/header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT user_id, name, email, phone, role, status, created_at FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('dashboard.php');
}
?>

<style>
.profile-card {
    max-width: 720px;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: 24px;
    background: white;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
}
.profile-card h2 {
    margin-bottom: 0.5rem;
    font-weight: 800;
}
.profile-card p {
    color: #6b7280;
    margin-bottom: 1rem;
}
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-top: 1.5rem;
}
.profile-grid .profile-item {
    background: #f8fafc;
    border-radius: 18px;
    padding: 1rem 1.2rem;
}
.profile-grid .profile-item h6 {
    font-size: 0.75rem;
    letter-spacing: 0.8px;
    margin-bottom: 0.4rem;
    text-transform: uppercase;
    color: #4b5563;
}
.profile-grid .profile-item p {
    margin-bottom: 0;
    color: #111827;
    font-weight: 600;
}
.profile-actions {
    margin-top: 1.5rem;
    display: flex;
    gap: 0.75rem;
}
.profile-actions .btn {
    min-width: 140px;
}
</style>

<div class="main-content">
    <div class="profile-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2>Customer Profile</h2>
                <p>View and keep your profile information up to date.</p>
            </div>
            <div class="text-end">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
            </div>
        </div>

        <div class="profile-grid">
            <div class="profile-item">
                <h6>Name</h6>
                <p><?= htmlspecialchars($user['name']) ?></p>
            </div>
            <div class="profile-item">
                <h6>Email</h6>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>
            <div class="profile-item">
                <h6>Phone</h6>
                <p><?= htmlspecialchars($user['phone'] ?? 'Not set') ?></p>
            </div>
            <div class="profile-item">
                <h6>Role</h6>
                <p><?= htmlspecialchars($user['role']) ?></p>
            </div>
            <div class="profile-item">
                <h6>Status</h6>
                <p><?= htmlspecialchars($user['status']) ?></p>
            </div>
            <div class="profile-item">
                <h6>Joined</h6>
                <p><?= date('F j, Y', strtotime($user['created_at'])) ?></p>
            </div>
        </div>

        
    </div>
</div>
