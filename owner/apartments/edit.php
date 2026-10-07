<?php
$pageTitle = 'Edit Apartment';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ownerId = (int)getCurrentUserId();
if (!$id) {
    setFlashMessage('danger', 'Invalid apartment ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM apartments WHERE apartment_id = ? AND owner_id = ?");
$stmt->execute([$id, $ownerId]);
$apartment = $stmt->fetch();

if (!$apartment) {
    setFlashMessage('danger', 'Apartment not found.');
    header('Location: index.php');
    exit;
}

$activeTenancyStmt = $pdo->prepare("SELECT tn.tenancy_id, tn.tenant_id, t.tenant_name, tn.move_out_date
    FROM tenancies tn
    JOIN tenants t ON t.tenant_id = tn.tenant_id
    WHERE tn.apartment_id = ?
      AND tn.tenancy_status = 'Active'
      AND tn.move_in_date <= CURDATE()
      AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
    ORDER BY tn.move_in_date DESC, tn.tenancy_id DESC
    LIMIT 1");
$activeTenancyStmt->execute([$id]);
$activeTenancy = $activeTenancyStmt->fetch();
$isOccupied = (bool)$activeTenancy;

$error = '';
$apartment_number = $apartment['apartment_number'];
$floor_level = $apartment['floor_level'];
$description = $apartment['description'];
$monthly_rent = $apartment['monthly_rent'];
$status = $apartment['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apartment_number = trim($_POST['apartment_number'] ?? '');
    $floor_level = trim($_POST['floor_level'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $monthly_rent = trim($_POST['monthly_rent'] ?? '');
    $requestedStatus = trim($_POST['status'] ?? $apartment['status']);
    $status = $isOccupied ? 'Occupied' : $requestedStatus;

    if ($apartment_number === '' || $floor_level === '' || $monthly_rent === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($floor_level) || (int)$floor_level < 0) {
        $error = 'Floor level must be a valid non-negative integer.';
    } elseif (!is_numeric($monthly_rent) || (float)$monthly_rent < 0) {
        $error = 'Monthly rent must be a positive number.';
    } elseif ($isOccupied && $requestedStatus !== 'Occupied') {
        $error = 'Occupied apartments cannot have their status changed manually. The tenancy lifecycle controls the Occupied status.';
    } elseif (!$isOccupied && !in_array($requestedStatus, ['Available', 'Under Maintenance'], true)) {
        $error = 'For an unoccupied apartment, status can only be Available or Under Maintenance. Occupied is assigned automatically when a tenancy begins.';
    } else {
        // Check uniqueness excluding current apartment
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM apartments WHERE apartment_number = ? AND apartment_id != ?");
        $stmt->execute([$apartment_number, $id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Apartment number '$apartment_number' is already taken by another unit.";
        } else {
            $stmt = $pdo->prepare("UPDATE apartments SET apartment_number = ?, floor_level = ?, description = ?, monthly_rent = ?, status = ? WHERE apartment_id = ?");
            $stmt->execute([$apartment_number, (int)$floor_level, $description, (float)$monthly_rent, $status, $id]);
            setFlashMessage('success', "Apartment '$apartment_number' was updated successfully.");
            header('Location: index.php');
            exit;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Edit Apartment: <?php echo htmlspecialchars($apartment['apartment_number']); ?></h1>
            <p>Update unit details and rent rate. Occupied status is controlled automatically by the tenancy lifecycle.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 700px;">
        <div class="card-header">
            <span>Apartment Information</span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="apartment_number">Apartment Number <span class="required-star">*</span></label>
                        <input type="text" id="apartment_number" name="apartment_number" class="form-control" value="<?php echo htmlspecialchars($apartment_number); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="floor_level">Floor Level <span class="required-star">*</span></label>
                        <input type="number" id="floor_level" name="floor_level" class="form-control" min="0" value="<?php echo htmlspecialchars($floor_level); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="monthly_rent">Monthly Rent (&#2547;) <span class="required-star">*</span></label>
                        <input type="number" id="monthly_rent" name="monthly_rent" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($monthly_rent); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status <span class="required-star">*</span></label>
                        <?php if ($isOccupied): ?>
                            <select id="status" name="status_display" class="form-control" disabled>
                                <option selected>Occupied</option>
                            </select>
                            <input type="hidden" name="status" value="Occupied">
                            <small style="color:var(--text-muted);">Occupied status cannot be changed while <?php echo htmlspecialchars($activeTenancy['tenant_name']); ?> has an active tenancy. Use the Move-Out workflow first.</small>
                        <?php else: ?>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Available" <?php echo $status === 'Available' ? 'selected' : ''; ?>>Available</option>
                                <option value="Under Maintenance" <?php echo $status === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                            </select>
                            <small style="color:var(--text-muted);">Occupied is assigned automatically when an approved tenant tenancy begins.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description & Features</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">&#10004; Update Apartment</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
