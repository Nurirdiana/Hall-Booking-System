<?php
include 'partials/header.php';

if(!isLoggedIn()) {
    redirect('index.php');
}

// ===== GET MONTH AND YEAR FOR CALENDAR =====
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

if ($month < 1) {
    $month = 12;
    $year--;
}
if ($month > 12) {
    $month = 1;
    $year++;
}

// ===== STATISTICS =====
$totalHalls = $pdo->query("SELECT COUNT(*) FROM halls")->fetchColumn();

if (isAdmin()) {
    $totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $pendingBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'PENDING'")->fetchColumn();
    $confirmedBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'CONFIRMED'")->fetchColumn();
    $cancelledBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'CANCELLED'")->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT b.*, u.name, h.hall_name 
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN halls h ON b.hall_id = h.hall_id
        ORDER BY b.booking_id DESC LIMIT 5
    ");
    $stmt->execute();
    $recentBookings = $stmt->fetchAll();
    
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $totalBookings = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_status = 'CONFIRMED'");
    $stmt->execute([$_SESSION['user_id']]);
    $confirmedBookings = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_status = 'PENDING'");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingBookings = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT b.*, h.hall_name 
        FROM bookings b
        JOIN halls h ON b.hall_id = h.hall_id
        WHERE b.user_id = ?
        ORDER BY b.booking_id DESC LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentBookings = $stmt->fetchAll();
}

if (isAdmin()) {
    $totalReceipts = $pdo->query("SELECT COUNT(*) FROM booking_receipts")->fetchColumn();
} else {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM booking_receipts r
        JOIN bookings b ON r.booking_id = b.booking_id
        WHERE b.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $totalReceipts = $stmt->fetchColumn();
}

$chartMonthKeys = [];
$chartLabels = [];
$chartValues = [];
$chartCursor = new DateTime('first day of this month');

for ($i = 5; $i >= 0; $i--) {
    $monthDate = (clone $chartCursor)->modify("-$i months");
    $monthKey = $monthDate->format('Y-m');
    $chartMonthKeys[] = $monthKey;
    $chartLabels[] = $monthDate->format('M');
    $chartValues[] = 0;
}

if (isAdmin()) {
    $stmt = $pdo->query(<<<SQL
        SELECT DATE_FORMAT(booking_date, '%Y-%m') AS month_key, COUNT(*) AS total
        FROM bookings
        WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(booking_date), MONTH(booking_date)
        ORDER BY YEAR(booking_date), MONTH(booking_date)
SQL);
} else {
    $stmt = $pdo->prepare(<<<SQL
        SELECT DATE_FORMAT(booking_date, '%Y-%m') AS month_key, COUNT(*) AS total
        FROM bookings
        WHERE user_id = ?
          AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(booking_date), MONTH(booking_date)
        ORDER BY YEAR(booking_date), MONTH(booking_date)
SQL);
    $stmt->execute([$_SESSION['user_id']]);
}

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $index = array_search($row['month_key'], $chartMonthKeys, true);
    if ($index !== false) {
        $chartValues[$index] = (int) $row['total'];
    }
}

$chartLabelsJson = json_encode($chartLabels);
$chartValuesJson = json_encode($chartValues);

// compute simple trend compared to the first month in range
$chartFirstVal = $chartValues[0] ?? 0;
$chartLastVal = end($chartValues) ?: 0;
if ($chartFirstVal > 0) {
    $chartTrendPct = round((($chartLastVal - $chartFirstVal) / $chartFirstVal) * 100, 1);
} else {
    $chartTrendPct = ($chartLastVal > 0) ? 100 : 0;
}

