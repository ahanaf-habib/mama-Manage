<?php
$pageTitle = 'Tenant Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/tenancy.php';
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$tenant_id = (int)$_SESSION['user_id'];

// 1. Fetch the current active tenancy and apartment.
$currentApt = getCurrentActiveTenancy($pdo, $tenant_id);

// 2. Fetch latest bill for this tenant
$stmt = $pdo->prepare("SELECT b.*,
    (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE bill_id = b.bill_id AND payment_status = 'Completed') as paid_amount,
    DATEDIFF(CURDATE(), b.due_date) as overdue_days
    FROM bills b
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    WHERE tn.tenant_id = ?
    ORDER BY b.billing_month DESC, b.bill_id DESC
    LIMIT 1");
$stmt->execute([$tenant_id]);
$latestBill = $stmt->fetch();

// 3. Fetch latest payment made by this tenant
$stmt = $pdo->prepare("SELECT p.*, b.billing_month
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    WHERE tn.tenant_id = ?
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT 1");
$stmt->execute([$tenant_id]);
$latestPayment = $stmt->fetch();

// 4. Fetch active complaints for this tenant
$stmt = $pdo->prepare("SELECT * FROM complaints 
    WHERE tenant_id = ? AND complaint_status != 'Resolved'
    ORDER BY submission_date DESC LIMIT 5");
$stmt->execute([$tenant_id]);
$activeComplaints = $stmt->fetchAll();

// 5. Fetch recent maintenance activities for this tenant's complaints
$stmt = $pdo->prepare("SELECT mr.*, c.category, c.priority, s.staff_name
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE c.tenant_id = ?
    ORDER BY mr.maintenance_id DESC LIMIT 5");
$stmt->execute([$tenant_id]);
$recentMaintenance = $stmt->fetchAll();

// 6. Fetch relevant notices for this tenant
// Everyone OR (Apartment AND apt_id) OR (Floor AND floor_level)
$aptId = $currentApt ? (int)$currentApt['apartment_id'] : -1;
$floorNum = $currentApt ? (int)$currentApt['floor_level'] : -1;

$stmt = $pdo->prepare("SELECT n.*, o.owner_name, a.apartment_number
    FROM notices n
    JOIN owners o ON n.owner_id = o.owner_id
    LEFT JOIN apartments a ON n.apartment_id = a.apartment_id
    WHERE n.target_type = 'Everyone'
       OR (n.target_type = 'Apartment' AND n.apartment_id = ?)
       OR (n.target_type = 'Floor' AND n.floor_number = ?)
    ORDER BY n.published_at DESC LIMIT 5");
$stmt->execute([$aptId, $floorNum]);
$notices = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            <p>Your personalized resident portal overview. Manage rent, invoices, and service requests.</p>
        </div>
        <div>
            <a href="complaints.php" class="btn btn-primary">&#43; Submit Complaint</a>
        </div>
    </div>

    <!-- Apartment Overview Card -->
    <div class="card">
        <div class="card-header">
            <span>&#127970; Your Apartment & Lease</span>
            <?php if ($currentApt): ?>
                <span class="badge badge-success">Active Tenancy</span>
            <?php else: ?>
                <span class="badge badge-secondary">No Active Lease</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($currentApt): ?>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Apartment Unit</span>
                        <span class="detail-value" style="font-size: 16px;">Apt <?php echo htmlspecialchars($currentApt['apartment_number']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Floor Level</span>
                        <span class="detail-value">Floor <?php echo (int)$currentApt['floor_level']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Monthly Rent</span>
                        <span class="detail-value" style="font-size: 16px; color: var(--primary);">&#2547; <?php echo number_format($currentApt['monthly_rent'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Move-In Date</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($currentApt['move_in_date'])); ?></span>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;">
                        <span class="detail-label">Apartment Features</span>
                        <span class="detail-value" style="font-weight: normal;"><?php echo htmlspecialchars($currentApt['apartment_desc'] ?? 'Standard apartment unit.'); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 20px;">
                    <p>You currently do not have an active apartment lease assigned in the building system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Financial Status Row -->
    <div class="grid-2">
        <!-- Current/Latest Bill -->
        <div class="card">
            <div class="card-header">
                <span>&#128179; Current Invoice</span>
                <a href="bills.php" class="btn btn-sm btn-secondary">All Invoices</a>
            </div>
            <div class="card-body">
                <?php if ($latestBill): ?>
                    <?php 
                    $dueBalance = max(0, (float)$latestBill['total_amount'] - (float)$latestBill['paid_amount']);
                    ?>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Billing Month</span>
                            <span class="detail-value"><?php echo date('F Y', strtotime($latestBill['billing_month'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Bill Status</span>
                            <span class="detail-value">
                                <span class="badge <?php 
                                    echo $latestBill['bill_status'] === 'Paid' ? 'badge-success' : ($latestBill['bill_status'] === 'Partially Paid' ? 'badge-info' : ($latestBill['bill_status'] === 'Overdue' ? 'badge-danger' : 'badge-warning')); 
                                ?>">
                                    <?php echo htmlspecialchars($latestBill['bill_status']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Total Invoiced</span>
                            <span class="detail-value">&#2547; <?php echo number_format($latestBill['total_amount'], 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Remaining Balance</span>
                            <span class="detail-value" style="color: <?php echo $dueBalance > 0 ? 'var(--danger)' : 'var(--success)'; ?>; font-weight: 700;">
                                &#2547; <?php echo number_format($dueBalance, 2); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Due Date</span>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($latestBill['due_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Overdue Notice</span>
                            <span class="detail-value">
                                <?php if ($latestBill['overdue_days'] > 0 && in_array($latestBill['bill_status'], ['Unpaid', 'Partially Paid', 'Overdue'])): ?>
                                    <span class="badge badge-danger"><?php echo (int)$latestBill['overdue_days']; ?> Days Past Due</span>
                                <?php else: ?>
                                    <span style="color: var(--success); font-size: 13px;">&#10004; On Schedule</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No invoices have been issued for your account yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Latest Payment -->
        <div class="card">
            <div class="card-header">
                <span>&#128176; Recent Payment Record</span>
                <a href="payments.php" class="btn btn-sm btn-secondary">Payment History</a>
            </div>
            <div class="card-body">
                <?php if ($latestPayment): ?>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Payment ID</span>
                            <span class="detail-value">#<?php echo (int)$latestPayment['payment_id']; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Amount Paid</span>
                            <span class="detail-value" style="color: var(--success); font-weight: 700;">&#2547; <?php echo number_format($latestPayment['payment_amount'], 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Payment Date</span>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($latestPayment['payment_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Method</span>
                            <span class="detail-value"><?php echo htmlspecialchars($latestPayment['payment_method']); ?></span>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <span class="detail-label">Transaction Reference</span>
                            <span class="detail-value"><code><?php echo htmlspecialchars($latestPayment['transaction_reference_id'] ?? 'Cash Receipt'); ?></code></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted);">No payments have been recorded on your account.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Active Complaints & Maintenance Activity -->
    <div class="grid-2">
        <!-- Active Complaints -->
        <div class="card">
            <div class="card-header">
                <span>&#128227; Your Active Complaints</span>
                <a href="complaints.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeComplaints)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No active complaints</td></tr>
                            <?php else: ?>
                                <?php foreach ($activeComplaints as $c): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($c['category']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $c['priority'] === 'High' ? 'badge-danger' : ($c['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                            ?>"><?php echo htmlspecialchars($c['priority']); ?></span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($c['submission_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $c['complaint_status'] === 'Resolved' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo htmlspecialchars($c['complaint_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Maintenance Activity -->
        <div class="card">
            <div class="card-header">
                <span>&#128295; Recent Maintenance Activity</span>
                <a href="maintenance.php" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Technician</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentMaintenance)): ?>
                                <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No maintenance ongoing</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentMaintenance as $rm): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($rm['category']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($rm['staff_name'] ?? 'Assigned Technician'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $rm['maintenance_status'] === 'Completed' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo htmlspecialchars($rm['maintenance_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Notices for this Tenant -->
    <div class="card">
        <div class="card-header">
            <span>&#128226; Notices & Announcements for You</span>
            <a href="notices.php" class="btn btn-sm btn-secondary">All Notices</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Announcement</th>
                            <th>Scope</th>
                            <th>Published Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notices)): ?>
                            <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No announcements published for you</td></tr>
                        <?php else: ?>
                            <?php foreach ($notices as $n): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($n['message_content']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $n['target_type'] === 'Everyone' ? 'badge-info' : ($n['target_type'] === 'Apartment' ? 'badge-warning' : 'badge-purple'); 
                                        ?>"><?php echo htmlspecialchars($n['target_type']); ?></span>
                                    </td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($n['published_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
