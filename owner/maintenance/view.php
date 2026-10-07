<?php
$pageTitle = 'Maintenance Work Order Details';
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
       c.complaint_id, c.category, c.priority, c.description as complaint_desc, c.submission_date, c.complaint_status,
       s.staff_id, s.staff_name, s.phone as staff_phone, s.experties,
       t.tenant_id, t.tenant_name, t.phone as tenant_phone,
       a.apartment_id, a.apartment_number, a.floor_level
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    JOIN tenants t ON c.tenant_id = t.tenant_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE mr.maintenance_id = ? AND a.owner_id = ?");
$stmt->execute([$id, $ownerId]);
$m = $stmt->fetch();

if (!$m) {
    setFlashMessage('danger', 'Maintenance record not found.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Work Order #<?php echo (int)$m['maintenance_id']; ?> &mdash; <?php echo htmlspecialchars($m['category']); ?></h1>
            <p>Technician dispatch details and repair documentation.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="edit.php?id=<?php echo (int)$m['maintenance_id']; ?>" class="btn btn-primary">&#9998; Edit Work Order</a>
            <a href="index.php" class="btn btn-secondary">&larr; Back to Maintenance</a>
        </div>
    </div>

    <!-- Summary Grid -->
    <div class="grid-2">
        <!-- Work Order Information -->
        <div class="card">
            <div class="card-header">
                <span>&#128295; Work Order Details</span>
                <span class="badge <?php 
                    echo $m['maintenance_status'] === 'Completed' ? 'badge-success' : ($m['maintenance_status'] === 'In Progress' ? 'badge-info' : 'badge-warning'); 
                ?>">
                    <?php echo htmlspecialchars($m['maintenance_status']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Work Order ID</span>
                        <span class="detail-value">#<?php echo (int)$m['maintenance_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Estimated Expense</span>
                        <span class="detail-value" style="font-size: 16px; font-weight: 700;">&#2547; <?php echo number_format($m['estimated_cost'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Completion Date</span>
                        <span class="detail-value"><?php echo $m['completion_date'] ? date('d M Y', strtotime($m['completion_date'])) : 'Incomplete / Active'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Originating Complaint</span>
                        <span class="detail-value">
                            <a href="../complaints/view.php?id=<?php echo (int)$m['complaint_id']; ?>" style="color: var(--primary);">
                                Complaint #<?php echo (int)$m['complaint_id']; ?>
                            </a>
                        </span>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;">
                        <span class="detail-label">Maintenance Notes</span>
                        <p style="background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid var(--border); font-size: 14px; margin-top: 4px;">
                            <?php echo nl2br(htmlspecialchars($m['maintenance_notes'] ?? 'No technician notes provided.')); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Technician & Unit -->
        <div>
            <!-- Staff Information -->
            <div class="card">
                <div class="card-header">
                    <span>&#128119; Assigned Technician</span>
                    <?php if ($m['staff_id']): ?>
                        <a href="../staff/view.php?id=<?php echo (int)$m['staff_id']; ?>" class="btn btn-sm btn-secondary">Staff Profile</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($m['staff_id']): ?>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($m['staff_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Expertise</span>
                                <span class="detail-value"><?php echo htmlspecialchars($m['experties']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><?php echo htmlspecialchars($m['staff_phone']); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No staff member assigned yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Complaint & Unit Context -->
            <div class="card">
                <div class="card-header">
                    <span>&#127970; Location & Tenant Context</span>
                    <span class="badge <?php 
                        echo $m['priority'] === 'High' ? 'badge-danger' : ($m['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                    ?>"><?php echo htmlspecialchars($m['priority']); ?> Priority</span>
                </div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Apartment</span>
                            <span class="detail-value">
                                <a href="../apartments/view.php?id=<?php echo (int)$m['apartment_id']; ?>" style="color: var(--primary);">
                                    Apt <?php echo htmlspecialchars($m['apartment_number']); ?> (Floor <?php echo (int)$m['floor_level']; ?>)
                                </a>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tenant</span>
                            <span class="detail-value">
                                <a href="../tenants/view.php?id=<?php echo (int)$m['tenant_id']; ?>" style="color: var(--primary);">
                                    <?php echo htmlspecialchars($m['tenant_name']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="detail-item" style="grid-column: span 2;">
                            <span class="detail-label">Reported Problem Description</span>
                            <span class="detail-value" style="font-weight: normal;"><?php echo htmlspecialchars($m['complaint_desc']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
