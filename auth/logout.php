<?php
session_start();
session_unset();
session_destroy();
header('Location: /smart_apartment_management_system/auth/login.php');
exit;
