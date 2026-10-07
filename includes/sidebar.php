<?php
$currentPage = $_SERVER['REQUEST_URI'];
$role = $_SESSION['user_role'] ?? '';
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h3>&#127963; SAMS</h3>
        <p>Apartment Management</p>
    </div>
    <ul class="sidebar-menu">
        <?php if ($role === 'owner'): ?>
        <li class="sidebar-section-title">Main</li>
        <li><a href="/smart_apartment_management_system/owner/dashboard.php" class="<?php echo strpos($currentPage, 'owner/dashboard') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128200;</span> Dashboard</a></li>
        
        <li class="sidebar-section-title">Management</li>
        <li><a href="/smart_apartment_management_system/owner/apartments/" class="<?php echo strpos($currentPage, 'owner/apartments') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#127970;</span> Apartments</a></li>
        <li><a href="/smart_apartment_management_system/owner/tenants/" class="<?php echo (strpos($currentPage, 'owner/tenants') !== false || strpos($currentPage, 'owner/tenancies') !== false) ? 'active' : ''; ?>"><span class="menu-icon">&#128101;</span> Tenants</a></li>
        <li><a href="/smart_apartment_management_system/owner/apartment_requests/" class="<?php echo strpos($currentPage, 'owner/apartment_requests') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128233;</span> Apartment Requests</a></li>
        <li><a href="/smart_apartment_management_system/owner/move_out_requests/" class="<?php echo strpos($currentPage, 'owner/move_out_requests') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128682;</span> Move-Out Requests</a></li>
        
        <li class="sidebar-section-title">Financial</li>
        <li><a href="/smart_apartment_management_system/owner/bills/" class="<?php echo strpos($currentPage, 'owner/bills') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128179;</span> Bills</a></li>
        <li><a href="/smart_apartment_management_system/owner/payments/" class="<?php echo strpos($currentPage, 'owner/payments') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128176;</span> Payments</a></li>
        <li><a href="/smart_apartment_management_system/owner/overdue/" class="<?php echo strpos($currentPage, 'owner/overdue') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#9888;</span> Overdue Payments</a></li>
        
        <li class="sidebar-section-title">Operations</li>
        <li><a href="/smart_apartment_management_system/owner/complaints/" class="<?php echo strpos($currentPage, 'owner/complaints') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128227;</span> Complaints</a></li>
        <li><a href="/smart_apartment_management_system/owner/maintenance/" class="<?php echo strpos($currentPage, 'owner/maintenance') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128295;</span> Maintenance</a></li>
        <li><a href="/smart_apartment_management_system/owner/staff/" class="<?php echo strpos($currentPage, 'owner/staff') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128119;</span> Staff</a></li>
        <li><a href="/smart_apartment_management_system/owner/notices/" class="<?php echo strpos($currentPage, 'owner/notices') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128226;</span> Notices</a></li>
        
        <li class="sidebar-section-title">Analytics</li>
        <li><a href="/smart_apartment_management_system/owner/reports/" class="<?php echo strpos($currentPage, 'owner/reports') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128202;</span> Reports</a></li>
        
        <li class="sidebar-section-title">Account</li>
        <li><a href="/smart_apartment_management_system/owner/profile.php" class="<?php echo strpos($currentPage, 'owner/profile') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128100;</span> Profile</a></li>
        <li><a href="/smart_apartment_management_system/auth/logout.php"><span class="menu-icon">&#128682;</span> Logout</a></li>
        
        <?php elseif ($role === 'tenant'): ?>
        <?php
        require_once __DIR__ . '/tenancy.php';
        ensureMoveOutRequestsTable($pdo);
        syncTenancyLifecycle($pdo);
        $sidebarTenantId = (int)($_SESSION['user_id'] ?? 0);
        $sidebarTenancy = getCurrentActiveTenancy($pdo, $sidebarTenantId);
        $hasActiveTenancy = (bool)$sidebarTenancy;
        ?>
        <li class="sidebar-section-title">Resident Portal</li>
        <?php if ($hasActiveTenancy): ?>
        <li><a href="/smart_apartment_management_system/tenant/dashboard.php" class="<?php echo strpos($currentPage, 'tenant/dashboard') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128200;</span> Dashboard</a></li>
        <?php endif; ?>
        <li><a href="/smart_apartment_management_system/tenant/apartment.php" class="<?php echo strpos($currentPage, 'tenant/apartment') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#127970;</span> Apartments</a></li>
        <?php if ($hasActiveTenancy): ?>
        <li><a href="/smart_apartment_management_system/tenant/bills.php" class="<?php echo strpos($currentPage, 'tenant/bills') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128179;</span> Bills</a></li>
        <li><a href="/smart_apartment_management_system/tenant/payments.php" class="<?php echo strpos($currentPage, 'tenant/payments') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128176;</span> Payments</a></li>
        <li><a href="/smart_apartment_management_system/tenant/complaints.php" class="<?php echo strpos($currentPage, 'tenant/complaints') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128227;</span> Complaints</a></li>
        <li><a href="/smart_apartment_management_system/tenant/maintenance.php" class="<?php echo strpos($currentPage, 'tenant/maintenance') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128295;</span> Maintenance</a></li>
        <li><a href="/smart_apartment_management_system/tenant/notices.php" class="<?php echo strpos($currentPage, 'tenant/notices') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128226;</span> Notices</a></li>
        <li><a href="/smart_apartment_management_system/tenant/move_out.php" class="<?php echo strpos($currentPage, 'tenant/move_out') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128682;</span> Move-Out Request</a></li>
        <?php endif; ?>
        <li class="sidebar-section-title">Account</li>
        <li><a href="/smart_apartment_management_system/tenant/profile.php" class="<?php echo strpos($currentPage, 'tenant/profile') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128100;</span> My Profile</a></li>
        <li><a href="/smart_apartment_management_system/tenant/support.php" class="<?php echo strpos($currentPage, 'tenant/support') !== false ? 'active' : ''; ?>"><span class="menu-icon">&#128172;</span> Support</a></li>
        <li><a href="/smart_apartment_management_system/auth/logout.php"><span class="menu-icon">&#128682;</span> Logout</a></li>
        <?php endif; ?>
    </ul>
</nav>
