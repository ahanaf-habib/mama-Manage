<?php
$pageTitle = 'Complaint Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT c.*, 
               t.tenant_name, 
               t.phone,
               a.apartment_number, 
               a.floor_level,
               mr.maintenance_id,
               mr.maintenance_status,
               s.staff_name
        FROM complaints c
        JOIN tenants t ON c.tenant_id = t.tenant_id
        JOIN apartments a ON c.apartment_id = a.apartment_id
        LEFT JOIN maintenance_requests mr ON c.complaint_id = mr.complaint_id
        LEFT JOIN staff s ON mr.staff_id = s.staff_id
        WHERE a.owner_id = ?";

$params = [$ownerId];
if ($search !== '') {
    $sql .= " AND (t.tenant_name LIKE ? OR a.apartment_number LIKE ? OR c.description LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($category !== '') {
    $sql .= " AND c.category = ?";
    $params[] = $category;
}

if ($priority !== '') {
    $sql .= " AND c.priority = ?";
    $params[] = $priority;
}

if ($status !== '') {
    $sql .= " AND c.complaint_status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY c.submission_date DESC, c.complaint_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Categories for filter
$catStmt = $pdo->prepare("SELECT DISTINCT c.category FROM complaints c JOIN apartments a ON c.apartment_id = a.apartment_id WHERE a.owner_id = ? ORDER BY c.category ASC");
$catStmt->execute([$ownerId]);
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Complaint Management</h1>
            <p>Review tenant grievances, dispatch maintenance, and track resolution lifecycles.</p>
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
            <input type="text" id="search" name="search" class="form-control" placeholder="Search tenant, apt, issue..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label for="category">Category</label>
            <select id="category" name="category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="priority">Priority</label>
            <select id="priority" name="priority" class="form-control">
                <option value="">All Priorities</option>
                <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High</option>
                <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Assigned" <?php echo $status === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="Resolved" <?php echo $status === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== '' || $category !== '' || $priority !== '' || $status !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Complaints Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Complaint ID</th>
                            <th>Tenant</th>
                            <th>Apartment</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Priority</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Assigned Staff</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaints)): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128227;</div>
                                        <h3>No Complaints Found</h3>
                                        <p>No complaints match your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$c['complaint_id']; ?></strong></td>
                                    <td>
                                        <a href="../tenants/view.php?id=<?php echo (int)$c['tenant_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($c['tenant_name']); ?>
                                        </a>
                                    </td>
                                    <td>Apt <?php echo htmlspecialchars($c['apartment_number']); ?></td>
                                    <td><?php echo htmlspecialchars($c['category']); ?></td>
                                    <td><?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 50, '...')); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $c['priority'] === 'High' ? 'badge-danger' : ($c['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                        ?>">
                                            <?php echo htmlspecialchars($c['priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($c['submission_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $c['complaint_status'] === 'Resolved' ? 'badge-success' : ($c['complaint_status'] === 'Pending' ? 'badge-warning' : 'badge-info'); 
                                        ?>">
                                            <?php echo htmlspecialchars($c['complaint_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($c['staff_name']): ?>
                                            <span>&#128119; <?php echo htmlspecialchars($c['staff_name']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="view.php?id=<?php echo (int)$c['complaint_id']; ?>" class="btn btn-sm btn-secondary">Review</a>
                                            <?php if (!$c['maintenance_id'] && $c['complaint_status'] !== 'Resolved'): ?>
                                                <a href="../maintenance/add.php?complaint_id=<?php echo (int)$c['complaint_id']; ?>" class="btn btn-sm btn-primary">Dispatch</a>
                                            <?php endif; ?>
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
