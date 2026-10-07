<?php
$pageTitle = 'Create Maintenance Request';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$preset_complaint_id = filter_input(INPUT_GET, 'complaint_id', FILTER_VALIDATE_INT);

// Fetch complaints needing maintenance (either without a request or the preset one)
$complaintsStmt = $pdo->prepare("SELECT c.complaint_id, c.category, c.priority, c.submission_date,
       t.tenant_name, a.apartment_number, mr.maintenance_id
    FROM complaints c
    JOIN tenants t ON c.tenant_id = t.tenant_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    LEFT JOIN maintenance_requests mr ON c.complaint_id = mr.complaint_id
    WHERE a.owner_id = ? AND (mr.maintenance_id IS NULL OR c.complaint_id = " . ($preset_complaint_id ?: 0) . ")
    ORDER BY c.submission_date DESC");
$complaintsStmt->execute([$ownerId]);
$complaints = $complaintsStmt->fetchAll();

// Fetch staff
$staffMembers = $pdo->query("SELECT staff_id, staff_name, experties FROM staff ORDER BY staff_name ASC")->fetchAll();

$error = '';
$complaint_id = $preset_complaint_id ?: '';
$staff_id = '';
$estimated_cost = '0.00';
$maintenance_status = 'Assigned';
$completion_date = '';
$maintenance_notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaint_id = filter_input(INPUT_POST, 'complaint_id', FILTER_VALIDATE_INT);
    $staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    $estimated_cost = trim($_POST['estimated_cost'] ?? '0.00');
    $maintenance_status = trim($_POST['maintenance_status'] ?? 'Assigned');
    $completion_date = trim($_POST['completion_date'] ?? '');
    $maintenance_notes = trim($_POST['maintenance_notes'] ?? '');

    if (!$complaint_id || !$staff_id) {
        $error = 'Please select a complaint and an assigned staff member.';
    } elseif (!is_numeric($estimated_cost) || (float)$estimated_cost < 0) {
        $error = 'Estimated cost must be a valid positive amount.';
    } elseif ($maintenance_status === 'Completed' && empty($completion_date)) {
        $error = 'Please provide a completion date when marking maintenance as Completed.';
    } else {
        try {
            $ownerCheck = $pdo->prepare("SELECT c.complaint_id FROM complaints c JOIN apartments a ON c.apartment_id = a.apartment_id LEFT JOIN maintenance_requests mr ON c.complaint_id = mr.complaint_id WHERE c.complaint_id = ? AND a.owner_id = ? AND mr.maintenance_id IS NULL");
            $ownerCheck->execute([$complaint_id, $ownerId]);
            if (!$ownerCheck->fetchColumn()) {
                throw new RuntimeException('You can only create maintenance work orders for your own apartments and unresolved complaints.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO maintenance_requests (complaint_id, staff_id, estimated_cost, maintenance_status, completion_date, maintenance_notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $complaint_id,
                $staff_id,
                (float)$estimated_cost,
                $maintenance_status,
                ($maintenance_status === 'Completed' && $completion_date) ? $completion_date : null,
                $maintenance_notes ?: null
            ]);
            $newId = $pdo->lastInsertId();

            // Update complaint status
            $newComplaintStatus = ($maintenance_status === 'Completed') ? 'Resolved' : (($maintenance_status === 'In Progress') ? 'In Progress' : 'Assigned');
            $stmt = $pdo->prepare("UPDATE complaints SET complaint_status = ? WHERE complaint_id = ? AND EXISTS (SELECT 1 FROM apartments a WHERE a.apartment_id = complaints.apartment_id AND a.owner_id = ?)");
            $stmt->execute([$newComplaintStatus, $complaint_id, $ownerId]);

            $pdo->commit();

            setFlashMessage('success', "Maintenance request #$newId was successfully dispatched.");
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
            <h1>Dispatch Maintenance Work Order</h1>
            <p>Schedule a technician to address an unresolved complaint.</p>
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
            <span>Work Order Particulars</span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="complaint_id">Select Originating Complaint <span class="required-star">*</span></label>
                    <select id="complaint_id" name="complaint_id" class="form-control" required>
                        <option value="">-- Choose Complaint --</option>
                        <?php foreach ($complaints as $c): ?>
                            <option value="<?php echo (int)$c['complaint_id']; ?>" <?php echo (int)$complaint_id === (int)$c['complaint_id'] ? 'selected' : ''; ?>>
                                Complaint #<?php echo (int)$c['complaint_id']; ?> &mdash; <?php echo htmlspecialchars($c['category']); ?> [Priority: <?php echo htmlspecialchars($c['priority']); ?>] &mdash; Apt <?php echo htmlspecialchars($c['apartment_number']); ?> (<?php echo htmlspecialchars($c['tenant_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_id">Assign Maintenance Staff <span class="required-star">*</span></label>
                        <select id="staff_id" name="staff_id" class="form-control" required>
                            <option value="">-- Choose Staff Member --</option>
                            <?php foreach ($staffMembers as $s): ?>
                                <option value="<?php echo (int)$s['staff_id']; ?>" <?php echo (int)$staff_id === (int)$s['staff_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['staff_name']); ?> (<?php echo htmlspecialchars($s['experties']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="estimated_cost">Estimated Repair Expense (&#2547;) <span class="required-star">*</span></label>
                        <input type="number" id="estimated_cost" name="estimated_cost" step="0.01" min="0" class="form-control" placeholder="0.00" value="<?php echo htmlspecialchars($estimated_cost); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="maintenance_status">Work Status <span class="required-star">*</span></label>
                        <select id="maintenance_status" name="maintenance_status" class="form-control" required onchange="toggleCompletionDate(this)">
                            <option value="Assigned" <?php echo $maintenance_status === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="In Progress" <?php echo $maintenance_status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $maintenance_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="form-group" id="completion_date_group">
                        <label for="completion_date">Completion Date</label>
                        <input type="date" id="completion_date" name="completion_date" class="form-control" value="<?php echo htmlspecialchars($completion_date); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="maintenance_notes">Technician & Repair Notes</label>
                    <textarea id="maintenance_notes" name="maintenance_notes" class="form-control" rows="3" placeholder="Describe inspection findings, materials used, or scheduled time..."><?php echo htmlspecialchars($maintenance_notes); ?></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Dispatch Work Order</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCompletionDate(select) {
    const compGroup = document.getElementById('completion_date_group');
    const compInput = document.getElementById('completion_date');
    if (select.value === 'Completed') {
        compInput.required = true;
        if (!compInput.value) {
            compInput.value = new Date().toISOString().split('T')[0];
        }
    } else {
        compInput.required = false;
        compInput.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
