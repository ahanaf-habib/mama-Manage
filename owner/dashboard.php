<?php
$pageTitle = 'Owner Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/owner_auth.php';
require_once __DIR__ . '/../includes/apartment_requests.php';
require_once __DIR__ . '/../includes/tenancy.php';
ensureApartmentRequestsTable($pdo);
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

// Fetch summary metrics scoped to the currently logged-in owner.
$ownerId = (int)getCurrentUserId();

// The apartment-request feature was added after the original database schema.
// Create the table automatically when an older local database is still in use.
ensureApartmentRequestsTable($pdo);

$stmt = $pdo->prepare("SELECT
    COUNT(*) as total,
    COALESCE(SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END), 0) as occupied,
    COALESCE(SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END), 0) as available,
    COALESCE(SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END), 0) as maintenance
    FROM apartments WHERE owner_id = ?");
$stmt->execute([$ownerId]);
$aptStats = $stmt->fetch();

// Tenants currently associated with this owner's apartments.
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT tn.tenant_id)
    FROM tenancies tn
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE a.owner_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())");
$stmt->execute([$ownerId]);
$totalTenants = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*)
    FROM tenancies tn
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE a.owner_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())");
$stmt->execute([$ownerId]);
$activeTenancies = $stmt->fetchColumn();

// Pending apartment rental requests for this owner.
$stmt = $pdo->prepare("SELECT COUNT(*)
    FROM apartment_requests
    WHERE owner_id = ? AND status = 'Pending'");
$stmt->execute([$ownerId]);
$pendingApartmentRequests = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT ar.*, t.tenant_name, t.phone AS tenant_phone,
       t.email AS tenant_email, a.apartment_number, a.floor_level, a.monthly_rent
    FROM apartment_requests ar
    JOIN tenants t ON ar.tenant_id = t.tenant_id
    JOIN apartments a ON ar.apartment_id = a.apartment_id
    WHERE ar.owner_id = ? AND ar.status = 'Pending'
    ORDER BY ar.requested_at DESC, ar.request_id DESC
    LIMIT 5");
$stmt->execute([$ownerId]);
$pendingApartmentRequestList = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*)
    FROM move_out_requests mor
    JOIN tenancies tn ON mor.tenancy_id = tn.tenancy_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE mor.owner_id = ? AND mor.status = 'Pending' AND a.owner_id = ?");
$stmt->execute([$ownerId, $ownerId]);
$pendingMoveOutRequests = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT mor.*, t.tenant_name, a.apartment_number
    FROM move_out_requests mor
    JOIN tenants t ON mor.tenant_id = t.tenant_id
    JOIN tenancies tn ON mor.tenancy_id = tn.tenancy_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE mor.owner_id = ? AND mor.status = 'Pending' AND a.owner_id = ?
    ORDER BY mor.requested_at DESC, mor.request_id DESC
    LIMIT 5");
$stmt->execute([$ownerId, $ownerId]);
$pendingMoveOutRequestList = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*)
    FROM complaints c
    JOIN apartments a ON c.apartment_id = a.apartment_id
    WHERE a.owner_id = ? AND c.complaint_status = 'Pending'");
$stmt->execute([$ownerId]);
$pendingComplaints = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*)
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    WHERE a.owner_id = ? AND mr.maintenance_status IN ('Assigned', 'In Progress')");
$stmt->execute([$ownerId]);
$activeMaintenance = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(p.payment_amount), 0)
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE a.owner_id = ? AND p.payment_status = 'Completed'");
$stmt->execute([$ownerId]);
$totalCollection = $stmt->fetchColumn();