// ===== CALENDAR BOOKINGS FOR CURRENT MONTH =====
$calendarBookings = [];
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart));
if (isAdmin()) {
    $stmt = $pdo->prepare("SELECT booking_date, booking_status, COUNT(*) as total FROM bookings WHERE booking_date BETWEEN ? AND ? GROUP BY booking_date, booking_status");
    $stmt->execute([$monthStart, $monthEnd]);
} else {
    $stmt = $pdo->prepare("SELECT booking_date, booking_status, COUNT(*) as total FROM bookings WHERE user_id = ? AND booking_date BETWEEN ? AND ? GROUP BY booking_date, booking_status");
    $stmt->execute([$_SESSION['user_id'], $monthStart, $monthEnd]);
}
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = $r['booking_date'];
    $s = $r['booking_status'];
    $calendarBookings[$d][$s] = (int)$r['total'];
}

// ===== CALENDAR FUNCTIONS =====
function getBookingCountForDate($pdo, $date) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = ?");
    $stmt->execute([$date]);
    return $stmt->fetchColumn();
}

$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfMonth = intval(date('w', mktime(0, 0, 0, $month, 1, $year)));
$today = date('Y-m-d');
$currentMonth = intval(date('m'));
$currentYear = intval(date('Y'));

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>

<style>
/* ========================================
   MODERN DASHBOARD WITH SIDEBAR - ENHANCED
   ======================================== */

:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
    --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --glass-bg: rgba(255, 255, 255, 0.15);
    --glass-border: rgba(255, 255, 255, 0.18);
    --shadow-glass: 0 8px 32px rgba(0, 0, 0, 0.1);
    --shadow-hover: 0 20px 40px rgba(102, 126, 234, 0.15);
    --radius-xl: 20px;
    --radius-lg: 16px;
}

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 270px;
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    backdrop-filter: blur(20px);
    color: white;
    padding: 1.5rem 0;
    z-index: 1000;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    box-shadow: 4px 0 30px rgba(0,0,0,0.3);
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}
.sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}

.sidebar .sidebar-brand {
    padding: 0 1.5rem 1.5rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    margin-bottom: 1.5rem;
    text-align: center;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.65rem;
}

.sidebar .sidebar-brand::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 20%;
    width: 60%;
    height: 2px;
    background: var(--primary-gradient);
    border-radius: 10px;
}

.sidebar .sidebar-brand .brand-logo img {
    width: 64px;
    max-width: 100%;
    height: auto;
    display: block;
    border-radius: 18px;
    background: rgba(255,255,255,0.08);
    padding: 0.5rem;
}

.sidebar .sidebar-brand .logo-icon {
    font-size: 2.8rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: block;
    margin-bottom: 0.25rem;
    animation: pulseGlow 3s ease-in-out infinite;
}

@keyframes pulseGlow {
    0%, 100% { filter: drop-shadow(0 0 10px rgba(102, 126, 234, 0.3)); }
    50% { filter: drop-shadow(0 0 20px rgba(102, 126, 234, 0.6)); }
}

.sidebar .sidebar-brand h3 {
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 0;
    letter-spacing: 1px;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.sidebar .sidebar-brand small {
    font-size: 0.65rem;
    opacity: 0.5;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.sidebar .sidebar-menu {
    list-style: none;
    padding: 0 0.75rem;
    margin: 0;
}

.sidebar .sidebar-menu li {
    padding: 0;
    margin-bottom: 0.25rem;
}

.sidebar .sidebar-menu li a {
    display: flex;
    align-items: center;
    padding: 0.7rem 1rem;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: all 0.3s ease;
    border-radius: 12px;
    font-size: 0.9rem;
    gap: 0.85rem;
    position: relative;
}

.sidebar .sidebar-menu li a i {
    width: 22px;
    text-align: center;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.sidebar .sidebar-menu li a:hover {
    color: white;
    background: rgba(255,255,255,0.06);
    transform: translateX(4px);
}

.sidebar .sidebar-menu li a:hover i {
    transform: scale(1.1);
}

.sidebar .sidebar-menu li a.active {
    color: white;
    background: rgba(102, 126, 234, 0.2);
    box-shadow: inset 0 0 0 1px rgba(102, 126, 234, 0.2);
}

.sidebar .sidebar-menu li a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 24px;
    background: var(--primary-gradient);
    border-radius: 0 4px 4px 0;
}

.sidebar .sidebar-menu li a .badge-sidebar {
    margin-left: auto;
    background: var(--primary-gradient);
    color: white;
    font-size: 0.6rem;
    padding: 0.15rem 0.6rem;
    border-radius: 50px;
    font-weight: 600;
}

.sidebar .sidebar-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(255,255,255,0.08), transparent);
    margin: 0.75rem 1.5rem;
}

