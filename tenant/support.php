<?php
$pageTitle = 'Support';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant_auth.php';
require_once __DIR__ . '/../includes/tenancy.php';
ensureMoveOutRequestsTable($pdo);
syncTenancyLifecycle($pdo);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$tenant_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT t.tenant_name, t.email, t.phone, a.apartment_number,
                              o.owner_name, o.phone AS owner_phone, o.email AS owner_email
                       FROM tenants t
                       LEFT JOIN tenancies tn ON t.tenant_id = tn.tenant_id AND tn.tenancy_status = 'Active' AND tn.move_in_date <= CURDATE() AND (tn.move_out_date IS NULL OR tn.move_out_date >= CURDATE())
                       LEFT JOIN apartments a ON tn.apartment_id = a.apartment_id
                       LEFT JOIN owners o ON a.owner_id = o.owner_id
                       WHERE t.tenant_id = ?
                       LIMIT 1");
$stmt->execute([$tenant_id]);
$contact = $stmt->fetch();
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Support</h1>
            <p>Get help with your apartment search or SAMS account.</p>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header"><span>&#128172; Need Help?</span></div>
            <div class="card-body">
                <p style="line-height:1.7; color:var(--text-muted);">
                    For apartment availability, tenancy assignment, rent information, or building-related assistance,
                    contact the apartment owner or building management.
                </p>
                <?php if ($contact && $contact['owner_name']): ?>
                    <div style="margin-top:20px; padding:16px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">
                        <strong><?php echo htmlspecialchars($contact['owner_name']); ?></strong><br>
                        <?php echo htmlspecialchars($contact['owner_phone']); ?><br>
                        <?php echo htmlspecialchars($contact['owner_email']); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="margin-top:20px;">You do not have an active apartment assignment yet. Please contact the owner of an available apartment.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span>&#128161; Quick Help</span></div>
            <div class="card-body">
                <ul style="line-height:2; color:var(--text-muted); padding-left:20px;">
                    <li>Use <strong>Apartments</strong> to browse available units.</li>
                    <li>Use <strong>My Profile</strong> to update your contact information.</li>
                    <li>For account problems, contact building management with your username.</li>
                    <li>For tenancy assignment, the owner must register your tenancy in the system.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