// 1. Recent Complaints belonging to this owner's apartments.
$stmt = $pdo->prepare("SELECT c.*, t.tenant_name, a.apartment_number
    FROM complaints c
    JOIN tenants t ON c.tenant_id = t.tenant_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    WHERE a.owner_id = ?
    ORDER BY c.submission_date DESC, c.complaint_id DESC LIMIT 5");
$stmt->execute([$ownerId]);
$recentComplaints = $stmt->fetchAll();

// 2. Recent Payments belonging to this owner's apartments.
$stmt = $pdo->prepare("SELECT p.*, b.billing_month, t.tenant_name, a.apartment_number
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE a.owner_id = ?
    ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 5");
$stmt->execute([$ownerId]);
$recentPayments = $stmt->fetchAll();

// 3. Overdue Bills belonging to this owner's apartments.
$stmt = $pdo->prepare("SELECT b.*, t.tenant_name, a.apartment_number,
    DATEDIFF(CURDATE(), b.due_date) as overdue_days,
    (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE bill_id = b.bill_id AND payment_status = 'Completed') as paid_amount
    FROM bills b
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE a.owner_id = ?
      AND (b.bill_status = 'Overdue' OR (b.bill_status IN ('Unpaid', 'Partially Paid') AND b.due_date < CURDATE()))
    ORDER BY b.due_date ASC LIMIT 5");
$stmt->execute([$ownerId]);
$overdueBills = $stmt->fetchAll();

// 4. Active Maintenance belonging to this owner's apartments.
$stmt = $pdo->prepare("SELECT mr.*, c.category, c.priority, s.staff_name, a.apartment_number
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE a.owner_id = ? AND mr.maintenance_status IN ('Assigned', 'In Progress')
    ORDER BY mr.maintenance_id DESC LIMIT 5");
$stmt->execute([$ownerId]);
$activeMaintenanceRequests = $stmt->fetchAll();

// 5. Recent notices published by this owner.
$stmt = $pdo->prepare("SELECT n.*, o.owner_name, a.apartment_number
    FROM notices n
    JOIN owners o ON n.owner_id = o.owner_id
    LEFT JOIN apartments a ON n.apartment_id = a.apartment_id
    WHERE n.owner_id = ?
    ORDER BY n.published_at DESC LIMIT 5");
$stmt->execute([$ownerId]);
$recentNotices = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Building Overview Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>. Real-time metrics across all building operations.</p>
        </div>
        <div>
            <a href="/smart_apartment_management_system/owner/reports/" class="btn btn-secondary">&#128202; View Full Reports</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="dashboard-cards">
        <div class="dashboard-card card-blue">
            <div class="card-top">
                <span class="card-label">Total Apartments</span>
                <span class="card-icon">&#127970;</span>
            </div>
            <div class="card-value"><?php echo (int)$aptStats['total']; ?></div>
        </div>

        <div class="dashboard-card card-green">
            <div class="card-top">
                <span class="card-label">Occupied</span>
                <span class="card-icon">&#128101;</span>
            </div>
            <div class="card-value"><?php echo (int)$aptStats['occupied']; ?></div>
        </div>

        <div class="dashboard-card card-cyan">
            <div class="card-top">
                <span class="card-label">Available</span>
                <span class="card-icon">&#128275;</span>
            </div>
            <div class="card-value"><?php echo (int)$aptStats['available']; ?></div>
        </div>

        <div class="dashboard-card card-yellow">
            <div class="card-top">
                <span class="card-label">Under Maintenance</span>
                <span class="card-icon">&#128295;</span>
            </div>
            <div class="card-value"><?php echo (int)$aptStats['maintenance']; ?></div>
        </div>

        <div class="dashboard-card card-blue">
            <div class="card-top">
                <span class="card-label">Total Tenants</span>
                <span class="card-icon">&#128100;</span>
            </div>
            <div class="card-value"><?php echo (int)$totalTenants; ?></div>
        </div>

        <div class="dashboard-card card-green">
            <div class="card-top">
                <span class="card-label">Active Tenancies</span>
                <span class="card-icon">&#128196;</span>
            </div>
            <div class="card-value"><?php echo (int)$activeTenancies; ?></div>
        </div>

        <div class="dashboard-card card-purple">
            <div class="card-top">
                <span class="card-label">Apartment Requests</span>
                <span class="card-icon">&#128233;</span>
            </div>
            <div class="card-value"><?php echo (int)$pendingApartmentRequests; ?></div>
        </div>

        <div class="dashboard-card card-purple">
            <div class="card-top">
                <span class="card-label">Pending Move-Outs</span>
                <span class="card-icon">&#128682;</span>
            </div>
            <div class="card-value"><?php echo (int)$pendingMoveOutRequests; ?></div>
        </div>

        <div class="dashboard-card card-yellow">
            <div class="card-top">
                <span class="card-label">Pending Complaints</span>
                <span class="card-icon">&#128227;</span>
            </div>
            <div class="card-value"><?php echo (int)$pendingComplaints; ?></div>
        </div>

        <div class="dashboard-card card-purple">
            <div class="card-top">
                <span class="card-label">Active Maintenance</span>
                <span class="card-icon">&#9874;</span>
            </div>
            <div class="card-value"><?php echo (int)$activeMaintenance; ?></div>
        </div>

        <div class="dashboard-card card-green" style="grid-column: span 2;">
            <div class="card-top">
                <span class="card-label">Total Payment Collection</span>
                <span class="card-icon">&#128176;</span>
            </div>
            <div class="card-value">&#2547; <?php echo number_format($totalCollection, 2); ?></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><span>&#128233; New Apartment Requests</span><a href="/smart_apartment_management_system/owner/apartment_requests/" class="btn btn-sm btn-secondary">View All</a></div>
        <div class="card-body" style="padding:0;"><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Tenant</th><th>Apartment</th><th>Monthly Rent</th><th>Requested</th><th>Status</th></tr></thead><tbody>
        <?php if (empty($pendingApartmentRequestList)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No new apartment requests.</td></tr>
        <?php else: foreach ($pendingApartmentRequestList as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['tenant_name']); ?></strong><br><small><?php echo htmlspecialchars($r['tenant_phone']); ?></small></td>
                <td>Apt <?php echo htmlspecialchars($r['apartment_number']); ?></td>
                <td>&#2547; <?php echo number_format($r['monthly_rent'], 2); ?></td>
                <td><?php echo date('d M Y, h:i A', strtotime($r['requested_at'])); ?></td>
                <td><span class="badge badge-warning">Pending</span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div></div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><span>&#128682; New Move-Out Requests</span><a href="/smart_apartment_management_system/owner/move_out_requests/" class="btn btn-sm btn-secondary">View All</a></div>
        <div class="card-body" style="padding:0;"><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Tenant</th><th>Apartment</th><th>Requested</th><th>Move-Out Date</th><th>Status</th></tr></thead><tbody>
        <?php if (empty($pendingMoveOutRequestList)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No new move-out requests.</td></tr>
        <?php else: foreach ($pendingMoveOutRequestList as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['tenant_name']); ?></strong></td>
                <td>Apt <?php echo htmlspecialchars($r['apartment_number']); ?></td>
                <td><?php echo date('d M Y, h:i A', strtotime($r['requested_at'])); ?></td>
                <td><?php echo date('d M Y', strtotime($r['proposed_move_out_date'])); ?></td>
                <td><span class="badge badge-warning">Pending</span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div></div>
    </div>

    <!-- Previews Section -->
    <div class="grid-2">
        <!-- Recent Complaints -->
        <div class="card">
            <div class="card-header">
                <span>&#128227; Recent Complaints</span>
                <a href="/smart_apartment_management_system/owner/complaints/" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Apt</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentComplaints)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No complaints filed</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentComplaints as $c): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($c['tenant_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($c['apartment_number']); ?></td>
                                        <td><?php echo htmlspecialchars($c['category']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $c['priority'] === 'High' ? 'badge-danger' : ($c['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); ?>"><?php echo htmlspecialchars($c['priority']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $c['complaint_status'] === 'Resolved' ? 'badge-success' : ($c['complaint_status'] === 'Pending' ? 'badge-warning' : 'badge-info'); ?>"><?php echo htmlspecialchars($c['complaint_status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header">
                <span>&#128176; Recent Payments</span>
                <a href="/smart_apartment_management_system/owner/payments/" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Apt</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No payments recorded</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentPayments as $p): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($p['tenant_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['apartment_number']); ?></td>
                                        <td>&#2547; <?php echo number_format($p['payment_amount'], 2); ?></td>
                                        <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                        <td><span class="badge badge-success"><?php echo htmlspecialchars($p['payment_status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Previews -->
    <div class="grid-2" style="margin-top: 24px;">
        <!-- Overdue Bills -->
        <div class="card">
            <div class="card-header">
                <span>&#9888; Overdue Bills</span>
                <a href="/smart_apartment_management_system/owner/overdue/" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Apt</th>
                                <th>Amount</th>
                                <th>Overdue By</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($overdueBills)): ?>
                                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No overdue bills</td></tr>
                            <?php else: ?>
                                <?php foreach ($overdueBills as $bill): ?>
                                    <?php $balance = max(0, (float)$bill['total_amount'] - (float)$bill['paid_amount']); ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($bill['tenant_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($bill['apartment_number']); ?></td>
                                        <td>&#2547; <?php echo number_format($bill['total_amount'], 2); ?></td>
                                        <td><?php echo max(0, (int)$bill['overdue_days']); ?> days</td>
                                        <td>&#2547; <?php echo number_format($balance, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Active Maintenance -->
        <div class="card">
            <div class="card-header">
                <span>&#128295; Active Maintenance</span>
                <a href="/smart_apartment_management_system/owner/maintenance/" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Apartment</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Staff</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activeMaintenanceRequests)): ?>
                                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);">No active maintenance</td></tr>
                            <?php else: ?>
                                <?php foreach ($activeMaintenanceRequests as $m): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($m['apartment_number']); ?></td>
                                        <td><?php echo htmlspecialchars($m['category']); ?></td>
                                        <td><span class="badge <?php echo $m['priority'] === 'High' ? 'badge-danger' : ($m['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); ?>"><?php echo htmlspecialchars($m['priority']); ?></span></td>
                                        <td><?php echo htmlspecialchars($m['staff_name'] ?? 'Unassigned'); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($m['maintenance_status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 24px;">
        <div class="card-header">
            <span>&#128240; Recent Notices</span>
            <a href="/smart_apartment_management_system/owner/notices/" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Published</th><th>Audience</th><th>Apartment</th><th>Message</th></tr></thead>
                    <tbody>
                    <?php if (empty($recentNotices)): ?>
                        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No notices published</td></tr>
                    <?php else: foreach ($recentNotices as $n): ?>
                        <tr>
                            <td><?php echo date('d M Y, h:i A', strtotime($n['published_at'])); ?></td>
                            <td><?php echo htmlspecialchars($n['target_type']); ?></td>
                            <td><?php echo $n['apartment_number'] ? 'Apt ' . htmlspecialchars($n['apartment_number']) : 'All'; ?></td>
                            <td><?php echo htmlspecialchars($n['message_content']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
