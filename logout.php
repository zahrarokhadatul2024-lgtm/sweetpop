<?php
require_once __DIR__ . '/includes/config.php';

// Unset customer session keys
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);

set_flash('info', 'Kamu telah berhasil logout. Sampai jumpa lagi di SweetPop! ♡');
header("Location: index.php");
exit;
