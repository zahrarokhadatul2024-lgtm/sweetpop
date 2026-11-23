<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/midtrans.php';

// Midtrans Webhook Notification Handler
$rawInput = file_get_contents('php://input');
$notif = json_decode($rawInput, true);

if (!$notif || empty($notif['order_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification payload']);
    exit;
}

$rawOrderId = $notif['order_id']; // e.g. "SP-0012-abc123"
$statusCode = $notif['status_code'] ?? '';
$grossAmount = $notif['gross_amount'] ?? '';
$serverKey = get_midtrans_server_key();
$signature = $notif['signature_key'] ?? '';

// Signature verification
if (!empty($serverKey)) {
    $expectedSignature = hash('sha512', $rawOrderId . $statusCode . $grossAmount . $serverKey);
    if ($signature !== $expectedSignature) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
        exit;
    }
}

// Extract numeric order_id from "SP-0012-..."
$cleanId = null;
if (preg_match('/SP-(\d+)/i', $rawOrderId, $m)) {
    $cleanId = (int)$m[1];
}

if (!$cleanId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cannot extract order ID']);
    exit;
}

$trxStatus = $notif['transaction_status'] ?? '';
$fraudStatus = $notif['fraud_status'] ?? '';
$paymentType = $notif['payment_type'] ?? '';

$now = date('Y-m-d H:i:s');

if ($trxStatus === 'capture') {
    if ($fraudStatus === 'accept') {
        // Success
        mysqli_query($con, "UPDATE orders SET p_status = 'Paid', status = 'processing', paid_at = '$now' WHERE order_id = $cleanId");
    }
} elseif ($trxStatus === 'settlement') {
    // Settled (QRIS / VA / GoPay / ShopeePay / Bank Transfer)
    mysqli_query($con, "UPDATE orders SET p_status = 'Paid', status = 'processing', paid_at = '$now' WHERE order_id = $cleanId");
} elseif ($trxStatus === 'pending') {
    mysqli_query($con, "UPDATE orders SET p_status = 'Pending' WHERE order_id = $cleanId AND p_status != 'Paid'");
} elseif (in_array($trxStatus, ['deny', 'cancel'])) {
    mysqli_query($con, "UPDATE orders SET p_status = 'Failed', status = 'cancelled' WHERE order_id = $cleanId");
} elseif ($trxStatus === 'expire') {
    mysqli_query($con, "UPDATE orders SET p_status = 'Expired', status = 'cancelled' WHERE order_id = $cleanId");
}

http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Notification processed successfully']);

