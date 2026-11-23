<?php
require_once __DIR__ . '/../includes/config.php';

unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);

header("Location: login.php");
exit;
