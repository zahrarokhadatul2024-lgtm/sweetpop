<?php
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = mysqli_prepare($con, "INSERT INTO email_info (email) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "s", $email);
        if (mysqli_stmt_execute($stmt)) {
            set_flash('success', 'Yayy! Terima kasih sudah subscribe newsletter SweetPop ✉🍰');
        } else {
            set_flash('info', 'Email kamu sudah terdaftar di newsletter SweetPop!');
        }
    } else {
        set_flash('danger', 'Mohon masukkan format email yang valid.');
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: " . $referer);
exit;
