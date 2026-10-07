<?php
$pageTitle = 'Bill Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$search = trim($_GET['search'] ?? '');
$month = trim($_GET['month'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT b.*, 
               t.tenant_name, 
               t.tenant_id,
               a.apartment_number, 
               a.floor_level,
               (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE bill_id = b.bill_id AND payment_status = 'Completed') as paid_amount
        FROM bills b
        JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
        JOIN tenants t ON tn.tenant_id = t.tenant_id
        JOIN apartments a ON tn.apartment_id = a.apartment_id
        WHERE a.owner_id = ?";

$params = [$ownerId];
if ($search !== '') {
    $sql .= " AND (t.tenant_name LIKE ? OR a.apartment_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($month !== '') {
    $sql .= " AND DATE_FORMAT(b.billing_month, '%Y-%m') = ?";
    $params[] = $month;
}

if ($status !== '') {
    $sql .= " AND b.bill_status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY b.billing_month DESC, b.bill_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Monthly Bill Management</h1>
            <p>Generate, monitor, and manage billing invoices for all active tenancies.</p>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">&#43; Create Bill</a>
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
            <label for="search">Search Tenant / Apartment</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search name or apt..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label for="month">Billing Month</label>
            <input type="month" id="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>">
        </div>
        <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Paid" <?php echo $status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="Partially Paid" <?php echo $status === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                <option value="Unpaid" <?php echo $status === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                <option value="Overdue" <?php echo $status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== '' || $month !== '' || $status !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Bills Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Tenant</th>
                            <th>Apartment</th>
                            <th>Billing Month</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Total Bill</th>
                            <th>Paid Amount</th>
                            <th>Due Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="11">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128179;</div>
                                        <h3>No Bills Found</h3>
                                        <p>No billing invoices match your search criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $b): ?>
                                <?php 
                                $remaining = max(0, $b['total_amount'] - $b['paid_amount']);
                                ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$b['bill_id']; ?></strong></td>
                                    <td>
                                        <a href="../tenants/view.php?id=<?php echo (int)$b['tenant_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($b['tenant_name']); ?>
                                        </a>
                                    </td>
                                    <td>Apt <?php echo htmlspecialchars($b['apartment_number']); ?></td>
                                    <td><strong><?php echo date('F Y', strtotime($b['billing_month'])); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($b['issue_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($b['due_date'])); ?></td>
                                    <td><strong>&#2547; <?php echo number_format($b['total_amount'], 2); ?></strong></td>
                                    <td>&#2547; <?php echo number_format($b['paid_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($remaining > 0): ?>
                                            <span style="color: var(--danger); font-weight: 600;">&#2547; <?php echo number_format($remaining, 2); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--success); font-weight: 600;">&#10004; Settled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $b['bill_status'] === 'Paid' ? 'badge-success' : ($b['bill_status'] === 'Partially Paid' ? 'badge-info' : ($b['bill_status'] === 'Overdue' ? 'badge-danger' : 'badge-warning')); 
                                        ?>">
                                            <?php echo htmlspecialchars($b['bill_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="view.php?id=<?php echo (int)$b['bill_id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                            <a href="edit.php?id=<?php echo (int)$b['bill_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
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
