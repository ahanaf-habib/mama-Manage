<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'owner') {
        header('Location: /smart_apartment_management_system/owner/dashboard.php');
    } else {
        require_once '../config/database.php';
        require_once '../includes/tenancy.php';
        ensureMoveOutRequestsTable($pdo);
        syncTenancyLifecycle($pdo);
        $tenancyStmt = $pdo->prepare("SELECT tenancy_id FROM tenancies WHERE tenant_id = ? AND tenancy_status = 'Active' AND move_in_date <= CURDATE() AND (move_out_date IS NULL OR move_out_date >= CURDATE()) LIMIT 1");
        $tenancyStmt->execute([$_SESSION['user_id']]);
        header('Location: ' . ($tenancyStmt->fetchColumn() ? '/smart_apartment_management_system/tenant/dashboard.php' : '/smart_apartment_management_system/tenant/apartment.php'));
    }
    exit;
}

$error = '';
$registered = $_GET['registered'] ?? '';
$registrationMessage = in_array($registered, ['owner', 'tenant'], true) ? ucfirst($registered) . ' account created successfully. Please log in.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/database.php';
    require_once '../includes/tenancy.php';
    ensureMoveOutRequestsTable($pdo);
    syncTenancyLifecycle($pdo);
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields.';
    } elseif (!in_array($role, ['owner', 'tenant'])) {
        $error = 'Invalid role selected.';
    } else {
        if ($role === 'owner') {
            $stmt = $pdo->prepare("SELECT owner_id, owner_name, username, password FROM owners WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && $user['password'] === $password) {
                $_SESSION['user_id'] = $user['owner_id'];
                $_SESSION['user_role'] = 'owner';
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_name'] = $user['owner_name'];
                header('Location: /smart_apartment_management_system/owner/dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $stmt = $pdo->prepare("SELECT tenant_id, tenant_name, username, password FROM tenants WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && $user['password'] === $password) {
                $_SESSION['user_id'] = $user['tenant_id'];
                $_SESSION['user_role'] = 'tenant';
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_name'] = $user['tenant_name'];
                // Existing tenants with an active tenancy get the full resident dashboard.
                $tenancyStmt = $pdo->prepare("SELECT tenancy_id FROM tenancies WHERE tenant_id = ? AND tenancy_status = 'Active' AND move_in_date <= CURDATE() AND (move_out_date IS NULL OR move_out_date >= CURDATE()) LIMIT 1");
                $tenancyStmt->execute([$user['tenant_id']]);
                header('Location: ' . ($tenancyStmt->fetchColumn() ? '/smart_apartment_management_system/tenant/dashboard.php' : '/smart_apartment_management_system/tenant/apartment.php'));
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SAMS</title>
    <link rel="stylesheet" href="/smart_apartment_management_system/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">&#127963; SAMS</div>
            <p class="login-subtitle">Smart Apartment Management System</p>
            
            <?php if ($registrationMessage): ?><div class="alert alert-success"><?php echo htmlspecialchars($registrationMessage); ?></div><?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username <span class="required-star">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label for="password">Password <span class="required-star">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
                </div>
                <div class="form-group">
                    <label>Login As <span class="required-star">*</span></label>
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="role" value="owner" <?php echo ($_POST['role'] ?? '') === 'owner' ? 'checked' : ''; ?>>
                            <span class="role-label">&#128100; Owner</span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="tenant" <?php echo ($_POST['role'] ?? 'tenant') === 'tenant' ? 'checked' : ''; ?>>
                            <span class="role-label">&#127968; Tenant</span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
            </form>

            <div style="text-align:center; margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                <span style="color:var(--text-muted);">New to SAMS?</span>
                <a href="signup.php" style="font-weight:600; margin-left:6px;">Create an account</a>
            </div>
        </div>
    </div>
</body>
</html>
