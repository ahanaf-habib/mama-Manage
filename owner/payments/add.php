<?php
$pageTitle = 'Record Payment';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$preset_bill_id = filter_input(INPUT_GET, 'bill_id', FILTER_VALIDATE_INT);

// Fetch outstanding/partially paid/unpaid/overdue bills or all bills
$billsStmt = $pdo->prepare("SELECT b.bill_id, b.billing_month, b.total_amount, b.bill_status,
       t.tenant_name, a.apartment_number,
       (SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE bill_id = b.bill_id AND payment_status = 'Completed') as paid_amount
    FROM bills b
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE a.owner_id = ? AND b.bill_status IN ('Unpaid', 'Partially Paid', 'Overdue')
    ORDER BY b.due_date ASC");
$billsStmt->execute([$ownerId]);
$bills = $billsStmt->fetchAll();

$error = '';
$bill_id = $preset_bill_id ?: '';
$payment_amount = '';
$payment_date = date('Y-m-d');
$payment_method = 'bKash';
$transaction_reference_id = '';
$payment_status = 'Completed';

// If preset bill provided, prefill remaining balance
if ($preset_bill_id) {
    foreach ($bills as $b) {
        if ($b['bill_id'] == $preset_bill_id) {
            $payment_amount = max(0, (float)$b['total_amount'] - (float)$b['paid_amount']);
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bill_id = filter_input(INPUT_POST, 'bill_id', FILTER_VALIDATE_INT);
    $payment_amount = trim($_POST['payment_amount'] ?? '');
    $payment_date = trim($_POST['payment_date'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $transaction_reference_id = trim($_POST['transaction_reference_id'] ?? '');
    $payment_status = trim($_POST['payment_status'] ?? 'Completed');

    if (!$bill_id || $payment_amount === '' || $payment_date === '' || $payment_method === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($payment_amount) || (float)$payment_amount <= 0) {
        $error = 'Payment amount must be a positive number.';
    } elseif (!in_array($payment_method, ['Cash', 'bKash', 'Bank Transfer'])) {
        $error = 'Invalid payment method selected.';
    } elseif (!in_array($payment_status, ['Completed', 'Pending', 'Failed'])) {
        $error = 'Invalid payment status selected.';
    } elseif (in_array($payment_method, ['bKash', 'Bank Transfer']) && empty($transaction_reference_id)) {
        $error = "Transaction Reference ID is required for $payment_method payments.";
    } else {
        try {
            // Ensure the selected bill belongs to this owner.
            $ownerCheck = $pdo->prepare("SELECT b.bill_id FROM bills b JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id JOIN apartments a ON tn.apartment_id = a.apartment_id WHERE b.bill_id = ? AND a.owner_id = ?");
            $ownerCheck->execute([$bill_id, $ownerId]);
            if (!$ownerCheck->fetchColumn()) {
                throw new RuntimeException('You can only record payments for your own tenants.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO payments (bill_id, payment_amount, payment_date, payment_method, transaction_reference_id, payment_status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $bill_id,
                (float)$payment_amount,
                $payment_date,
                $payment_method,
                $transaction_reference_id ?: null,
                $payment_status
            ]);
            $paymentId = $pdo->lastInsertId();

            // Automatic reconciliation of bill status
            $stmt = $pdo->prepare("SELECT b.total_amount, b.due_date FROM bills b JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id JOIN apartments a ON tn.apartment_id = a.apartment_id WHERE b.bill_id = ? AND a.owner_id = ?");
            $stmt->execute([$bill_id, $ownerId]);
            $billInfo = $stmt->fetch();

            if ($billInfo) {
                // Sum all completed payments for this bill
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) FROM payments WHERE bill_id = ? AND payment_status = 'Completed'");
                $stmt->execute([$bill_id]);
                $totalPaid = (float)$stmt->fetchColumn();
                $totalBill = (float)$billInfo['total_amount'];

                if ($totalPaid >= $totalBill) {
                    $newStatus = 'Paid';
                } elseif ($totalPaid > 0) {
                    $newStatus = 'Partially Paid';
                } else {
                    $newStatus = (strtotime($billInfo['due_date']) < time()) ? 'Overdue' : 'Unpaid';
                }

                $stmt = $pdo->prepare("UPDATE bills SET bill_status = ? WHERE bill_id = ? AND EXISTS (SELECT 1 FROM tenancies tn JOIN apartments a ON tn.apartment_id = a.apartment_id WHERE tn.tenancy_id = bills.tenancy_id AND a.owner_id = ?)");
                $stmt->execute([$newStatus, $bill_id, $ownerId]);
            }

            $pdo->commit();

            setFlashMessage('success', "Payment #$paymentId of ৳" . number_format($payment_amount, 2) . " was successfully recorded.");
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Record Payment Collection</h1>
            <p>Log a simulated payment received from an occupant.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 700px;">
        <div class="card-header">
            <span>Payment Receipt Information</span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="bill_id">Select Invoice <span class="required-star">*</span></label>
                    <select id="bill_id" name="bill_id" class="form-control" required onchange="updateDueAmount(this)">
                        <option value="">-- Choose Invoice to Pay --</option>
                        <?php foreach ($bills as $b): ?>
                            <?php 
                            $due = max(0, (float)$b['total_amount'] - (float)$b['paid_amount']); 
                            ?>
                            <option value="<?php echo (int)$b['bill_id']; ?>" 
                                data-due="<?php echo htmlspecialchars($due); ?>"
                                <?php echo (int)$bill_id === (int)$b['bill_id'] ? 'selected' : ''; ?>>
                                Bill #<?php echo (int)$b['bill_id']; ?> &mdash; <?php echo htmlspecialchars($b['tenant_name']); ?> (Apt <?php echo htmlspecialchars($b['apartment_number']); ?>) &mdash; Due: &#2547;<?php echo number_format($due, 2); ?> [<?php echo htmlspecialchars($b['bill_status']); ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_amount">Payment Amount (&#2547;) <span class="required-star">*</span></label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0.01" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($payment_amount); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_date">Payment Date <span class="required-star">*</span></label>
                        <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?php echo htmlspecialchars($payment_date); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_method">Payment Method <span class="required-star">*</span></label>
                        <select id="payment_method" name="payment_method" class="form-control" required>
                            <option value="bKash" <?php echo $payment_method === 'bKash' ? 'selected' : ''; ?>>bKash</option>
                            <option value="Bank Transfer" <?php echo $payment_method === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Cash" <?php echo $payment_method === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_status">Payment Status <span class="required-star">*</span></label>
                        <select id="payment_status" name="payment_status" class="form-control" required>
                            <option value="Completed" <?php echo $payment_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Pending" <?php echo $payment_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Failed" <?php echo $payment_status === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="transaction_reference_id">Transaction Reference ID <span id="ref_required_star" class="required-star">*</span></label>
                    <input type="text" id="transaction_reference_id" name="transaction_reference_id" class="form-control" placeholder="e.g. BK260901001 or TR260902001" value="<?php echo htmlspecialchars($transaction_reference_id); ?>">
                    <small style="color: var(--text-muted);">Required for bKash and Bank Transfer. Optional for Cash.</small>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Record Payment</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateDueAmount(select) {
    const selectedOption = select.options[select.selectedIndex];
    const due = selectedOption.getAttribute('data-due');
    if (due && !document.getElementById('payment_amount').value) {
        document.getElementById('payment_amount').value = parseFloat(due).toFixed(2);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
