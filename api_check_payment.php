<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/midtrans.php';

mysqli_report(MYSQLI_REPORT_OFF);

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT) ?: (filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: (isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0)));

if (!$orderId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parameter order_id tidak valid.'
    ]);
    exit;
}

$stmt = mysqli_prepare($con, "SELECT order_id, trx_id, payment_method, total_amount, status, p_status, payment_expiry, paid_at, created_at, midtrans_bank, midtrans_va_number, midtrans_bill_key, midtrans_biller_code FROM orders WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Pesanan tidak ditemukan.'
    ]);
    exit;
}

// Check if expired based on current server time
$isExpired = false;
$remainingSeconds = 0;
if (!empty($order['payment_expiry']) && $order['p_status'] === 'Pending') {
    $nowTime = time();
    $expTime = strtotime($order['payment_expiry']);
    $remainingSeconds = max(0, $expTime - $nowTime);
    if ($remainingSeconds <= 0) {
        $isExpired = true;
        // Auto update to expired in DB
        mysqli_query($con, "UPDATE orders SET p_status = 'Expired', status = 'cancelled' WHERE order_id = $orderId AND p_status = 'Pending'");
        $order['p_status'] = 'Expired';
        $order['status'] = 'cancelled';
    }
}

// Live Pull from Midtrans Sandbox if order is pending and Midtrans is active
if ($order['p_status'] === 'Pending' && !empty(get_midtrans_server_key())) {
    $midtransOrderId = 'SP-' . str_pad($orderId, 4, '0', STR_PAD_LEFT) . '-' . substr(md5($orderId . '_' . ($order['created_at'] ?? time())), 0, 6);
    $mtRes = fetch_midtrans_status($midtransOrderId);
    if ($mtRes && !empty($mtRes['transaction_status'])) {
        $st = $mtRes['transaction_status'];
        if (in_array($st, ['settlement', 'capture'])) {
            $now = date('Y-m-d H:i:s');
            mysqli_query($con, "UPDATE orders SET p_status = 'Paid', status = 'processing', paid_at = '$now' WHERE order_id = $orderId");
            $order['p_status'] = 'Paid';
            $order['status'] = 'processing';
            $order['paid_at'] = $now;
        } elseif (in_array($st, ['deny', 'cancel', 'expire'])) {
            mysqli_query($con, "UPDATE orders SET p_status = 'Failed', status = 'cancelled' WHERE order_id = $orderId");
            $order['p_status'] = 'Failed';
            $order['status'] = 'cancelled';
        }
    }
}

echo json_encode([
    'status' => 'success',
    'order_id' => (int)$order['order_id'],
    'trx_id' => $order['trx_id'],
    'payment_method' => $order['payment_method'],
    'total_amount' => (float)$order['total_amount'],
    'order_status' => $order['status'],
    'p_status' => $order['p_status'],
    'is_paid' => ($order['p_status'] === 'Paid'),
    'is_expired' => ($order['p_status'] === 'Expired'),
    'remaining_seconds' => $remainingSeconds,
    'paid_at' => $order['paid_at'],
    'va_number' => $order['midtrans_va_number'],
    'bill_key' => $order['midtrans_bill_key'],
    'biller_code' => $order['midtrans_biller_code'],
    'bank' => $order['midtrans_bank'],
    'server_time' => date('Y-m-d H:i:s')
]);

