<?php
require_once __DIR__.'/../../config/database.php';
$id=(int)($_GET['id']??0);
$stmt=$pdo->prepare('SELECT tenant_id FROM tenancies WHERE tenancy_id=?');
$stmt->execute([$id]);
$tenantId=(int)($stmt->fetchColumn()?:0);
header('Location: /smart_apartment_management_system/owner/tenants/' . ($tenantId ? 'view.php?id='.$tenantId : ''));
exit;
