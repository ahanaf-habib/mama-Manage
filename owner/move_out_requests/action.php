<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/tenancy.php';

ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

$ownerId = (int)$_SESSION['user_id'];
$requestId = (int)($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($requestId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    setFlashMessage('error', 'Invalid move-out request action.');
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT mor.*, tn.apartment_id, tn.tenancy_status
        FROM move_out_requests mor
        JOIN tenancies tn ON tn.tenancy_id = mor.tenancy_id
        JOIN apartments a ON a.apartment_id = tn.apartment_id
        WHERE mor.request_id = ?
          AND mor.owner_id = ?
          AND mor.status = 'Pending'
          AND a.owner_id = ?
        FOR UPDATE");
    $stmt->execute([$requestId, $ownerId, $ownerId]);
    $request = $stmt->fetch();
    if (!$request) {
        throw new Exception('This move-out request is no longer pending or does not belong to you.');
    }
    if ($request['tenancy_status'] !== 'Active') {
        throw new Exception('The tenancy is no longer active.');
    }

    if ($action === 'reject') {
        $pdo->prepare("UPDATE move_out_requests SET status='Rejected', responded_at=NOW() WHERE request_id=? AND owner_id=?")
            ->execute([$requestId, $ownerId]);
        $rejectNotice = 'Your move-out request for Apt ' . $request['apartment_id'] . ' was rejected by the owner. Please contact building management for more information.';
        $pdo->prepare("INSERT INTO notices (owner_id, message_content, published_at, target_type, apartment_id) VALUES (?, ?, NOW(), 'Apartment', ?)")
            ->execute([$ownerId, $rejectNotice, (int)$request['apartment_id']]);
        $pdo->commit();
        setFlashMessage('success', 'Move-out request rejected. The tenant was notified.');
        header('Location: index.php');
        exit;
    }

    $minDate = (new DateTimeImmutable('today'))->modify('+15 days')->format('Y-m-d');
    if ($request['proposed_move_out_date'] < $minDate) {
        throw new Exception('The proposed move-out date is now less than 15 days away. The tenant must provide a fresh notice date at least 15 days from today.');
    }

    $scheduledStmt = $pdo->prepare("SELECT move_in_date FROM tenancies
        WHERE tenant_id=? AND tenancy_status='Active' AND move_in_date > CURDATE() AND tenancy_id<>?
        ORDER BY move_in_date ASC LIMIT 1 FOR UPDATE");
    $scheduledStmt->execute([(int)$request['tenant_id'], (int)$request['tenancy_id']]);
    $scheduledDate = $scheduledStmt->fetchColumn();
    if ($scheduledDate && $request['proposed_move_out_date'] >= $scheduledDate) {
        throw new Exception("This move-out date would overlap the tenant's next scheduled tenancy.");
    }

    $pdo->prepare("UPDATE tenancies
        SET move_out_date = ?
        WHERE tenancy_id = ? AND tenancy_status = 'Active'")
        ->execute([$request['proposed_move_out_date'], (int)$request['tenancy_id']]);

    $pdo->prepare("UPDATE move_out_requests
        SET status='Approved', responded_at=NOW()
        WHERE request_id=? AND owner_id=?")
        ->execute([$requestId, $ownerId]);

    $approvalNotice = 'Your move-out request for Apt ' . $request['apartment_id'] . ' was approved. Your tenancy will end on '
        . date('d M Y', strtotime($request['proposed_move_out_date'])) . '.';
    $pdo->prepare("INSERT INTO notices (owner_id, message_content, published_at, target_type, apartment_id) VALUES (?, ?, NOW(), 'Apartment', ?)")
        ->execute([$ownerId, $approvalNotice, (int)$request['apartment_id']]);

    $pdo->commit();
    setFlashMessage('success', 'Move-out approved. The tenancy will end on ' . date('d M Y', strtotime($request['proposed_move_out_date'])) . '. The tenant was notified.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlashMessage('error', $e->getMessage());
}

header('Location: index.php');
exit;
