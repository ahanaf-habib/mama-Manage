<?php
$pageTitle = 'Move-Out Requests';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/tenancy.php';

$ownerId = (int)$_SESSION['user_id'];
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

$stmt = $pdo->prepare("SELECT mor.*, t.tenant_name, t.phone AS tenant_phone, t.email AS tenant_email,
        a.apartment_id, a.apartment_number, a.floor_level, a.monthly_rent,
        tn.move_in_date, tn.move_out_date AS tenancy_move_out_date, tn.tenancy_status
    FROM move_out_requests mor
    JOIN tenants t ON mor.tenant_id = t.tenant_id
    JOIN tenancies tn ON mor.tenancy_id = tn.tenancy_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE mor.owner_id = ?
    ORDER BY CASE mor.status WHEN 'Pending' THEN 0 WHEN 'Approved' THEN 1 ELSE 2 END,
             mor.requested_at DESC, mor.request_id DESC");
$stmt->execute([$ownerId]);
$requests = $stmt->fetchAll();
$success = getFlashMessage('success');
$error = getFlashMessage('error');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-content">
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="page-header">
        <div><h1>Move-Out Requests</h1><p>Review tenant move-out notices and scheduled move-outs. Every move-out requires at least 15 days notice.</p></div>
    </div>
    <div class="card"><div class="card-body" style="padding:0;"><div class="table-responsive"><table class="data-table">
        <thead><tr><th>Tenant</th><th>Apartment</th><th>Requested</th><th>Proposed Move-Out</th><th>Type</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$requests): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">No move-out requests yet.</td></tr>
        <?php else: foreach ($requests as $r): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($r['tenant_name']); ?></strong><br><small><?php echo htmlspecialchars($r['tenant_phone']); ?> &middot; <?php echo htmlspecialchars($r['tenant_email']); ?></small></td>
                <td>Apt <?php echo htmlspecialchars($r['apartment_number']); ?><br><small>Floor <?php echo (int)$r['floor_level']; ?></small></td>
                <td><?php echo date('d M Y, h:i A', strtotime($r['requested_at'])); ?></td>
                <td><strong><?php echo date('d M Y', strtotime($r['proposed_move_out_date'])); ?></strong></td>
                <td><?php echo htmlspecialchars($r['initiated_by']); ?></td>
                <td><span class="badge <?php echo $r['status'] === 'Pending' ? 'badge-warning' : ($r['status'] === 'Approved' ? 'badge-success' : 'badge-danger'); ?>"><?php echo htmlspecialchars(formatMoveOutStatus($r['status'], $r['initiated_by'])); ?></span></td>
                <td>
                    <?php if ($r['status'] === 'Pending'): ?>
                        <div style="display:flex;gap:6px;">
                            <form method="POST" action="action.php">
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('Approve this move-out date? The tenant will receive at least 15 days notice.');">Approve</button>
                            </form>
                            <form method="POST" action="action.php">
                                <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-sm btn-secondary" type="submit" onclick="return confirm('Reject this move-out request?');">Reject</button>
                            </form>
                        </div>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div></div></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
