<?php
$pageTitle = 'Analytical Reports';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$tab = $_GET['tab'] ?? 'apartments';
if (!in_array($tab, ['apartments', 'tenants', 'billing', 'payments', 'complaints', 'maintenance'])) {
    $tab = 'apartments';
}
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Comprehensive DBMS Operational Reports</h1>
            <p>Analytical aggregation queries, financial breakdowns, and operational metrics.</p>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="tabs-nav">
        <a href="?tab=apartments" class="tab-link <?php echo $tab === 'apartments' ? 'active' : ''; ?>">&#127970; Apartments</a>
        <a href="?tab=tenants" class="tab-link <?php echo $tab === 'tenants' ? 'active' : ''; ?>">&#128101; Tenants & Leases</a>
        <a href="?tab=billing" class="tab-link <?php echo $tab === 'billing' ? 'active' : ''; ?>">&#128179; Invoicing & Billing</a>
        <a href="?tab=payments" class="tab-link <?php echo $tab === 'payments' ? 'active' : ''; ?>">&#128176; Payment Collections</a>
        <a href="?tab=complaints" class="tab-link <?php echo $tab === 'complaints' ? 'active' : ''; ?>">&#128227; Complaints</a>
        <a href="?tab=maintenance" class="tab-link <?php echo $tab === 'maintenance' ? 'active' : ''; ?>">&#128295; Maintenance & Expenses</a>
    </div>

    <?php if ($tab === 'apartments'): ?>
        <!-- Tab 1: Apartments Report -->
        <?php
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
            SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance,
            AVG(monthly_rent) as avg_rent,
            SUM(monthly_rent) as total_rent_capacity
            FROM apartments WHERE owner_id = ?");
        $stmt->execute([$ownerId]);
        $aptStats = $stmt->fetch();

        // Floor breakdown for this owner's apartments only.
        $stmt = $pdo->prepare("SELECT 
            floor_level,
            COUNT(*) as total_units,
            SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied_units,
            SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available_units,
            SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance_units,
            AVG(monthly_rent) as avg_floor_rent,
            SUM(monthly_rent) as floor_revenue_potential
            FROM apartments
            WHERE owner_id = ?
            GROUP BY floor_level
            ORDER BY floor_level ASC");
        $stmt->execute([$ownerId]);
        $floorBreakdown = $stmt->fetchAll();
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-val"><?php echo (int)$aptStats['total']; ?></div>
                <div class="stat-box-lbl">Total Apartments</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--success);"><?php echo (int)$aptStats['occupied']; ?></div>
                <div class="stat-box-lbl">Occupied Units</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--info);"><?php echo (int)$aptStats['available']; ?></div>
                <div class="stat-box-lbl">Available Units</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--warning);"><?php echo (int)$aptStats['maintenance']; ?></div>
                <div class="stat-box-lbl">Under Maintenance</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val">&#2547; <?php echo number_format($aptStats['avg_rent'], 2); ?></div>
                <div class="stat-box-lbl">Average Rent</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>&#127970; Apartments Inventory Breakdown by Floor Level</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Floor Level</th>
                                <th>Total Units</th>
                                <th>Occupied</th>
                                <th>Available</th>
                                <th>Under Maint</th>
                                <th>Occupancy Rate</th>
                                <th>Avg Rent (&#2547;)</th>
                                <th>Total Monthly Potential</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($floorBreakdown as $fb): ?>
                                <?php 
                                $occRate = ($fb['total_units'] > 0) ? round(($fb['occupied_units'] / $fb['total_units']) * 100, 1) : 0; 
                                ?>
                                <tr>
                                    <td><strong>Floor <?php echo (int)$fb['floor_level']; ?></strong></td>
                                    <td><?php echo (int)$fb['total_units']; ?></td>
                                    <td><span class="badge badge-success"><?php echo (int)$fb['occupied_units']; ?></span></td>
                                    <td><span class="badge badge-info"><?php echo (int)$fb['available_units']; ?></span></td>
                                    <td><span class="badge badge-warning"><?php echo (int)$fb['maintenance_units']; ?></span></td>
                                    <td><strong><?php echo $occRate; ?>%</strong></td>
                                    <td>&#2547; <?php echo number_format($fb['avg_floor_rent'], 2); ?></td>
                                    <td><strong>&#2547; <?php echo number_format($fb['floor_revenue_potential'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'tenants'): ?>
        <!-- Tab 2: Tenants & Leases Report -->
        <?php
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT tn.tenant_id)
            FROM tenancies tn
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $tenantCount = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenancies tn JOIN apartments a ON tn.apartment_id = a.apartment_id WHERE tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE()) AND a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $activeTenancies = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenancies tn JOIN apartments a ON tn.apartment_id = a.apartment_id WHERE tn.tenancy_status = 'Ended' AND a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $endedTenancies = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT tn.*, 
            t.tenant_name, t.phone, t.email, t.id_reference,
            a.apartment_number, a.floor_level, a.monthly_rent,
            DATEDIFF(COALESCE(tn.move_out_date, CURDATE()), tn.move_in_date) as duration_days
            FROM tenancies tn
            JOIN tenants t ON tn.tenant_id = t.tenant_id
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE a.owner_id = ?
            ORDER BY tn.move_in_date DESC");
        $stmt->execute([$ownerId]);
        $historyList = $stmt->fetchAll();
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-val"><?php echo (int)$tenantCount; ?></div>
                <div class="stat-box-lbl">Total Registered Tenants</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--success);"><?php echo (int)$activeTenancies; ?></div>
                <div class="stat-box-lbl">Active Leases</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--text-muted);"><?php echo (int)$endedTenancies; ?></div>
                <div class="stat-box-lbl">Historical Ended Leases</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>&#128196; Comprehensive Tenancy & Living History Log</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tenancy ID</th>
                                <th>Tenant Name</th>
                                <th>Phone</th>
                                <th>Apartment</th>
                                <th>Monthly Rent</th>
                                <th>Move-In Date</th>
                                <th>Move-Out Date</th>
                                <th>Tenancy Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyList as $hl): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$hl['tenancy_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($hl['tenant_name']); ?></td>
                                    <td><?php echo htmlspecialchars($hl['phone']); ?></td>
                                    <td>Apt <?php echo htmlspecialchars($hl['apartment_number']); ?></td>
                                    <td>&#2547; <?php echo number_format($hl['monthly_rent'], 2); ?></td>
                                    <td><?php echo date('d M Y', strtotime($hl['move_in_date'])); ?></td>
                                    <td><?php echo $hl['move_out_date'] ? date('d M Y', strtotime($hl['move_out_date'])) : 'Present'; ?></td>
                                    <td><?php echo (int)$hl['duration_days']; ?> days</td>
                                    <td>
                                        <span class="badge <?php echo $hl['tenancy_status'] === 'Active' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo htmlspecialchars($hl['tenancy_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'billing'): ?>
        <!-- Tab 3: Billing Report -->
        <?php
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_bills,
            SUM(CASE WHEN b.bill_status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN b.bill_status = 'Partially Paid' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN b.bill_status = 'Unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN b.bill_status = 'Overdue' THEN 1 ELSE 0 END) as overdue_count,
            SUM(b.total_amount) as total_billed,
            SUM(b.rent_amount) as total_rent,
            SUM(b.utility_charge) as total_utility,
            SUM(b.maintenance_service_charge) as total_maintenance_charge,
            SUM(b.other_charge) as total_other
            FROM bills b
            JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $billSummary = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(p.payment_amount), 0)
            FROM payments p
            JOIN bills b ON p.bill_id = b.bill_id
            JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE p.payment_status = 'Completed' AND a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $totalCollected = $stmt->fetchColumn();
        $totalOutstanding = max(0, (float)$billSummary['total_billed'] - (float)$totalCollected);

        // Monthly billing breakdown for this owner's apartments only.
        $stmt = $pdo->prepare("SELECT 
            DATE_FORMAT(b.billing_month, '%Y-%m') as month_key,
            COUNT(*) as total_invoices,
            SUM(b.total_amount) as month_billed,
            SUM(CASE WHEN b.bill_status = 'Paid' THEN 1 ELSE 0 END) as paid_invoices,
            SUM(CASE WHEN b.bill_status = 'Partially Paid' THEN 1 ELSE 0 END) as partial_invoices,
            SUM(CASE WHEN b.bill_status = 'Unpaid' THEN 1 ELSE 0 END) as unpaid_invoices,
            SUM(CASE WHEN b.bill_status = 'Overdue' THEN 1 ELSE 0 END) as overdue_invoices
            FROM bills b
            JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE a.owner_id = ?
            GROUP BY DATE_FORMAT(b.billing_month, '%Y-%m')
            ORDER BY month_key DESC");
        $stmt->execute([$ownerId]);
        $monthlyBills = $stmt->fetchAll();
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-val"><?php echo (int)$billSummary['total_bills']; ?></div>
                <div class="stat-box-lbl">Total Invoices</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val">&#2547; <?php echo number_format($billSummary['total_billed'], 2); ?></div>
                <div class="stat-box-lbl">Total Invoiced Amount</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--success);">&#2547; <?php echo number_format($totalCollected, 2); ?></div>
                <div class="stat-box-lbl">Total Collections</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--danger);">&#2547; <?php echo number_format($totalOutstanding, 2); ?></div>
                <div class="stat-box-lbl">Total Outstanding Due</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>&#128179; Monthly Billing Aggregation</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Billing Month</th>
                                <th>Total Invoices</th>
                                <th>Total Invoiced (&#2547;)</th>
                                <th>Paid</th>
                                <th>Partial</th>
                                <th>Unpaid</th>
                                <th>Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyBills as $mb): ?>
                                <tr>
                                    <td><strong><?php echo date('F Y', strtotime($mb['month_key'] . '-01')); ?></strong></td>
                                    <td><?php echo (int)$mb['total_invoices']; ?></td>
                                    <td><strong>&#2547; <?php echo number_format($mb['month_billed'], 2); ?></strong></td>
                                    <td><span class="badge badge-success"><?php echo (int)$mb['paid_invoices']; ?></span></td>
                                    <td><span class="badge badge-info"><?php echo (int)$mb['partial_invoices']; ?></span></td>
                                    <td><span class="badge badge-warning"><?php echo (int)$mb['unpaid_invoices']; ?></span></td>
                                    <td><span class="badge badge-danger"><?php echo (int)$mb['overdue_invoices']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'payments'): ?>
        <!-- Tab 4: Payments Report -->
        <?php
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN p.payment_status = 'Completed' THEN p.payment_amount ELSE 0 END) as total_completed,
            AVG(CASE WHEN p.payment_status = 'Completed' THEN p.payment_amount ELSE NULL END) as avg_payment,
            SUM(CASE WHEN p.payment_status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN p.payment_status = 'Failed' THEN 1 ELSE 0 END) as failed_count
            FROM payments p
            JOIN bills b ON p.bill_id = b.bill_id
            JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $paySummary = $stmt->fetch();

        // Payment Method breakdown for this owner.
        $stmt = $pdo->prepare("SELECT 
            p.payment_method,
            COUNT(*) as transaction_count,
            SUM(p.payment_amount) as total_amount,
            AVG(p.payment_amount) as avg_amount
            FROM payments p
            JOIN bills b ON p.bill_id = b.bill_id
            JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE p.payment_status = 'Completed' AND a.owner_id = ?
            GROUP BY p.payment_method
            ORDER BY total_amount DESC");
        $stmt->execute([$ownerId]);
        $methodStats = $stmt->fetchAll();

        // Monthly collection for this owner.
        $stmt = $pdo->prepare("SELECT 
            DATE_FORMAT(p.payment_date, '%Y-%m') as pay_month,
            COUNT(*) as payment_count,
            SUM(p.payment_amount) as total_collected
            FROM payments p
            JOIN bills b ON p.bill_id = b.bill_id
            JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
            JOIN apartments a ON tn.apartment_id = a.apartment_id
            WHERE p.payment_status = 'Completed' AND a.owner_id = ?
            GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
            ORDER BY pay_month DESC");
        $stmt->execute([$ownerId]);
        $monthlyCollections = $stmt->fetchAll();
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-val"><?php echo (int)$paySummary['total_payments']; ?></div>
                <div class="stat-box-lbl">Total Transactions</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--success);">&#2547; <?php echo number_format($paySummary['total_completed'], 2); ?></div>
                <div class="stat-box-lbl">Total Completed Collection</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val">&#2547; <?php echo number_format($paySummary['avg_payment'] ?? 0, 2); ?></div>
                <div class="stat-box-lbl">Average Transaction</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <span>&#128176; Collections by Payment Method</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Transactions</th>
                                    <th>Total Collected (&#2547;)</th>
                                    <th>Avg Transaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($methodStats as $ms): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ms['payment_method']); ?></strong></td>
                                        <td><?php echo (int)$ms['transaction_count']; ?></td>
                                        <td><strong>&#2547; <?php echo number_format($ms['total_amount'], 2); ?></strong></td>
                                        <td>&#2547; <?php echo number_format($ms['avg_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span>&#128197; Monthly Collection Performance</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Transactions</th>
                                    <th>Total Collected (&#2547;)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyCollections as $mc): ?>
                                    <tr>
                                        <td><strong><?php echo date('F Y', strtotime($mc['pay_month'] . '-01')); ?></strong></td>
                                        <td><?php echo (int)$mc['payment_count']; ?></td>
                                        <td><strong style="color: var(--success);">&#2547; <?php echo number_format($mc['total_collected'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'complaints'): ?>
        <!-- Tab 5: Complaints Report -->
        <?php
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN c.complaint_status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN c.complaint_status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN c.complaint_status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN c.complaint_status = 'Resolved' THEN 1 ELSE 0 END) as resolved
            FROM complaints c
            JOIN apartments a ON c.apartment_id = a.apartment_id
            WHERE a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $compStats = $stmt->fetch();

        // By Category for this owner.
        $stmt = $pdo->prepare("SELECT 
            c.category,
            COUNT(*) as total,
            SUM(CASE WHEN c.complaint_status = 'Resolved' THEN 1 ELSE 0 END) as resolved_count,
            SUM(CASE WHEN c.complaint_status != 'Resolved' THEN 1 ELSE 0 END) as unresolved_count
            FROM complaints c
            JOIN apartments a ON c.apartment_id = a.apartment_id
            WHERE a.owner_id = ?
            GROUP BY c.category
            ORDER BY total DESC");
        $stmt->execute([$ownerId]);
        $categoryBreakdown = $stmt->fetchAll();

        // By Priority for this owner.
        $stmt = $pdo->prepare("SELECT 
            c.priority,
            COUNT(*) as total,
            SUM(CASE WHEN c.complaint_status = 'Resolved' THEN 1 ELSE 0 END) as resolved_count
            FROM complaints c
            JOIN apartments a ON c.apartment_id = a.apartment_id
            WHERE a.owner_id = ?
            GROUP BY c.priority
            ORDER BY FIELD(c.priority, 'High', 'Medium', 'Low')");
        $stmt->execute([$ownerId]);
        $priorityBreakdown = $stmt->fetchAll();
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-val"><?php echo (int)$compStats['total']; ?></div>
                <div class="stat-box-lbl">Total Complaints</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--warning);"><?php echo (int)$compStats['pending']; ?></div>
                <div class="stat-box-lbl">Pending Review</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--info);"><?php echo (int)$compStats['in_progress'] + (int)$compStats['assigned']; ?></div>
                <div class="stat-box-lbl">In Process</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--success);"><?php echo (int)$compStats['resolved']; ?></div>
                <div class="stat-box-lbl">Resolved</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <span>&#128227; Complaints Grouped by Category</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total Filed</th>
                                    <th>Resolved</th>
                                    <th>Unresolved</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryBreakdown as $cb): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($cb['category']); ?></strong></td>
                                        <td><?php echo (int)$cb['total']; ?></td>
                                        <td><span class="badge badge-success"><?php echo (int)$cb['resolved_count']; ?></span></td>
                                        <td><span class="badge badge-warning"><?php echo (int)$cb['unresolved_count']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span>&#9888; Complaints Grouped by Urgency Priority</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Priority Level</th>
                                    <th>Total Complaints</th>
                                    <th>Resolution Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($priorityBreakdown as $pb): ?>
                                    <?php 
                                    $rate = ($pb['total'] > 0) ? round(($pb['resolved_count'] / $pb['total']) * 100, 1) : 0; 
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php 
                                                echo $pb['priority'] === 'High' ? 'badge-danger' : ($pb['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                            ?>"><?php echo htmlspecialchars($pb['priority']); ?> Priority</span>
                                        </td>
                                        <td><strong><?php echo (int)$pb['total']; ?></strong></td>
                                        <td><?php echo $rate; ?>% (<?php echo (int)$pb['resolved_count']; ?> resolved)</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'maintenance'): ?>
        <!-- Tab 6: Maintenance Report -->
        <?php
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN mr.maintenance_status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN mr.maintenance_status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN mr.maintenance_status = 'Assigned' THEN 1 ELSE 0 END) as assigned_count,
            SUM(mr.estimated_cost) as total_estimated_cost,
            SUM(CASE WHEN mr.maintenance_status = 'Completed' THEN mr.estimated_cost ELSE 0 END) as completed_cost,
            AVG(mr.estimated_cost) as avg_cost
            FROM maintenance_requests mr
            JOIN complaints c ON mr.complaint_id = c.complaint_id
            JOIN apartments a ON c.apartment_id = a.apartment_id
            WHERE a.owner_id = ?");
        $stmt->execute([$ownerId]);
        $maintSummary = $stmt->fetch();

        // Maintenance by Staff for this owner's work orders. The staff directory itself remains global.
        $stmt = $pdo->prepare("SELECT 
            s.staff_id, s.staff_name, s.experties,
            COUNT(mr.maintenance_id) as total_assigned,
            SUM(CASE WHEN mr.maintenance_status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(mr.estimated_cost) as total_repair_costs
            FROM staff s
            JOIN maintenance_requests mr ON s.staff_id = mr.staff_id
            JOIN complaints c ON mr.complaint_id = c.complaint_id
            JOIN apartments a ON c.apartment_id = a.apartment_id
            WHERE a.owner_id = ?
            GROUP BY s.staff_id, s.staff_name, s.experties
            ORDER BY total_assigned DESC");
        $stmt->execute([$ownerId]);
        $staffStats = $stmt->fetchAll();

        // Maintenance by Complaint Priority for this owner.
        $stmt = $pdo->prepare("SELECT 
            c.priority,
            COUNT(mr.maintenance_id) as request_count,
            SUM(mr.estimated_cost) as total_priority_cost,
            AVG(mr.estimated_cost) as avg_priority_cost
            FROM maintenance_requests mr
            JOIN complaints c ON mr.complaint_id = c.complaint_id
            JOIN apartments a ON c.apartment_id = a.apartment_id
            WHERE a.owner_id = ?
            GROUP BY c.priority
            ORDER BY FIELD(c.priority, 'High', 'Medium', 'Low')");
        $stmt->execute([$ownerId]);
        $priorityCosts = $stmt->fetchAll();
        ?>
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-val"><?php echo (int)$maintSummary['total_requests']; ?></div>
                <div class="stat-box-lbl">Total Work Orders</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--success);"><?php echo (int)$maintSummary['completed_count']; ?></div>
                <div class="stat-box-lbl">Completed Repairs</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val" style="color: var(--warning);">&#2547; <?php echo number_format($maintSummary['total_estimated_cost'], 2); ?></div>
                <div class="stat-box-lbl">Total Estimated Maintenance Expense</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-val">&#2547; <?php echo number_format($maintSummary['avg_cost'] ?? 0, 2); ?></div>
                <div class="stat-box-lbl">Average Repair Cost</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <span>&#128119; Technician Assignment & Cost Summary</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Expertise</th>
                                    <th>Total Assigned</th>
                                    <th>Completed</th>
                                    <th>Total Estimated Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffStats as $ss): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ss['staff_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($ss['experties']); ?></td>
                                        <td><?php echo (int)$ss['total_assigned']; ?></td>
                                        <td><span class="badge badge-success"><?php echo (int)$ss['completed_tasks']; ?></span></td>
                                        <td><strong>&#2547; <?php echo number_format($ss['total_repair_costs'] ?? 0, 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span>&#9888; Repair Expense by Complaint Priority</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Priority</th>
                                    <th>Work Orders</th>
                                    <th>Total Estimated Cost (&#2547;)</th>
                                    <th>Average Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($priorityCosts as $pc): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php 
                                                echo $pc['priority'] === 'High' ? 'badge-danger' : ($pc['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                            ?>"><?php echo htmlspecialchars($pc['priority']); ?> Priority</span>
                                        </td>
                                        <td><?php echo (int)$pc['request_count']; ?></td>
                                        <td><strong>&#2547; <?php echo number_format($pc['total_priority_cost'], 2); ?></strong></td>
                                        <td>&#2547; <?php echo number_format($pc['avg_priority_cost'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
