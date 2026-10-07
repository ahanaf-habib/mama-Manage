<?php
$pageTitle = 'Notice Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$ownerId = (int)getCurrentUserId();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('danger', 'Invalid notice ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT n.*, o.owner_name, a.apartment_number, a.floor_level
    FROM notices n
    JOIN owners o ON n.owner_id = o.owner_id
    LEFT JOIN apartments a ON n.apartment_id = a.apartment_id
    WHERE n.notice_id = ? AND n.owner_id = ?");
$stmt->execute([$id, $ownerId]);
$notice = $stmt->fetch();

if (!$notice) {
    setFlashMessage('danger', 'Notice record not found.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Notice #<?php echo (int)$notice['notice_id']; ?></h1>
            <p>Published on <?php echo date('d M Y, h:i A', strtotime($notice['published_at'])); ?> by <?php echo htmlspecialchars($notice['owner_name']); ?>.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="edit.php?id=<?php echo (int)$notice['notice_id']; ?>" class="btn btn-primary">&#9998; Edit Notice</a>
            <a href="index.php" class="btn btn-secondary">&larr; Back to Notices</a>
        </div>
    </div>

    <div class="card" style="max-width: 800px;">
        <div class="card-header">
            <span>&#128226; Announcement Details</span>
            <span class="badge <?php 
                echo $notice['target_type'] === 'Everyone' ? 'badge-info' : ($notice['target_type'] === 'Apartment' ? 'badge-warning' : 'badge-purple'); 
            ?>">
                Target: <?php echo htmlspecialchars($notice['target_type']); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="detail-grid" style="margin-bottom: 20px;">
                <div class="detail-item">
                    <span class="detail-label">Scope Description</span>
                    <span class="detail-value">
                        <?php 
                        if ($notice['target_type'] === 'Apartment') {
                            echo 'Specific Unit: Apt ' . htmlspecialchars($notice['apartment_number'] ?? $notice['apartment_id']);
                        } elseif ($notice['target_type'] === 'Floor') {
                            echo 'Floor Level ' . (int)$notice['floor_number'] . ' Only';
                        } else {
                            echo 'All Tenants & Residents (Building-wide)';
                        }
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Published By</span>
                    <span class="detail-value"><?php echo htmlspecialchars($notice['owner_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Published Date & Time</span>
                    <span class="detail-value"><?php echo date('d M Y, h:i:s A', strtotime($notice['published_at'])); ?></span>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">

            <div>
                <span class="detail-label">Notice Content:</span>
                <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border); font-size: 15px; line-height: 1.6; margin-top: 8px;">
                    <?php echo nl2br(htmlspecialchars($notice['message_content'])); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