.sidebar .sidebar-footer {
    position: absolute;
    bottom: 1.5rem;
    left: 0;
    right: 0;
    padding: 0 1rem;
}

.sidebar .sidebar-footer .user-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid rgba(255,255,255,0.05);
}

.sidebar .sidebar-footer .user-card .avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.sidebar .sidebar-footer .user-card .user-info {
    flex: 1;
}

.sidebar .sidebar-footer .user-card .user-info .name {
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0;
}

.sidebar .sidebar-footer .user-card .user-info .role {
    font-size: 0.65rem;
    opacity: 0.5;
    margin-bottom: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sidebar .sidebar-footer .user-card .logout-btn {
    color: rgba(255,255,255,0.3);
    transition: all 0.3s ease;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
}

.sidebar .sidebar-footer .user-card .logout-btn:hover {
    color: #f87171;
    background: rgba(248, 113, 113, 0.15);
    transform: rotate(90deg);
}

/* ===== MAIN CONTENT ===== */
.main-content {
    margin-left: 270px;
    padding: 2rem 2.5rem;
    min-height: 100vh;
    background: #f0f2f5;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== TOP HEADER ===== */
.top-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.top-header .page-title h4 {
    font-weight: 800;
    margin-bottom: 0;
    font-size: 1.5rem;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.top-header .page-title p {
    color: #6b7280;
    margin-bottom: 0;
    font-size: 0.9rem;
}

.top-header .page-title p i {
    color: #667eea;
}

.top-header .header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.top-header .header-actions .btn-primary {
    background: var(--primary-gradient);
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.top-header .header-actions .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

/* ===== STATS CARDS ===== */
.booking-chart {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.booking-chart-bars {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.75rem;
    height: 220px;
    padding: 1rem 0.25rem 0;
}

.booking-chart-column {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.booking-chart-track {
    width: 100%;
    max-width: 48px;
    height: 170px;
    background: linear-gradient(180deg, #f3f4f6 0%, #e5e7eb 100%);
    border-radius: 999px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    box-shadow: inset 0 2px 6px rgba(0,0,0,0.06);
}

.booking-chart-bar {
    width: 100%;
    border-radius: 999px;
    background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    transition: height 0.3s ease;
}

.booking-chart-label {
    font-size: 0.8rem;
    color: #6b7280;
    font-weight: 600;
}

.booking-chart-value {
    font-size: 0.8rem;
    color: #111827;
    font-weight: 700;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card-dash {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 1rem;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.03);
}

.stat-card-dash::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(102, 126, 234, 0.04) 0%, transparent 70%);
    transform: translate(30%, -30%);
}

.stat-card-dash:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-hover);
}

.stat-card-dash .stat-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.stat-card-dash .stat-icon-box svg {
    width: 1.2em;
    height: 1.2em;
    display: block;
}

.stat-card-dash .stat-icon-box.blue { 
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe); 
    color: #4f46e5; 
}
.stat-card-dash .stat-icon-box.green { 
    background: linear-gradient(135deg, #d1fae5, #a7f3d0); 
    color: #059669; 
}
.stat-card-dash .stat-icon-box.purple { 
    background: linear-gradient(135deg, #ede9fe, #c4b5fd); 
    color: #7c3aed; 
}
.stat-card-dash .stat-icon-box.orange { 
    background: linear-gradient(135deg, #fef3c7, #fcd34d); 
    color: #d97706; 
}

.stat-card-dash .stat-info {
    flex: 1;
    z-index: 1;
}

.stat-card-dash .stat-info h5 {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-bottom: 0.15rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card-dash .stat-info h3 {
    font-size: 1.6rem;
    font-weight: 800;
    margin-bottom: 0;
    background: linear-gradient(135deg, #1a1a2e, #0f3460);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-card-dash .stat-trend {
    position: absolute;
    bottom: 0.75rem;
    right: 1rem;
    font-size: 0.7rem;
    color: #059669;
    background: #ecfdf5;
    padding: 0.15rem 0.6rem;
    border-radius: 50px;
    font-weight: 600;
    z-index: 1;
}

/* ===== CALENDAR ===== */
.calendar-container {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
}

.calendar-container:hover {
    box-shadow: var(--shadow-hover);
}

.calendar-container .calendar-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.calendar-container .calendar-nav h6 {
    font-weight: 700;
    margin-bottom: 0;
    flex-grow: 1;
    text-align: center;
    min-width: 150px;
    font-size: 0.95rem;
    color: #1a1a2e;
}

.calendar-container .calendar-nav .nav-btn {
    background: #f3f4f6;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    color: #4b5563;
    text-decoration: none;
    font-size: 0.9rem;
}

.calendar-container .calendar-nav .nav-btn:hover {
    background: var(--primary-gradient);
    color: white;
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.calendar-container .calendar-nav .nav-btn.today-btn {
    background: transparent;
    border: 1px solid #e5e7eb;
    font-size: 0.7rem;
    width: auto;
    padding: 0 0.75rem;
    border-radius: 50px;
    color: #6b7280;
}

.calendar-container .calendar-nav .nav-btn.today-btn:hover {
    background: var(--primary-gradient);
    border-color: transparent;
    color: white;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-grid .day-name {
    text-align: center;
    font-size: 0.65rem;
    font-weight: 700;
    color: #9ca3af;
    padding: 0.4rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.calendar-grid .day-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 0.9rem 0;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.18s ease;
    position: relative;
    color: #1a1a2e;
}

.calendar-grid .day-cell:hover:not(.empty) {
    background: #f3f4f6;
    transform: scale(1.05);
}

.calendar-grid .day-cell.today {
    background: var(--primary-gradient);
    color: white;
    font-weight: 700;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.calendar-grid .day-cell.has-booking::after {
    content: '';
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.calendar-grid .day-cell.today.has-booking::after {
    background: white;
    box-shadow: 0 0 10px rgba(255,255,255,0.5);
}

.calendar-grid .day-cell.other-month {
    color: #d1d5db;
}

.calendar-grid .day-cell.empty {
    cursor: default;
}

.calendar-grid .day-cell.empty:hover {
    background: transparent;
    transform: none;
}

/* ===== TABLE ===== */
.table-dashboard {
    font-size: 0.9rem;
}

.table-dashboard thead {
    background: #f9fafb;
}

.table-dashboard thead th {
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
    padding: 0.75rem 1rem;
    border-bottom: 2px solid #e5e7eb;
}

.table-dashboard tbody td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f3f4f6;
}

.table-dashboard tbody tr {
    transition: all 0.3s ease;
}

.table-dashboard tbody tr:hover {
    background: #f9fafb;
}

.status-approved, .status-confirmed {
    color: #059669;
    background: #ecfdf5;
    padding: 0.2rem 0.85rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-block;
}

.status-pending {
    color: #d97706;
    background: #fef3c7;
    padding: 0.2rem 0.85rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-block;
    animation: pulsePending 2s ease-in-out infinite;
}

@keyframes pulsePending {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.status-rejected, .status-cancelled {
    color: #dc2626;
    background: #fee2e2;
    padding: 0.2rem 0.85rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-block;
}

/* ===== CARDS ===== */
.card {
    background: white;
    border: none;
    border-radius: var(--radius-lg);
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: var(--shadow-hover);
}

.card .card-body {
    padding: 1.5rem;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    .sidebar.open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
        padding: 1.25rem;
    }
    
    .sidebar-toggle {
        display: block !important;
    }
}

@media (min-width: 993px) {
    .sidebar-toggle {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .top-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card-dash .stat-info h3 {
        font-size: 1.3rem;
    }
}

/* ===== SIDEBAR TOGGLE BUTTON ===== */
.sidebar-toggle {
    background: none;
    border: none;
    font-size: 1.3rem;
    color: #1a1a2e;
    padding: 0.25rem 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.sidebar-toggle:hover {
    color: #667eea;
}

/* ===== ANIMATION ===== */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card-dash {
    animation: fadeInUp 0.6s ease forwards;
}
.stat-card-dash:nth-child(1) { animation-delay: 0.05s; }
.stat-card-dash:nth-child(2) { animation-delay: 0.1s; }
.stat-card-dash:nth-child(3) { animation-delay: 0.15s; }
.stat-card-dash:nth-child(4) { animation-delay: 0.2s; }

/* ===== BOOKING OVERVIEW ===== */
.booking-overview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.booking-overview-item:last-child {
    border-bottom: none;
}

.booking-overview-item .label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #4b5563;
}

.booking-overview-item .value {
    font-weight: 700;
    font-size: 0.95rem;
    color: #1a1a2e;
}
</style>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="dashboard.php" class="brand-logo">
            <img src="assets/logo.svg" alt="Hall Booking logo">
        </a>
        <h3>Hall Booking</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="active">
                <i class="fa-solid fa-home"></i>
                Dashboard
            </a>
        </li>
        <li>
            <a href="halls.php">
                <i class="fa-solid fa-building"></i>
                Halls
                <span class="badge-sidebar"><?= $totalHalls ?></span>
            </a>
        </li>
        <li>
            <a href="bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                Bookings
                <span class="badge-sidebar"><?= $totalBookings ?></span>
            </a>
        </li>
        <li>
            <a href="receipts.php">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                Receipts
                <span class="badge-sidebar"><?= $totalReceipts ?></span>
            </a>
        </li>
        
        <div class="sidebar-divider"></div>
        
        <li>
            <a href="bookings.php?status=pending">
                <i class="fa-solid fa-clock"></i>
                Pending
                <span class="badge-sidebar" style="background: #f59e0b;"><?= $pendingBookings ?></span>
            </a>
        </li>
        <li>
            <a href="bookings.php?status=confirmed">
                <i class="fa-solid fa-check-circle"></i>
                Approved
                <span class="badge-sidebar" style="background: #10b981;"><?= $confirmedBookings ?></span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="user-card">
            <a href="profile.php" class="profile-link">
                <div class="avatar">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
                <div class="user-info">
                    <div class="name"><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div class="role"><?= htmlspecialchars($_SESSION['role']) ?></div>
                </div>
            </a>
            <a href="logout.php" class="logout-btn" title="Logout">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    
    <div class="top-header">
        <div class="page-title">
            <h4>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>
            </h4>
            <p>
                <i class="fa-solid fa-calendar-alt me-1"></i>
                <?= date('l, d F Y') ?> — Live user overview
            </p>
        </div>
        <div class="header-actions">
            <a href="booking_form.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus me-1"></i> New Booking
            </a>
            <a href="halls.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-building me-1"></i> View Halls
            </a>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card-dash">
            <div class="stat-icon-box blue">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M4 21V9.5L12 4l8 5.5V21H14v-6H10v6H4Zm2-2h4v-4h4v4h4V10.86l-6-4.125-6 4.125V19Zm5-5.5h2V14h-2v-.5Z" />
                </svg>
            </div>
            <div class="stat-info">
                <h5>Total Halls</h5>
                <h3><?= number_format($totalHalls) ?></h3>
            </div>
        </div>
        <div class="stat-card-dash">
            <div class="stat-icon-box green">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2Zm0 16H5V9h14v11Zm-9-4l-3-3 1.41-1.41L10 13.17l5.59-5.59L17 9l-7 7Z"/>
                </svg>
            </div>
            <div class="stat-info">
                <h5>Total Bookings</h5>
                <h3><?= number_format($totalBookings) ?></h3>
            </div>
        </div>
        <div class="stat-card-dash">
            <div class="stat-icon-box purple">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2Zm0 16H5V5h14v14Zm-3-6h-6v2h6v-2Zm0-4h-6v2h6V9Zm-8 8h2v-6H8v6Z"/>
                </svg>
            </div>
            <div class="stat-info">
                <h5>Total Receipts</h5>
                <h3><?= number_format($totalReceipts) ?></h3>
            </div>
        </div>
        <div class="stat-card-dash">
            <div class="stat-icon-box orange">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm1 11.59V7h-2v6l5.25 3.15 1-1.7L13 13.59Z"/>
                </svg>
            </div>
            <div class="stat-info">
                <h5>Pending</h5>
                <h3><?= number_format($pendingBookings) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-chart-column me-2" style="color: #667eea;"></i>
                            Booking Trends
                        </h5>
                        <span class="text-muted" style="font-size: 0.85rem;">Last 6 months</span>
                    </div>
                    <div style="position:relative;height:260px;">
                        <canvas id="bookingChartCanvas"></canvas>
                    </div>
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div class="text-muted">Last 6 months</div>
                        <div style="font-weight:700;color:#0f172a;">Trend: <?= ($chartTrendPct >= 0 ? '+' : '') . $chartTrendPct ?>%</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-clock me-2" style="color: #667eea;"></i>
                            Recent Bookings
                        </h5>
                        <a href="bookings.php" class="text-primary text-decoration-none" style="font-size: 0.85rem; font-weight: 600;">
                            View All <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    
                    <?php if(count($recentBookings) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-dashboard">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>User / Hall</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentBookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <span style="font-weight: 700; color: #667eea;">
                                                    #BK<?= str_pad($booking['booking_id'], 3, '0', STR_PAD_LEFT) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: #1a1a2e;"><?= htmlspecialchars($booking['name'] ?? $_SESSION['name']) ?></div>
                                                <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($booking['hall_name']) ?></small>
                                            </td>
                                            <td>
                                                <?= date('d M Y', strtotime($booking['booking_date'])) ?>
                                                <br>
                                                <small class="text-muted" style="font-size: 0.7rem;">
                                                    <?= date('h:i A', strtotime($booking['start_time'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="status-<?= strtolower($booking['booking_status']) ?>">
                                                    <?= htmlspecialchars($booking['booking_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fa-solid fa-calendar-xmark" style="font-size: 2.5rem; color: #d1d5db;"></i>
                            <p class="text-muted mt-2">No bookings found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="calendar-container mb-4">
                <div class="calendar-nav">
                    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    <h6>
                        <i class="fas fa-calendar-alt me-2" style="color: #667eea;"></i>
                        <?= $monthName ?> <?= $year ?>
                    </h6>
                    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                    <a href="?month=<?= $currentMonth ?>&year=<?= $currentYear ?>" class="nav-btn today-btn">
                        <i class="fa-solid fa-circle me-1" style="font-size: 0.5rem; color: #667eea;"></i> Today
                    </a>
                </div>
                
                <div class="calendar-grid">
                    <div class="day-name">Sun</div>
                    <div class="day-name">Mon</div>
                    <div class="day-name">Tue</div>
                    <div class="day-name">Wed</div>
                    <div class="day-name">Thu</div>
                    <div class="day-name">Fri</div>
                    <div class="day-name">Sat</div>
                    
                    <?php for($i = 0; $i < $firstDayOfMonth; $i++): ?>
                        <div class="day-cell empty"></div>
                    <?php endfor; ?>
                    
                    <?php for($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php 
                            $date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                            $isToday = $date == $today;
                            $statusClass = '';
                            if (isset($calendarBookings[$date])) {
                                if (isset($calendarBookings[$date]['CONFIRMED'])) {
                                    $statusClass = 'confirmed';
                                } elseif (isset($calendarBookings[$date]['PENDING'])) {
                                    $statusClass = 'pending';
                                } elseif (isset($calendarBookings[$date]['CANCELLED'])) {
                                    $statusClass = 'cancelled';
                                }
                            }
                            $hasBooking = $statusClass !== '';
                        ?>
                        <div class="day-cell <?= $isToday ? 'today' : '' ?> <?= $hasBooking ? 'has-booking ' . $statusClass : '' ?>">
                            <?= $day ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="calendar-legend mt-2">
                    <div class="item"><span class="swatch" style="background:#10b981"></span><span>Confirmed</span></div>
                    <div class="item"><span class="swatch" style="background:#f59e0b"></span><span>Pending</span></div>
                    <div class="item"><span class="swatch" style="background:#ef4444"></span><span>Cancelled</span></div>
                    <div class="item"><span class="swatch" style="background:#e5e7eb;border:1px solid #d1d5db"></span><span>Available</span></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="fas fa-chart-pie me-2" style="color: #667eea;"></i>
                        Booking Overview
                    </h6>
                    <div class="booking-overview-item">
                        <span class="label">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>
                            Approved
                        </span>
                        <span class="value"><?= number_format($confirmedBookings) ?></span>
                    </div>
                    <div class="booking-overview-item">
                        <span class="label">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;"></span>
                            Pending
                        </span>
                        <span class="value"><?= number_format($pendingBookings) ?></span>
                    </div>
                    <div class="booking-overview-item">
                        <span class="label">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>
                            Rejected
                        </span>
                        <span class="value"><?= number_format($cancelledBookings ?? 0) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 992) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    }
});

// Auto close sidebar on resize to desktop
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 992) {
        sidebar.classList.remove('open');
    }
});
</script>
<?php
// insert chart assets and calendar legend styles before footer
?>
<style>
.calendar-legend { display:flex; gap:0.75rem; align-items:center; margin-top:0.75rem; flex-wrap:wrap; justify-content:flex-start; width:100%; }
.calendar-legend .item { display:flex; gap:0.5rem; align-items:center; font-size:0.88rem; color:#374151 }
.calendar-legend .swatch { width:12px; height:12px; border-radius:3px; display:inline-block }
.day-cell { position: relative; }
/* status dot using ::after so it is centered and contained in the cell */
.calendar-grid .day-cell.has-booking::after {
    content: '';
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.calendar-grid .day-cell.confirmed::after { background: #10b981; box-shadow: 0 0 0 6px rgba(16,185,129,0.06); }
.calendar-grid .day-cell.pending::after { background: #f59e0b; box-shadow: 0 0 0 6px rgba(245,158,11,0.06); }
.calendar-grid .day-cell.cancelled::after { background: #ef4444; box-shadow: 0 0 0 6px rgba(239,68,68,0.06); }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script>
const ctx = document.getElementById('bookingChartCanvas');
if (ctx) {
    const labels = <?= $chartLabelsJson ?>;
    const dataVals = <?= $chartValuesJson ?>;
    const bookingChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Bookings',
                data: dataVals,
                fill: true,
                backgroundColor: 'rgba(102,126,234,0.12)',
                borderColor: '#3b82f6',
                tension: 0.35,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#3b82f6',
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    color: '#0f172a',
                    anchor: 'end',
                    align: 'top',
                    formatter: function(value) { return value; },
                    font: { weight: '700' }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        },
        plugins: [ChartDataLabels]
    });
}
</script>

<?php include 'partials/footer.php'; ?>