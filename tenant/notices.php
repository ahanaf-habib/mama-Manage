<?php
$pageTitle = 'Notices & Announcements';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/tenancy.php';
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$tenant_id = $_SESSION['user_id'];

// Find tenant's current active apartment and floor
$stmt = $pdo->prepare("SELECT tn.apartment_id, a.apartment_number, a.floor_level
    FROM tenancies tn
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE tn.tenant_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
    LIMIT 1");
$stmt->execute([$tenant_id]);
$currentApt = $stmt->fetch();

$aptId = $currentApt ? (int)$currentApt['apartment_id'] : -1;
$floorNum = $currentApt ? (int)$currentApt['floor_level'] : -1;

$stmt = $pdo->prepare("SELECT n.*, o.owner_name, a.apartment_number
    FROM notices n
    JOIN owners o ON n.owner_id = o.owner_id
    LEFT JOIN apartments a ON n.apartment_id = a.apartment_id
    WHERE n.target_type = 'Everyone'
       OR (n.target_type = 'Apartment' AND n.apartment_id = ?)
       OR (n.target_type = 'Floor' AND n.floor_number = ?)
    ORDER BY n.published_at DESC");
$stmt->execute([$aptId, $floorNum]);
$notices = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Notices & Community Announcements</h1>
            <p>Official broadcasts from building management relevant to your apartment and floor.</p>
        </div>
        <div>
            <?php if ($currentApt): ?>
                <span class="badge badge-info" style="font-size: 13px; padding: 6px 12px;">
                    Subscribed to: Building-wide, Floor <?php echo (int)$currentApt['floor_level']; ?>, Apt <?php echo htmlspecialchars($currentApt['apartment_number']); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($notices)): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">&#128226;</div>
                    <h3>No Notices Available</h3>
                    <p>There are no active notices or announcements addressed to you at this time.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($notices as $n): ?>
                <div class="card" style="border-left: 4px solid <?php echo $n['target_type'] === 'Everyone' ? 'var(--info)' : ($n['target_type'] === 'Apartment' ? 'var(--warning)' : 'var(--primary)'); ?>;">
                    <div class="card-header">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="badge <?php 
                                echo $n['target_type'] === 'Everyone' ? 'badge-info' : ($n['target_type'] === 'Apartment' ? 'badge-warning' : 'badge-purple'); 
                            ?>">
                                <?php echo htmlspecialchars($n['target_type']); ?>
                            </span>
                            <span style="font-weight: 600; font-size: 13px; color: var(--text-muted);">
                                Published by <?php echo htmlspecialchars($n['owner_name']); ?>
                            </span>
                        </div>
                        <span style="font-size: 12px; color: var(--text-muted);">
                            &#128197; <?php echo date('d M Y, h:i A', strtotime($n['published_at'])); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div style="font-size: 15px; line-height: 1.6; color: var(--text-main);">
                            <?php echo nl2br(htmlspecialchars($n['message_content'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
