<?php
$pageTitle = 'Apartment Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$ownerId = (int)getCurrentUserId();

// Filters
$search = trim($_GET['search'] ?? '');
$floor = trim($_GET['floor'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT a.*, 
               t.tenant_name, 
               t.tenant_id,
               tn.tenancy_id
        FROM apartments a
        LEFT JOIN tenancies tn ON a.apartment_id = tn.apartment_id AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
        LEFT JOIN tenants t ON tn.tenant_id = t.tenant_id
        WHERE a.owner_id = ?";

$params = [$ownerId];

if ($search !== '') {
    $sql .= " AND a.apartment_number LIKE ?";
    $params[] = "%$search%";
}

if ($floor !== '') {
    $sql .= " AND a.floor_level = ?";
    $params[] = $floor;
}

if ($status !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY a.floor_level ASC, a.apartment_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$apartments = $stmt->fetchAll();

// Distinct floors for filter dropdown
$floorStmt = $pdo->prepare("SELECT DISTINCT floor_level FROM apartments WHERE owner_id = ? ORDER BY floor_level ASC");
$floorStmt->execute([$ownerId]);
$floors = $floorStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Apartment Management</h1>
            <p>Manage all apartments, occupancy status, and active tenants.</p>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">&#43; Add Apartment</a>
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
            <label for="search">Search Apartment</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="e.g. A-101" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label for="floor">Floor Level</label>
            <select id="floor" name="floor" class="form-control">
                <option value="">All Floors</option>
                <?php foreach ($floors as $fl): ?>
                    <option value="<?php echo (int)$fl; ?>" <?php echo $floor !== '' && (int)$floor === (int)$fl ? 'selected' : ''; ?>>
                        Floor <?php echo (int)$fl; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="Available" <?php echo $status === 'Available' ? 'selected' : ''; ?>>Available</option>
                <option value="Occupied" <?php echo $status === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                <option value="Under Maintenance" <?php echo $status === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== '' || $floor !== '' || $status !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Apartments Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Apt Number</th>
                            <th>Floor</th>
                            <th>Description</th>
                            <th>Monthly Rent</th>
                            <th>Status</th>
                            <th>Current Tenant</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($apartments)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#127970;</div>
                                        <h3>No Apartments Found</h3>
                                        <p>No apartments match your search or filter criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($apartments as $apt): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($apt['apartment_number']); ?></strong></td>
                                    <td>Floor <?php echo (int)$apt['floor_level']; ?></td>
                                    <td><?php echo htmlspecialchars($apt['description'] ?? 'N/A'); ?></td>
                                    <td>&#2547; <?php echo number_format($apt['monthly_rent'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $apt['status'] === 'Available' ? 'badge-success' : ($apt['status'] === 'Occupied' ? 'badge-info' : 'badge-warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($apt['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($apt['tenant_name']): ?>
                                            <a href="../tenants/view.php?id=<?php echo (int)$apt['tenant_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 500;">
                                                <?php echo htmlspecialchars($apt['tenant_name']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="view.php?id=<?php echo (int)$apt['apartment_id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                            <a href="edit.php?id=<?php echo (int)$apt['apartment_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                            <a href="delete.php?id=<?php echo (int)$apt['apartment_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Are you sure you want to delete apartment <?php echo htmlspecialchars($apt['apartment_number']); ?>?');">Delete</a>
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
