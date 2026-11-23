<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$orderId = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$result = $input['result'] ?? [];

if (!$orderId) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID tidak valid.']);
    exit;
}

$now = date('Y-m-d H:i:s');
$status = $result['transaction_status'] ?? 'settlement';

if (in_array($status, ['settlement', 'capture'])) {
    $stmt = mysqli_prepare($con, "UPDATE orders SET p_status = 'Paid', status = 'processing', paid_at = ? WHERE order_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $now, $orderId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['status' => 'success', 'p_status' => 'Paid']);
} elseif ($status === 'pending') {
    mysqli_query($con, "UPDATE orders SET p_status = 'Pending' WHERE order_id = $orderId AND p_status != 'Paid'");
    echo json_encode(['status' => 'success', 'p_status' => 'Pending']);
} else {
    echo json_encode(['status' => 'info', 'message' => 'Status: ' . $status]);
}

