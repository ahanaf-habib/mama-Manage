<?php
$pageTitle = 'Schedule Move-Out';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/tenancy.php';

ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

$ownerId = (int)$_SESSION['user_id'];
$tenantId = (int)($_GET['id'] ?? $_POST['tenant_id'] ?? 0);
$error = '';

$tenantStmt = $pdo->prepare("SELECT t.tenant_id, t.tenant_name, t.phone, t.email,
        tn.tenancy_id, tn.move_in_date, tn.move_out_date, tn.tenancy_status,
        a.apartment_id, a.apartment_number, a.floor_level, a.monthly_rent
    FROM tenants t
    JOIN tenancies tn ON tn.tenant_id = t.tenant_id
    JOIN apartments a ON a.apartment_id = tn.apartment_id
    WHERE t.tenant_id = ?
      AND a.owner_id = ?
      AND tn.tenancy_status = 'Active'
      AND tn.move_in_date <= CURDATE()
      AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
    ORDER BY tn.move_in_date DESC, tn.tenancy_id DESC
    LIMIT 1");
$tenantStmt->execute([$tenantId, $ownerId]);
$tenancy = $tenantStmt->fetch();

if (!$tenancy) {
    setFlashMessage('error', 'No current active tenancy was found for this tenant under your ownership.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposedDate = trim($_POST['proposed_move_out_date'] ?? '');
    $minDate = (new DateTimeImmutable('today'))->modify('+15 days')->format('Y-m-d');
    try {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $proposedDate);
        if (!$date || $date->format('Y-m-d') !== $proposedDate) {
            throw new Exception('Please choose a valid move-out date.');
        }
        if ($proposedDate < $minDate) {
            throw new Exception('Owner-initiated move-out also requires at least 15 days notice.');
        }

        $pdo->beginTransaction();
        $scheduledStmt = $pdo->prepare("SELECT move_in_date FROM tenancies
            WHERE tenant_id=? AND tenancy_status='Active' AND move_in_date > CURDATE()
            ORDER BY move_in_date ASC LIMIT 1 FOR UPDATE");
        $scheduledStmt->execute([(int)$tenancy['tenant_id']]);
        $scheduled = $scheduledStmt->fetchColumn();
        if ($scheduled && $proposedDate >= $scheduled) {
            throw new Exception("The current tenancy must end before the tenant's next scheduled tenancy begins. Choose an earlier move-out date.");
        }
        $lock = $pdo->prepare("SELECT tenancy_id FROM tenancies WHERE tenancy_id=? AND tenancy_status='Active' FOR UPDATE");
        $lock->execute([(int)$tenancy['tenancy_id']]);
        if (!$lock->fetchColumn()) {
            throw new Exception('The tenancy is no longer active.');
        }

        $existingStmt = $pdo->prepare("SELECT request_id, status
            FROM move_out_requests
            WHERE tenancy_id=? AND status IN ('Pending','Approved')
            ORDER BY request_id DESC LIMIT 1 FOR UPDATE");
        $existingStmt->execute([(int)$tenancy['tenancy_id']]);
        $existing = $existingStmt->fetch();

        $pdo->prepare("UPDATE tenancies SET move_out_date=? WHERE tenancy_id=? AND tenancy_status='Active'")
            ->execute([$proposedDate, (int)$tenancy['tenancy_id']]);

        if ($existing) {
            $pdo->prepare("UPDATE move_out_requests
                SET proposed_move_out_date=?, status='Approved', responded_at=NOW(), initiated_by='Owner'
                WHERE request_id=? AND owner_id=?")
                ->execute([$proposedDate, (int)$existing['request_id'], $ownerId]);
        } else {
            $pdo->prepare("INSERT INTO move_out_requests
                (tenancy_id, tenant_id, owner_id, proposed_move_out_date, status, initiated_by, responded_at)
                VALUES (?, ?, ?, ?, 'Approved', 'Owner', NOW())")
                ->execute([(int)$tenancy['tenancy_id'], (int)$tenancy['tenant_id'], $ownerId, $proposedDate]);
        }

        // Send a persistent, tenant-specific notice so the tenant is clearly
        // informed that the owner scheduled the move-out and of the exact date.
        $noticeText = 'Move-out notice from the building owner: your move-out from Apt ' . $tenancy['apartment_number'] . ' is scheduled for '
            . date('d M Y', strtotime($proposedDate))
            . '. Please plan accordingly. This notice was issued at least 15 days in advance.';
        $pdo->prepare("INSERT INTO notices
            (owner_id, message_content, published_at, target_type, apartment_id)
            VALUES (?, ?, NOW(), 'Apartment', ?)")
            ->execute([$ownerId, $noticeText, (int)$tenancy['apartment_id']]);

        $pdo->commit();
        setFlashMessage('success', 'Move-out scheduled for ' . date('d M Y', strtotime($proposedDate)) . '. The tenant has at least 15 days notice.');
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$currentRequest = getLatestMoveOutRequest($pdo, (int)$tenancy['tenancy_id']);
$minDate = (new DateTimeImmutable('today'))->modify('+15 days')->format('Y-m-d');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<div class="main-content">
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="page-header"><div><h1>Schedule Move-Out</h1><p>Owner-initiated move-outs must provide at least 15 days notice.</p></div></div>
    <div class="grid-2">
        <div class="card"><div class="card-header"><span>&#128100; Tenant</span></div><div class="card-body">
            <div class="detail-grid">
                <div class="detail-item"><span class="detail-label">Tenant</span><span class="detail-value"><?php echo htmlspecialchars($tenancy['tenant_name']); ?></span></div>
                <div class="detail-item"><span class="detail-label">Phone</span><span class="detail-value"><?php echo htmlspecialchars($tenancy['phone']); ?></span></div>
                <div class="detail-item"><span class="detail-label">Apartment</span><span class="detail-value">Apt <?php echo htmlspecialchars($tenancy['apartment_number']); ?> · Floor <?php echo (int)$tenancy['floor_level']; ?></span></div>
                <div class="detail-item"><span class="detail-label">Move-In</span><span class="detail-value"><?php echo date('d M Y', strtotime($tenancy['move_in_date'])); ?></span></div>
            </div>
        </div></div>
        <div class="card"><div class="card-header"><span>&#128682; Move-Out Date</span></div><div class="card-body">
            <?php if ($currentRequest): ?>
                <div class="alert alert-warning">Current schedule: <strong><?php echo date('d M Y', strtotime($currentRequest['proposed_move_out_date'])); ?></strong> (<?php echo htmlspecialchars(formatMoveOutStatus($currentRequest['status'], $currentRequest['initiated_by'])); ?>)</div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="tenant_id" value="<?php echo $tenantId; ?>">
                <div class="form-group"><label for="proposed_move_out_date">Move-Out Date <span class="required-star">*</span></label><input type="date" id="proposed_move_out_date" name="proposed_move_out_date" class="form-control" min="<?php echo htmlspecialchars($minDate); ?>" value="<?php echo htmlspecialchars($_POST['proposed_move_out_date'] ?? ($currentRequest['proposed_move_out_date'] ?? $minDate)); ?>" required></div>
                <div class="alert alert-info">The date must be at least 15 days from today. The tenancy stays active until the scheduled date passes.</div>
                <button class="btn btn-primary w-100" type="submit">Schedule Move-Out</button>
            </form>
        </div></div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
