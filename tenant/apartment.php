<?php
$pageTitle = 'Available Apartments';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/apartment_requests.php';
require_once __DIR__ . '/../includes/tenancy.php';

ensureApartmentRequestsTable($pdo);
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

$tenantId = (int)$_SESSION['user_id'];
$currentTenancy = getCurrentActiveTenancy($pdo, $tenantId);
$scheduledTenancy = getScheduledTenancy($pdo, $tenantId);
$currentMoveOutRequest = $currentTenancy ? getLatestMoveOutRequest($pdo, (int)$currentTenancy['tenancy_id']) : null;
$tenantMoveOutNotice = getLatestMoveOutNoticeForTenant($pdo, $tenantId);
$visibleMoveOutRequest = $currentMoveOutRequest ?: $tenantMoveOutNotice;
$canRequestWhileTenanted = $currentTenancy ? ($currentMoveOutRequest !== null && !$scheduledTenancy) : !$scheduledTenancy;

// Send a rental request directly to the apartment owner.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_apartment_id'])) {
    $requestApartmentId = (int)$_POST['request_apartment_id'];
    try {
        $pdo->beginTransaction();

        $check = $pdo->prepare("SELECT apartment_id, owner_id, status FROM apartments WHERE apartment_id = ? FOR UPDATE");
        $check->execute([$requestApartmentId]);
        $apt = $check->fetch();
        if (!$apt || $apt['status'] !== 'Available') {
            throw new Exception('This apartment is no longer available.');
        }

        $active = $pdo->prepare("SELECT tenancy_id, move_in_date, move_out_date
            FROM tenancies
            WHERE tenant_id = ? AND tenancy_status = 'Active'
            ORDER BY CASE WHEN move_in_date <= CURDATE() THEN 0 ELSE 1 END, move_in_date ASC, tenancy_id ASC
            LIMIT 1
            FOR UPDATE");
        $active->execute([$tenantId]);
        $anyScheduledOrCurrent = $active->fetch();

        if ($anyScheduledOrCurrent) {
            $isCurrent = $anyScheduledOrCurrent['move_in_date'] <= (new DateTimeImmutable('today'))->format('Y-m-d')
                && (!$anyScheduledOrCurrent['move_out_date'] || $anyScheduledOrCurrent['move_out_date'] >= (new DateTimeImmutable('today'))->format('Y-m-d'));
            if (!$isCurrent) {
                throw new Exception('You already have another apartment scheduled to become your tenancy. Wait until that tenancy ends before requesting another apartment.');
            }
            $activeTenancyId = (int)$anyScheduledOrCurrent['tenancy_id'];
            if (!tenantHasMoveOutPermission($pdo, $activeTenancyId)) {
                throw new Exception('You already have an active tenancy. Submit a move-out request first before requesting another apartment.');
            }

            $futureConflict = $pdo->prepare("SELECT tenancy_id FROM tenancies WHERE tenant_id=? AND tenancy_status='Active' AND move_in_date > CURDATE() LIMIT 1 FOR UPDATE");
            $futureConflict->execute([$tenantId]);
            if ($futureConflict->fetchColumn()) {
                throw new Exception('You already have a new apartment scheduled. You cannot send another apartment request.');
            }
        }

        $pending = $pdo->prepare("SELECT request_id
            FROM apartment_requests
            WHERE tenant_id = ? AND apartment_id = ? AND status = 'Pending'
            LIMIT 1");
        $pending->execute([$tenantId, $requestApartmentId]);
        if ($pending->fetchColumn()) {
            throw new Exception('You have already requested this apartment.');
        }

        $stmt = $pdo->prepare("INSERT INTO apartment_requests (tenant_id, apartment_id, owner_id, status)
            VALUES (?, ?, ?, 'Pending')");
        $stmt->execute([$tenantId, $requestApartmentId, $apt['owner_id']]);
        $pdo->commit();
        setFlashMessage('success', 'Apartment request sent to the owner successfully.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
    }
    header('Location: apartment.php');
    exit;
}

$flashSuccess = getFlashMessage('success');
$flashError = getFlashMessage('error');

$stmt = $pdo->query("SELECT a.apartment_id, a.apartment_number, a.floor_level, a.description,
        a.monthly_rent, a.status, o.owner_name, o.phone AS owner_phone, o.email AS owner_email
    FROM apartments a
    JOIN owners o ON a.owner_id = o.owner_id
    WHERE a.status = 'Available'
    ORDER BY a.floor_level ASC, a.apartment_number ASC");
$apartments = $stmt->fetchAll();

$requestStmt = $pdo->prepare("SELECT ar.*, o.owner_name,
        a.apartment_number, a.floor_level, a.monthly_rent,
        (SELECT tn.move_in_date
         FROM tenancies tn
         WHERE tn.tenant_id = ar.tenant_id
           AND tn.apartment_id = ar.apartment_id
         ORDER BY tn.tenancy_id DESC
         LIMIT 1) AS move_in_date
    FROM apartment_requests ar
    JOIN owners o ON o.owner_id = ar.owner_id
    JOIN apartments a ON a.apartment_id = ar.apartment_id
    WHERE ar.tenant_id = ?
    ORDER BY ar.requested_at DESC, ar.request_id DESC");
$requestStmt->execute([$tenantId]);
$tenantRequests = $requestStmt->fetchAll();
$requestStatuses = [];
foreach ($tenantRequests as $request) {
    $aptKey = (int)$request['apartment_id'];
    if (!isset($requestStatuses[$aptKey])) {
        $requestStatuses[$aptKey] = [
            'status' => $request['status'],
            'move_in_date' => $request['move_in_date'] ?? null
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>

    <?php if (($currentTenancy && $currentMoveOutRequest) || $tenantMoveOutNotice): ?>
        <div class="alert alert-warning">
            <strong>Move-out notice:</strong>
            <?php if ($visibleMoveOutRequest['initiated_by'] === 'Owner'): ?>
                your owner has scheduled your move-out for <strong><?php echo date('d M Y', strtotime($visibleMoveOutRequest['proposed_move_out_date'])); ?></strong>. You may now request another available apartment.
            <?php elseif ($visibleMoveOutRequest['status'] === 'Pending'): ?>
                your move-out request is waiting for the owner's approval. You may now request another available apartment.
            <?php else: ?>
                your move-out is scheduled for <strong><?php echo date('d M Y', strtotime($visibleMoveOutRequest['proposed_move_out_date'])); ?></strong>.
                You may now request another available apartment. A new tenancy will begin after your current tenancy ends.
            <?php endif; ?>
        </div>
    <?php elseif ($scheduledTenancy): ?>
        <div class="alert alert-info">
            <strong>Next tenancy scheduled:</strong> Apt <?php echo htmlspecialchars($scheduledTenancy['apartment_number']); ?> will become your new tenancy on <strong><?php echo date('d M Y', strtotime($scheduledTenancy['move_in_date'])); ?></strong>. You cannot request another apartment while this scheduled tenancy exists.
        </div>
    <?php elseif ($currentTenancy): ?>
        <div class="alert alert-info">
            <strong>Current tenancy:</strong> you are currently renting Apt <?php echo htmlspecialchars($currentTenancy['apartment_number']); ?>.
            You can view available apartments, but you must submit a move-out request before you can apply for another unit. <a href="move_out.php" style="font-weight:600;">Submit Move-Out Request</a>.
        </div>
    <?php endif; ?>

    <?php if (!empty($tenantRequests)): ?>
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header"><span>&#128233; My Apartment Requests</span></div>
            <div class="card-body" style="padding:0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Apartment</th><th>Owner</th><th>Requested</th><th>Move-In Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($tenantRequests, 0, 10) as $r): ?>
                            <tr>
                                <td><strong>Apt <?php echo htmlspecialchars($r['apartment_number']); ?></strong><br><small>Floor <?php echo (int)$r['floor_level']; ?> · &#2547; <?php echo number_format($r['monthly_rent'], 2); ?></small></td>
                                <td><?php echo htmlspecialchars($r['owner_name']); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($r['requested_at'])); ?></td>
                                <td>
                                    <?php if ($r['status'] === 'Approved' && !empty($r['move_in_date'])): ?>
                                        <strong><?php echo date('d M Y', strtotime($r['move_in_date'])); ?></strong>
                                    <?php elseif ($r['status'] === 'Pending'): ?>
                                        <span style="color:var(--text-muted);">Pending approval</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'Pending'): ?>
                                        <span class="badge badge-warning">Waiting for owner approval</span>
                                    <?php elseif ($r['status'] === 'Approved'): ?>
                                        <span class="badge badge-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Rejected / Invalid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Available Apartments</h1>
            <p>Browse apartment units that are currently available for tenancy.</p>
        </div>
    </div>

    <?php if (empty($apartments)): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">&#127970;</div>
                    <h3>No Apartments Available</h3>
                    <p>There are currently no apartment units marked as available. Please check again later or contact building management.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="grid-2">
            <?php foreach ($apartments as $apt): ?>
                <div class="card">
                    <div class="card-header">
                        <span>&#127970; Apt <?php echo htmlspecialchars($apt['apartment_number']); ?></span>
                        <span class="badge badge-success">Available</span>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">Floor Level</span>
                                <span class="detail-value">Floor <?php echo (int)$apt['floor_level']; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Monthly Rent</span>
                                <span class="detail-value" style="font-size:16px; color:var(--primary);">&#2547; <?php echo number_format($apt['monthly_rent'], 2); ?></span>
                            </div>
                            <div class="detail-item" style="grid-column:span 2;">
                                <span class="detail-label">Description</span>
                                <span class="detail-value" style="font-weight:normal;"><?php echo htmlspecialchars($apt['description'] ?: 'Standard apartment unit.'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Owner</span>
                                <span class="detail-value"><?php echo htmlspecialchars($apt['owner_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Contact</span>
                                <span class="detail-value"><?php echo htmlspecialchars($apt['owner_phone']); ?></span>
                            </div>
                        </div>
                        <div style="margin-top:20px;">
                            <?php $requestInfo = $requestStatuses[(int)$apt['apartment_id']] ?? null; $requestStatus = $requestInfo['status'] ?? null; ?>
                            <?php if ($requestStatus === 'Pending'): ?>
                                <div class="alert alert-warning" style="margin:0;">&#9203; Request sent. Waiting for the owner to approve it.</div>
                            <?php elseif ($requestStatus === 'Approved'): ?>
                                <div class="alert alert-success" style="margin:0;">&#10004; Your request was approved.<?php if (!empty($requestInfo['move_in_date'])): ?><br><strong>Move-in date: <?php echo date('d M Y', strtotime($requestInfo['move_in_date'])); ?></strong><?php endif; ?></div>
                            <?php elseif ($scheduledTenancy): ?>
                                <div class="alert alert-info" style="margin:0;">A new apartment is already scheduled for you on <?php echo date('d M Y', strtotime($scheduledTenancy['move_in_date'])); ?>.</div>
                            <?php elseif ($currentTenancy && !$canRequestWhileTenanted): ?>
                                <div class="alert alert-info" style="margin:0;">Submit a move-out request first to apply for a new apartment.</div>
                            <?php else: ?>
                                <form method="POST" action="" onsubmit="return confirm('Send a rental request for this apartment to the owner?');">
                                    <input type="hidden" name="request_apartment_id" value="<?php echo (int)$apt['apartment_id']; ?>">
                                    <button type="submit" class="btn btn-primary w-100">&#128233; Request This Apartment</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
