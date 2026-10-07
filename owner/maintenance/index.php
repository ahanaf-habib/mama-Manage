<?php
$pageTitle = 'Maintenance Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$search = trim($_GET['search'] ?? '');
$staff_id = trim($_GET['staff_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');

$sql = "SELECT mr.*, 
               c.category as complaint_category,
               c.priority as complaint_priority,
               c.description as complaint_desc,
               s.staff_name,
               s.phone as staff_phone,
               s.experties as staff_experties,
               t.tenant_name,
               t.tenant_id,
               a.apartment_number,
               a.floor_level
        FROM maintenance_requests mr
        JOIN complaints c ON mr.complaint_id = c.complaint_id
        JOIN tenants t ON c.tenant_id = t.tenant_id
        JOIN apartments a ON c.apartment_id = a.apartment_id
        LEFT JOIN staff s ON mr.staff_id = s.staff_id
        WHERE a.owner_id = ?";

$params = [$ownerId];
if ($search !== '') {
    $sql .= " AND (t.tenant_name LIKE ? OR a.apartment_number LIKE ? OR c.category LIKE ? OR mr.maintenance_notes LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($staff_id !== '') {
    $sql .= " AND mr.staff_id = ?";
    $params[] = $staff_id;
}

if ($status !== '') {
    $sql .= " AND mr.maintenance_status = ?";
    $params[] = $status;
}

if ($priority !== '') {
    $sql .= " AND c.priority = ?";
    $params[] = $priority;
}

$sql .= " ORDER BY mr.maintenance_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$maintenanceList = $stmt->fetchAll();

// Staff list for filter
$staffMembers = $pdo->query("SELECT staff_id, staff_name, experties FROM staff ORDER BY staff_name ASC")->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Maintenance Management</h1>
            <p>Work order dispatching, technician scheduling, and repair expense management.</p>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">&#43; Create Work Order</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search work order..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label for="staff_id">Technician</label>
            <select id="staff_id" name="staff_id" class="form-control">
                <option value="">All Staff</option>
                <?php foreach ($staffMembers as $sm): ?>
                    <option value="<?php echo (int)$sm['staff_id']; ?>" <?php echo $staff_id !== '' && (int)$staff_id === (int)$sm['staff_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sm['staff_name']); ?> (<?php echo htmlspecialchars($sm['experties']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="status">Work Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Assigned" <?php echo $status === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="priority">Complaint Priority</label>
            <select id="priority" name="priority" class="form-control">
                <option value="">All Priorities</option>
                <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High</option>
                <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== '' || $staff_id !== '' || $status !== '' || $priority !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Maintenance Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Work Order #</th>
                            <th>Complaint Category</th>
                            <th>Tenant</th>
                            <th>Apartment</th>
                            <th>Complaint Priority</th>
                            <th>Assigned Staff</th>
                            <th>Estimated Expense</th>
                            <th>Status</th>
                            <th>Completion Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($maintenanceList)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128295;</div>
                                        <h3>No Maintenance Records Found</h3>
                                        <p>No work orders match the current filter selection.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($maintenanceList as $m): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$m['maintenance_id']; ?></strong></td>
                                    <td>
                                        <a href="../complaints/view.php?id=<?php echo (int)$m['complaint_id']; ?>" style="color: var(--text-main); font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($m['complaint_category']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="../tenants/view.php?id=<?php echo (int)$m['tenant_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                            <?php echo htmlspecialchars($m['tenant_name']); ?>
                                        </a>
                                    </td>
                                    <td>Apt <?php echo htmlspecialchars($m['apartment_number']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $m['complaint_priority'] === 'High' ? 'badge-danger' : ($m['complaint_priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                        ?>">
                                            <?php echo htmlspecialchars($m['complaint_priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($m['staff_name']): ?>
                                            <span>&#128119; <?php echo htmlspecialchars($m['staff_name']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>&#2547; <?php echo number_format($m['estimated_cost'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $m['maintenance_status'] === 'Completed' ? 'badge-success' : ($m['maintenance_status'] === 'In Progress' ? 'badge-info' : 'badge-warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($m['maintenance_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $m['completion_date'] ? date('d M Y', strtotime($m['completion_date'])) : '&mdash;'; ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="view.php?id=<?php echo (int)$m['maintenance_id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                            <a href="edit.php?id=<?php echo (int)$m['maintenance_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        </div>
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
