<?php
require_once __DIR__ . '/config.php';

function get_midtrans_server_key() {
    $key = getenv('MIDTRANS_SERVER_KEY');
    if (!$key && isset($_ENV['MIDTRANS_SERVER_KEY'])) $key = $_ENV['MIDTRANS_SERVER_KEY'];
    if (!$key) {
        $settings = get_store_settings();
        $key = $settings['midtrans_server_key'] ?? '';
    }
    return trim($key ?: '');
}

function get_midtrans_client_key() {
    $key = getenv('MIDTRANS_CLIENT_KEY');
    if (!$key && isset($_ENV['MIDTRANS_CLIENT_KEY'])) $key = $_ENV['MIDTRANS_CLIENT_KEY'];
    if (!$key) {
        $settings = get_store_settings();
        $key = $settings['midtrans_client_key'] ?? '';
    }
    return trim($key ?: '');
}

function is_midtrans_enabled() {
    return !empty(get_midtrans_server_key());
}

/**
 * Generate Midtrans Snap Token
 */
function create_midtrans_snap_token($order, $items) {
    global $con;
    
    // Check if token already generated and saved
    if (!empty($order['snap_token'])) {
        return $order['snap_token'];
    }

    $serverKey = get_midtrans_server_key();
    if (empty($serverKey)) {
        return null;
    }

    $apiUrl = "https://app.sandbox.midtrans.com/snap/v1/transactions";

    // Item details
    $itemDetails = [];
    $sumItems = 0;
    foreach ($items as $it) {
        $price = (int)$it['product_price'];
        $qty = (int)$it['quantity'];
        $itemDetails[] = [
            'id' => (string)$it['product_id'],
            'price' => $price,
            'quantity' => $qty,
            'name' => mb_strimwidth($it['product_name'], 0, 48)
        ];
        $sumItems += ($price * $qty);
    }

    // Shipping or discount adjustment
    $diff = (int)$order['total_amount'] - $sumItems;
    if ($diff != 0) {
        $itemDetails[] = [
            'id' => 'ADJ',
            'price' => $diff,
            'quantity' => 1,
            'name' => ($diff > 0 ? 'Biaya Pengiriman' : 'Kupon Diskon')
        ];
    }

    $midtransOrderId = 'SP-' . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) . '-' . substr(md5($order['order_id'] . '_' . ($order['created_at'] ?? time())), 0, 6);

    $payload = [
        'transaction_details' => [
            'order_id' => $midtransOrderId,
            'gross_amount' => (int)$order['total_amount']
        ],
        'item_details' => $itemDetails,
        'customer_details' => [
            'first_name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'phone' => $order['customer_phone'],
            'shipping_address' => [
                'first_name' => $order['customer_name'],
                'address' => $order['shipping_address'],
                'city' => $order['shipping_city']
            ]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201 || $httpCode === 200) {
        $respData = json_decode($response, true);
        $token = $respData['token'] ?? null;
        if ($token) {
            $up = mysqli_prepare($con, "UPDATE orders SET snap_token = ? WHERE order_id = ?");
            mysqli_stmt_bind_param($up, "si", $token, $order['order_id']);
            mysqli_stmt_execute($up);
            return $token;
        }
    }

    return null;
}

/**
 * Create Midtrans Core API Charge for QRIS (Returns QR Code URL for simulator)
 */
function create_midtrans_qris_charge($order) {
    global $con;
    $orderId = (int)$order['order_id'];

    if (!empty($order['midtrans_qr_url'])) {
        return $order['midtrans_qr_url'];
    }

    $serverKey = get_midtrans_server_key();
    if (empty($serverKey)) return null;

    $apiUrl = "https://api.sandbox.midtrans.com/v2/charge";
    $midtransOrderId = 'SP-' . str_pad($orderId, 4, '0', STR_PAD_LEFT) . '-' . substr(md5($orderId . '_' . ($order['created_at'] ?? time())), 0, 6);

    $payload = [
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id' => $midtransOrderId,
            'gross_amount' => (int)$order['total_amount']
        ],
        'qris' => [
            'acquirer' => 'gopay'
        ],
        'customer_details' => [
            'first_name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'phone' => $order['customer_phone']
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        $res = json_decode($response, true);
        $qrUrl = '';
        if (!empty($res['actions'])) {
            foreach ($res['actions'] as $act) {
                if ($act['name'] === 'generate-qr-code') {
                    $qrUrl = $act['url'];
                    break;
                }
            }
        }
        if ($qrUrl) {
            $up = mysqli_prepare($con, "UPDATE orders SET midtrans_qr_url = ? WHERE order_id = ?");
            mysqli_stmt_bind_param($up, "si", $qrUrl, $orderId);
            mysqli_stmt_execute($up);
            return $qrUrl;
        }
    }
    return null;
}

