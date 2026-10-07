<?php
$pageTitle = 'Payment Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$search = trim($_GET['search'] ?? '');
$method = trim($_GET['method'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT p.*, 
               b.billing_month, 
               b.total_amount as bill_total,
               t.tenant_name, 
               t.tenant_id,
               a.apartment_number, 
               a.floor_level
        FROM payments p
        JOIN bills b ON p.bill_id = b.bill_id
        JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
        JOIN tenants t ON tn.tenant_id = t.tenant_id
        JOIN apartments a ON tn.apartment_id = a.apartment_id
        WHERE a.owner_id = ?";

$params = [$ownerId];
if ($search !== '') {
    $sql .= " AND (t.tenant_name LIKE ? OR a.apartment_number LIKE ? OR p.transaction_reference_id LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

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
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Payment Management</h1>
            <p>Monitor collection records, transaction verification, and tenant remittances.</p>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">&#43; Record Payment</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label for="search">Search Tenant / Apt / Txn ID</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
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
            <label for="status">Payment Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Failed" <?php echo $status === 'Failed' ? 'selected' : ''; ?>>Failed</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== '' || $method !== '' || $status !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Tenant</th>
                            <th>Apartment</th>
                            <th>For Bill</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Transaction Ref</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128176;</div>
                                        <h3>No Payments Found</h3>
                                        <p>No payment records match your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$p['payment_id']; ?></strong></td>
                                    <td>
                                        <a href="../tenants/view.php?id=<?php echo (int)$p['tenant_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($p['tenant_name']); ?>
                                        </a>
                                    </td>
                                    <td>Apt <?php echo htmlspecialchars($p['apartment_number']); ?></td>
                                    <td>
                                        <a href="../bills/view.php?id=<?php echo (int)$p['bill_id']; ?>" style="color: var(--text-main); text-decoration: none;">
                                            Bill #<?php echo (int)$p['bill_id']; ?> (<?php echo date('M Y', strtotime($p['billing_month'])); ?>)
                                        </a>
                                    </td>
                                    <td><strong>&#2547; <?php echo number_format($p['payment_amount'], 2); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($p['payment_method']); ?></span>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($p['transaction_reference_id'] ?: 'N/A'); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $p['payment_status'] === 'Completed' ? 'badge-success' : ($p['payment_status'] === 'Pending' ? 'badge-warning' : 'badge-danger'); 
                                        ?>">
                                            <?php echo htmlspecialchars($p['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo (int)$p['payment_id']; ?>" class="btn btn-sm btn-secondary">View</a>
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
