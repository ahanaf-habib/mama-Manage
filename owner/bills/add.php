<?php
$pageTitle = 'Create Bill';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$preset_tenancy_id = filter_input(INPUT_GET, 'tenancy_id', FILTER_VALIDATE_INT);

// Active tenancies
$stmt = $pdo->prepare("SELECT tn.tenancy_id, tn.tenant_id, t.tenant_name, a.apartment_number, a.floor_level, a.monthly_rent
    FROM tenancies tn
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE()) AND a.owner_id = ?
    ORDER BY a.apartment_number ASC");
$stmt->execute([$ownerId]);
$tenancies = $stmt->fetchAll();

$error = '';
$tenancy_id = $preset_tenancy_id ?: '';
$billing_month = date('Y-m-01');
$issue_date = date('Y-m-d');
$due_date = date('Y-m-10', strtotime('+10 days'));
$rent_amount = '';
$utility_charge = '0.00';
$maintenance_service_charge = '0.00';
$other_charge = '0.00';
$total_amount = '';
$bill_status = 'Unpaid';

// If preset tenancy provided, populate rent amount
if ($preset_tenancy_id) {
    foreach ($tenancies as $t) {
        if ($t['tenancy_id'] == $preset_tenancy_id) {
            $rent_amount = $t['monthly_rent'];
            $total_amount = $rent_amount;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenancy_id = filter_input(INPUT_POST, 'tenancy_id', FILTER_VALIDATE_INT);
    $billing_month_input = trim($_POST['billing_month'] ?? '');
    $issue_date = trim($_POST['issue_date'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $rent_amount = trim($_POST['rent_amount'] ?? '');
    $utility_charge = trim($_POST['utility_charge'] ?? '0.00');
    $maintenance_service_charge = trim($_POST['maintenance_service_charge'] ?? '0.00');
    $other_charge = trim($_POST['other_charge'] ?? '0.00');
    $bill_status = trim($_POST['bill_status'] ?? 'Unpaid');

    // Standardize billing_month to YYYY-MM-01
    if (strlen($billing_month_input) === 7) {
        $billing_month = $billing_month_input . '-01';
    } else {
        $billing_month = date('Y-m-01', strtotime($billing_month_input));
    }

    if (!$tenancy_id || $billing_month === '' || $issue_date === '' || $due_date === '' || $rent_amount === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($rent_amount) || (float)$rent_amount < 0) {
        $error = 'Rent amount must be a positive value.';
    } elseif (!is_numeric($utility_charge) || (float)$utility_charge < 0 || !is_numeric($maintenance_service_charge) || (float)$maintenance_service_charge < 0 || !is_numeric($other_charge) || (float)$other_charge < 0) {
        $error = 'Charges must be valid non-negative numbers.';
    } elseif (strtotime($due_date) < strtotime($issue_date)) {
        $error = 'Due date cannot be earlier than issue date.';
    } else {
        // Enforce business rule: total_amount = rent + utility + maintenance + other
        $calculated_total = (float)$rent_amount + (float)$utility_charge + (float)$maintenance_service_charge + (float)$other_charge;

        // Ensure the selected tenancy belongs to the logged-in owner.
        $ownerCheck = $pdo->prepare("SELECT COUNT(*) FROM tenancies tn JOIN apartments a ON tn.apartment_id = a.apartment_id WHERE tn.tenancy_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE()) AND a.owner_id = ?");
        $ownerCheck->execute([$tenancy_id, $ownerId]);
        if ((int)$ownerCheck->fetchColumn() !== 1) {
            $error = 'You can only create bills for your own apartments.';
        } else {
        // Check if bill already exists for this tenancy and month
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE tenancy_id = ? AND billing_month = ?");
        $stmt->execute([$tenancy_id, $billing_month]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'A bill for this tenancy and month has already been generated.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO bills (tenancy_id, billing_month, issue_date, due_date, rent_amount, utility_charge, maintenance_service_charge, other_charge, total_amount, bill_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tenancy_id,
                $billing_month,
                $issue_date,
                $due_date,
                (float)$rent_amount,
                (float)$utility_charge,
                (float)$maintenance_service_charge,
                (float)$other_charge,
                $calculated_total,
                $bill_status
            ]);
            $newBillId = $pdo->lastInsertId();
            setFlashMessage('success', "Bill #$newBillId was successfully generated with total ৳" . number_format($calculated_total, 2));
            header('Location: index.php');
            exit;
        }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Create Monthly Bill</h1>
            <p>Generate an itemized invoice for an active tenancy agreement.</p>
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
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="tenancy_id">Select Tenancy (Occupant & Unit) <span class="required-star">*</span></label>
                    <select id="tenancy_id" name="tenancy_id" class="form-control" required onchange="updateRentFromSelection(this)">
                        <option value="">-- Choose Active Tenancy --</option>
                        <?php foreach ($tenancies as $t): ?>
                            <option value="<?php echo (int)$t['tenancy_id']; ?>" 
                                data-rent="<?php echo htmlspecialchars($t['monthly_rent']); ?>"
                                <?php echo (int)$tenancy_id === (int)$t['tenancy_id'] ? 'selected' : ''; ?>>
                                Apt <?php echo htmlspecialchars($t['apartment_number']); ?> - <?php echo htmlspecialchars($t['tenant_name']); ?> (Monthly Rent: &#2547;<?php echo number_format($t['monthly_rent'], 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="billing_month">Billing Month <span class="required-star">*</span></label>
                        <input type="month" id="billing_month" name="billing_month" class="form-control" value="<?php echo date('Y-m', strtotime($billing_month)); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="bill_status">Initial Bill Status <span class="required-star">*</span></label>
                        <select id="bill_status" name="bill_status" class="form-control" required>
                            <option value="Unpaid" <?php echo $bill_status === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="Paid" <?php echo $bill_status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Partially Paid" <?php echo $bill_status === 'Partially Paid' ? 'selected' : ''; ?>>Partially Paid</option>
                            <option value="Overdue" <?php echo $bill_status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                </div>

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

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
                <h4 style="font-size: 14px; margin-bottom: 16px;">Charge Breakdown (&#2547;)</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label for="rent_amount">Apartment Rent <span class="required-star">*</span></label>
                        <input type="number" id="rent_amount" name="rent_amount" step="0.01" min="0" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($rent_amount); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="utility_charge">Utility Charge</label>
                        <input type="number" id="utility_charge" name="utility_charge" step="0.01" min="0" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($utility_charge); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="maintenance_service_charge">Maintenance Service Charge</label>
                        <input type="number" id="maintenance_service_charge" name="maintenance_service_charge" step="0.01" min="0" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($maintenance_service_charge); ?>">
                    </div>
                    <div class="form-group">
                        <label for="other_charge">Other Charge</label>
                        <input type="number" id="other_charge" name="other_charge" step="0.01" min="0" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($other_charge); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="total_amount"><strong>Total Bill Amount (&#2547;)</strong></label>
                    <input type="text" id="total_amount" name="total_amount" class="form-control" style="font-size: 16px; font-weight: 700; background-color: #f8fafc;" readonly value="<?php echo htmlspecialchars($total_amount); ?>" placeholder="Calculated automatically">
                    <small style="color: var(--text-muted);">Calculated automatically as: Rent + Utility + Maintenance Service + Other</small>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Generate Bill</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateRentFromSelection(select) {
    const selectedOption = select.options[select.selectedIndex];
    const rent = selectedOption.getAttribute('data-rent');
    if (rent) {
        document.getElementById('rent_amount').value = rent;
        // Trigger calculation
        const event = new Event('input', { bubbles: true });
        document.getElementById('rent_amount').dispatchEvent(event);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
