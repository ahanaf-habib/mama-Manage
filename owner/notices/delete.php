<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid notice ID specified.');
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM notices WHERE notice_id = ? AND owner_id = ?");
    $stmt->execute([$id, $ownerId]);
    setFlashMessage('success', "Notice #$id was successfully removed.");
} catch (PDOException $e) {
    setFlashMessage('danger', "Database error: unable to delete notice.");
}

header('Location: index.php');
exit;
