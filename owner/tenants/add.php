<?php
$pageTitle = 'Add Tenant';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$error = '';
$tenant_name = '';
$phone = '';
$email = '';
$emergency_contact = '';
$id_reference = '';
$username = '';
$password = '';
$apartment_id = '';
$move_in_date = date('Y-m-d');
$move_out_date = '';
$tenancy_status = 'Active';

// Only apartments managed by the logged-in owner are available for assignment.
$ownerId = (int) getCurrentUserId();
$apartmentsStmt = $pdo->prepare("SELECT apartment_id, apartment_number, floor_level, monthly_rent, status FROM apartments WHERE owner_id = ? ORDER BY floor_level ASC, apartment_number ASC");
$apartmentsStmt->execute([$ownerId]);
$apartments = $apartmentsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_name = trim($_POST['tenant_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $id_reference = trim($_POST['id_reference'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $apartment_id = trim($_POST['apartment_id'] ?? '');
    $move_in_date = trim($_POST['move_in_date'] ?? '');
    $move_out_date = trim($_POST['move_out_date'] ?? '');
    $tenancy_status = trim($_POST['tenancy_status'] ?? 'Active');

    if ($tenant_name === '' || $phone === '' || $email === '' || $id_reference === '' || $username === '' || $password === '') {
        $error = 'Please fill in all required tenant fields marked with an asterisk (*).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!in_array($tenancy_status, ['Active', 'Ended'], true)) {
        $error = 'Invalid tenancy status selected.';
    } elseif ($apartment_id !== '' && $move_in_date === '') {
        $error = 'Please specify a move-in date when assigning an apartment.';
    } elseif ($tenancy_status === 'Ended' && $apartment_id !== '' && $move_out_date === '') {
        $error = 'Please specify a move-out date when creating an ended tenancy.';
    } elseif ($apartment_id !== '' && $tenancy_status === 'Active' && $move_out_date !== '') {
        $error = 'Active tenancies should not have a move-out date.';
    } else {
        $stmt = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM tenants WHERE email = ?) as email_exists,
            (SELECT COUNT(*) FROM tenants WHERE id_reference = ?) as id_exists,
            (SELECT COUNT(*) FROM tenants WHERE username = ?) as user_exists");
        $stmt->execute([$email, $id_reference, $username]);
        $check = $stmt->fetch();

        if ($check['email_exists'] > 0) {
            $error = 'A tenant with this email address is already registered.';
        } elseif ($check['id_exists'] > 0) {
            $error = 'A tenant with this NID / ID Reference is already registered.';
        } elseif ($check['user_exists'] > 0) {
            $error = 'This username is already taken. Please choose another.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO tenants (tenant_name, phone, email, emergency_contact, id_reference, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenant_name, $phone, $email, $emergency_contact ?: null, $id_reference, $username, $password]);
                $tenantId = (int)$pdo->lastInsertId();

                if ($apartment_id !== '') {
                    $stmt = $pdo->prepare("SELECT apartment_id, status FROM apartments WHERE apartment_id = ? AND owner_id = ? FOR UPDATE");
                    $stmt->execute([(int)$apartment_id, $ownerId]);
                    $apartment = $stmt->fetch();
                    if (!$apartment) {
                        throw new Exception('Selected apartment is not managed by this owner.');
                    }
                    if ($tenancy_status === 'Active' && $apartment['status'] === 'Occupied') {
                        throw new Exception('The selected apartment is already occupied.');
                    }
                    if ($tenancy_status === 'Active' && $apartment['status'] === 'Under Maintenance') {
                        throw new Exception('The selected apartment is under maintenance and cannot receive an active tenancy.');
                    }

                    $stmt = $pdo->prepare("INSERT INTO tenancies (tenant_id, apartment_id, move_in_date, move_out_date, tenancy_status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$tenantId, (int)$apartment_id, $move_in_date, $move_out_date !== '' ? $move_out_date : null, $tenancy_status]);

                    $status = $tenancy_status === 'Active' ? 'Occupied' : 'Available';
                    $stmt = $pdo->prepare("UPDATE apartments SET status = ? WHERE apartment_id = ? AND owner_id = ?");
                    $stmt->execute([$status, (int)$apartment_id, $ownerId]);
                }

                $pdo->commit();
                setFlashMessage('success', "Tenant '$tenant_name' registered successfully.");
                header('Location: index.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Register New Tenant</h1>
            <p>Create a tenant profile, portal account, and apartment assignment in one place.</p>
        </div>
        <div><a href="index.php" class="btn btn-secondary">&larr; Back to Tenants</a></div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card" style="max-width: 900px;">
        <div class="card-header"><span>Tenant Details</span></div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group"><label for="tenant_name">Full Name <span class="required-star">*</span></label><input type="text" id="tenant_name" name="tenant_name" class="form-control" placeholder="e.g. Arafat Hossain" value="<?php echo htmlspecialchars($tenant_name); ?>" required></div>
                    <div class="form-group"><label for="phone">Phone Number <span class="required-star">*</span></label><input type="text" id="phone" name="phone" class="form-control" placeholder="e.g. 01712001111" value="<?php echo htmlspecialchars($phone); ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="email">Email Address <span class="required-star">*</span></label><input type="email" id="email" name="email" class="form-control" placeholder="e.g. arafat@example.com" value="<?php echo htmlspecialchars($email); ?>" required></div>
                    <div class="form-group"><label for="emergency_contact">Emergency Contact</label><input type="text" id="emergency_contact" name="emergency_contact" class="form-control" placeholder="e.g. 01712002222" value="<?php echo htmlspecialchars($emergency_contact); ?>"></div>
                </div>
                <div class="form-group"><label for="id_reference">NID / ID Reference Number <span class="required-star">*</span></label><input type="text" id="id_reference" name="id_reference" class="form-control" placeholder="e.g. NID-100001" value="<?php echo htmlspecialchars($id_reference); ?>" required></div>
                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
                <div class="form-row">
                    <div class="form-group"><label for="username">Portal Username <span class="required-star">*</span></label><input type="text" id="username" name="username" class="form-control" placeholder="e.g. arafat" value="<?php echo htmlspecialchars($username); ?>" required></div>
                    <div class="form-group"><label for="password">Portal Password <span class="required-star">*</span></label><input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required></div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
                <div class="card-header" style="padding: 0 0 15px 0; border: 0;"><span>&#127970; Apartment Assignment</span></div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="apartment_id">Apartment</label>
                        <select id="apartment_id" name="apartment_id" class="form-control">
                            <option value="">No apartment assignment</option>
                            <?php foreach ($apartments as $a): ?>
                                <option value="<?php echo (int)$a['apartment_id']; ?>" <?php echo (string)$apartment_id === (string)$a['apartment_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['apartment_number']); ?> (Floor <?php echo (int)$a['floor_level']; ?>) - &#2547;<?php echo number_format($a['monthly_rent'], 2); ?>/mo [<?php echo htmlspecialchars($a['status']); ?>]</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="move_in_date">Move-In Date</label><input type="date" id="move_in_date" name="move_in_date" class="form-control" value="<?php echo htmlspecialchars($move_in_date); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="move_out_date">Move-Out Date</label><input type="date" id="move_out_date" name="move_out_date" class="form-control" value="<?php echo htmlspecialchars($move_out_date); ?>"></div>
                    <div class="form-group"><label for="tenancy_status">Tenancy Status</label><select id="tenancy_status" name="tenancy_status" class="form-control"><option value="Active" <?php echo $tenancy_status === 'Active' ? 'selected' : ''; ?>>Active (Occupied)</option><option value="Ended" <?php echo $tenancy_status === 'Ended' ? 'selected' : ''; ?>>Ended (Moved Out)</option></select></div>
                </div>

                <div style="display:flex;gap:10px;margin-top:24px;"><button type="submit" class="btn btn-primary">&#10004; Save Tenant &amp; Assignment</button><a href="index.php" class="btn btn-secondary">Cancel</a></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
