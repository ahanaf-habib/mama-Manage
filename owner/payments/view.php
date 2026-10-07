<?php
$pageTitle = 'Payment Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid payment ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT p.*, 
       b.billing_month, b.issue_date, b.due_date, b.total_amount as bill_total, b.bill_status,
       t.tenant_id, t.tenant_name, t.phone, t.email,
       a.apartment_id, a.apartment_number, a.floor_level
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE p.payment_id = ? AND a.owner_id = ?");
$stmt->execute([$id, $ownerId]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlashMessage('danger', 'Payment record not found.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Payment Receipt #<?php echo (int)$payment['payment_id']; ?></h1>
            <p>Verification record for payment of &#2547;<?php echo number_format($payment['payment_amount'], 2); ?>.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <div class="grid-2">
        <!-- Payment Details Card -->
        <div class="card">
            <div class="card-header">
                <span>&#128176; Payment Particulars</span>
                <span class="badge <?php 
                    echo $payment['payment_status'] === 'Completed' ? 'badge-success' : ($payment['payment_status'] === 'Pending' ? 'badge-warning' : 'badge-danger'); 
                ?>">
                    <?php echo htmlspecialchars($payment['payment_status']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Payment ID</span>
                        <span class="detail-value">#<?php echo (int)$payment['payment_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Amount Paid</span>
                        <span class="detail-value" style="font-size: 18px; color: var(--success);">&#2547; <?php echo number_format($payment['payment_amount'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Payment Date</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Payment Method</span>
                        <span class="detail-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;">
                        <span class="detail-label">Transaction Reference ID</span>
                        <span class="detail-value"><code><?php echo htmlspecialchars($payment['transaction_reference_id'] ?? 'None (Cash transaction)'); ?></code></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoiced Bill Card -->
        <div class="card">
            <div class="card-header">
                <span>&#128179; Invoiced Bill Details</span>
                <a href="../bills/view.php?id=<?php echo (int)$payment['bill_id']; ?>" class="btn btn-sm btn-secondary">View Bill #<?php echo (int)$payment['bill_id']; ?></a>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Billing Month</span>
                        <span class="detail-value"><?php echo date('F Y', strtotime($payment['billing_month'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Invoiced</span>
                        <span class="detail-value">&#2547; <?php echo number_format($payment['bill_total'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tenant</span>
                        <span class="detail-value">
                            <a href="../tenants/view.php?id=<?php echo (int)$payment['tenant_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                <?php echo htmlspecialchars($payment['tenant_name']); ?>
                            </a>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Apartment</span>
                        <span class="detail-value">Apt <?php echo htmlspecialchars($payment['apartment_number']); ?> (Floor <?php echo (int)$payment['floor_level']; ?>)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
