<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/apartment_requests.php';

ensureApartmentRequestsTable($pdo);
$ownerId=(int)$_SESSION['user_id']; $requestId=(int)($_POST['request_id']??0); $action=$_POST['action']??'';
if($requestId<=0 || !in_array($action,['approve','reject'],true)){setFlashMessage('error','Invalid apartment request action.');header('Location: index.php');exit;}
try{
$pdo->beginTransaction();
$stmt=$pdo->prepare("SELECT ar.*,a.status AS apartment_status FROM apartment_requests ar JOIN apartments a ON ar.apartment_id=a.apartment_id WHERE ar.request_id=? AND ar.owner_id=? AND ar.status='Pending' FOR UPDATE"); $stmt->execute([$requestId,$ownerId]); $request=$stmt->fetch(); if(!$request) throw new Exception('This request is no longer pending or does not belong to you.');
if($action==='reject'){ $pdo->prepare("UPDATE apartment_requests SET status='Rejected',responded_at=NOW() WHERE request_id=? AND owner_id=?")->execute([$requestId,$ownerId]); $pdo->commit(); setFlashMessage('success','Apartment request rejected.'); header('Location: index.php'); exit; }
if($request['apartment_status']!=='Available') throw new Exception('This apartment is no longer available.');
$active=$pdo->prepare("SELECT tn.*
    FROM tenancies tn
    WHERE tn.tenant_id=?
      AND tn.tenancy_status='Active'
      AND tn.move_in_date <= CURDATE()
      AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
    ORDER BY tn.move_in_date DESC, tn.tenancy_id DESC
    LIMIT 1
    FOR UPDATE");
$active->execute([(int)$request['tenant_id']]);
$activeTenancy=$active->fetch();

$existingScheduled=$pdo->prepare("SELECT tenancy_id, move_in_date, apartment_id
    FROM tenancies
    WHERE tenant_id=? AND tenancy_status='Active' AND move_in_date > CURDATE()
    LIMIT 1 FOR UPDATE");
$existingScheduled->execute([(int)$request['tenant_id']]);
$scheduledTenancy=$existingScheduled->fetch();
if($scheduledTenancy) throw new Exception('This tenant already has another apartment scheduled. The tenant cannot have multiple upcoming apartment assignments.');

$moveInDate = (new DateTimeImmutable('today'))->format('Y-m-d');
if ($activeTenancy) {
    $moveOutStmt=$pdo->prepare("SELECT proposed_move_out_date, status
        FROM move_out_requests
        WHERE tenancy_id=? AND status='Approved'
        ORDER BY request_id DESC LIMIT 1");
    $moveOutStmt->execute([(int)$activeTenancy['tenancy_id']]);
    $approvedMoveOut=$moveOutStmt->fetch();
    if (!$approvedMoveOut) {
        throw new Exception('This tenant still has an active tenancy. The current move-out must be approved before a new apartment request can be accepted.');
    }
    $moveInDate=(new DateTimeImmutable($approvedMoveOut['proposed_move_out_date']))->modify('+1 day')->format('Y-m-d');

    // A tenant cannot have overlapping current or future scheduled tenancies.
    $futureConflict=$pdo->prepare("SELECT tenancy_id
        FROM tenancies
        WHERE tenant_id=? AND tenancy_status='Active' AND tenancy_id<>?
          AND move_out_date IS NULL
        LIMIT 1");
    $futureConflict->execute([(int)$request['tenant_id'],(int)$activeTenancy['tenancy_id']]);
    if($futureConflict->fetchColumn()) throw new Exception('The tenant already has another tenancy that has no scheduled end date.');
}

$pdo->prepare("INSERT INTO tenancies (tenant_id,apartment_id,move_in_date,move_out_date,tenancy_status) VALUES (?, ?, ?, NULL, 'Active')")
    ->execute([(int)$request['tenant_id'],(int)$request['apartment_id'],$moveInDate]);
$upd=$pdo->prepare("UPDATE apartments SET status='Occupied' WHERE apartment_id=? AND owner_id=? AND status='Available'");
$upd->execute([(int)$request['apartment_id'],$ownerId]);
if($upd->rowCount()!==1) throw new Exception('The apartment could not be marked as occupied.');

// Once one apartment is accepted, every other pending request from the same tenant
// and every other pending request for the accepted apartment becomes invalid.
$pdo->prepare("UPDATE apartment_requests
    SET status='Rejected', responded_at=NOW()
    WHERE status='Pending'
      AND request_id<>?
      AND (tenant_id=? OR apartment_id=? )")
    ->execute([$requestId,(int)$request['tenant_id'],(int)$request['apartment_id']]);

$pdo->prepare("UPDATE apartment_requests SET status='Approved',responded_at=NOW() WHERE request_id=? AND owner_id=?")->execute([$requestId,$ownerId]);
$pdo->commit(); setFlashMessage('success', $moveInDate === (new DateTimeImmutable('today'))->format('Y-m-d') ? 'Request approved. The tenant has been added and the tenancy is now active.' : 'Request approved. The new tenancy has been scheduled to begin after the current tenancy ends.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();setFlashMessage('error',$e->getMessage());}
header('Location: index.php');exit;
