<?php
$pageTitle = 'My Payments';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$tenant_id = $_SESSION['user_id'];
$method = trim($_GET['method'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT p.*, 
               b.billing_month, 
               b.total_amount as bill_total,
               a.apartment_number
        FROM payments p
        JOIN bills b ON p.bill_id = b.bill_id
        JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
        JOIN apartments a ON tn.apartment_id = a.apartment_id
        WHERE tn.tenant_id = ?";

$params = [$tenant_id];

if ($method !== '') {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method;
}

if ($status !== '') {
    $sql .= " AND p.payment_status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Total paid by this tenant
$stmt = $pdo->prepare("SELECT COALESCE(SUM(p.payment_amount), 0) 
    FROM payments p 
    JOIN bills b ON p.bill_id = b.bill_id 
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id 
    WHERE tn.tenant_id = ? AND p.payment_status = 'Completed'");
$stmt->execute([$tenant_id]);
$totalPaid = $stmt->fetchColumn();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Payment History & Receipts</h1>
            <p>Verification records of all rent remittances and utility settlements.</p>
        </div>
        <div>
            <span style="font-size: 15px; font-weight: 700; color: var(--success); padding: 8px 16px; background: var(--success-bg); border-radius: 6px;">
                Total Lifetime Paid: &#2547; <?php echo number_format($totalPaid, 2); ?>
            </span>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label for="method">Payment Method</label>
            <select id="method" name="method" class="form-control">
                <option value="">All Methods</option>
                <option value="Cash" <?php echo $method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="bKash" <?php echo $method === 'bKash' ? 'selected' : ''; ?>>bKash</option>
                <option value="Bank Transfer" <?php echo $method === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Failed" <?php echo $status === 'Failed' ? 'selected' : ''; ?>>Failed</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($method !== '' || $status !== ''): ?>
            <a href="payments.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Invoice Month</th>
                            <th>Apartment</th>
                            <th>Amount Paid</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Transaction Reference ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128176;</div>
                                        <h3>No Payments Recorded</h3>
                                        <p>You have no payment receipts matching this criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$p['payment_id']; ?></strong></td>
                                    <td><?php echo date('F Y', strtotime($p['billing_month'])); ?></td>
                                    <td>Apt <?php echo htmlspecialchars($p['apartment_number']); ?></td>
                                    <td><strong style="color: var(--success);">&#2547; <?php echo number_format($p['payment_amount'], 2); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($p['payment_method']); ?></span>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($p['transaction_reference_id'] ?? 'Cash (Direct Receipt)'); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $p['payment_status'] === 'Completed' ? 'badge-success' : ($p['payment_status'] === 'Pending' ? 'badge-warning' : 'badge-danger'); 
                                        ?>">
                                            <?php echo htmlspecialchars($p['payment_status']); ?>
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
