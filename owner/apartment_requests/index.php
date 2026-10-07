<?php
$pageTitle = 'Apartment Requests';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/apartment_requests.php';

$ownerId = (int)$_SESSION['user_id'];
ensureApartmentRequestsTable($pdo);

$stmt = $pdo->prepare("SELECT ar.*, t.tenant_name, t.phone AS tenant_phone,
        t.email AS tenant_email, a.apartment_number, a.floor_level, a.monthly_rent,
        (SELECT tn.move_in_date
         FROM tenancies tn
         WHERE tn.tenant_id = ar.tenant_id
           AND tn.apartment_id = ar.apartment_id
         ORDER BY tn.tenancy_id DESC
         LIMIT 1) AS move_in_date
    FROM apartment_requests ar
    JOIN tenants t ON ar.tenant_id = t.tenant_id
    JOIN apartments a ON ar.apartment_id = a.apartment_id
    WHERE ar.owner_id = ?
    ORDER BY CASE ar.status
        WHEN 'Pending' THEN 0
        WHEN 'Approved' THEN 1
        ELSE 2
    END, ar.requested_at DESC, ar.request_id DESC");
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
        <div>
            <h1>Apartment Requests</h1>
            <p>Review rental requests. When one request is approved, all other pending requests from that tenant and for that apartment are automatically invalidated.</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Tenant</th><th>Apartment</th><th>Rent</th><th>Requested</th><th>Move-In</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$requests): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">No apartment requests yet.</td></tr>
                    <?php else: foreach ($requests as $r): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($r['tenant_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($r['tenant_phone']); ?> &middot; <?php echo htmlspecialchars($r['tenant_email']); ?></small>
                            </td>
                            <td>
                                Apt <?php echo htmlspecialchars($r['apartment_number']); ?><br>
                                <small>Floor <?php echo (int)$r['floor_level']; ?></small>
                            </td>
                            <td>&#2547; <?php echo number_format($r['monthly_rent'], 2); ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($r['requested_at'])); ?></td>
                            <td><?php echo $r['status'] === 'Approved' && !empty($r['move_in_date']) ? '<strong>' . date('d M Y', strtotime($r['move_in_date'])) . '</strong>' : ($r['status'] === 'Pending' ? 'After approval' : '—'); ?></td>
                            <td>
                                <span class="badge <?php echo $r['status'] === 'Pending' ? 'badge-warning' : ($r['status'] === 'Approved' ? 'badge-success' : 'badge-danger'); ?>">
                                    <?php echo htmlspecialchars($r['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'Pending'): ?>
                                    <div style="display:flex;gap:6px;">
                                        <form method="POST" action="action.php">
                                            <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('Approve this request? The system will create a tenancy and set the exact move-in date automatically.');">Approve</button>
                                        </form>
                                        <form method="POST" action="action.php">
                                            <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-sm btn-secondary" type="submit" onclick="return confirm('Reject this apartment request?');">Reject</button>
                                        </form>
                                    </div>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
