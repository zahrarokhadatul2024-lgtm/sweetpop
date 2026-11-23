<?php
require_once __DIR__ . '/includes/config.php';

// Disable default PHP error display for clean UI
mysqli_report(MYSQLI_REPORT_OFF);

$payCode = trim($_GET['code'] ?? $_POST['code'] ?? '');
$action  = trim($_POST['action'] ?? '');
$method  = trim($_GET['method'] ?? $_POST['method'] ?? 'bca'); // 'qris', 'bca', 'bri', 'bni', 'deeplink'

$orderData = null;
$errorMsg = null;
$successMsg = null;

// Helper to find order by code/id/trx_id/va/bill_key
function find_order_by_code($con, $rawCode) {
    if (empty($rawCode)) return null;
    $rawCode = trim($rawCode);

    // 1. Try exact trx_id match
    $stmt = mysqli_prepare($con, "SELECT * FROM orders WHERE trx_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $rawCode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($ord = mysqli_fetch_assoc($res)) {
        mysqli_stmt_close($stmt);
        return $ord;
    }
    mysqli_stmt_close($stmt);

    // 2. Try match midtrans_va_number or midtrans_bill_key
    $stmt = mysqli_prepare($con, "SELECT * FROM orders WHERE midtrans_va_number = ? OR midtrans_bill_key = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $rawCode, $rawCode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($ord = mysqli_fetch_assoc($res)) {
        mysqli_stmt_close($stmt);
        return $ord;
    }
    mysqli_stmt_close($stmt);

    // 3. Extract numeric ID (e.g. from SP-0106, 88080111, or 111)
    $cleanNum = preg_replace('/[^\d]/', '', $rawCode);
    if (!empty($cleanNum)) {
        if (strpos($cleanNum, '8808') === 0 && strlen($cleanNum) > 4) {
            $candidateId = (int)substr($cleanNum, 4);
        } else {
            $candidateId = (int)$cleanNum;
        }

        if ($candidateId > 0) {
            $stmt = mysqli_prepare($con, "SELECT * FROM orders WHERE order_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $candidateId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($ord = mysqli_fetch_assoc($res)) {
                mysqli_stmt_close($stmt);
                return $ord;
            }
            mysqli_stmt_close($stmt);
        }
    }

    return null;
}

// Handle Form Submission (Inquire or Pay)
if (!empty($payCode)) {
    $orderData = find_order_by_code($con, $payCode);
    if (!$orderData) {
        $errorMsg = "Nomor Pembayaran / Kode Tagihan '{$payCode}' tidak ditemukan di database. Pastikan kode yang disalin sudah benar.";
    } else {
        // Handle Payment Action
        if ($action === 'pay') {
            $orderId = (int)$orderData['order_id'];
            $now = date('Y-m-d H:i:s');

            $upStmt = mysqli_prepare($con, "UPDATE orders SET p_status = 'Paid', status = 'processing', paid_at = ? WHERE order_id = ?");
            mysqli_stmt_bind_param($upStmt, "si", $now, $orderId);
            mysqli_stmt_execute($upStmt);
            mysqli_stmt_close($upStmt);

            // Refresh orderData
            $orderData = find_order_by_code($con, (string)$orderId);
            $successMsg = "Pembayaran BERHASIL diproses! Status transaksi otomatis LUNAS dan telah diteruskan ke toko SweetPop.";

        } elseif ($action === 'cancel') {
            $orderId = (int)$orderData['order_id'];
            $upStmt = mysqli_prepare($con, "UPDATE orders SET p_status = 'Failed', status = 'cancelled' WHERE order_id = ?");
            mysqli_stmt_bind_param($upStmt, "i", $orderId);
            mysqli_stmt_execute($upStmt);
            mysqli_stmt_close($upStmt);

            // Restore stock
            $itemsRes = mysqli_query($con, "SELECT product_id, quantity FROM order_items WHERE order_id = $orderId");
            if ($itemsRes) {
                while ($it = mysqli_fetch_assoc($itemsRes)) {
                    $pid = (int)$it['product_id'];
                    $qty = (int)$it['quantity'];
                    mysqli_query($con, "UPDATE products SET product_stock = product_stock + $qty WHERE product_id = $pid");
                }
            }

            $orderData = find_order_by_code($con, (string)$orderId);
            $errorMsg = "Transaksi pembayaran telah DIBATALKAN / DITOLAK.";
        }
    }
}

