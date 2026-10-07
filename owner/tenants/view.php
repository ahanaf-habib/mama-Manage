<?php
$pageTitle = 'Tenant Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/owner_auth.php';
require_once __DIR__ . '/../../includes/tenancy.php';
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ownerId = (int)getCurrentUserId();
if (!$id) {
    setFlashMessage('danger', 'Invalid tenant ID specified.');
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT t.*
    FROM tenants t
    WHERE t.tenant_id = ?
      AND EXISTS (
          SELECT 1 FROM tenancies tx
          JOIN apartments ax ON tx.apartment_id = ax.apartment_id
          WHERE tx.tenant_id = t.tenant_id AND ax.owner_id = ?
      )");
$stmt->execute([$id, $ownerId]);
$tenant = $stmt->fetch();

if (!$tenant) {
    setFlashMessage('danger', 'Tenant record not found.');
    header('Location: index.php');
    exit;
}

// Current active tenancy & apartment info
$stmt = $pdo->prepare("SELECT tn.*, a.apartment_number, a.floor_level, a.monthly_rent, a.description as apartment_desc
    FROM tenancies tn
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE tn.tenant_id = ? AND a.owner_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
    LIMIT 1");
$stmt->execute([$id, $ownerId]);
$currentTenancy = $stmt->fetch();

// All tenancy history
$stmt = $pdo->prepare("SELECT tn.*, a.apartment_number, a.floor_level, a.monthly_rent
    FROM tenancies tn
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE tn.tenant_id = ? AND a.owner_id = ?
    ORDER BY tn.move_in_date DESC");
$stmt->execute([$id, $ownerId]);
$tenancyHistory = $stmt->fetchAll();

// All bills for this tenant
$stmt = $pdo->prepare("SELECT b.*, a.apartment_number
    FROM bills b
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE tn.tenant_id = ? AND a.owner_id = ?
    ORDER BY b.billing_month DESC");
$stmt->execute([$id, $ownerId]);
$bills = $stmt->fetchAll();

// All payments by this tenant
$stmt = $pdo->prepare("SELECT p.*, b.billing_month, a.apartment_number
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN tenancies tn ON b.tenancy_id = tn.tenancy_id
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE tn.tenant_id = ? AND a.owner_id = ?
    ORDER BY p.payment_date DESC");
$stmt->execute([$id, $ownerId]);
$payments = $stmt->fetchAll();

// All complaints filed by this tenant
$stmt = $pdo->prepare("SELECT c.*, a.apartment_number
    FROM complaints c
    JOIN apartments a ON c.apartment_id = a.apartment_id
    WHERE c.tenant_id = ? AND a.owner_id = ?
    ORDER BY c.submission_date DESC");
$stmt->execute([$id, $ownerId]);
$complaints = $stmt->fetchAll();

// Maintenance requests for this tenant's complaints
$stmt = $pdo->prepare("SELECT mr.*, c.category, c.priority, s.staff_name
    FROM maintenance_requests mr
    JOIN complaints c ON mr.complaint_id = c.complaint_id
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE c.tenant_id = ? AND EXISTS (SELECT 1 FROM apartments ax WHERE ax.apartment_id = c.apartment_id AND ax.owner_id = ?)
    ORDER BY mr.maintenance_id DESC");
$stmt->execute([$id, $ownerId]);
$maintenanceHistory = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Tenant Profile: <?php echo htmlspecialchars($tenant['tenant_name']); ?></h1>
            <p>Complete tenant record, rental agreements, payment history, and service logs.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="edit.php?id=<?php echo (int)$tenant['tenant_id']; ?>" class="btn btn-primary">&#9998; Edit Profile</a>
            <a href="index.php" class="btn btn-secondary">&larr; Back to List</a>
        </div>
    </div>

    <!-- Personal Info & Active Lease Grid -->
    <div class="grid-2">
        <!-- Personal Info -->
        <div class="card">
            <div class="card-header">
                <span>&#128100; Personal Information</span>
                <code>Portal User: <?php echo htmlspecialchars($tenant['username']); ?></code>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Full Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($tenant['tenant_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">NID / ID Reference</span>
                        <span class="detail-value"><?php echo htmlspecialchars($tenant['id_reference']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value"><?php echo htmlspecialchars($tenant['phone']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?php echo htmlspecialchars($tenant['email']); ?></span>
                    </div>
                    <div class="detail-item" style="grid-column: span 2;">
                        <span class="detail-label">Emergency Contact</span>
                        <span class="detail-value"><?php echo htmlspecialchars($tenant['emergency_contact'] ?? 'None recorded'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Active Tenancy -->
        <div class="card">
            <div class="card-header">
                <span>&#127970; Current Apartment</span>
                <?php if ($currentTenancy): ?>
                    <span class="badge badge-success">Active Lease</span>
                <?php else: ?>
                    <span class="badge badge-secondary">No Active Lease</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($currentTenancy): ?>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Apartment</span>
                            <span class="detail-value">
                                <a href="../apartments/view.php?id=<?php echo (int)$currentTenancy['apartment_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                    Apt <?php echo htmlspecialchars($currentTenancy['apartment_number']); ?> (Floor <?php echo (int)$currentTenancy['floor_level']; ?>)
                                </a>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Monthly Rent</span>
                            <span class="detail-value">&#2547; <?php echo number_format($currentTenancy['monthly_rent'], 2); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Move-In Date</span>
                            <span class="detail-value"><?php echo date('d M Y', strtotime($currentTenancy['move_in_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Move-Out Date</span>
                            <span class="detail-value"><?php echo $currentTenancy['move_out_date'] ? date('d M Y', strtotime($currentTenancy['move_out_date'])) : 'Present (Active)'; ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted); margin-bottom: 12px;">This tenant currently has no active tenancy assigned.</p>
                    <a href="edit.php?id=<?php echo (int)$tenant['tenant_id']; ?>" class="btn btn-sm btn-primary">&#43; Assign to Apartment</a>
                <?php endif; ?>
            </div>
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
                            <th>Apartment</th>
                            <th>Floor</th>
                            <th>Monthly Rent</th>
                            <th>Move-in Date</th>
                            <th>Move-out Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenancyHistory)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">No tenancy history on record</td></tr>
                        <?php else: ?>
                            <?php foreach ($tenancyHistory as $th): ?>
                                <tr>
                                    <td><strong>Apt <?php echo htmlspecialchars($th['apartment_number']); ?></strong></td>
                                    <td>Floor <?php echo (int)$th['floor_level']; ?></td>
                                    <td>&#2547; <?php echo number_format($th['monthly_rent'], 2); ?></td>
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

    <!-- Bills & Payments Grid -->
    <div class="grid-2">
        <!-- Bills -->
        <div class="card">
            <div class="card-header">
                <span>&#128179; Billing History</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($bills)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No bills found</td></tr>
                            <?php else: ?>
                                <?php foreach ($bills as $b): ?>
                                    <tr>
                                        <td><strong><?php echo date('M Y', strtotime($b['billing_month'])); ?></strong></td>
                                        <td>&#2547; <?php echo number_format($b['total_amount'], 2); ?></td>
                                        <td><?php echo date('d M Y', strtotime($b['due_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $b['bill_status'] === 'Paid' ? 'badge-success' : ($b['bill_status'] === 'Partially Paid' ? 'badge-info' : ($b['bill_status'] === 'Overdue' ? 'badge-danger' : 'badge-warning')); 
                                            ?>">
                                                <?php echo htmlspecialchars($b['bill_status']); ?>
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

        <!-- Payments -->
        <div class="card">
            <div class="card-header">
                <span>&#128176; Payment Records</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No payments recorded</td></tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><strong>&#2547; <?php echo number_format($p['payment_amount'], 2); ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $p['payment_status'] === 'Completed' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo htmlspecialchars($p['payment_status']); ?>
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

    <!-- Complaints & Maintenance Grid -->
    <div class="grid-2">
        <!-- Complaints -->
        <div class="card">
            <div class="card-header">
                <span>&#128227; Tenant Complaints</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($complaints)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No complaints filed</td></tr>
                            <?php else: ?>
                                <?php foreach ($complaints as $c): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($c['category']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $c['priority'] === 'High' ? 'badge-danger' : ($c['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                            ?>"><?php echo htmlspecialchars($c['priority']); ?></span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($c['submission_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $c['complaint_status'] === 'Resolved' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo htmlspecialchars($c['complaint_status']); ?>
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

        <!-- Maintenance -->
        <div class="card">
            <div class="card-header">
                <span>&#128295; Maintenance Requests</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Staff</th>
                                <th>Est. Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($maintenanceHistory)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No maintenance records</td></tr>
                            <?php else: ?>
                                <?php foreach ($maintenanceHistory as $m): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($m['category']); ?></td>
                                        <td><?php echo htmlspecialchars($m['staff_name'] ?? 'Unassigned'); ?></td>
                                        <td>&#2547; <?php echo number_format($m['estimated_cost'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $m['maintenance_status'] === 'Completed' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo htmlspecialchars($m['maintenance_status']); ?>
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
