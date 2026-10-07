<?php
$pageTitle = 'Complaint Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid complaint ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT c.*, 
       t.tenant_id, t.tenant_name, t.phone, t.email,
       a.apartment_id, a.apartment_number, a.floor_level
    FROM complaints c
    JOIN tenants t ON c.tenant_id = t.tenant_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    WHERE c.complaint_id = ? AND a.owner_id = ?");
$stmt->execute([$id, $ownerId]);
$complaint = $stmt->fetch();

if (!$complaint) {
    setFlashMessage('danger', 'Complaint record not found.');
    header('Location: index.php');
    exit;
}

$flash = getFlashMessage();
$error = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = trim($_POST['complaint_status'] ?? '');
    if (in_array($newStatus, ['Pending', 'Assigned', 'In Progress', 'Resolved'])) {
        $stmt = $pdo->prepare("UPDATE complaints SET complaint_status = ? WHERE complaint_id = ? AND EXISTS (SELECT 1 FROM apartments a WHERE a.apartment_id = complaints.apartment_id AND a.owner_id = ?)");
        $stmt->execute([$newStatus, $id, $ownerId]);
        setFlashMessage('success', "Complaint #$id status was updated to '$newStatus'.");
        header("Location: view.php?id=$id");
        exit;
    } else {
        $error = 'Invalid status specified.';
    }
}

// Fetch linked maintenance request if any
$stmt = $pdo->prepare("SELECT mr.*, s.staff_name, s.phone as staff_phone, s.experties
    FROM maintenance_requests mr
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE mr.complaint_id = ?
    LIMIT 1");
$stmt->execute([$id]);
$maintenance = $stmt->fetch();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Complaint #<?php echo (int)$complaint['complaint_id']; ?> &mdash; <?php echo htmlspecialchars($complaint['category']); ?></h1>
            <p>Reported by <?php echo htmlspecialchars($complaint['tenant_name']); ?> for Apt <?php echo htmlspecialchars($complaint['apartment_number']); ?>.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to Complaints</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Complaint Information -->
        <div class="card">
            <div class="card-header">
                <span>&#128227; Complaint Details</span>
                <span class="badge <?php 
                    echo $complaint['priority'] === 'High' ? 'badge-danger' : ($complaint['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                ?>">
                    <?php echo htmlspecialchars($complaint['priority']); ?> Priority
                </span>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Complaint ID</span>
                        <span class="detail-value">#<?php echo (int)$complaint['complaint_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Category</span>
                        <span class="detail-value"><?php echo htmlspecialchars($complaint['category']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Current Status</span>
                        <span class="detail-value">
                            <span class="badge <?php 
                                echo $complaint['complaint_status'] === 'Resolved' ? 'badge-success' : ($complaint['complaint_status'] === 'Pending' ? 'badge-warning' : 'badge-info'); 
                            ?>">
                                <?php echo htmlspecialchars($complaint['complaint_status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Submission Date</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($complaint['submission_date'])); ?></span>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;">
                        <span class="detail-label">Description of Problem</span>
                        <p style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border); font-size: 14px; margin-top: 4px;">
                            <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Occupant & Status Control -->
        <div>
            <!-- Occupant Details -->
            <div class="card">
                <div class="card-header">
                    <span>&#128100; Occupant & Unit Information</span>
                </div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Tenant</span>
                            <span class="detail-value">
                                <a href="../tenants/view.php?id=<?php echo (int)$complaint['tenant_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                    <?php echo htmlspecialchars($complaint['tenant_name']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value"><?php echo htmlspecialchars($complaint['phone']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Apartment</span>
                            <span class="detail-value">
                                <a href="../apartments/view.php?id=<?php echo (int)$complaint['apartment_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                    Apt <?php echo htmlspecialchars($complaint['apartment_number']); ?> (Floor <?php echo (int)$complaint['floor_level']; ?>)
                                </a>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($complaint['email']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Update Status Box -->
            <div class="card">
                <div class="card-header">
                    <span>&#9881; Lifecycle Management</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_status">
                        <div class="form-group">
                            <label for="complaint_status">Update Complaint Status</label>
                            <select id="complaint_status" name="complaint_status" class="form-control" required>
                                <option value="Pending" <?php echo $complaint['complaint_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Assigned" <?php echo $complaint['complaint_status'] === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                                <option value="In Progress" <?php echo $complaint['complaint_status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Resolved" <?php echo $complaint['complaint_status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">&#10004; Save Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Maintenance Dispatch -->
    <div class="card">
        <div class="card-header">
            <span>&#128295; Dispatched Maintenance Work</span>
            <?php if (!$maintenance && $complaint['complaint_status'] !== 'Resolved'): ?>
                <a href="../maintenance/add.php?complaint_id=<?php echo (int)$complaint['complaint_id']; ?>" class="btn btn-sm btn-primary">&#43; Create Maintenance Request</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($maintenance): ?>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Maintenance ID</span>
                        <span class="detail-value">
                            <a href="../maintenance/view.php?id=<?php echo (int)$maintenance['maintenance_id']; ?>" style="color: var(--primary);">
                                #<?php echo (int)$maintenance['maintenance_id']; ?>
                            </a>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Assigned Staff</span>
                        <span class="detail-value">&#128119; <?php echo htmlspecialchars($maintenance['staff_name'] ?? 'Unassigned'); ?> (<?php echo htmlspecialchars($maintenance['experties'] ?? ''); ?>)</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Estimated Repair Expense</span>
                        <span class="detail-value">&#2547; <?php echo number_format($maintenance['estimated_cost'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Maintenance Status</span>
                        <span class="detail-value">
                            <span class="badge <?php 
                                echo $maintenance['maintenance_status'] === 'Completed' ? 'badge-success' : ($maintenance['maintenance_status'] === 'In Progress' ? 'badge-info' : 'badge-warning'); 
                            ?>">
                                <?php echo htmlspecialchars($maintenance['maintenance_status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Completion Date</span>
                        <span class="detail-value"><?php echo $maintenance['completion_date'] ? date('d M Y', strtotime($maintenance['completion_date'])) : 'Incomplete'; ?></span>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;">
                        <span class="detail-label">Technician Notes</span>
                        <span class="detail-value" style="font-weight: normal;"><?php echo htmlspecialchars($maintenance['maintenance_notes'] ?? 'None recorded'); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted); margin-bottom: 12px;">No maintenance request has been initiated for this complaint.</p>
                <?php if ($complaint['complaint_status'] !== 'Resolved'): ?>
                    <a href="../maintenance/add.php?complaint_id=<?php echo (int)$complaint['complaint_id']; ?>" class="btn btn-sm btn-primary">&#43; Create Maintenance Request</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
