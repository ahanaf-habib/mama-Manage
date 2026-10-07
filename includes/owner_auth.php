<?php
require_once __DIR__ . '/auth.php';
if (isset($pdo) && $pdo instanceof PDO) {
    require_once __DIR__ . '/tenancy.php';
    ensureMoveOutRequestsTable($pdo);
    syncTenancyLifecycle($pdo);
}
if (getCurrentUserRole() !== 'owner') {
    header('Location: /smart_apartment_management_system/auth/login.php');
    exit;
}
