<?php
$pageTitle = 'Overdue Payments';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$search = trim($_GET['search'] ?? '');

$sql = "SELECT b.*, 
               t.tenant_name, 
               t.tenant_id,
               t.phone,
               a.apartment_number, 
               a.floor_level,
               DATEDIFF(CURDATE(), b.due_date) as overdue_days,
               COALESCE(p.paid_sum, 0) as paid_amount,
               (b.total_amount - COALESCE(p.paid_sum, 0)) as outstanding_amount
        FROM bills b
        JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
        JOIN tenants t ON tn.tenant_id = t.tenant_id
        JOIN apartments a ON tn.apartment_id = a.apartment_id
        LEFT JOIN (
            SELECT bill_id, SUM(payment_amount) as paid_sum 
            FROM payments 
            WHERE payment_status = 'Completed' 
            GROUP BY bill_id
        ) p ON b.bill_id = p.bill_id
        WHERE a.owner_id = ?
          AND (b.bill_status = 'Overdue' OR (b.bill_status IN ('Unpaid', 'Partially Paid') AND b.due_date < CURDATE()))
          AND (b.total_amount - COALESCE(p.paid_sum, 0)) > 0";

$params = [$ownerId];
if ($search !== '') {
    $sql .= " AND (t.tenant_name LIKE ? OR a.apartment_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY overdue_days DESC, b.due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$overdueList = $stmt->fetchAll();

// Calculate totals
$totalOverdueBills = count($overdueList);
$totalOutstandingAmount = 0;
foreach ($overdueList as $item) {
    $totalOutstandingAmount += (float)$item['outstanding_amount'];
}
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Overdue Payment Tracking</h1>
            <p>Real-time monitor of past-due invoices, arrears, and accumulated overdue days.</p>
        </div>
        <div>
            <a href="../bills/index.php" class="btn btn-secondary">&larr; All Bills</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics Cards -->
    <div class="stats-row">
        <div class="stat-box" style="border-left: 4px solid var(--danger);">
            <div class="stat-box-val" style="color: var(--danger);"><?php echo (int)$totalOverdueBills; ?></div>
            <div class="stat-box-lbl">Overdue Invoices</div>
        </div>
        <div class="stat-box" style="border-left: 4px solid var(--danger); grid-column: span 2;">
            <div class="stat-box-val" style="color: var(--danger);">&#2547; <?php echo number_format($totalOutstandingAmount, 2); ?></div>
            <div class="stat-box-lbl">Total Outstanding Default Balance</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label for="search">Search Tenant or Apartment</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Overdue Bills Table -->
    <div class="card">
        <div class="card-header">
            <span>&#9888; Defaulted & Overdue Accounts</span>
            <span class="badge badge-danger"><?php echo $totalOverdueBills; ?> Delinquent</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Phone</th>
                            <th>Apartment</th>
                            <th>Bill ID</th>
                            <th>Billing Month</th>
                            <th>Total Bill</th>
                            <th>Amount Paid</th>
                            <th>Outstanding</th>
                            <th>Due Date</th>
                            <th>Overdue Days</th>
                            <th>Bill Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overdueList)): ?>
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#9989;</div>
                                        <h3>Zero Overdue Payments</h3>
                                        <p>All tenant accounts are in good standing with no delinquent balances.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($overdueList as $o): ?>
                                <tr>
                                    <td>
                                        <a href="../tenants/view.php?id=<?php echo (int)$o['tenant_id']; ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($o['tenant_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($o['phone']); ?></td>
                                    <td>Apt <?php echo htmlspecialchars($o['apartment_number']); ?></td>
                                    <td>
                                        <a href="../bills/view.php?id=<?php echo (int)$o['bill_id']; ?>" style="color: var(--text-main); font-weight: 500;">
                                            #<?php echo (int)$o['bill_id']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('M Y', strtotime($o['billing_month'])); ?></td>
                                    <td>&#2547; <?php echo number_format($o['total_amount'], 2); ?></td>
                                    <td>&#2547; <?php echo number_format($o['paid_amount'], 2); ?></td>
                                    <td>
                                        <strong style="color: var(--danger); font-size: 14px;">
                                            &#2547; <?php echo number_format($o['outstanding_amount'], 2); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($o['due_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-danger">
                                            <?php echo (int)$o['overdue_days']; ?> Days
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $o['bill_status'] === 'Overdue' ? 'badge-danger' : 'badge-warning'; ?>">
                                            <?php echo htmlspecialchars($o['bill_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="../payments/add.php?bill_id=<?php echo (int)$o['bill_id']; ?>" class="btn btn-sm btn-success">Collect</a>
                                            <a href="../bills/view.php?id=<?php echo (int)$o['bill_id']; ?>" class="btn btn-sm btn-secondary">Invoice</a>
                                        </div>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
