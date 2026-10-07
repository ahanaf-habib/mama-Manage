<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid apartment ID.');
    header('Location: index.php');
    exit;
}

// Check if apartment exists
$ownerId = (int)getCurrentUserId();
$stmt = $pdo->prepare("SELECT apartment_number FROM apartments WHERE apartment_id = ? AND owner_id = ?");
$stmt->execute([$id, $ownerId]);
$apt = $stmt->fetch();
if (!$apt) {
    setFlashMessage('danger', 'Apartment not found.');
    header('Location: index.php');
    exit;
}

$aptNumber = $apt['apartment_number'];

// Dependency checks
// 1. Check tenancies
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tenancies WHERE apartment_id = ?");
$stmt->execute([$id]);
$tenancyCount = $stmt->fetchColumn();

// 2. Check complaints
$stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE apartment_id = ?");
$stmt->execute([$id]);
$complaintCount = $stmt->fetchColumn();

// 3. Check notices
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notices WHERE apartment_id = ?");
$stmt->execute([$id]);
$noticeCount = $stmt->fetchColumn();

if ($tenancyCount > 0 || $complaintCount > 0 || $noticeCount > 0) {
    $reasons = [];
    if ($tenancyCount > 0) $reasons[] = "$tenancyCount tenancy record(s)";
    if ($complaintCount > 0) $reasons[] = "$complaintCount complaint(s)";
    if ($noticeCount > 0) $reasons[] = "$noticeCount notice(s)";

    setFlashMessage('danger', "Cannot delete apartment '$aptNumber' because it is referenced by: " . implode(', ', $reasons) . ". You may mark its status as 'Under Maintenance' instead to keep historical integrity.");
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM apartments WHERE apartment_id = ? AND owner_id = ?");
    $stmt->execute([$id, $ownerId]);
    setFlashMessage('success', "Apartment '$aptNumber' was successfully deleted.");
} catch (PDOException $e) {
    setFlashMessage('danger', "Database error: unable to delete apartment due to constraint checks.");
}

header('Location: index.php');
exit;
