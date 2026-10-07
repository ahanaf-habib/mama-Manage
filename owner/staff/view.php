<?php
$pageTitle = 'Staff Member Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid staff ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    setFlashMessage('danger', 'Staff member not found.');
    header('Location: index.php');
    exit;
}

// Assigned maintenance requests
$stmt = $pdo->prepare("SELECT mr.*, 
       c.category, c.priority,
       t.tenant_name, t.phone as tenant_phone,
       a.apartment_number, a.floor_level
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    JOIN tenants t ON c.tenant_id = t.tenant_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    WHERE mr.staff_id = ?
    ORDER BY mr.maintenance_id DESC");
$stmt->execute([$id]);
$tasks = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Staff Profile: <?php echo htmlspecialchars($staff['staff_name']); ?></h1>
            <p>Technician assignment records, service history, and contact details.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="edit.php?id=<?php echo (int)$staff['staff_id']; ?>" class="btn btn-primary">&#9998; Edit Staff</a>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <!-- Staff Information Card -->
    <div class="card" style="max-width: 650px;">
        <div class="card-header">
            <span>&#128119; Technician Details</span>
            <span class="badge badge-purple"><?php echo htmlspecialchars($staff['experties']); ?></span>
        </div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Staff ID</span>
                    <span class="detail-value">#<?php echo (int)$staff['staff_id']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Full Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['staff_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['phone']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Specialization</span>
                    <span class="detail-value"><?php echo htmlspecialchars($staff['experties']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Maintenance Tasks -->
    <div class="card">
        <div class="card-header">
            <span>&#128295; Maintenance Work History (<?php echo count($tasks); ?> total assignments)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Work Order #</th>
                            <th>Complaint Category</th>
                            <th>Tenant</th>
                            <th>Apartment</th>
                            <th>Priority</th>
                            <th>Estimated Expense</th>
                            <th>Status</th>
                            <th>Completion Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr><td colspan="9" style="text-align: center; color: var(--text-muted);">No maintenance tasks assigned yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $t): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$t['maintenance_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($t['category']); ?></td>
                                    <td><?php echo htmlspecialchars($t['tenant_name']); ?></td>
                                    <td>Apt <?php echo htmlspecialchars($t['apartment_number']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $t['priority'] === 'High' ? 'badge-danger' : ($t['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                        ?>"><?php echo htmlspecialchars($t['priority']); ?></span>
                                    </td>
                                    <td>&#2547; <?php echo number_format($t['estimated_cost'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $t['maintenance_status'] === 'Completed' ? 'badge-success' : ($t['maintenance_status'] === 'In Progress' ? 'badge-info' : 'badge-warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($t['maintenance_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $t['completion_date'] ? date('d M Y', strtotime($t['completion_date'])) : 'Incomplete'; ?></td>
                                    <td>
                                        <a href="../maintenance/view.php?id=<?php echo (int)$t['maintenance_id']; ?>" class="btn btn-sm btn-secondary">Review</a>
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
