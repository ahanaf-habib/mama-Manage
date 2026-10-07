<?php
$pageTitle = 'Apartment Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ownerId = (int)getCurrentUserId();
if (!$id) {
    setFlashMessage('danger', 'Invalid apartment ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT a.*, o.owner_name FROM apartments a JOIN owners o ON a.owner_id = o.owner_id WHERE a.apartment_id = ? AND a.owner_id = ?");
$stmt->execute([$id, $ownerId]);
$apartment = $stmt->fetch();

if (!$apartment) {
    setFlashMessage('danger', 'Apartment not found.');
    header('Location: index.php');
    exit;
}

// Current active tenancy
$stmt = $pdo->prepare("SELECT tn.*, t.tenant_name, t.phone, t.email, t.emergency_contact, t.id_reference
    FROM tenancies tn
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    WHERE tn.apartment_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
    LIMIT 1");
$stmt->execute([$id]);
$activeTenancy = $stmt->fetch();

// Tenancy History
$stmt = $pdo->prepare("SELECT tn.*, t.tenant_name, t.phone, t.email
    FROM tenancies tn
    JOIN tenants t ON tn.tenant_id = t.tenant_id
    WHERE tn.apartment_id = ?
    ORDER BY tn.move_in_date DESC");
$stmt->execute([$id]);
$tenancyHistory = $stmt->fetchAll();

// Complaints related to this apartment
$stmt = $pdo->prepare("SELECT c.*, t.tenant_name
    FROM complaints c
    JOIN tenants t ON c.tenant_id = t.tenant_id
    WHERE c.apartment_id = ?
    ORDER BY c.submission_date DESC LIMIT 5");
$stmt->execute([$id]);
$complaints = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Apartment Details: <?php echo htmlspecialchars($apartment['apartment_number']); ?></h1>
            <p>Comprehensive overview of apartment status, occupant, and history.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="edit.php?id=<?php echo (int)$apartment['apartment_id']; ?>" class="btn btn-primary">&#9998; Edit</a>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <!-- Overview Card -->
    <div class="card">
        <div class="card-header">
            <span>&#127970; Unit Overview</span>
            <span class="badge <?php 
                echo $apartment['status'] === 'Available' ? 'badge-success' : ($apartment['status'] === 'Occupied' ? 'badge-info' : 'badge-warning'); 
            ?>">
                <?php echo htmlspecialchars($apartment['status']); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Apartment Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($apartment['apartment_number']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Floor Level</span>
                    <span class="detail-value">Floor <?php echo (int)$apartment['floor_level']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Monthly Rent</span>
                    <span class="detail-value">&#2547; <?php echo number_format($apartment['monthly_rent'], 2); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Managing Owner</span>
                    <span class="detail-value"><?php echo htmlspecialchars($apartment['owner_name']); ?></span>
                </div>
                <div class="detail-item" style="grid-column: span 2;">
                    <span class="detail-label">Description & Amenities</span>
                    <span class="detail-value" style="font-weight: normal;"><?php echo htmlspecialchars($apartment['description'] ?? 'No description provided.'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Current Tenant Section -->
    <div class="card">
        <div class="card-header">
            <span>&#128100; Current Occupant</span>
            <?php if ($activeTenancy): ?>
                <span class="badge badge-success">Active Lease</span>
            <?php else: ?>
                <span class="badge badge-secondary">Vacant</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($activeTenancy): ?>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Tenant Name</span>
                        <span class="detail-value">
                            <a href="../tenants/view.php?id=<?php echo (int)$activeTenancy['tenant_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                <?php echo htmlspecialchars($activeTenancy['tenant_name']); ?>
                            </a>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value"><?php echo htmlspecialchars($activeTenancy['phone']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?php echo htmlspecialchars($activeTenancy['email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">NID / ID Reference</span>
                        <span class="detail-value"><?php echo htmlspecialchars($activeTenancy['id_reference']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Move-in Date</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($activeTenancy['move_in_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Emergency Contact</span>
                        <span class="detail-value"><?php echo htmlspecialchars($activeTenancy['emergency_contact'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <p style="color: var(--text-muted);">This apartment currently has no active tenancy.</p>
                <div style="margin-top: 12px;">
                    <a href="../tenancies/assign.php?apartment_id=<?php echo (int)$apartment['apartment_id']; ?>" class="btn btn-sm btn-primary">&#43; Assign Tenant</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tenancy History -->
    <div class="card">
        <div class="card-header">
            <span>&#128196; Tenancy History</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tenant Name</th>
                            <th>Phone</th>
                            <th>Move-in Date</th>
                            <th>Move-out Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenancyHistory)): ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No tenancy history recorded</td></tr>
                        <?php else: ?>
                            <?php foreach ($tenancyHistory as $th): ?>
                                <tr>
                                    <td>
                                        <a href="../tenants/view.php?id=<?php echo (int)$th['tenant_id']; ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($th['tenant_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($th['phone']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($th['move_in_date'])); ?></td>
                                    <td><?php echo $th['move_out_date'] ? date('d M Y', strtotime($th['move_out_date'])) : 'Present'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $th['tenancy_status'] === 'Active' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo htmlspecialchars($th['tenancy_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
