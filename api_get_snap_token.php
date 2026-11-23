<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/midtrans.php';

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID tidak valid.']);
    exit;
}

$stmt = mysqli_prepare($con, "SELECT * FROM orders WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Pesanan tidak ditemukan.']);
    exit;
}

// Fetch items
$iStmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE order_id = ?");
mysqli_stmt_bind_param($iStmt, "i", $orderId);
mysqli_stmt_execute($iStmt);
$iRes = mysqli_stmt_get_result($iStmt);
$items = [];
while ($row = mysqli_fetch_assoc($iRes)) {
    $items[] = $row;
}
mysqli_stmt_close($iStmt);

$serverKey = get_midtrans_server_key();
$clientKey = get_midtrans_client_key();

if (empty($serverKey) || empty($clientKey)) {
    echo json_encode([
        'status' => 'config_needed',
        'message' => 'Midtrans Server Key atau Client Key belum diisi. Silakan masukkan Server Key & Client Key Sandbox kamu di Admin Portal > Pengaturan Sistem.',
        'client_key' => ''
    ]);
    exit;
}

$snapToken = create_midtrans_snap_token($order, $items);

if ($snapToken) {
    echo json_encode([
        'status' => 'success',
        'snap_token' => $snapToken,
        'client_key' => $clientKey
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal membuat Snap Token dari Midtrans. Periksa kembali Server Key Sandbox kamu di Admin Settings.'
    ]);
}

