<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid staff ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT staff_name FROM staff WHERE staff_id = ?");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    setFlashMessage('danger', 'Staff record not found.');
    header('Location: index.php');
    exit;
}

$staffName = $staff['staff_name'];

// Check assigned maintenance requests
$stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenance_requests WHERE staff_id = ?");
$stmt->execute([$id]);
$assignedTasks = $stmt->fetchColumn();

if ($assignedTasks > 0) {
    setFlashMessage('danger', "Cannot delete staff member '$staffName' because they have $assignedTasks maintenance request record(s) linked to their account. Deleting them would compromise maintenance history integrity.");
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM staff WHERE staff_id = ?");
    $stmt->execute([$id]);
    setFlashMessage('success', "Staff member '$staffName' was successfully removed.");
} catch (PDOException $e) {
    setFlashMessage('danger', "Database error: unable to delete staff member.");
}

header('Location: index.php');
exit;
