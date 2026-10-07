<?php
$pageTitle = 'Owner Profile';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/owner_auth.php';

$owner_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM owners WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$owner = $stmt->fetch();

if (!$owner) {
    die("Owner record not found.");
}

$flash = getFlashMessage();
$error = '';
$owner_name = $owner['owner_name'];
$phone = $owner['phone'];
$email = $owner['email'];
$username = $owner['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_name = trim($_POST['owner_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $new_password = trim($_POST['password'] ?? '');

    if ($owner_name === '' || $phone === '' || $email === '' || $username === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } else {
        // Unique checks
        $stmt = $pdo->prepare("SELECT 
            (SELECT COUNT(*) FROM owners WHERE email = ? AND owner_id != ?) as email_exists,
            (SELECT COUNT(*) FROM owners WHERE username = ? AND owner_id != ?) as user_exists");
        $stmt->execute([$email, $owner_id, $username, $owner_id]);
        $check = $stmt->fetch();

        if ($check['email_exists'] > 0) {
            $error = 'This email address is already in use by another owner account.';
        } elseif ($check['user_exists'] > 0) {
            $error = 'This username is already taken.';
        } else {
            if ($new_password !== '') {
                $stmt = $pdo->prepare("UPDATE owners SET owner_name = ?, phone = ?, email = ?, username = ?, password = ? WHERE owner_id = ?");
                $stmt->execute([$owner_name, $phone, $email, $username, $new_password, $owner_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE owners SET owner_name = ?, phone = ?, email = ?, username = ? WHERE owner_id = ?");
                $stmt->execute([$owner_name, $phone, $email, $username, $owner_id]);
            }

            $_SESSION['user_name'] = $owner_name;
            $_SESSION['username'] = $username;

            setFlashMessage('success', 'Your profile details were successfully updated.');
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
            <h1>Owner Account Profile</h1>
            <p>Manage your management credentials and contact details.</p>
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

    <div class="card" style="max-width: 650px;">
        <div class="card-header">
            <span>&#128100; Profile Details</span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="owner_name">Full Name <span class="required-star">*</span></label>
                    <input type="text" id="owner_name" name="owner_name" class="form-control" value="<?php echo htmlspecialchars($owner_name); ?>" required>
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

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username <span class="required-star">*</span></label>
                        <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Change Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
