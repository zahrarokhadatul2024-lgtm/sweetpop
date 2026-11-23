<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Disable default PHP error output in JSON API
mysqli_report(MYSQLI_REPORT_OFF);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$action  = isset($input['action']) ? trim($input['action']) : '';

if (!$orderId || !in_array($action, ['success', 'failed', 'expire', 'reset'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parameter order_id atau action tidak valid.'
    ]);
    exit;
}

// Fetch existing order
$stmt = mysqli_prepare($con, "SELECT * FROM orders WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Data pesanan tidak ditemukan.'
    ]);
    exit;
}

$now = date('Y-m-d H:i:s');
$prevPStatus = $order['p_status'] ?? 'Pending';

if ($action === 'success') {
    // Payment Successful / Settlement
    $upStmt = mysqli_prepare($con, "
        UPDATE orders 
        SET p_status = 'Paid', status = 'processing', paid_at = ? 
        WHERE order_id = ?
    ");
    mysqli_stmt_bind_param($upStmt, "si", $now, $orderId);
    mysqli_stmt_execute($upStmt);
    mysqli_stmt_close($upStmt);

    $msg = 'Simulasi pembayaran BERHASIL! Status pesanan otomatis menjadi LUNAS dan masuk ke antrean dapur bakehouse.';
    $newPStatus = 'Paid';
    $newOrderStatus = 'processing';

} elseif ($action === 'failed') {
    // Payment Failed / Denied
    $upStmt = mysqli_prepare($con, "
        UPDATE orders 
        SET p_status = 'Failed', status = 'cancelled' 
        WHERE order_id = ?
    ");
    mysqli_stmt_bind_param($upStmt, "i", $orderId);
    mysqli_stmt_execute($upStmt);
    mysqli_stmt_close($upStmt);

    // If previously pending/paid, restore stock
    if ($prevPStatus !== 'Failed' && $prevPStatus !== 'Expired') {
        $itemsRes = mysqli_query($con, "SELECT product_id, quantity FROM order_items WHERE order_id = $orderId");
        if ($itemsRes) {
            while ($it = mysqli_fetch_assoc($itemsRes)) {
                $pid = (int)$it['product_id'];
                $qty = (int)$it['quantity'];
                mysqli_query($con, "UPDATE products SET product_stock = product_stock + $qty WHERE product_id = $pid");
            }
        }
    }

    $msg = 'Simulasi pembayaran DITOLAK / GAGAL. Pesanan dibatalkan dan stok produk telah dikembalikan.';
    $newPStatus = 'Failed';
    $newOrderStatus = 'cancelled';

} elseif ($action === 'expire') {
    // Payment Expired
    $upStmt = mysqli_prepare($con, "
        UPDATE orders 
        SET p_status = 'Expired', status = 'cancelled' 
        WHERE order_id = ?
    ");
    mysqli_stmt_bind_param($upStmt, "i", $orderId);
    mysqli_stmt_execute($upStmt);
    mysqli_stmt_close($upStmt);

    // Restore stock if needed
    if ($prevPStatus !== 'Failed' && $prevPStatus !== 'Expired') {
        $itemsRes = mysqli_query($con, "SELECT product_id, quantity FROM order_items WHERE order_id = $orderId");
        if ($itemsRes) {
            while ($it = mysqli_fetch_assoc($itemsRes)) {
                $pid = (int)$it['product_id'];
                $qty = (int)$it['quantity'];
                mysqli_query($con, "UPDATE products SET product_stock = product_stock + $qty WHERE product_id = $pid");
            }
        }
    }

    $msg = 'Batas waktu pembayaran telah habis (EXPIRED). Pesanan dibatalkan secara otomatis.';
    $newPStatus = 'Expired';
    $newOrderStatus = 'cancelled';

} elseif ($action === 'reset') {
    // Reset to Pending for re-testing
    $newExp = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $upStmt = mysqli_prepare($con, "
        UPDATE orders 
        SET p_status = 'Pending', status = 'pending', payment_expiry = ?, paid_at = NULL 
        WHERE order_id = ?
    ");
    mysqli_stmt_bind_param($upStmt, "si", $newExp, $orderId);
    mysqli_stmt_execute($upStmt);
    mysqli_stmt_close($upStmt);

    $msg = 'Status pembayaran telah direset ke PENDING untuk pengujian ulang.';
    $newPStatus = 'Pending';
    $newOrderStatus = 'pending';
}

echo json_encode([
    'status' => 'success',
    'action' => $action,
    'p_status' => $newPStatus,
    'order_status' => $newOrderStatus,
    'paid_at' => ($newPStatus === 'Paid') ? $now : null,
    'message' => $msg
]);

