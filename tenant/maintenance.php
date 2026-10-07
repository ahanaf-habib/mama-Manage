<?php
$pageTitle = 'Maintenance Updates';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$tenant_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT mr.*, 
       c.complaint_id, c.category, c.priority, c.description as complaint_desc, c.submission_date,
       s.staff_name, s.phone as staff_phone, s.experties,
       a.apartment_number
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    JOIN apartments a ON c.apartment_id = a.apartment_id
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE c.tenant_id = ?
    ORDER BY mr.maintenance_id DESC");
$stmt->execute([$tenant_id]);
$maintenanceList = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Maintenance & Technician Activity</h1>
            <p>Track dispatched technicians, repair status, and service notes for your unit.</p>
        </div>
        <div>
            <a href="complaints.php" class="btn btn-secondary">&larr; My Complaints</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>&#128295; Work Orders for Your Apartment</span>
            <span class="badge badge-info"><?php echo count($maintenanceList); ?> Orders Logged</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Work Order #</th>
                            <th>Complaint Category</th>
                            <th>Urgency Priority</th>
                            <th>Assigned Technician</th>
                            <th>Technician Contact</th>
                            <th>Repair Status</th>
                            <th>Completion Date</th>
                            <th>Technician Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($maintenanceList)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128295;</div>
                                        <h3>No Maintenance Records</h3>
                                        <p>You have no ongoing or historical maintenance work orders.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($maintenanceList as $m): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$m['maintenance_id']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($m['category']); ?></strong>
                                        <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars(mb_strimwidth($m['complaint_desc'], 0, 40, '...')); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $m['priority'] === 'High' ? 'badge-danger' : ($m['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                        ?>"><?php echo htmlspecialchars($m['priority']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($m['staff_name']): ?>
                                            <span>&#128119; <?php echo htmlspecialchars($m['staff_name']); ?></span>
                                            <br><small class="badge badge-purple"><?php echo htmlspecialchars($m['experties']); ?></small>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Awaiting Assignment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['staff_phone'] ?? '&mdash;'); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $m['maintenance_status'] === 'Completed' ? 'badge-success' : ($m['maintenance_status'] === 'In Progress' ? 'badge-info' : 'badge-warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($m['maintenance_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $m['completion_date'] ? date('d M Y', strtotime($m['completion_date'])) : 'Pending'; ?></td>
                                    <td><?php echo htmlspecialchars($m['maintenance_notes'] ?? 'None recorded'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
