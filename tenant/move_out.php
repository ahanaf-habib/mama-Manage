<?php
$pageTitle = 'Move-Out Request';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/tenancy.php';

ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

$tenantId = (int)$_SESSION['user_id'];
$currentTenancy = getCurrentActiveTenancy($pdo, $tenantId);
$scheduledTenancy = getScheduledTenancy($pdo, $tenantId);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentTenancy) {
    $proposedDate = trim($_POST['proposed_move_out_date'] ?? '');
    $minDate = (new DateTimeImmutable('today'))->modify('+15 days')->format('Y-m-d');

    try {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $proposedDate);
        if (!$date || $date->format('Y-m-d') !== $proposedDate) {
            throw new Exception('Please choose a valid move-out date.');
        }
        if ($proposedDate < $minDate) {
            throw new Exception('A move-out request must provide at least 15 days notice.');
        }

        $existing = getLatestMoveOutRequest($pdo, (int)$currentTenancy['tenancy_id']);
        if ($existing) {
            throw new Exception('A move-out request is already pending or scheduled for this tenancy.');
        }

        $stmt = $pdo->prepare("INSERT INTO move_out_requests
            (tenancy_id, tenant_id, owner_id, proposed_move_out_date, status, initiated_by)
            VALUES (?, ?, ?, ?, 'Pending', 'Tenant')");
        $stmt->execute([
            (int)$currentTenancy['tenancy_id'],
            $tenantId,
            (int)$currentTenancy['owner_id'],
            $proposedDate
        ]);

        setFlashMessage('success', 'Move-out request submitted successfully. You provided at least 15 days notice. You may now request another available apartment.');
        header('Location: move_out.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$success = getFlashMessage('success');
$currentMoveOutRequest = $currentTenancy ? getLatestMoveOutRequest($pdo, (int)$currentTenancy['tenancy_id']) : null;
$tenantMoveOutNotice = getLatestMoveOutNoticeForTenant($pdo, $tenantId);
$minDate = (new DateTimeImmutable('today'))->modify('+15 days')->format('Y-m-d');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="page-header">
        <div>
            <h1>Move-Out Request</h1>
            <p>Submit your move-out notice to the apartment owner.</p>
        </div>
    </div>

    <?php if (!$currentTenancy): ?>
        <div class="card"><div class="card-body"><div class="empty-state">
            <div class="empty-state-icon">&#128682;</div>
            <h3>No Active Tenancy</h3>
            <p>You do not currently have an active tenancy. You can browse and request available apartments.</p>
            <a href="apartment.php" class="btn btn-primary">View Available Apartments</a>
        </div></div></div>
    <?php else: ?>
        <?php if ($scheduledTenancy): ?><div class="alert alert-info">A new tenancy is already scheduled for Apt <?php echo htmlspecialchars($scheduledTenancy['apartment_number']); ?> starting <?php echo date('d M Y', strtotime($scheduledTenancy['move_in_date'])); ?>. No additional move-out request is needed right now.</div><?php endif; ?>
        <div class="grid-2">
            <div class="card">
                <div class="card-header"><span>&#127970; Current Tenancy</span><span class="badge badge-success">Active</span></div>
                <div class="card-body">
                    <div class="detail-grid">
                        <div class="detail-item"><span class="detail-label">Apartment</span><span class="detail-value">Apt <?php echo htmlspecialchars($currentTenancy['apartment_number']); ?></span></div>
                        <div class="detail-item"><span class="detail-label">Monthly Rent</span><span class="detail-value">&#2547; <?php echo number_format($currentTenancy['monthly_rent'], 2); ?></span></div>
                        <div class="detail-item"><span class="detail-label">Move-In Date</span><span class="detail-value"><?php echo date('d M Y', strtotime($currentTenancy['move_in_date'])); ?></span></div>
                        <div class="detail-item"><span class="detail-label">Current Move-Out Date</span><span class="detail-value"><?php echo $currentTenancy['move_out_date'] ? date('d M Y', strtotime($currentTenancy['move_out_date'])) : 'Not scheduled'; ?></span></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span>&#128196; Notice</span></div>
                <div class="card-body">
                    <?php if ($scheduledTenancy && !$currentMoveOutRequest && !$tenantMoveOutNotice): ?>
                        <div class="alert alert-info"><strong>Next tenancy scheduled:</strong> Apt <?php echo htmlspecialchars($scheduledTenancy['apartment_number']); ?> begins on <?php echo date('d M Y', strtotime($scheduledTenancy['move_in_date'])); ?>.</div>
                    <?php elseif ($currentMoveOutRequest || $tenantMoveOutNotice): ?>
                        <?php $displayMoveOut = $currentMoveOutRequest ?: $tenantMoveOutNotice; ?>
                        <div class="alert <?php echo $displayMoveOut['status'] === 'Pending' ? 'alert-warning' : 'alert-success'; ?>">
                            <?php if ($displayMoveOut['initiated_by'] === 'Owner'): ?>
                                <strong>Move-out scheduled by owner</strong><br>
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars(formatMoveOutStatus($displayMoveOut['status'], $displayMoveOut['initiated_by'])); ?></strong><br>
                            <?php endif; ?>
                            Move-out date: <?php echo date('d M Y', strtotime($displayMoveOut['proposed_move_out_date'])); ?>
                        </div>
                        <?php if ($displayMoveOut['initiated_by'] === 'Owner'): ?>
                            <p style="color:var(--text-muted);">The owner scheduled this move-out. This notice remains visible to you while the scheduled date is still current. You may apply for another available apartment.</p>
                        <?php elseif ($displayMoveOut['status'] === 'Pending'): ?>
                            <p style="color:var(--text-muted);">The owner must approve your request. Your 15-day notice requirement is already satisfied.</p>
                        <?php else: ?>
                            <p style="color:var(--text-muted);">You may now apply for another available apartment. The new tenancy cannot overlap the current tenancy.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color:var(--text-muted);">You must give the owner at least 15 days notice before moving out.</p>
                        <form method="POST" style="margin-top:20px;">
                            <div class="form-group">
                                <label for="proposed_move_out_date">Requested Move-Out Date <span class="required-star">*</span></label>
                                <input type="date" id="proposed_move_out_date" name="proposed_move_out_date" class="form-control" min="<?php echo htmlspecialchars($minDate); ?>" value="<?php echo htmlspecialchars($_POST['proposed_move_out_date'] ?? $minDate); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Submit Move-Out Request</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
