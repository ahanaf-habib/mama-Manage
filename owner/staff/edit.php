<?php
$pageTitle = 'Edit Staff Member';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid staff ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM staff WHERE staff_id = ?");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    setFlashMessage('danger', 'Staff record not found.');
    header('Location: index.php');
    exit;
}

$error = '';
$staff_name = $staff['staff_name'];
$phone = $staff['phone'];
$experties = $staff['experties'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_name = trim($_POST['staff_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $experties = trim($_POST['experties'] ?? '');

    if ($staff_name === '' || $phone === '' || $experties === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare("UPDATE staff SET staff_name = ?, phone = ?, experties = ? WHERE staff_id = ?");
        $stmt->execute([$staff_name, $phone, $experties, $id]);
        setFlashMessage('success', "Staff member '$staff_name' was successfully updated.");
        header('Location: index.php');
        exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Edit Staff Member: <?php echo htmlspecialchars($staff['staff_name']); ?></h1>
            <p>Update contact information or trade specialization.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 650px;">
        <div class="card-header">
            <span>Staff Particulars</span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="staff_name">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="staff_name" name="staff_name" class="form-control" value="<?php echo htmlspecialchars($staff_name); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number <span class="required-star">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
                </div>

                <div class="form-group">
                    <label for="experties">Trade Expertise / Specialization <span class="required-star">*</span></label>
                    <input type="text" id="experties" name="experties" class="form-control" value="<?php echo htmlspecialchars($experties); ?>" required>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Update Staff Member</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