/**
 * Create Midtrans Core API Charge for Banks (BCA, BRI, BNI, Mandiri, BSI)
 */
function create_midtrans_bank_charge($order, $bank = 'bca') {
    global $con;
    $orderId = (int)$order['order_id'];
    $bank = strtolower(trim($bank));

    // Check if order already has VA or Bill Key saved
    if ($bank === 'mandiri') {
        if (!empty($order['midtrans_bill_key'])) {
            return [
                'type' => 'echannel',
                'bank' => 'mandiri',
                'biller_code' => !empty($order['midtrans_biller_code']) ? $order['midtrans_biller_code'] : '70012',
                'bill_key' => $order['midtrans_bill_key']
            ];
        }
    } else {
        if (!empty($order['midtrans_va_number'])) {
            return [
                'type' => 'bank_transfer',
                'bank' => $bank,
                'va_number' => $order['midtrans_va_number']
            ];
        }
    }

    $serverKey = get_midtrans_server_key();
    if (empty($serverKey)) return null;

    $apiUrl = "https://api.sandbox.midtrans.com/v2/charge";
    $midtransOrderId = 'SP-' . str_pad($orderId, 4, '0', STR_PAD_LEFT) . '-' . substr(md5($orderId . '_' . ($order['created_at'] ?? time())), 0, 6);

    if ($bank === 'mandiri') {
        $payload = [
            'payment_type' => 'echannel',
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => (int)$order['total_amount']
            ],
            'echannel' => [
                'bill_info1' => 'Pembayaran Pesanan',
                'bill_info2' => 'SweetPop Bakery'
            ],
            'customer_details' => [
                'first_name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone']
            ]
        ];
    } else {
        $payload = [
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => (int)$order['total_amount']
            ],
            'bank_transfer' => [
                'bank' => $bank
            ],
            'customer_details' => [
                'first_name' => $order['customer_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone']
            ]
        ];
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        $res = json_decode($response, true);
        if ($bank === 'mandiri') {
            $billerCode = $res['biller_code'] ?? '70012';
            $billKey = $res['bill_key'] ?? '';
            if ($billKey) {
                $up = mysqli_prepare($con, "UPDATE orders SET midtrans_bank = 'mandiri', midtrans_biller_code = ?, midtrans_bill_key = ? WHERE order_id = ?");
                mysqli_stmt_bind_param($up, "ssi", $billerCode, $billKey, $orderId);
                mysqli_stmt_execute($up);
                return [
                    'type' => 'echannel',
                    'bank' => 'mandiri',
                    'biller_code' => $billerCode,
                    'bill_key' => $billKey
                ];
            }
        } else {
            $vaNum = $res['va_numbers'][0]['va_number'] ?? '';
            if ($vaNum) {
                $up = mysqli_prepare($con, "UPDATE orders SET midtrans_bank = ?, midtrans_va_number = ? WHERE order_id = ?");
                mysqli_stmt_bind_param($up, "ssi", $bank, $vaNum, $orderId);
                mysqli_stmt_execute($up);
                return [
                    'type' => 'bank_transfer',
                    'bank' => $bank,
                    'va_number' => $vaNum
                ];
            }
        }
    }
    return null;
}

/**
 * Backward compatibility helper for Bank BSI
 */
function create_midtrans_bsi_charge($order) {
    $res = create_midtrans_bank_charge($order, 'bsi');
    return $res['va_number'] ?? null;
}

/**
 * Check Midtrans Status directly via API
 */
function fetch_midtrans_status($midtransOrderId) {
    $serverKey = get_midtrans_server_key();
    if (empty($serverKey) || empty($midtransOrderId)) return null;

    $apiUrl = "https://api.sandbox.midtrans.com/v2/" . urlencode($midtransOrderId) . "/status";

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return null;
}


