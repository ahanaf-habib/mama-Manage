<?php
$pageTitle = 'Staff Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$search = trim($_GET['search'] ?? '');

$sql = "SELECT s.*, 
               COUNT(mr.maintenance_id) as total_tasks,
               SUM(CASE WHEN mr.maintenance_status IN ('Assigned', 'In Progress') THEN 1 ELSE 0 END) as active_tasks
        FROM staff s
        LEFT JOIN maintenance_requests mr ON s.staff_id = mr.staff_id
        WHERE 1=1";

$params = [];
if ($search !== '') {
    $sql .= " AND (s.staff_name LIKE ? OR s.phone LIKE ? OR s.experties LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$sql .= " GROUP BY s.staff_id ORDER BY s.staff_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffList = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Staff Management</h1>
            <p>Maintenance personnel, trade specialists, and repair workforce.</p>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">&#43; Add Staff Member</a>
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
            <label for="search">Search Staff</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search name, phone, trade expertise..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Staff Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Full Name</th>
                            <th>Phone</th>
                            <th>Trade Expertise</th>
                            <th>Active Tasks</th>
                            <th>Total Tasks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffList)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128119;</div>
                                        <h3>No Staff Members Found</h3>
                                        <p>No maintenance personnel registered yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staffList as $s): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$s['staff_id']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($s['staff_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                    <td>
                                        <span class="badge badge-purple"><?php echo htmlspecialchars($s['experties'] ?? 'General'); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($s['active_tasks'] > 0): ?>
                                            <span class="badge badge-warning"><?php echo (int)$s['active_tasks']; ?> Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">&#10004; Idle</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$s['total_tasks']; ?> total</td>
                                    <td>
                                        <div class="actions">
                                            <a href="view.php?id=<?php echo (int)$s['staff_id']; ?>" class="btn btn-sm btn-secondary">View Work</a>
                                            <a href="edit.php?id=<?php echo (int)$s['staff_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                            <a href="delete.php?id=<?php echo (int)$s['staff_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Are you sure you want to remove staff member <?php echo htmlspecialchars($s['staff_name']); ?>?');">Delete</a>
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
