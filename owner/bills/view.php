<?php
$pageTitle = 'Bill Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid bill ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT b.*, 
       t.tenant_id, t.tenant_name, t.phone, t.email,
       a.apartment_id, a.apartment_number, a.floor_level
    FROM bills b
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE b.bill_id = ? AND a.owner_id = ?");
$stmt->execute([$id, $ownerId]);
$bill = $stmt->fetch();

if (!$bill) {
    setFlashMessage('danger', 'Bill record not found.');
    header('Location: index.php');
    exit;
}

// Payment records for this bill
$stmt = $pdo->prepare("SELECT * FROM payments WHERE bill_id = ? ORDER BY payment_date DESC, payment_id DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

// Dynamic calculation of completed payments
$paidAmount = 0;
foreach ($payments as $p) {
    if ($p['payment_status'] === 'Completed') {
        $paidAmount += (float)$p['payment_amount'];
    }
}
$dueBalance = max(0, (float)$bill['total_amount'] - $paidAmount);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Invoice #<?php echo (int)$bill['bill_id']; ?> &mdash; <?php echo date('F Y', strtotime($bill['billing_month'])); ?></h1>
            <p>Invoice issued to <?php echo htmlspecialchars($bill['tenant_name']); ?> for Apartment <?php echo htmlspecialchars($bill['apartment_number']); ?>.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <?php if ($dueBalance > 0): ?>
                <a href="../payments/add.php?bill_id=<?php echo (int)$bill['bill_id']; ?>" class="btn btn-success">&#128176; Record Payment</a>
            <?php endif; ?>
            <a href="edit.php?id=<?php echo (int)$bill['bill_id']; ?>" class="btn btn-primary">&#9998; Edit Bill</a>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <!-- Summary Grid -->
    <div class="grid-3">
        <div class="card">
            <div class="card-header">
                <span>&#128196; Invoice Info</span>
                <span class="badge <?php 
                    echo $bill['bill_status'] === 'Paid' ? 'badge-success' : ($bill['bill_status'] === 'Partially Paid' ? 'badge-info' : ($bill['bill_status'] === 'Overdue' ? 'badge-danger' : 'badge-warning')); 
                ?>">
                    <?php echo htmlspecialchars($bill['bill_status']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="detail-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-item">
                        <span class="detail-label">Billing Month</span>
                        <span class="detail-value"><?php echo date('F Y', strtotime($bill['billing_month'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issue Date</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($bill['issue_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Due Date</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($bill['due_date'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>&#128100; Occupant & Unit</span>
            </div>
            <div class="card-body">
                <div class="detail-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-item">
                        <span class="detail-label">Tenant Name</span>
                        <span class="detail-value">
                            <a href="../tenants/view.php?id=<?php echo (int)$bill['tenant_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                <?php echo htmlspecialchars($bill['tenant_name']); ?>
                            </a>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Apartment</span>
                        <span class="detail-value">
                            <a href="../apartments/view.php?id=<?php echo (int)$bill['apartment_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                Apt <?php echo htmlspecialchars($bill['apartment_number']); ?> (Floor <?php echo (int)$bill['floor_level']; ?>)
                            </a>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value"><?php echo htmlspecialchars($bill['phone']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>&#128176; Payment Balance</span>
            </div>
            <div class="card-body">
                <div class="detail-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-item">
                        <span class="detail-label">Total Invoiced</span>
                        <span class="detail-value" style="font-size: 18px;">&#2547; <?php echo number_format($bill['total_amount'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Amount Paid</span>
                        <span class="detail-value" style="color: var(--success);">&#2547; <?php echo number_format($paidAmount, 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Outstanding Due</span>
                        <span class="detail-value" style="color: <?php echo $dueBalance > 0 ? 'var(--danger)' : 'var(--success)'; ?>; font-size: 16px; font-weight: 700;">
                            &#2547; <?php echo number_format($dueBalance, 2); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charges Breakdown Table -->
    <div class="card">
        <div class="card-header">
            <span>&#128179; Itemized Charges Breakdown</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Charge Description</th>
                            <th style="text-align: right;">Amount (&#2547;)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Apartment Monthly Rent</td>
                            <td style="text-align: right;">&#2547; <?php echo number_format($bill['rent_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Utility Charge (Electricity / Gas / Water)</td>
                            <td style="text-align: right;">&#2547; <?php echo number_format($bill['utility_charge'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Maintenance Service Charge</td>
                            <td style="text-align: right;">&#2547; <?php echo number_format($bill['maintenance_service_charge'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Other / Incidental Charges</td>
                            <td style="text-align: right;">&#2547; <?php echo number_format($bill['other_charge'], 2); ?></td>
                        </tr>
                        <tr style="background-color: #f8fafc; font-weight: 700; font-size: 15px;">
                            <td>Total Amount Due</td>
                            <td style="text-align: right; color: var(--primary);">&#2547; <?php echo number_format($bill['total_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Records for this Bill -->
    <div class="card">
        <div class="card-header">
            <span>&#128176; Payment Records on this Invoice</span>
            <?php if ($dueBalance > 0): ?>
                <a href="../payments/add.php?bill_id=<?php echo (int)$bill['bill_id']; ?>" class="btn btn-sm btn-success">&#43; Add Payment</a>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Reference Number</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No payments recorded against this invoice yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$p['payment_id']; ?></strong></td>
                                    <td><strong>&#2547; <?php echo number_format($p['payment_amount'], 2); ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                                    <td><code><?php echo htmlspecialchars($p['transaction_reference_id'] ?? 'N/A'); ?></code></td>
                                    <td>
                                        <span class="badge <?php echo $p['payment_status'] === 'Completed' ? 'badge-success' : 'badge-warning'; ?>">
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
