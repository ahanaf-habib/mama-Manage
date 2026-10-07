<?php
$pageTitle = 'Edit Tenant';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/tenancy.php';
ensureMoveOutRequestsTable($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ownerId = (int)getCurrentUserId();
if (!$id) { setFlashMessage('danger', 'Invalid tenant ID specified.'); header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT t.* FROM tenants t
    WHERE t.tenant_id = ?
      AND EXISTS (SELECT 1 FROM tenancies tx JOIN apartments ax ON tx.apartment_id = ax.apartment_id WHERE tx.tenant_id = t.tenant_id AND ax.owner_id = ?)");
$stmt->execute([$id, $ownerId]);
$tenant = $stmt->fetch();
if (!$tenant) { setFlashMessage('danger', 'Tenant record not found.'); header('Location: index.php'); exit; }

$tenancyStmt = $pdo->prepare("SELECT tn.*, a.apartment_number, a.floor_level, a.monthly_rent, a.status AS apartment_status
    FROM tenancies tn JOIN apartments a ON a.apartment_id = tn.apartment_id
    WHERE tn.tenant_id = ? AND a.owner_id = ?
    ORDER BY (tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())) DESC,
             (tn.tenancy_status = 'Active') DESC, tn.move_in_date DESC, tn.tenancy_id DESC
    LIMIT 1");
$tenancyStmt->execute([$id, $ownerId]);
$currentTenancy = $tenancyStmt->fetch();

$apartmentsStmt = $pdo->prepare("SELECT apartment_id, apartment_number, floor_level, monthly_rent, status FROM apartments WHERE owner_id = ? ORDER BY floor_level ASC, apartment_number ASC");
$apartmentsStmt->execute([$ownerId]);
$apartments = $apartmentsStmt->fetchAll();

$error = '';
$tenant_name = $tenant['tenant_name'];
$phone = $tenant['phone'];
$email = $tenant['email'];
$emergency_contact = $tenant['emergency_contact'];
$id_reference = $tenant['id_reference'];
$username = $tenant['username'];
$apartment_id = $currentTenancy['apartment_id'] ?? '';
$move_in_date = $currentTenancy['move_in_date'] ?? '';
$move_out_date = $currentTenancy['move_out_date'] ?? '';
$tenancy_status = $currentTenancy['tenancy_status'] ?? 'Active';
$manage_tenancy = $currentTenancy ? '1' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_name = trim($_POST['tenant_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $id_reference = trim($_POST['id_reference'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $new_password = trim($_POST['password'] ?? '');
    $apartment_id = trim($_POST['apartment_id'] ?? '');
    $move_in_date = trim($_POST['move_in_date'] ?? '');
    $move_out_date = trim($_POST['move_out_date'] ?? '');
    $tenancy_status = trim($_POST['tenancy_status'] ?? 'Active');
    $manage_tenancy = isset($_POST['manage_tenancy']) ? '1' : '';

    if ($tenant_name === '' || $phone === '' || $email === '' || $id_reference === '' || $username === '') {
        $error = 'Please fill in all required tenant fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!in_array($tenancy_status, ['Active', 'Ended'], true)) {
        $error = 'Invalid tenancy status selected.';
    } elseif ($manage_tenancy && $apartment_id !== '' && $move_in_date === '') {
        $error = 'Please specify a move-in date when managing an apartment assignment.';
    } elseif ($manage_tenancy && $apartment_id === '' && $currentTenancy && $currentTenancy['tenancy_status'] === 'Active' && $tenancy_status === 'Active') {
        $error = 'Select an apartment or mark the current tenancy as Ended.';
    } elseif ($manage_tenancy && $tenancy_status === 'Ended' && $apartment_id !== '' && $move_out_date === '') {
        $error = 'Please specify a move-out date when ending a tenancy.';
    } elseif ($manage_tenancy && $currentTenancy && $currentTenancy['tenancy_status'] === 'Active' && $tenancy_status === 'Ended') {
        $error = 'Use the Schedule Move-Out action. Owner-initiated tenancy endings require at least 15 days notice and cannot be ended immediately from Edit Tenant.';
    } elseif ($manage_tenancy && $currentTenancy && $currentTenancy['tenancy_status'] === 'Active' && $move_out_date !== ($currentTenancy['move_out_date'] ?? '')) {
        $error = 'Use the Schedule Move-Out action to change the move-out date. The 15-day notice rule must be enforced there.';
    } else {
        $stmt = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM tenants WHERE email = ? AND tenant_id != ?) as email_exists,
            (SELECT COUNT(*) FROM tenants WHERE id_reference = ? AND tenant_id != ?) as id_exists,
            (SELECT COUNT(*) FROM tenants WHERE username = ? AND tenant_id != ?) as user_exists");
        $stmt->execute([$email, $id, $id_reference, $id, $username, $id]);
        $check = $stmt->fetch();

        if ($check['email_exists'] > 0) $error = 'Another tenant is already using this email address.';
        elseif ($check['id_exists'] > 0) $error = 'Another tenant has registered with this NID / ID Reference.';
        elseif ($check['user_exists'] > 0) $error = 'This username is already taken.';
        else {
            try {
                $pdo->beginTransaction();

                if ($new_password !== '') {
                    $stmt = $pdo->prepare("UPDATE tenants SET tenant_name = ?, phone = ?, email = ?, emergency_contact = ?, id_reference = ?, username = ?, password = ? WHERE tenant_id = ?");
                    $stmt->execute([$tenant_name, $phone, $email, $emergency_contact ?: null, $id_reference, $username, $new_password, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE tenants SET tenant_name = ?, phone = ?, email = ?, emergency_contact = ?, id_reference = ?, username = ? WHERE tenant_id = ?");
                    $stmt->execute([$tenant_name, $phone, $email, $emergency_contact ?: null, $id_reference, $username, $id]);
                }

                if ($manage_tenancy) {
                    $active = $pdo->prepare("SELECT tn.* FROM tenancies tn JOIN apartments a ON a.apartment_id = tn.apartment_id WHERE tn.tenant_id = ? AND a.owner_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE()) ORDER BY tn.tenancy_id DESC LIMIT 1 FOR UPDATE");
                    $active->execute([$id, $ownerId]);
                    $activeTenancy = $active->fetch();

                    if ($apartment_id !== '') {
                        $aptStmt = $pdo->prepare("SELECT apartment_id, status FROM apartments WHERE apartment_id = ? AND owner_id = ? FOR UPDATE");
                        $aptStmt->execute([(int)$apartment_id, $ownerId]);
                        $apt = $aptStmt->fetch();
                        if (!$apt) throw new Exception('Selected apartment is not managed by this owner.');
                        if ($tenancy_status === 'Active') {
                            $occupancy = $pdo->prepare("SELECT COUNT(*) FROM tenancies WHERE apartment_id = ? AND tenancy_status = 'Active' AND tenancy_id != ?");
                            $occupancy->execute([(int)$apartment_id, $activeTenancy['tenancy_id'] ?? 0]);
                            if ((int)$occupancy->fetchColumn() > 0) throw new Exception('The selected apartment already has another active tenancy.');
                            if ($apt['status'] === 'Under Maintenance') throw new Exception('The selected apartment is under maintenance.');
                        }

                        if ($activeTenancy) {
                            if ((int)$activeTenancy['apartment_id'] !== (int)$apartment_id) {
                                throw new Exception('The current tenant cannot be moved to another apartment through Edit Tenant. Use the move-out workflow with the required 15-day notice.');
                            }
                            $u = $pdo->prepare("UPDATE tenancies SET apartment_id = ?, move_in_date = ?, move_out_date = ?, tenancy_status = ? WHERE tenancy_id = ?");
                            $preservedMoveOut = $tenancy_status === 'Ended' ? $move_out_date : ($activeTenancy['move_out_date'] ?? null);
                            $u->execute([(int)$apartment_id, $move_in_date, $preservedMoveOut, $tenancy_status, (int)$activeTenancy['tenancy_id']]);
                            $newStatus = $tenancy_status === 'Active' ? 'Occupied' : 'Available';
                            $pdo->prepare("UPDATE apartments SET status = ? WHERE apartment_id = ? AND owner_id = ?")->execute([$newStatus, (int)$apartment_id, $ownerId]);
                        } else {
                            $latest = $pdo->prepare("SELECT tn.tenancy_id FROM tenancies tn JOIN apartments a ON a.apartment_id = tn.apartment_id WHERE tn.tenant_id = ? AND a.owner_id = ? ORDER BY tn.tenancy_id DESC LIMIT 1 FOR UPDATE");
                            $latest->execute([$id, $ownerId]);
                            $latestId = (int)($latest->fetchColumn() ?: 0);
                            if ($latestId > 0 && $tenancy_status === 'Ended') {
                                $u = $pdo->prepare("UPDATE tenancies SET apartment_id = ?, move_in_date = ?, move_out_date = ?, tenancy_status = ? WHERE tenancy_id = ?");
                                $u->execute([(int)$apartment_id, $move_in_date, $move_out_date, 'Ended', $latestId]);
                            } elseif ($latestId > 0 && $tenancy_status === 'Active') {
                                $u = $pdo->prepare("UPDATE tenancies SET apartment_id = ?, move_in_date = ?, move_out_date = NULL, tenancy_status = 'Active' WHERE tenancy_id = ?");
                                $u->execute([(int)$apartment_id, $move_in_date, $latestId]);
                            } else {
                                $u = $pdo->prepare("INSERT INTO tenancies (tenant_id, apartment_id, move_in_date, move_out_date, tenancy_status) VALUES (?, ?, ?, ?, ?)");
                                $u->execute([$id, (int)$apartment_id, $move_in_date, $tenancy_status === 'Ended' ? $move_out_date : null, $tenancy_status]);
                            }
                            $newStatus = $tenancy_status === 'Active' ? 'Occupied' : 'Available';
                            $pdo->prepare("UPDATE apartments SET status = ? WHERE apartment_id = ? AND owner_id = ?")->execute([$newStatus, (int)$apartment_id, $ownerId]);
                        }
                    } elseif ($activeTenancy && $tenancy_status === 'Ended') {
                        if ($move_out_date === '') throw new Exception('Please specify a move-out date when ending the current tenancy.');
                        $pdo->prepare("UPDATE tenancies SET move_out_date = ?, tenancy_status = 'Ended' WHERE tenancy_id = ?")->execute([$move_out_date, (int)$activeTenancy['tenancy_id']]);
                        $pdo->prepare("UPDATE apartments SET status = 'Available' WHERE apartment_id = ? AND owner_id = ?")->execute([(int)$activeTenancy['apartment_id'], $ownerId]);
                    }
                }

                $pdo->commit();
                setFlashMessage('success', "Tenant '$tenant_name' profile was successfully updated.");
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
        <div><h1>Edit Tenant: <?php echo htmlspecialchars($tenant['tenant_name']); ?></h1><p>Update tenant details and manage the apartment assignment from the same page.</p></div>
        <div><a href="index.php" class="btn btn-secondary">&larr; Back to Tenants</a></div>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card" style="max-width: 900px;">
        <div class="card-header"><span>Tenant Details</span></div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group"><label for="tenant_name">Full Name <span class="required-star">*</span></label><input type="text" id="tenant_name" name="tenant_name" class="form-control" value="<?php echo htmlspecialchars($tenant_name); ?>" required></div>
                    <div class="form-group"><label for="phone">Phone Number <span class="required-star">*</span></label><input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="email">Email Address <span class="required-star">*</span></label><input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required></div>
                    <div class="form-group"><label for="emergency_contact">Emergency Contact</label><input type="text" id="emergency_contact" name="emergency_contact" class="form-control" value="<?php echo htmlspecialchars($emergency_contact ?? ''); ?>"></div>
                </div>
                <div class="form-group"><label for="id_reference">NID / ID Reference Number <span class="required-star">*</span></label><input type="text" id="id_reference" name="id_reference" class="form-control" value="<?php echo htmlspecialchars($id_reference); ?>" required></div>
                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
                <div class="form-row">
                    <div class="form-group"><label for="username">Portal Username <span class="required-star">*</span></label><input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required></div>
                    <div class="form-group"><label for="password">New Password</label><input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to keep current password"></div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">
                <div class="card-header" style="padding: 0 0 15px 0; border: 0;"><span>&#127970; Apartment &amp; Tenancy</span><?php if ($currentTenancy): ?><span class="badge <?php echo $currentTenancy['tenancy_status'] === 'Active' ? 'badge-success' : 'badge-secondary'; ?>"><?php echo htmlspecialchars($currentTenancy['tenancy_status']); ?></span><?php endif; ?></div>
                <div class="form-group"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="manage_tenancy" value="1" <?php echo $manage_tenancy ? 'checked' : ''; ?> style="width:auto;"> Manage apartment and tenancy details with this save</label></div>
                <div class="form-row">
                    <div class="form-group"><label for="apartment_id">Apartment</label><select id="apartment_id" name="apartment_id" class="form-control"><option value="">No apartment assignment</option><?php foreach ($apartments as $a): ?><option value="<?php echo (int)$a['apartment_id']; ?>" <?php echo (string)$apartment_id === (string)$a['apartment_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['apartment_number']); ?> (Floor <?php echo (int)$a['floor_level']; ?>) - &#2547;<?php echo number_format($a['monthly_rent'], 2); ?>/mo [<?php echo htmlspecialchars($a['status']); ?>]</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="move_in_date">Move-In Date</label><input type="date" id="move_in_date" name="move_in_date" class="form-control" value="<?php echo htmlspecialchars($move_in_date); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="move_out_date">Move-Out Date</label><input type="date" id="move_out_date" name="move_out_date" class="form-control" value="<?php echo htmlspecialchars($move_out_date); ?>" <?php echo ($currentTenancy && $currentTenancy['tenancy_status'] === 'Active') ? 'readonly' : ''; ?>></div>
                    <div class="form-group"><label for="tenancy_status">Tenancy Status</label><select id="tenancy_status" name="tenancy_status" class="form-control"><option value="Active" <?php echo $tenancy_status === 'Active' ? 'selected' : ''; ?>>Active (Occupied)</option><option value="Ended" <?php echo $tenancy_status === 'Ended' ? 'selected' : ''; ?>>Ended (Moved Out)</option></select></div>
                </div>
                <div style="display:flex;gap:10px;margin-top:24px;"><button type="submit" class="btn btn-primary">&#10004; Save Changes</button><a href="index.php" class="btn btn-secondary">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
