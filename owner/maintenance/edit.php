<?php
$pageTitle = 'Edit Maintenance Request';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid maintenance ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT mr.*, 
       c.complaint_id, c.category, c.priority, c.description as complaint_desc,
       t.tenant_name, a.apartment_number, a.floor_level
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    JOIN tenants t ON c.tenant_id = t.tenant_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    WHERE mr.maintenance_id = ? AND a.owner_id = ?");
$stmt->execute([$id, $ownerId]);
$maintenance = $stmt->fetch();

if (!$maintenance) {
    setFlashMessage('danger', 'Maintenance record not found.');
    header('Location: index.php');
    exit;
}

$staffMembers = $pdo->query("SELECT staff_id, staff_name, experties FROM staff ORDER BY staff_name ASC")->fetchAll();

$error = '';
$staff_id = $maintenance['staff_id'];
$estimated_cost = $maintenance['estimated_cost'];
$maintenance_status = $maintenance['maintenance_status'];
$completion_date = $maintenance['completion_date'] ?? '';
$maintenance_notes = $maintenance['maintenance_notes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    $estimated_cost = trim($_POST['estimated_cost'] ?? '0.00');
    $maintenance_status = trim($_POST['maintenance_status'] ?? 'Assigned');
    $completion_date = trim($_POST['completion_date'] ?? '');
    $maintenance_notes = trim($_POST['maintenance_notes'] ?? '');

    if (!$staff_id) {
        $error = 'Please assign a staff member.';
    } elseif (!is_numeric($estimated_cost) || (float)$estimated_cost < 0) {
        $error = 'Estimated cost must be a non-negative number.';
    } elseif ($maintenance_status === 'Completed' && empty($completion_date)) {
        $error = 'Please specify a completion date when marking maintenance as Completed.';
    } else {
        try {
            $pdo->beginTransaction();

            $finalCompletionDate = ($maintenance_status === 'Completed') ? $completion_date : null;

            $stmt = $pdo->prepare("UPDATE maintenance_requests SET 
                staff_id = ?, 
                estimated_cost = ?, 
                maintenance_status = ?, 
                completion_date = ?, 
                maintenance_notes = ? 
                WHERE maintenance_id = ? AND EXISTS (SELECT 1 FROM maintenance_requests mx JOIN complaints cx ON mx.complaint_id = cx.complaint_id JOIN apartments ax ON cx.apartment_id = ax.apartment_id WHERE mx.maintenance_id = ? AND ax.owner_id = ?)");
            $stmt->execute([
                $staff_id,
                (float)$estimated_cost,
                $maintenance_status,
                $finalCompletionDate,
                $maintenance_notes ?: null,
                $id,
                $id,
                $ownerId
            ]);

            // Sync complaint status
            $newComplaintStatus = ($maintenance_status === 'Completed') ? 'Resolved' : (($maintenance_status === 'In Progress') ? 'In Progress' : 'Assigned');
            $stmt = $pdo->prepare("UPDATE complaints SET complaint_status = ? WHERE complaint_id = ? AND EXISTS (SELECT 1 FROM apartments a WHERE a.apartment_id = complaints.apartment_id AND a.owner_id = ?)");
            $stmt->execute([$newComplaintStatus, $maintenance['complaint_id'], $ownerId]);

            $pdo->commit();

            setFlashMessage('success', "Maintenance request #$id was updated successfully.");
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
            <h1>Edit Maintenance Work Order #<?php echo (int)$maintenance['maintenance_id']; ?></h1>
            <p>Update assigned technician, estimated repair cost, or status.</p>
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
            <span>Originating Issue Overview</span>
            <span class="badge <?php 
                echo $maintenance['priority'] === 'High' ? 'badge-danger' : ($maintenance['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
            ?>"><?php echo htmlspecialchars($maintenance['priority']); ?> Priority</span>
        </div>
        <div class="card-body">
            <!-- Complaint Summary -->
            <div class="detail-grid" style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
                <div class="detail-item">
                    <span class="detail-label">Complaint Category</span>
                    <span class="detail-value"><?php echo htmlspecialchars($maintenance['category']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Apartment</span>
                    <span class="detail-value">Apt <?php echo htmlspecialchars($maintenance['apartment_number']); ?> (Floor <?php echo (int)$maintenance['floor_level']; ?>)</span>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <span class="detail-label">Issue Description</span>
                    <span class="detail-value" style="font-weight: normal;"><?php echo htmlspecialchars($maintenance['complaint_desc']); ?></span>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_id">Assigned Technician <span class="required-star">*</span></label>
                        <select id="staff_id" name="staff_id" class="form-control" required>
                            <?php foreach ($staffMembers as $s): ?>
                                <option value="<?php echo (int)$s['staff_id']; ?>" <?php echo (int)$staff_id === (int)$s['staff_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['staff_name']); ?> (<?php echo htmlspecialchars($s['experties']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="estimated_cost">Estimated Repair Expense (&#2547;) <span class="required-star">*</span></label>
                        <input type="number" id="estimated_cost" name="estimated_cost" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($estimated_cost); ?>" required>
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
                    <textarea id="maintenance_notes" name="maintenance_notes" class="form-control" rows="3"><?php echo htmlspecialchars($maintenance_notes); ?></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Update Work Order</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCompletionDate(select) {
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
