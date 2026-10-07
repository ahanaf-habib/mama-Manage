<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'SAMS'); ?> - Smart Apartment Management System</title>
    <link rel="stylesheet" href="/smart_apartment_management_system/assets/css/style.css">
</head>
<body>
    <header class="top-header">
        <div class="header-left">
            <button class="menu-toggle" onclick="toggleSidebar()">&#9776;</button>
            <a href="/smart_apartment_management_system/" class="header-brand">&#127963; SAMS</a>
        </div>
        <div class="header-right">
            <span class="header-user-info">
                <span class="header-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
                <span class="header-user-role badge <?php echo ($_SESSION['user_role'] ?? '') === 'owner' ? 'badge-primary' : 'badge-info'; ?>">
                    <?php echo ucfirst(htmlspecialchars($_SESSION['user_role'] ?? '')); ?>
                </span>
            </span>
            <a href="/smart_apartment_management_system/auth/logout.php" class="btn btn-sm btn-secondary">Logout</a>
        </div>
    </header>
