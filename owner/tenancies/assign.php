<?php
$tenantId=(int)($_GET['tenant_id']??0);
header('Location: /smart_apartment_management_system/owner/tenants/' . ($tenantId ? 'edit.php?id='.$tenantId : ''));
exit;
