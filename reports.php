<?php
include 'partials/header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$userId = $_SESSION['user_id'];
$isAdminUser = isAdmin();

if ($isAdminUser) {
    $totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $statusCounts = $pdo->query("SELECT booking_status, COUNT(*) as count FROM bookings GROUP BY booking_status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $hallStats = $pdo->query("SELECT h.hall_name, COUNT(b.booking_id) as bookings, SUM(b.total_amount) as revenue FROM halls h LEFT JOIN bookings b ON h.hall_id = b.hall_id GROUP BY h.hall_id ORDER BY bookings DESC")->fetchAll();
    $dailyStats = $pdo->query("SELECT booking_date, COUNT(*) as total, SUM(booking_status='CONFIRMED') as confirmed, SUM(booking_status='PENDING') as pending, SUM(booking_status='CANCELLED') as cancelled FROM bookings GROUP BY booking_date ORDER BY booking_date DESC LIMIT 14")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalBookings = $stmt->fetchColumn();

    $statusCountsStmt = $pdo->prepare("SELECT booking_status, COUNT(*) as count FROM bookings WHERE user_id = ? GROUP BY booking_status");
    $statusCountsStmt->execute([$userId]);
    $statusCounts = $statusCountsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $hallStatsStmt = $pdo->prepare("SELECT h.hall_name, COUNT(b.booking_id) as bookings, SUM(b.total_amount) as revenue FROM halls h JOIN bookings b ON h.hall_id = b.hall_id WHERE b.user_id = ? GROUP BY h.hall_id ORDER BY bookings DESC");
    $hallStatsStmt->execute([$userId]);
    $hallStats = $hallStatsStmt->fetchAll();

    $dailyStatsStmt = $pdo->prepare("SELECT booking_date, COUNT(*) as total, SUM(booking_status='CONFIRMED') as confirmed, SUM(booking_status='PENDING') as pending, SUM(booking_status='CANCELLED') as cancelled FROM bookings WHERE user_id = ? GROUP BY booking_date ORDER BY booking_date DESC LIMIT 14");
    $dailyStatsStmt->execute([$userId]);
    $dailyStats = $dailyStatsStmt->fetchAll();
}

$pendingCount = $statusCounts['PENDING'] ?? 0;
$confirmedCount = $statusCounts['CONFIRMED'] ?? 0;
$cancelledCount = $statusCounts['CANCELLED'] ?? 0;
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.75rem;
    }
    .page-header h2 {
        margin: 0;
        font-size: clamp(1.8rem, 2.1vw, 2.4rem);
        letter-spacing: -0.03em;
    }
    .page-header p {
        margin: 0.75rem 0 0;
        color: #6b7280;
        max-width: 680px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stat-card-dash {
        position: relative;
        overflow: hidden;
        border-radius: 1.25rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        padding: 1.4rem 1.2rem;
        background: #ffffff;
        display: flex;
        gap: 1rem;
        align-items: center;
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
    }
    .stat-card-dash .stat-icon-box {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .stat-card-dash .stat-icon-box svg {
        width: 1.2em;
        height: 1.2em;
        display: block;
    }
    .stat-icon-box.purple { background: linear-gradient(135deg,#ede9fe,#c4b5fd); color: #7c3aed; }
    .stat-icon-box.green { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color: #059669; }
    .stat-icon-box.orange { background: linear-gradient(135deg,#fef3c7,#fcd34d); color: #d97706; }
    .stat-icon-box.red { background: linear-gradient(135deg,#fee2e2,#fecaca); color: #dc2626; }
    .stat-info h5 {
        margin: 0 0 0.35rem;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6b7280;
    }
    .stat-info h3 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 800;
        color: #111827;
    }
    .status-breakdown {
        display: grid;
        gap: 0.85rem;
    }
    .status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.1rem;
        border-radius: 1rem;
        background: #f8fafc;
        border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .status-item span {
        font-weight: 600;
        color: #111827;
    }
    .status-badge {
        padding: 0.35rem 0.8rem;
        border-radius: 999px;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.72rem;
        color: #fff;
    }
    .status-badge.confirmed { background: #10b981; }
    .status-badge.pending { background: #f59e0b; }
    .status-badge.cancelled { background: #ef4444; }
    .report-card {
        border-radius: 1.25rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
        background: #fff;
    }
    .report-card h5 {
        margin-bottom: 1rem;
        font-weight: 700;
    }
    .report-table thead {
        background: #f8fafc;
    }
    .report-table th {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6b7280;
        border-bottom: 2px solid #e5e7eb;
    }
    .report-table td {
        vertical-align: middle;
    }
</style>

<div class="page-header">
    <div>
        <h2><?= $isAdminUser ? 'Reports' : 'My Booking Summary' ?></h2>
        <p class="text-muted mb-0"><?= $isAdminUser ? 'Summary of bookings, hall usage, and booking status performance.' : 'Your personal booking summary and recent activity.' ?></p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<div class="stats-grid mb-4">
    <div class="stat-card-dash">
        <div class="stat-icon-box purple">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M4 12l4 4 8-8 4 4V4H4v8Zm12 4h2v4H6v-4h2v2h8v-2Z" />
            </svg>
        </div>
        <div class="stat-info">
            <h5>Total Bookings</h5>
            <h3><?= number_format($totalBookings) ?></h3>
        </div>
    </div>
    <div class="stat-card-dash">
        <div class="stat-icon-box green">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17Z" />
            </svg>
        </div>
        <div class="stat-info">
            <h5>Confirmed</h5>
            <h3><?= number_format($confirmedCount) ?></h3>
        </div>
    </div>
    <div class="stat-card-dash">
        <div class="stat-icon-box orange">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M6 2a2 2 0 0 0-2 2v2h2V4h12v2h2V4a2 2 0 0 0-2-2H6Zm12 16v-4H6v4a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2Zm0-6V8H6v4h12Z" />
            </svg>
        </div>
        <div class="stat-info">
            <h5>Pending</h5>
            <h3><?= number_format($pendingCount) ?></h3>
        </div>
    </div>
    <div class="stat-card-dash">
        <div class="stat-icon-box red">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm4.59 13.41L13.41 12l3.18-3.18-1.41-1.41L12 10.59 8.82 7.41 7.41 8.82 10.59 12l-3.18 3.18 1.41 1.41L12 13.41l3.18 3.18 1.41-1.41Z" />
            </svg>
        </div>
        <div class="stat-info">
            <h5>Cancelled</h5>
            <h3><?= number_format($cancelledCount) ?></h3>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card p-4">
            <h5 class="mb-3">Hall Usage</h5>
            <div class="table-responsive">
                <table class="table table-dashboard mb-0">
                    <thead>
                        <tr>
                            <th>Hall</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hallStats as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['hall_name']) ?></td>
                                <td><?= number_format($row['bookings']) ?></td>
                                <td>RM <?= number_format($row['revenue'] ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="report-card p-4">
            <h5>Status Breakdown</h5>
            <div class="status-breakdown">
                <div class="status-item">
                    <span>Confirmed</span>
                    <span class="status-badge confirmed"><?= number_format($confirmedCount) ?></span>
                </div>
                <div class="status-item">
                    <span>Pending</span>
                    <span class="status-badge pending"><?= number_format($pendingCount) ?></span>
                </div>
                <div class="status-item">
                    <span>Cancelled</span>
                    <span class="status-badge cancelled"><?= number_format($cancelledCount) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card p-4">
    <h5 class="mb-3">Recent Booking Trends</h5>
    <div class="table-responsive">
        <table class="table table-dashboard mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Confirmed</th>
                    <th>Pending</th>
                    <th>Cancelled</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dailyStats as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d M Y', strtotime($row['booking_date']))) ?></td>
                        <td><?= number_format($row['total']) ?></td>
                        <td><?= number_format($row['confirmed']) ?></td>
                        <td><?= number_format($row['pending']) ?></td>
                        <td><?= number_format($row['cancelled']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'partials/footer.php'; ?>