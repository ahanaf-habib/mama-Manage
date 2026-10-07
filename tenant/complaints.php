<?php
$pageTitle = 'My Complaints';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/tenancy.php';
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);

$tenant_id = $_SESSION['user_id'];
$flash = getFlashMessage();
$error = '';

// Check active tenancy to find apartment
$stmt = $pdo->prepare("SELECT tn.apartment_id, a.apartment_number, a.floor_level
    FROM tenancies tn
    JOIN apartments a ON tn.apartment_id = a.apartment_id
    WHERE tn.tenant_id = ? AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
    LIMIT 1");
$stmt->execute([$tenant_id]);
$activeApt = $stmt->fetch();

// Handle new complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_complaint') {
    $category = trim($_POST['category'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $description = trim($_POST['description'] ?? '');

    if (!$activeApt) {
        $error = 'You do not have an active apartment lease to report complaints against.';
    } elseif ($category === '' || $description === '') {
        $error = 'Please select a category and provide a detailed description.';
    } elseif (!in_array($priority, ['Low', 'Medium', 'High'])) {
        $error = 'Invalid priority specified.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO complaints (tenant_id, apartment_id, category, description, priority, submission_date, complaint_status) VALUES (?, ?, ?, ?, ?, CURDATE(), 'Pending')");
        $stmt->execute([
            $tenant_id,
            $activeApt['apartment_id'],
            $category,
            $description,
            $priority
        ]);
        $newId = $pdo->lastInsertId();
        setFlashMessage('success', "Complaint #$newId was successfully submitted. Management will review your ticket shortly.");
        header('Location: complaints.php');
        exit;
    }
}

// Fetch all complaints filed by this tenant
$stmt = $pdo->prepare("SELECT c.*, 
       a.apartment_number,
       mr.maintenance_id, mr.maintenance_status, mr.completion_date,
       s.staff_name
    FROM complaints c
    JOIN apartments a ON c.apartment_id = a.apartment_id
    LEFT JOIN maintenance_requests mr ON c.complaint_id = mr.complaint_id
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE c.tenant_id = ?
    ORDER BY c.submission_date DESC, c.complaint_id DESC");
$stmt->execute([$tenant_id]);
$complaints = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>My Maintenance & Service Complaints</h1>
            <p>Report facility defects, appliance breakdowns, or general building concerns.</p>
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

    <!-- Complaint Submission Box -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <span>&#43; Lodge a Service Complaint / Request</span>
            <?php if ($activeApt): ?>
                <span class="badge badge-info">Reporting for: Apt <?php echo htmlspecialchars($activeApt['apartment_number']); ?></span>
            <?php else: ?>
                <span class="badge badge-danger">No Active Unit Assigned</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($activeApt): ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_complaint">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Issue Category <span class="required-star">*</span></label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">-- Choose Category --</option>
                                <option value="Plumbing">Plumbing (Leakage, Drainage, Taps)</option>
                                <option value="Electrical">Electrical (Power sockets, Lighting, Wiring)</option>
                                <option value="Air Conditioning">Air Conditioning / HVAC</option>
                                <option value="Carpentry">Carpentry & Locks</option>
                                <option value="Painting">Painting & Walls</option>
                                <option value="General">General Facility / Sanitation</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">Urgency Priority <span class="required-star">*</span></label>
                            <select id="priority" name="priority" class="form-control" required>
                                <option value="Low">Low (Minor inconvenience)</option>
                                <option value="Medium" selected>Medium (Standard repair)</option>
                                <option value="High">High (Urgent / Water leakage / Power loss)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Detailed Description of Defect <span class="required-star">*</span></label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Explain the problem, specific location within apartment, and any observations..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">&#10004; Submit Complaint Ticket</button>
                </form>
            <?php else: ?>
                <p style="color: var(--text-muted);">You do not currently have an active tenancy. Only active occupants can file service complaints.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Complaint History Table -->
    <div class="card">
        <div class="card-header">
            <span>&#128227; My Complaint History (<?php echo count($complaints); ?> tickets)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ticket #</th>
                            <th>Apartment</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Priority</th>
                            <th>Submitted Date</th>
                            <th>Complaint Status</th>
                            <th>Assigned Staff</th>
                            <th>Maintenance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaints)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">&#128227;</div>
                                        <h3>No Complaints Submitted</h3>
                                        <p>You have not logged any service requests or complaints.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td><strong>#<?php echo (int)$c['complaint_id']; ?></strong></td>
                                    <td>Apt <?php echo htmlspecialchars($c['apartment_number']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($c['category']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($c['description']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $c['priority'] === 'High' ? 'badge-danger' : ($c['priority'] === 'Medium' ? 'badge-warning' : 'badge-info'); 
                                        ?>"><?php echo htmlspecialchars($c['priority']); ?></span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($c['submission_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $c['complaint_status'] === 'Resolved' ? 'badge-success' : ($c['complaint_status'] === 'Pending' ? 'badge-warning' : 'badge-info'); 
                                        ?>">
                                            <?php echo htmlspecialchars($c['complaint_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($c['staff_name']): ?>
                                            <span>&#128119; <?php echo htmlspecialchars($c['staff_name']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['maintenance_id']): ?>
                                            <span class="badge <?php echo $c['maintenance_status'] === 'Completed' ? 'badge-success' : 'badge-warning'; ?>">
                                                Work Order #<?php echo (int)$c['maintenance_id']; ?> (<?php echo htmlspecialchars($c['maintenance_status']); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Awaiting Dispatch</span>
                                        <?php endif; ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
