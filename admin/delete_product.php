<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id) {
    // Check product
    $stmt = mysqli_prepare($con, "SELECT product_title FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($prod = mysqli_fetch_assoc($res)) {
        $delStmt = mysqli_prepare($con, "DELETE FROM products WHERE product_id = ?");
        mysqli_stmt_bind_param($delStmt, "i", $id);

        if (mysqli_stmt_execute($delStmt)) {
            set_flash('success', "Produk '" . $prod['product_title'] . "' berhasil dihapus dari katalog.");
        } else {
            set_flash('danger', "Gagal menghapus produk: " . mysqli_error($con));
        }
    }
}

header("Location: products.php");
exit;
