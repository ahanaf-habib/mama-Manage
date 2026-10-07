<?php
$pageTitle = 'Edit Notice';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid notice ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notices WHERE notice_id = ? AND owner_id = ?");
$stmt->execute([$id, $ownerId]);
$notice = $stmt->fetch();

if (!$notice) {
    setFlashMessage('danger', 'Notice record not found.');
    header('Location: index.php');
    exit;
}

$aptStmt = $pdo->prepare("SELECT apartment_id, apartment_number, floor_level FROM apartments WHERE owner_id = ? ORDER BY apartment_number ASC");
$aptStmt->execute([$ownerId]);
$apartments = $aptStmt->fetchAll();
$floorStmt = $pdo->prepare("SELECT DISTINCT floor_level FROM apartments WHERE owner_id = ? ORDER BY floor_level ASC");
$floorStmt->execute([$ownerId]);
$floors = $floorStmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$message_content = $notice['message_content'];
$target_type = $notice['target_type'];
$apartment_id = $notice['apartment_id'];
$floor_number = $notice['floor_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_content = trim($_POST['message_content'] ?? '');
    $target_type = trim($_POST['target_type'] ?? 'Everyone');
    $apartment_id_raw = trim($_POST['apartment_id'] ?? '');
    $floor_number_raw = trim($_POST['floor_number'] ?? '');

    if ($message_content === '') {
        $error = 'Announcement message cannot be empty.';
    } elseif (!in_array($target_type, ['Everyone', 'Apartment', 'Floor'])) {
        $error = 'Invalid target audience selected.';
    } else {
        $final_apartment_id = null;
        $final_floor_number = null;
        $valid = true;

        if ($target_type === 'Apartment') {
            if ($apartment_id_raw === '') {
                $error = 'Please select a specific apartment.';
                $valid = false;
            } else {
                $final_apartment_id = (int)$apartment_id_raw;
                $check = $pdo->prepare("SELECT COUNT(*) FROM apartments WHERE apartment_id = ? AND owner_id = ?");
                $check->execute([$final_apartment_id, $ownerId]);
                if ((int)$check->fetchColumn() !== 1) {
                    $error = 'You can only target apartments that belong to you.';
                    $valid = false;
                }
                $apartment_id = $final_apartment_id;
            }
        } elseif ($target_type === 'Floor') {
            if ($floor_number_raw === '') {
                $error = 'Please select a specific floor level.';
                $valid = false;
            } else {
                $final_floor_number = (int)$floor_number_raw;
                $check = $pdo->prepare("SELECT COUNT(*) FROM apartments WHERE floor_level = ? AND owner_id = ?");
                $check->execute([$final_floor_number, $ownerId]);
                if ((int)$check->fetchColumn() === 0) {
                    $error = 'You can only target floors that belong to your apartments.';
                    $valid = false;
                }
                $floor_number = $final_floor_number;
            }
        }

        if ($valid) {
            $stmt = $pdo->prepare("UPDATE notices SET message_content = ?, target_type = ?, apartment_id = ?, floor_number = ? WHERE notice_id = ? AND owner_id = ?");
            $stmt->execute([$message_content, $target_type, $final_apartment_id, $final_floor_number, $id, $ownerId]);
            setFlashMessage('success', "Notice #$id was updated successfully.");
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
            <h1>Edit Notice #<?php echo (int)$notice['notice_id']; ?></h1>
            <p>Revise announcement text or change targeting rules.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width: 750px;">
        <div class="card-header">
            <span>Notice Particulars</span>
            <span>Published: <?php echo date('d M Y, h:i A', strtotime($notice['published_at'])); ?></span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="message_content">Announcement Message <span class="required-star">*</span></label>
                    <textarea id="message_content" name="message_content" class="form-control" rows="5" required><?php echo htmlspecialchars($message_content); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="target_type">Target Audience Scope <span class="required-star">*</span></label>
                    <select id="target_type" name="target_type" class="form-control" required>
                        <option value="Everyone" <?php echo $target_type === 'Everyone' ? 'selected' : ''; ?>>Everyone (Building-wide Announcement)</option>
                        <option value="Apartment" <?php echo $target_type === 'Apartment' ? 'selected' : ''; ?>>Specific Apartment</option>
                        <option value="Floor" <?php echo $target_type === 'Floor' ? 'selected' : ''; ?>>Specific Floor Level</option>
                    </select>
                </div>

                <!-- Apartment Field Group -->
                <div class="form-group" id="apartment_field_group" style="display: <?php echo $target_type === 'Apartment' ? 'block' : 'none'; ?>;">
                    <label for="apartment_id">Target Apartment Unit <span class="required-star">*</span></label>
                    <select id="apartment_id" name="apartment_id" class="form-control">
                        <option value="">-- Choose Apartment --</option>
                        <?php foreach ($apartments as $a): ?>
                            <option value="<?php echo (int)$a['apartment_id']; ?>" <?php echo (int)$apartment_id === (int)$a['apartment_id'] ? 'selected' : ''; ?>>
                                Apt <?php echo htmlspecialchars($a['apartment_number']); ?> (Floor <?php echo (int)$a['floor_level']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Floor Field Group -->
                <div class="form-group" id="floor_field_group" style="display: <?php echo $target_type === 'Floor' ? 'block' : 'none'; ?>;">
                    <label for="floor_number">Target Floor Level <span class="required-star">*</span></label>
                    <select id="floor_number" name="floor_number" class="form-control">
                        <option value="">-- Choose Floor --</option>
                        <?php foreach ($floors as $fl): ?>
                            <option value="<?php echo (int)$fl; ?>" <?php echo $floor_number !== '' && (int)$floor_number === (int)$fl ? 'selected' : ''; ?>>
                                Floor Level <?php echo (int)$fl; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">&#10004; Update Notice</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
