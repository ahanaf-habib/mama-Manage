<?php
$pageTitle = 'Add Apartment';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$error = '';
$apartment_number = '';
$floor_level = '';
$description = '';
$monthly_rent = '';
$status = 'Available';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apartment_number = trim($_POST['apartment_number'] ?? '');
    $floor_level = trim($_POST['floor_level'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $monthly_rent = trim($_POST['monthly_rent'] ?? '');
    $status = trim($_POST['status'] ?? 'Available');

    if ($apartment_number === '' || $floor_level === '' || $monthly_rent === '') {
        $error = 'Please fill in all required fields (Apartment Number, Floor, Monthly Rent).';
    } elseif (!is_numeric($floor_level) || (int)$floor_level < 0) {
        $error = 'Floor level must be a valid non-negative integer.';
    } elseif (!is_numeric($monthly_rent) || (float)$monthly_rent < 0) {
        $error = 'Monthly rent must be a valid positive number.';
    } elseif (!in_array($status, ['Available', 'Occupied', 'Under Maintenance'])) {
        $error = 'Invalid status selected.';
    } else {
        // Check uniqueness of apartment number
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM apartments WHERE apartment_number = ?");
        $stmt->execute([$apartment_number]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Apartment number '$apartment_number' already exists. Please choose a unique number.";
        } else {
            $owner_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("INSERT INTO apartments (owner_id, apartment_number, floor_level, description, monthly_rent, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$owner_id, $apartment_number, (int)$floor_level, $description, (float)$monthly_rent, $status]);
            setFlashMessage('success', "Apartment '$apartment_number' was successfully added.");
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
            <h1>Add New Apartment</h1>
            <p>Register a new apartment unit in the building.</p>
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
                        <input type="text" id="apartment_number" name="apartment_number" class="form-control" placeholder="e.g. A-101" value="<?php echo htmlspecialchars($apartment_number); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="floor_level">Floor Level <span class="required-star">*</span></label>
                        <input type="number" id="floor_level" name="floor_level" class="form-control" placeholder="e.g. 1" min="0" value="<?php echo htmlspecialchars($floor_level); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="monthly_rent">Monthly Rent (&#2547;) <span class="required-star">*</span></label>
                        <input type="number" id="monthly_rent" name="monthly_rent" step="0.01" min="0" class="form-control" placeholder="e.g. 28000.00" value="<?php echo htmlspecialchars($monthly_rent); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Initial Status <span class="required-star">*</span></label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="Available" <?php echo $status === 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Occupied" <?php echo $status === 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                            <option value="Under Maintenance" <?php echo $status === 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description & Features</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="e.g. 2 bedroom apartment with attached balcony and modern fittings"><?php echo htmlspecialchars($description); ?></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">&#10004; Save Apartment</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
