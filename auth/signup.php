<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'owner') {
        header('Location: /smart_apartment_management_system/owner/dashboard.php');
    } else {
        header('Location: /smart_apartment_management_system/tenant/apartment.php');
    }
    exit;
}

require_once '../config/database.php';

$error = '';
$success = '';
$role = $_POST['role'] ?? 'tenant';
$role = in_array($role, ['owner', 'tenant'], true) ? $role : 'tenant';

$owner_name = trim($_POST['owner_name'] ?? '');
$owner_phone = trim($_POST['owner_phone'] ?? '');
$owner_email = trim($_POST['owner_email'] ?? '');
$owner_username = trim($_POST['owner_username'] ?? '');

$tenant_name = trim($_POST['tenant_name'] ?? '');
$tenant_phone = trim($_POST['tenant_phone'] ?? '');
$tenant_email = trim($_POST['tenant_email'] ?? '');
$emergency_contact = trim($_POST['emergency_contact'] ?? '');
$id_reference = trim($_POST['id_reference'] ?? '');
$tenant_username = trim($_POST['tenant_username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($password === '' || $confirm_password === '') {
        $error = 'Please enter and confirm your password.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters long.';
    } elseif ($role === 'owner') {
        if ($owner_name === '' || $owner_phone === '' || $owner_email === '' || $owner_username === '') {
            $error = 'Please fill in all required owner information.';
        } elseif (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid owner email address.';
        } else {
            $stmt = $pdo->prepare("SELECT
                (SELECT COUNT(*) FROM owners WHERE email = ?) AS email_exists,
                (SELECT COUNT(*) FROM owners WHERE username = ?) AS username_exists");
            $stmt->execute([$owner_email, $owner_username]);
            $check = $stmt->fetch();

            if ((int)$check['email_exists'] > 0) {
                $error = 'This owner email is already registered.';
            } elseif ((int)$check['username_exists'] > 0) {
                $error = 'This owner username is already taken.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO owners (owner_name, phone, email, username, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$owner_name, $owner_phone, $owner_email, $owner_username, $password]);
                header('Location: login.php?registered=owner');
                exit;
            }
        }
    } else {
        if ($tenant_name === '' || $tenant_phone === '' || $tenant_email === '' || $id_reference === '' || $tenant_username === '') {
            $error = 'Please fill in all required tenant information.';
        } elseif (!filter_var($tenant_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid tenant email address.';
        } else {
            $stmt = $pdo->prepare("SELECT
                (SELECT COUNT(*) FROM tenants WHERE email = ?) AS email_exists,
                (SELECT COUNT(*) FROM tenants WHERE id_reference = ?) AS id_exists,
                (SELECT COUNT(*) FROM tenants WHERE username = ?) AS username_exists");
            $stmt->execute([$tenant_email, $id_reference, $tenant_username]);
            $check = $stmt->fetch();

            if ((int)$check['email_exists'] > 0) {
                $error = 'This tenant email is already registered.';
            } elseif ((int)$check['id_exists'] > 0) {
                $error = 'This ID/NID reference is already registered.';
            } elseif ((int)$check['username_exists'] > 0) {
                $error = 'This tenant username is already taken.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tenants (tenant_name, phone, email, emergency_contact, id_reference, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_name, $tenant_phone, $tenant_email, $emergency_contact !== '' ? $emergency_contact : null, $id_reference, $tenant_username, $password]);
                header('Location: login.php?registered=tenant');
                exit;
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
    <title>Sign Up - SAMS</title>
    <link rel="stylesheet" href="/smart_apartment_management_system/assets/css/style.css">
</head>
<body>
<div class="login-container">
    <div class="login-card" style="max-width:760px;">
        <div class="login-logo">&#127963; SAMS</div>
        <p class="login-subtitle">Create your Smart Apartment Management System account</p>

        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Account Type <span class="required-star">*</span></label>
                <div class="role-selector">
                    <label class="role-option">
                        <input type="radio" name="role" value="owner" <?php echo $role === 'owner' ? 'checked' : ''; ?> onchange="toggleSignupFields()">
                        <span class="role-label">&#128100; Owner</span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="tenant" <?php echo $role === 'tenant' ? 'checked' : ''; ?> onchange="toggleSignupFields()">
                        <span class="role-label">&#127968; Tenant</span>
                    </label>
                </div>
            </div>

            <div id="ownerFields" style="display:<?php echo $role === 'owner' ? 'block' : 'none'; ?>;">
                <h3 style="margin:18px 0 12px;">Owner Information</h3>
                <div class="form-row">
                    <div class="form-group"><label>Full Name <span class="required-star">*</span></label><input type="text" name="owner_name" class="form-control" value="<?php echo htmlspecialchars($owner_name); ?>"></div>
                    <div class="form-group"><label>Phone <span class="required-star">*</span></label><input type="text" name="owner_phone" class="form-control" value="<?php echo htmlspecialchars($owner_phone); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email <span class="required-star">*</span></label><input type="email" name="owner_email" class="form-control" value="<?php echo htmlspecialchars($owner_email); ?>"></div>
                    <div class="form-group"><label>Username <span class="required-star">*</span></label><input type="text" name="owner_username" class="form-control" value="<?php echo htmlspecialchars($owner_username); ?>"></div>
                </div>
            </div>

            <div id="tenantFields" style="display:<?php echo $role === 'tenant' ? 'block' : 'none'; ?>;">
                <h3 style="margin:18px 0 12px;">Tenant Information</h3>
                <div class="form-row">
                    <div class="form-group"><label>Full Name <span class="required-star">*</span></label><input type="text" name="tenant_name" class="form-control" value="<?php echo htmlspecialchars($tenant_name); ?>"></div>
                    <div class="form-group"><label>Phone <span class="required-star">*</span></label><input type="text" name="tenant_phone" class="form-control" value="<?php echo htmlspecialchars($tenant_phone); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Email <span class="required-star">*</span></label><input type="email" name="tenant_email" class="form-control" value="<?php echo htmlspecialchars($tenant_email); ?>"></div>
                    <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($emergency_contact); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>NID / ID Reference <span class="required-star">*</span></label><input type="text" name="id_reference" class="form-control" value="<?php echo htmlspecialchars($id_reference); ?>"></div>
                    <div class="form-group"><label>Username <span class="required-star">*</span></label><input type="text" name="tenant_username" class="form-control" value="<?php echo htmlspecialchars($tenant_username); ?>"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group"><label>Password <span class="required-star">*</span></label><input type="password" name="password" class="form-control" required placeholder="Create a password"></div>
                <div class="form-group"><label>Confirm Password <span class="required-star">*</span></label><input type="password" name="confirm_password" class="form-control" required placeholder="Repeat your password"></div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100">Create Account</button>
        </form>

        <div style="text-align:center; margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
            <span style="color:var(--text-muted);">Already have an account?</span>
            <a href="login.php" style="font-weight:600; margin-left:6px;">Back to Login</a>
        </div>
    </div>
</div>
<script>
function toggleSignupFields() {
    const role = document.querySelector('input[name="role"]:checked')?.value;
    document.getElementById('ownerFields').style.display = role === 'owner' ? 'block' : 'none';
    document.getElementById('tenantFields').style.display = role === 'tenant' ? 'block' : 'none';
}
toggleSignupFields();
</script>
</body>
</html>
