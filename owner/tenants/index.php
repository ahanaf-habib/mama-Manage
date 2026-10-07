<?php
$pageTitle = 'Tenant Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/tenancy.php';
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$flash = getFlashMessage();
$ownerId = (int)getCurrentUserId();
$search = trim($_GET['search'] ?? '');

$sql = "SELECT t.*,
               a.apartment_id,
               a.apartment_number,
               a.floor_level,
               tn.tenancy_id,
               tn.move_in_date,
               tn.move_out_date,
               tn.tenancy_status,
               (SELECT mor.status FROM move_out_requests mor WHERE mor.tenancy_id = tn.tenancy_id AND mor.status IN ('Pending','Approved') ORDER BY mor.request_id DESC LIMIT 1) AS move_out_request_status
        FROM tenants t
        LEFT JOIN tenancies tn ON tn.tenancy_id = (
            SELECT tn2.tenancy_id
            FROM tenancies tn2
            JOIN apartments a2 ON a2.apartment_id = tn2.apartment_id
            WHERE tn2.tenant_id = t.tenant_id
              AND a2.owner_id = ?
            ORDER BY (tn2.tenancy_status = 'Active' AND tn2.move_in_date <= CURDATE() AND (tn2.move_out_date IS NULL OR tn2.move_out_date >= CURDATE())) DESC, tn2.move_in_date DESC, tn2.tenancy_id DESC
            LIMIT 1
        )
        LEFT JOIN apartments a ON tn.apartment_id = a.apartment_id
        WHERE tn.tenancy_id IS NOT NULL";
$params = [$ownerId];
if ($search !== '') {
    $sql .= " AND (t.tenant_name LIKE ? OR t.email LIKE ? OR t.phone LIKE ? OR t.id_reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY t.tenant_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div><h1>Tenant Management</h1><p>Manage tenant profiles, apartment assignments, floor, move-in and move-out information in one place.</p></div>
        <div><a href="add.php" class="btn btn-primary">&#43; Add Tenant</a></div>
    </div>
    <?php if ($flash): ?><div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>

    <form method="GET" class="filter-bar">
        <div class="filter-group"><label for="search">Search Tenant</label><input type="text" id="search" name="search" class="form-control" placeholder="Search by name, email, phone, NID..." value="<?php echo htmlspecialchars($search); ?>"></div>
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($search !== ''): ?><a href="index.php" class="btn btn-sm btn-secondary" style="height: 38px;">Reset</a><?php endif; ?>
    </form>

    <div class="card"><div class="card-body" style="padding: 0;"><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Tenant Name</th><th>Phone</th><th>Email</th><th>NID / ID</th><th>Apartment</th><th>Floor</th><th>Move-In</th><th>Move-Out</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($tenants)): ?>
            <tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon">&#128100;</div><h3>No Tenants Found</h3><p>No tenant records match your query.</p></div></td></tr>
        <?php else: foreach ($tenants as $t): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($t['tenant_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($t['phone']); ?></td>
                <td><?php echo htmlspecialchars($t['email']); ?></td>
                <td><code><?php echo htmlspecialchars($t['id_reference']); ?></code></td>
                <td><?php if ($t['apartment_number']): ?><a href="../apartments/view.php?id=<?php echo (int)$t['apartment_id']; ?>" style="color: var(--primary); text-decoration:none; font-weight:500;">Apt <?php echo htmlspecialchars($t['apartment_number']); ?></a><?php else: ?><span style="color: var(--text-muted);">Unassigned</span><?php endif; ?></td>
                <td><?php echo $t['floor_level'] !== null ? 'Floor ' . (int)$t['floor_level'] : '—'; ?></td>
                <td><?php echo $t['move_in_date'] ? date('d M Y', strtotime($t['move_in_date'])) : '—'; ?></td>
                <td><?php echo $t['move_out_date'] ? date('d M Y', strtotime($t['move_out_date'])) : ($t['tenancy_status'] === 'Active' ? 'Present' : '—'); ?><?php if (!empty($t['move_out_request_status'])): ?><br><small style="color:var(--text-muted);"><?php echo $t['move_out_request_status'] === 'Pending' ? 'Move-out pending approval' : 'Move-out scheduled'; ?></small><?php endif; ?></td>
                <td><span class="badge <?php echo $t['tenancy_status'] === 'Active' ? 'badge-success' : ($t['tenancy_status'] === 'Ended' ? 'badge-secondary' : 'badge-warning'); ?>"><?php echo $t['tenancy_status'] ? htmlspecialchars($t['tenancy_status']) : 'Unassigned'; ?></span></td>
                <td><div class="actions"><a href="view.php?id=<?php echo (int)$t['tenant_id']; ?>" class="btn btn-sm btn-secondary">View</a><a href="edit.php?id=<?php echo (int)$t['tenant_id']; ?>" class="btn btn-sm btn-secondary">Edit</a><?php if ($t['tenancy_status'] === 'Active' && $t['move_in_date'] && $t['move_in_date'] <= date('Y-m-d') && (!$t['move_out_date'] || $t['move_out_date'] >= date('Y-m-d'))): ?><a href="move_out.php?id=<?php echo (int)$t['tenant_id']; ?>" class="btn btn-sm btn-secondary">Move-Out</a><?php endif; ?></div></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div></div></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
