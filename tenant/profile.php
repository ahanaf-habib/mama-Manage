<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';

$tenant_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

if (!$tenant) {
    die("Tenant record not found.");
}

$flash = getFlashMessage();
$error = '';
$tenant_name = $tenant['tenant_name'];
$phone = $tenant['phone'];
$email = $tenant['email'];
$emergency_contact = $tenant['emergency_contact'];
$id_reference = $tenant['id_reference'];
$username = $tenant['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_name = trim($_POST['tenant_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $new_password = trim($_POST['password'] ?? '');

    if ($tenant_name === '' || $phone === '' || $email === '') {
        $error = 'Please fill in all required fields (Name, Phone, Email).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        // Unique check for email excluding current tenant
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE email = ? AND tenant_id != ?");
        $stmt->execute([$email, $tenant_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'This email address is already in use by another tenant account.';
        } else {
            if ($new_password !== '') {
                $stmt = $pdo->prepare("UPDATE tenants SET tenant_name = ?, phone = ?, email = ?, emergency_contact = ?, password = ? WHERE tenant_id = ?");
                $stmt->execute([$tenant_name, $phone, $email, $emergency_contact ?: null, $new_password, $tenant_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE tenants SET tenant_name = ?, phone = ?, email = ?, emergency_contact = ? WHERE tenant_id = ?");
                $stmt->execute([$tenant_name, $phone, $email, $emergency_contact ?: null, $tenant_id]);
            }

            $_SESSION['user_name'] = $tenant_name;
            setFlashMessage('success', 'Your profile information has been successfully updated.');
            header('Location: profile.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>My Resident Profile</h1>
            <p>Manage your contact details, emergency contacts, and account security.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Edit Profile Form -->
        <div class="card">
            <div class="card-header">
                <span>&#128100; Edit Contact Information</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="tenant_name">Full Name <span class="required-star">*</span></label>
                        <input type="text" id="tenant_name" name="tenant_name" class="form-control" value="<?php echo htmlspecialchars($tenant_name); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number <span class="required-star">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address <span class="required-star">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact Phone</label>
                        <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" placeholder="e.g. Family member phone" value="<?php echo htmlspecialchars($emergency_contact ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Change Account Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to retain current password">
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn btn-primary">&#10004; Save Profile Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Identity & Reference Overview -->
        <div class="card">
            <div class="card-header">
                <span>&#128203; Official Identification Record</span>
            </div>
            <div class="card-body">
                <div class="detail-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-item">
                        <span class="detail-label">Tenant ID</span>
                        <span class="detail-value">#<?php echo (int)$tenant['tenant_id']; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">NID / National Identity Reference</span>
                        <span class="detail-value"><code><?php echo htmlspecialchars($tenant['id_reference']); ?></code></span>
                        <small style="color: var(--text-muted); margin-top: 2px;">Official government identification linked by building administration.</small>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Portal Username</span>
                        <span class="detail-value"><?php echo htmlspecialchars($tenant['username']); ?></span>
                    </div>
                </div>

                <div style="margin-top: 24px; padding: 14px; background: #f8fafc; border-radius: 6px; border: 1px solid var(--border); font-size: 13px; color: var(--text-muted);">
                    &#128274; <strong>Security & Privacy Note:</strong> Your NID and Username are fixed identification keys managed by the property owner. To update your legal reference, please contact building administration.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