// Fetch order items if order is found
$orderItems = [];
if ($orderData) {
    $iStmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE order_id = ?");
    mysqli_stmt_bind_param($iStmt, "i", $orderData['order_id']);
    mysqli_stmt_execute($iStmt);
    $iRes = mysqli_stmt_get_result($iStmt);
    while ($row = mysqli_fetch_assoc($iRes)) {
        $orderItems[] = $row;
    }
    mysqli_stmt_close($iStmt);
}

// Method label mapping
$methodLabels = [
    'bca'      => 'BCA Virtual Account',
    'bri'      => 'BRI Virtual Account',
    'bni'      => 'BNI Virtual Account',
    'mandiri'  => 'Mandiri Bill Payment',
    'bsi'      => 'BSI Virtual Account',
    'qris'     => 'QRIS (openAPI & non openAPI)',
    'deeplink' => 'Deeplink (ShopeePay / GoPay)'
];
$currentMethodTitle = $methodLabels[$method] ?? 'BCA Virtual Account';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midtrans Payment Simulator | Sandbox Mode</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --midtrans-dark: #1e293b;
            --midtrans-blue: #0284c7;
            --midtrans-light-blue: #e0f2fe;
            --sidebar-bg: #ffffff;
            --page-bg: #f1f5f9;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --success: #10b981;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            background-color: var(--page-bg);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.15rem;
            color: #0369a1;
            text-decoration: none;
        }

        .badge-sandbox {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Simulator Layout */
        .simulator-layout {
            display: flex;
            flex: 1;
            min-height: calc(100vh - 60px);
        }

        /* Sidebar matching Screenshot 2 */
        .simulator-sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 24px 0;
            flex-shrink: 0;
        }

        .sidebar-title {
            padding: 0 20px 14px;
            font-size: 0.85rem;
            font-weight: 800;
            color: #475569;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-item a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #334155;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-item a:hover {
            background: #f8fafc;
            color: #0284c7;
        }

        .sidebar-item.active a {
            background: var(--midtrans-light-blue);
            color: #0369a1;
            font-weight: 700;
            border-right: 3px solid #0284c7;
        }

        .sidebar-group-title {
            padding: 14px 20px 6px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-subitem a {
            padding-left: 36px;
            font-size: 0.92rem;
        }

        /* Content Area */
        .simulator-content {
            flex: 1;
            padding: 32px 40px 60px;
            max-width: 800px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .card-header-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .card-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 22px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .input-group {
            display: flex;
            gap: 8px;
        }

        .form-control {
            flex: 1;
            padding: 12px 14px;
            font-size: 1.05rem;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: #0369a1;
        }

        .form-control:focus {
            outline: none;
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0369a1;
        }

        .btn-success {
            background: #10b981;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #dc2626;
            color: #ffffff;
        }

        /* Bill Details */
        .bill-details {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .bill-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 0.9rem;
            border-bottom: 1px dashed var(--border);
        }

        .bill-row:last-child {
            border-bottom: none;
        }

        .bill-row.total {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid var(--border);
            padding-top: 10px;
            margin-top: 6px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .status-badge.paid {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .status-badge.pending {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .receipt-card {
            background: #ecfdf5;
            border: 1.5px solid #10b981;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-icon {
            width: 54px;
            height: 54px;
            background: #10b981;
            color: #ffffff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            margin-bottom: 12px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .simulator-layout {
                flex-direction: column;
            }
            .simulator-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }
            .simulator-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Topbar -->
    <header class="topbar">
        <a href="payment_simulator.php" class="topbar-brand">
            <i class="fa-solid fa-credit-card"></i>
            <span>MIDTRANS PAYMENT SIMULATOR</span>
        </a>
        <span class="badge-sandbox">
            <i class="fa-solid fa-flask"></i> Sandbox Test Mode
        </span>
    </header>

    <div class="simulator-layout">
        <!-- Sidebar matching Midtrans Simulator in User Screenshot 2 -->
        <aside class="simulator-sidebar">
            <div class="sidebar-title">PAYMENT SIMULATOR</div>
            <ul class="sidebar-menu">
                <li class="sidebar-item <?= ($method === 'qris') ? 'active' : ''; ?>">
                    <a href="payment_simulator.php?method=qris<?= !empty($payCode) ? '&code=' . urlencode($payCode) : ''; ?>">
                        QRIS (openAPI & non openAPI)
                    </a>
                </li>
                <li class="sidebar-item <?= ($method === 'deeplink') ? 'active' : ''; ?>">
                    <a href="payment_simulator.php?method=deeplink<?= !empty($payCode) ? '&code=' . urlencode($payCode) : ''; ?>">
                        Deeplink (openAPI & non openAPI)
                    </a>
                </li>
                
                <li class="sidebar-group-title">
                    <i class="fa-solid fa-chevron-down" style="font-size:0.75rem;"></i> Virtual Account
                </li>
                <li class="sidebar-item sidebar-subitem <?= ($method === 'bca') ? 'active' : ''; ?>">
                    <a href="payment_simulator.php?method=bca<?= !empty($payCode) ? '&code=' . urlencode($payCode) : ''; ?>">
                        BCA VA
                    </a>
                </li>
                <li class="sidebar-item sidebar-subitem <?= ($method === 'bri') ? 'active' : ''; ?>">
                    <a href="payment_simulator.php?method=bri<?= !empty($payCode) ? '&code=' . urlencode($payCode) : ''; ?>">
                        BRI VA
                    </a>
                </li>
                <li class="sidebar-item sidebar-subitem <?= ($method === 'bni') ? 'active' : ''; ?>">
                    <a href="payment_simulator.php?method=bni<?= !empty($payCode) ? '&code=' . urlencode($payCode) : ''; ?>">
                        BNI VA
                    </a>
                </li>
                <li class="sidebar-item sidebar-subitem <?= ($method === 'mandiri') ? 'active' : ''; ?>">
                    <a href="payment_simulator.php?method=mandiri<?= !empty($payCode) ? '&code=' . urlencode($payCode) : ''; ?>">
                        Mandiri Bill
                    </a>
                </li>
                <li class="sidebar-item sidebar-subitem <?= ($method === 'bsi') ? 'active' : ''; ?>">
                    <a href="payment_simulator.php?method=bsi<?= !empty($payCode) ? '&code=' . urlencode($payCode) : ''; ?>">
                        BSI VA
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content Area -->
        <main class="simulator-content">
            
            <?php if ($successMsg): ?>
                <div class="receipt-card">
                    <div class="receipt-icon"><i class="fa-solid fa-check"></i></div>
                    <h2 style="font-size: 1.45rem; color: #065f46; margin-bottom: 6px;">Payment Successful! (Settlement)</h2>
                    <p style="color: #047857; font-size: 0.92rem; margin-bottom: 18px;">
                        Dana sebesar <strong><?= format_rupiah($orderData['total_amount']); ?></strong> telah diverifikasi lunas untuk toko <strong>SWEETPOP</strong>.
                    </p>

                    <div style="background:#ffffff; border-radius:8px; padding:14px; border:1px solid #a7f3d0; text-align:left; font-size:0.88rem; margin-bottom:16px;">
                        <div class="bill-row">
                            <span>No. Faktur:</span>
                            <strong>#SP-<?= str_pad($orderData['order_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                        </div>
                        <div class="bill-row">
                            <span>Ref Transaksi:</span>
                            <strong style="font-family:'JetBrains Mono';"><?= htmlspecialchars($orderData['trx_id'] ?: 'SPX-' . date('Ymd')); ?></strong>
                        </div>
                        <div class="bill-row">
                            <span>Status:</span>
                            <strong style="color:#059669;">LUNAS (SETTLED) ✓</strong>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; justify-content:center;">
                        <a href="order_success.php?id=<?= $orderData['order_id']; ?>" class="btn btn-success">
                            <i class="fa-solid fa-store"></i> Kembali ke Tab SweetPop
                        </a>
                        <a href="payment_simulator.php?method=<?= htmlspecialchars($method); ?>" class="btn" style="background:#ffffff; color:#334155; border:1px solid var(--border);">
                            Uji Transaksi Lain
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><?= htmlspecialchars($errorMsg); ?></div>
                </div>
            <?php endif; ?>

            <!-- Inquire Form matching Midtrans Style -->
            <div class="card">
                <h1 class="card-header-title"><?= htmlspecialchars($currentMethodTitle); ?> Simulator</h1>
                <p class="card-subtitle">
                    Masukkan <strong>Virtual Account Number / Kode Pembayaran</strong> yang disalin dari halaman pesanan SweetPop untuk memeriksa dan menyelesaikan simulasi transaksi.
                </p>

                <form method="GET" action="payment_simulator.php">
                    <input type="hidden" name="method" value="<?= htmlspecialchars($method); ?>">
                    <div class="form-group">
                        <label class="form-label"><?= ($method === 'qris') ? 'QRIS / Barcode Data / Kode' : 'Virtual Account Number'; ?></label>
                        <div class="input-group">
                            <input type="text" name="code" class="form-control" placeholder="<?= ($method === 'qris') ? 'Contoh: 88080111 atau SP-0111' : 'Contoh: 88080111'; ?>" value="<?= htmlspecialchars($payCode); ?>" required autofocus>
                            <button type="submit" class="btn btn-primary">
                                Inquire
                            </button>
                        </div>
                        <small style="color:var(--text-muted); display:block; margin-top:6px; font-size:0.8rem;">
                            Format diterima: Kode VA (misal: <code>88080111</code>), Faktur (<code>SP-0111</code>), atau ID Order (<code>111</code>).
                        </small>
                    </div>
                </form>
            </div>

            <!-- Bill Inquire Result -->
            <?php if ($orderData && !$successMsg): ?>
                <div class="card" style="border: 1.5px solid #0284c7;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:12px;">
                        <div>
                            <span style="font-size:0.75rem; text-transform:uppercase; font-weight:700; color:var(--text-muted);">Merchant</span>
                            <h3 style="font-size:1.2rem; color:#0f172a;">SWEETPOP BAKERY YOGYAKARTA</h3>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:0.75rem; text-transform:uppercase; font-weight:700; color:var(--text-muted); display:block; margin-bottom:2px;">Status</span>
                            <?php if ($orderData['p_status'] === 'Paid'): ?>
                                <span class="status-badge paid"><i class="fa-solid fa-check"></i> Lunas</span>
                            <?php else: ?>
                                <span class="status-badge pending"><i class="fa-solid fa-clock"></i> Belum Dibayar</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bill-details">
                        <div class="bill-row">
                            <span style="color:var(--text-muted);">Order ID:</span>
                            <strong>#SP-<?= str_pad($orderData['order_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                        </div>
                        <div class="bill-row">
                            <span style="color:var(--text-muted);">Nama Pembeli:</span>
                            <strong><?= htmlspecialchars($orderData['customer_name']); ?></strong>
                        </div>
                        <div class="bill-row">
                            <span style="color:var(--text-muted);">Metode Asli:</span>
                            <strong><?= htmlspecialchars($orderData['payment_method']); ?></strong>
                        </div>

                        <?php if (!empty($orderItems)): ?>
                            <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--border);">
                                <span style="font-size:0.78rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">ITEM PESANAN:</span>
                                <?php foreach ($orderItems as $it): ?>
                                    <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:3px;">
                                        <span>• <?= htmlspecialchars($it['product_name']); ?> (x<?= $it['quantity']; ?>)</span>
                                        <span><?= format_rupiah($it['subtotal']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="bill-row total">
                            <span>Total Amount:</span>
                            <span style="color:#0284c7;"><?= format_rupiah($orderData['total_amount']); ?></span>
                        </div>
                    </div>

                    <?php if ($orderData['p_status'] === 'Paid'): ?>
                        <div style="background:#ecfdf5; border-radius:8px; padding:12px; text-align:center; color:#047857; font-weight:700;">
                            <i class="fa-solid fa-circle-check"></i> Transaksi ini sudah LUNAS terverifikasi.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="payment_simulator.php" style="display:flex; flex-direction:column; gap:10px;">
                            <input type="hidden" name="code" value="<?= htmlspecialchars($payCode); ?>">
                            <input type="hidden" name="method" value="<?= htmlspecialchars($method); ?>">
                            
                            <button type="submit" name="action" value="pay" class="btn btn-success" style="font-size:1rem; padding:14px;">
                                <i class="fa-solid fa-bolt"></i> Pay (Simulasikan Pembayaran Berhasil)
                            </button>

                            <button type="submit" name="action" value="cancel" class="btn btn-danger" onclick="return confirm('Batalkan simulasi pembayaran ini?');">
                                <i class="fa-solid fa-xmark"></i> Tolak / Batalkan
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="text-align:center; margin-top:24px; font-size:0.82rem; color:var(--text-muted);">
                Official Simulator Online: 
                <a href="https://simulator.sandbox.midtrans.com/bca/va/index" target="_blank" style="color:#0284c7; font-weight:600; text-decoration:underline;">
                    Midtrans BCA VA Simulator <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i>
                </a> • 
                <a href="https://simulator.sandbox.midtrans.com/qris/index" target="_blank" style="color:#0284c7; font-weight:600; text-decoration:underline;">
                    Midtrans QRIS Simulator <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.75rem;"></i>
                </a>
            </div>

        </main>
    </div>

</body>
</html>
