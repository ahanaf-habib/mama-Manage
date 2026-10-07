<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function getCurrentUserName() {
    return $_SESSION['user_name'] ?? '';
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function getFlashMessage($type = null) {
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $storedType = $_SESSION['flash_type'] ?? 'info';
    $message = $_SESSION['flash_message'];

    // When a specific type is requested, leave an unmatched flash message
    // untouched so a following call for the correct type can still read it.
    if ($type !== null) {
        if ($storedType !== $type) {
            return null;
        }

        unset($_SESSION['flash_type'], $_SESSION['flash_message']);
        return $message;
    }

    unset($_SESSION['flash_type'], $_SESSION['flash_message']);
    return [
        'type' => $storedType,
        'message' => $message
    ];
}

if (!isLoggedIn()) {
    header('Location: /smart_apartment_management_system/auth/login.php');
    exit;
}
