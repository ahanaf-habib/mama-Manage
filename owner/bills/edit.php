<?php
$pageTitle = 'Edit Bill';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid bill ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT b.*, t.tenant_name, a.apartment_number, a.floor_level
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

$error = '';
$issue_date = $bill['issue_date'];
$due_date = $bill['due_date'];
$rent_amount = $bill['rent_amount'];
$utility_charge = $bill['utility_charge'];
$maintenance_service_charge = $bill['maintenance_service_charge'];
$other_charge = $bill['other_charge'];
$bill_status = $bill['bill_status'];
$total_amount = $bill['total_amount'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_date = trim($_POST['issue_date'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $rent_amount = trim($_POST['rent_amount'] ?? '');
    $utility_charge = trim($_POST['utility_charge'] ?? '0.00');
    $maintenance_service_charge = trim($_POST['maintenance_service_charge'] ?? '0.00');
    $other_charge = trim($_POST['other_charge'] ?? '0.00');
    $bill_status = trim($_POST['bill_status'] ?? 'Unpaid');

    if ($issue_date === '' || $due_date === '' || $rent_amount === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($rent_amount) || (float)$rent_amount < 0 || !is_numeric($utility_charge) || !is_numeric($maintenance_service_charge) || !is_numeric($other_charge)) {
        $error = 'All charges must be valid non-negative numbers.';
    } elseif (strtotime($due_date) < strtotime($issue_date)) {
        $error = 'Due date cannot be earlier than issue date.';
    } else {
        $calculated_total = (float)$rent_amount + (float)$utility_charge + (float)$maintenance_service_charge + (float)$other_charge;

        $stmt = $pdo->prepare("UPDATE bills
            SET issue_date = ?,
                due_date = ?,
                rent_amount = ?,
                utility_charge = ?,
                maintenance_service_charge = ?,
                other_charge = ?,
                total_amount = ?,
                bill_status = ?
            WHERE bill_id = ?
              AND EXISTS (
                  SELECT 1
                  FROM tenancies tn
                  JOIN apartments a ON tn.apartment_id = a.apartment_id
                  WHERE tn.tenancy_id = bills.tenancy_id
                    AND a.owner_id = ?
              )");
        $stmt->execute([
            $issue_date,
            $due_date,
            (float)$rent_amount,
            (float)$utility_charge,
            (float)$maintenance_service_charge,
            (float)$other_charge,
            $calculated_total,
            $bill_status,
            $id,
            $ownerId
        ]);

        setFlashMessage('success', "Bill #$id was updated successfully with revised total ৳" . number_format($calculated_total, 2));
        header('Location: index.php');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Edit Bill #<?php echo (int)$bill['bill_id']; ?></h1>
            <p>Update charges, due date, or payment status for <?php echo htmlspecialchars($bill['tenant_name']); ?> (Apt <?php echo htmlspecialchars($bill['apartment_number']); ?>).</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 750px;">
        <div class="card-header">
            <span>Bill Particulars</span>
            <span>Billing Month: <strong><?php echo date('F Y', strtotime($bill['billing_month'])); ?></strong></span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="issue_date">Issue Date <span class="required-star">*</span></label>
                        <input type="date" id="issue_date" name="issue_date" class="form-control" value="<?php echo htmlspecialchars($issue_date); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="due_date">Due Date <span class="required-star">*</span></label>
                        <input type="date" id="due_date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($due_date); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bill_status">Bill Status <span class="required-star">*</span></label>
                    <select id="bill_status" name="bill_status" class="form-control" required>
                        <option value="Unpaid" <?php echo $bill_status === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="Partially Paid" <?php echo $bill_status === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                        <option value="Paid" <?php echo $bill_status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Overdue" <?php echo $bill_status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
                <h4 style="font-size: 14px; margin-bottom: 16px;">Charge Breakdown (&#2547;)</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label for="rent_amount">Apartment Rent <span class="required-star">*</span></label>
                        <input type="number" id="rent_amount" name="rent_amount" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($rent_amount); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="utility_charge">Utility Charge</label>
                        <input type="number" id="utility_charge" name="utility_charge" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($utility_charge); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="maintenance_service_charge">Maintenance Service Charge</label>
                        <input type="number" id="maintenance_service_charge" name="maintenance_service_charge" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($maintenance_service_charge); ?>">
                    </div>
                    <div class="form-group">
                        <label for="other_charge">Other Charge</label>
                        <input type="number" id="other_charge" name="other_charge" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($other_charge); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="total_amount"><strong>Total Bill Amount (&#2547;)</strong></label>
                    <input type="text" id="total_amount" name="total_amount" class="form-control" style="font-size: 16px; font-weight: 700; background-color: #f8fafc;" readonly value="<?php echo htmlspecialchars($total_amount); ?>">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Update Bill</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
