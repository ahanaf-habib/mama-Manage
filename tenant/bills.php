<?php
$pageTitle = 'My Bills';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$tenant_id = $_SESSION['user_id'];
$status = trim($_GET['status'] ?? '');
$month = trim($_GET['month'] ?? '');

$sql = "SELECT b.*, 
               a.apartment_number,
               a.floor_level,
               (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE bill_id = b.bill_id AND payment_status = 'Completed') as paid_amount,
               DATEDIFF(CURDATE(), b.due_date) as overdue_days
        FROM bills b
        JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
        JOIN apartments a ON tn.apartment_id = a.apartment_id
        WHERE tn.tenant_id = ?";

$params = [$tenant_id];

if ($status !== '') {
    $sql .= " AND b.bill_status = ?";
    $params[] = $status;
}

if ($month !== '') {
    $sql .= " AND DATE_FORMAT(b.billing_month, '%Y-%m') = ?";
    $params[] = $month;
}

$sql .= " ORDER BY b.billing_month DESC, b.bill_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>My Invoices & Billing Statements</h1>
            <p>Review monthly rent, utilities, service charges, and payment balances.</p>
        </div>
        <div>
            <a href="payments.php" class="btn btn-secondary">&#128176; Payment History</a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label for="month">Billing Month</label>
            <input type="month" id="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>">
        </div>
        <div class="filter-group">
            <label for="status">Payment Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="Partially Paid" <?php echo $status === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                <option value="Unpaid" <?php echo $status === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                <option value="Overdue" <?php echo $status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($status !== '' || $month !== ''): ?>
            <a href="bills.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Bills Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Month</th>
                            <th>Apartment</th>
                            <th>Rent</th>
                            <th>Utility</th>
                            <th>Maint Charge</th>
                            <th>Other</th>
                            <th>Total Bill</th>
                            <th>Paid Amount</th>
                            <th>Remaining Balance</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128179;</div>
                                        <h3>No Invoices Found</h3>
                                        <p>No billing invoices match your filter selection.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $b): ?>
                                <?php 
                                $remaining = max(0, (float)$b['total_amount'] - (float)$b['paid_amount']);
                                ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$b['bill_id']; ?></strong></td>
                                    <td><strong><?php echo date('F Y', strtotime($b['billing_month'])); ?></strong></td>
                                    <td>Apt <?php echo htmlspecialchars($b['apartment_number']); ?></td>
                                    <td>&#2547; <?php echo number_format($b['rent_amount'], 2); ?></td>
                                    <td>&#2547; <?php echo number_format($b['utility_charge'], 2); ?></td>
                                    <td>&#2547; <?php echo number_format($b['maintenance_service_charge'], 2); ?></td>
                                    <td>&#2547; <?php echo number_format($b['other_charge'], 2); ?></td>
                                    <td><strong>&#2547; <?php echo number_format($b['total_amount'], 2); ?></strong></td>
                                    <td>&#2547; <?php echo number_format($b['paid_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($remaining > 0): ?>
                                            <span style="color: var(--danger); font-weight: 700;">&#2547; <?php echo number_format($remaining, 2); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--success); font-weight: 600;">&#10004; Fully Paid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($b['due_date'])); ?>
                                        <?php if ($b['overdue_days'] > 0 && in_array($b['bill_status'], ['Unpaid', 'Partially Paid', 'Overdue'])): ?>
                                            <br><span class="badge badge-danger"><?php echo (int)$b['overdue_days']; ?>d overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $b['bill_status'] === 'Paid' ? 'badge-success' : ($b['bill_status'] === 'Partially Paid' ? 'badge-info' : ($b['bill_status'] === 'Overdue' ? 'badge-danger' : 'badge-warning')); 
                                        ?>">
                                            <?php echo htmlspecialchars($b['bill_status']); ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
