<?php
$pageTitle = 'Notice Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$target_type = trim($_GET['target_type'] ?? '');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT n.*, 
               o.owner_name,
               a.apartment_number
        FROM notices n
        JOIN owners o ON n.owner_id = o.owner_id
        LEFT JOIN apartments a ON n.apartment_id = a.apartment_id
        WHERE n.owner_id = ?";

$params = [$ownerId];
if ($target_type !== '') {
    $sql .= " AND n.target_type = ?";
    $params[] = $target_type;
}

if ($search !== '') {
    $sql .= " AND n.message_content LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY n.published_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notices = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Notice Management</h1>
            <p>View all published notices and manage building-wide or unit-targeted announcements.</p>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">&#43; Publish Notice</a>
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
            <label for="search">Search Notices</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search keywords..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label for="target_type">Target Audience</label>
            <select id="target_type" name="target_type" class="form-control">
                <option value="">All Audiences</option>
                <option value="Everyone" <?php echo $target_type === 'Everyone' ? 'selected' : ''; ?>>Everyone (Building-wide)</option>
                <option value="Apartment" <?php echo $target_type === 'Apartment' ? 'selected' : ''; ?>>Apartment Unit</option>
                <option value="Floor" <?php echo $target_type === 'Floor' ? 'selected' : ''; ?>>Floor Level</option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($search !== '' || $target_type !== ''): ?>
            <a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Notices Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Notice ID</th>
                            <th>Announcement Message</th>
                            <th>Target Audience</th>
                            <th>Target Scope</th>
                            <th>Published By</th>
                            <th>Published At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notices)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128226;</div>
                                        <h3>No Notices Found</h3>
                                        <p>No announcements match your search criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notices as $n): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$n['notice_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars(mb_strimwidth($n['message_content'], 0, 90, '...')); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $n['target_type'] === 'Everyone' ? 'badge-info' : ($n['target_type'] === 'Apartment' ? 'badge-warning' : 'badge-purple'); 
                                        ?>">
                                            <?php echo htmlspecialchars($n['target_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($n['target_type'] === 'Apartment') {
                                            echo '<strong>Apt ' . htmlspecialchars($n['apartment_number'] ?? $n['apartment_id']) . '</strong>';
                                        } elseif ($n['target_type'] === 'Floor') {
                                            echo '<strong>Floor ' . (int)$n['floor_number'] . '</strong>';
                                        } else {
                                            echo '<span style="color: var(--text-muted);">All Residents</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($n['owner_name']); ?></td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($n['published_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="view.php?id=<?php echo (int)$n['notice_id']; ?>" class="btn btn-sm btn-secondary">View</a>
                                            <a href="edit.php?id=<?php echo (int)$n['notice_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                            <a href="delete.php?id=<?php echo (int)$n['notice_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Are you sure you want to delete this notice?');">Delete</a>
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
