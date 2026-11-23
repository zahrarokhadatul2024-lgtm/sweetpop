<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Check Direct Checkout or Regular Cart
$directId = filter_input(INPUT_GET, 'direct_id', FILTER_VALIDATE_INT);
if ($directId) {
    // Add to cart directly
    $_SESSION['cart'][$directId] = 1;
}

if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Fetch Cart Products
$cartItems = [];
$subtotal = 0;
$ids = array_keys($_SESSION['cart']);
$idsList = implode(',', array_map('intval', $ids));

if (!empty($idsList)) {
    $cartQuery = "SELECT * FROM products WHERE product_id IN ($idsList)";
    $cartRes = mysqli_query($con, $cartQuery);
    while ($row = mysqli_fetch_assoc($cartRes)) {
        $qty = $_SESSION['cart'][$row['product_id']];
        $itemSubtotal = $row['product_price'] * $qty;
        $subtotal += $itemSubtotal;

        $cartItems[] = [
            'product' => $row,
            'qty' => $qty,
            'subtotal' => $itemSubtotal
        ];
    }
}

$discountAmount = 0;
if (isset($_SESSION['coupon']) && $subtotal > 0) {
    $discountAmount = $subtotal * $_SESSION['coupon']['discount'];
}
$shippingCost = ($subtotal >= 50000 || $subtotal == 0) ? 0 : 15000;
$grandTotal = max(0, $subtotal - $discountAmount + $shippingCost);

// Pre-fill logged in user info
$userId = current_user_id();
$userData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'mobile' => '',
    'address1' => '',
    'address2' => ''
];

if ($userId) {
    $uStmt = mysqli_prepare($con, "SELECT * FROM user_info WHERE user_id = ?");
    mysqli_stmt_bind_param($uStmt, "i", $userId);
    mysqli_stmt_execute($uStmt);
    $uRes = mysqli_stmt_get_result($uStmt);
    if ($u = mysqli_fetch_assoc($uRes)) {
        $userData = $u;
    }
}

// Process Order Submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name   = trim($_POST['customer_name'] ?? '');
    $customer_email  = trim($_POST['customer_email'] ?? '');
    $customer_phone  = trim($_POST['customer_phone'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $shipping_city   = trim($_POST['shipping_city'] ?? 'Yogyakarta');
    $delivery_date   = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : date('Y-m-d');
    $delivery_time   = trim($_POST['delivery_time'] ?? 'Slot 1: 09:00 - 12:00 WIB (Pagi)');
    $cake_message    = trim($_POST['cake_message'] ?? '');
    $candle_request  = trim($_POST['candle_request'] ?? 'Lilin Spiral Aesthetic (Free)');
    $payment_method_raw = trim($_POST['payment_method'] ?? 'QRIS');
    $notes              = trim($_POST['notes'] ?? '');

    // If COD, shipping is fixed Rp 10.000, otherwise standard shipping calculation
    if ($payment_method_raw === 'COD') {
        $actualShipping = 10000;
        $payment_method = 'COD (Bayar di Tempat)';
        $codNote = "COD (Bayar Tunai ke Kurir • Ongkir Rp 10.000)";
        $notes = $notes ? ($notes . " • " . $codNote) : $codNote;
    } else {
        $actualShipping = $shippingCost;
        if ($payment_method_raw === 'BCA' || $payment_method_raw === 'Bank BCA') {
            $payment_method = 'Bank BCA (Virtual Account)';
        } elseif ($payment_method_raw === 'BRI' || $payment_method_raw === 'Bank BRI') {
            $payment_method = 'Bank BRI (Virtual Account)';
        } elseif ($payment_method_raw === 'BNI' || $payment_method_raw === 'Bank BNI') {
            $payment_method = 'Bank BNI (Virtual Account)';
        } elseif ($payment_method_raw === 'Mandiri' || $payment_method_raw === 'Bank Mandiri') {
            $payment_method = 'Bank Mandiri (Bill Payment)';
        } elseif ($payment_method_raw === 'Bank Transfer' || $payment_method_raw === 'BSI' || $payment_method_raw === 'Bank BSI') {
            $payment_method = 'Transfer Bank BSI';
        } elseif ($payment_method_raw === 'ShopeePay') {
            $payment_method = 'ShopeePay';
        } else {
            $payment_method = 'QRIS';
        }
    }
    $finalGrandTotal = max(0, $subtotal - $discountAmount + $actualShipping);

    if ($customer_name === '') $errors[] = "Nama penerima wajib diisi.";
    if ($customer_email === '' || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email valid wajib diisi.";
    if ($customer_phone === '') $errors[] = "Nomor WhatsApp/HP wajib diisi.";
    if ($shipping_address === '') $errors[] = "Alamat pengiriman wajib diisi.";

    if (empty($errors)) {
        mysqli_begin_transaction($con);
        try {
            // Generate unique transaction tracking ID
            $trxId = 'SPX-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $pStatus = 'Pending';
            $uidParam = $userId ? (int)$userId : null;

            // Ensure columns exist in orders table
            $checkTrxCol = mysqli_query($con, "SHOW COLUMNS FROM orders LIKE 'trx_id'");
            if ($checkTrxCol && mysqli_num_rows($checkTrxCol) === 0) {
                @mysqli_query($con, "ALTER TABLE orders ADD COLUMN trx_id VARCHAR(255) NULL");
            }
            $checkPStatusCol = mysqli_query($con, "SHOW COLUMNS FROM orders LIKE 'p_status'");
            if ($checkPStatusCol && mysqli_num_rows($checkPStatusCol) === 0) {
                @mysqli_query($con, "ALTER TABLE orders ADD COLUMN p_status VARCHAR(50) DEFAULT 'Pending'");
            }
            $checkExpCol = mysqli_query($con, "SHOW COLUMNS FROM orders LIKE 'payment_expiry'");
            if ($checkExpCol && mysqli_num_rows($checkExpCol) === 0) {
                @mysqli_query($con, "ALTER TABLE orders ADD COLUMN payment_expiry DATETIME NULL");
            }
            $checkPaidCol = mysqli_query($con, "SHOW COLUMNS FROM orders LIKE 'paid_at'");
            if ($checkPaidCol && mysqli_num_rows($checkPaidCol) === 0) {
                @mysqli_query($con, "ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL");
            }

            // Expiry 15 mins for digital payment, null for COD
            $paymentExpiry = ($payment_method_raw !== 'COD') ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
            $pStatus = ($payment_method_raw === 'COD') ? 'Menunggu Kurir' : 'Pending';

            // 1. Insert into orders table with bakery personalization
            $orderStmt = mysqli_prepare($con, "
                INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, shipping_address, shipping_city, delivery_date, delivery_time, payment_method, notes, cake_message, candle_request, total_amount, status, trx_id, p_status, payment_expiry)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");
            if (!$orderStmt) {
                throw new Exception("Gagal menyiapkan data pesanan: " . mysqli_error($con));
            }

            mysqli_stmt_bind_param($orderStmt, "isssssssssssdsss", 
                $uidParam, $customer_name, $customer_email, $customer_phone, 
                $shipping_address, $shipping_city, $delivery_date, $delivery_time, 
                $payment_method, $notes, $cake_message, $candle_request, 
                $finalGrandTotal, $trxId, $pStatus, $paymentExpiry
            );

            if (!mysqli_stmt_execute($orderStmt)) {
                throw new Exception("Gagal menyimpan data pesanan: " . mysqli_stmt_error($orderStmt));
            }
            $orderId = mysqli_insert_id($con);
            mysqli_stmt_close($orderStmt);

            // 2. Insert into order_items table and update stock
            $itemStmt = mysqli_prepare($con, "
                INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if (!$itemStmt) {
                throw new Exception("Gagal menyiapkan detail pesanan: " . mysqli_error($con));
            }

            $stockStmt = mysqli_prepare($con, "
                UPDATE products SET product_stock = GREATEST(0, product_stock - ?) WHERE product_id = ?
            ");
            if (!$stockStmt) {
                throw new Exception("Gagal menyiapkan update stok: " . mysqli_error($con));
            }

            foreach ($cartItems as $item) {
                $p = $item['product'];
                $pId = $p['product_id'];
                $pName = $p['product_title'];
                $pPrice = $p['product_price'];
                $qty = (int)$item['qty'];
                $sub = $item['subtotal'];

                mysqli_stmt_bind_param($itemStmt, "iisdid", $orderId, $pId, $pName, $pPrice, $qty, $sub);
                if (!mysqli_stmt_execute($itemStmt)) {
                    throw new Exception("Gagal menyimpan item pesanan: " . mysqli_stmt_error($itemStmt));
                }

                mysqli_stmt_bind_param($stockStmt, "ii", $qty, $pId);
                mysqli_stmt_execute($stockStmt);
            }

            mysqli_stmt_close($itemStmt);
            mysqli_stmt_close($stockStmt);

            mysqli_commit($con);

            // Clear Cart & Coupon
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon']);

            // Redirect to Success
            header("Location: order_success.php?id=" . $orderId);
            exit;

        } catch (Exception $e) {
            mysqli_rollback($con);
            $errors[] = "Gagal memproses pesanan: " . $e->getMessage();
        }
    }
}

$pageTitle = "Checkout";
include 'includes/header.php';
?>

<div class="container" style="padding: 40px 20px 80px;">
    <div style="margin-bottom: 30px;">
        <span class="section-tag">final sweet step 🍰</span>
        <h1 style="font-size:2.6rem;">Checkout Pesanan</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <?php foreach ($errors as $err): ?>
                    <div>• <?= htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form action="checkout.php" method="POST">
        <div class="checkout-grid">
            <!-- Left: Shipping & Payment Form -->
            <div class="card-box">
                <h3 style="font-size:1.3rem; margin-bottom: 20px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <i class="fa-solid fa-location-dot" style="color:var(--pink);"></i> Informasi Pengiriman
                </h3>

                <div class="form-group">
                    <label class="form-label">Nama Penerima *</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="Nama Lengkap" value="<?= htmlspecialchars($_POST['customer_name'] ?? ($userData['first_name'] ? $userData['first_name'] . ' ' . $userData['last_name'] : '')); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email Konfirmasi *</label>
                        <input type="email" name="customer_email" class="form-control" placeholder="kamu@email.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? $userData['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">No. WhatsApp / HP *</label>
                        <input type="text" name="customer_phone" class="form-control" placeholder="08123456789" value="<?= htmlspecialchars($_POST['customer_phone'] ?? $userData['mobile']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Lengkap Pengiriman *</label>
                    <textarea name="shipping_address" class="form-control" rows="3" placeholder="Nama Jalan, Nomor Rumah, RT/RW, Kelurahan, Kecamatan" required><?= htmlspecialchars($_POST['shipping_address'] ?? $userData['address1']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kota / Kabupaten *</label>
                        <input type="text" name="shipping_city" class="form-control" value="<?= htmlspecialchars($_POST['shipping_city'] ?? ($userData['address2'] ?: 'Yogyakarta')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan Tambahan (Opsional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Patokan rumah, pesan untuk kurir..." value="<?= htmlspecialchars($_POST['notes'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Bakery Schedule & Custom Cake Details -->
                <h3 style="font-size:1.3rem; margin: 30px 0 20px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <i class="fa-solid fa-cake-candles" style="color:var(--pink);"></i> Jadwal Kirim & Personalisasi Kue
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-regular fa-calendar" style="color:var(--pink);"></i> Tanggal Pengantaran / Pick-up *</label>
                        <input type="date" name="delivery_date" class="form-control" min="<?= date('Y-m-d'); ?>" value="<?= htmlspecialchars($_POST['delivery_date'] ?? date('Y-m-d')); ?>" required>
                        <small style="color:var(--text-muted); font-size:0.8rem;">Freshly baked sesuai tanggal yang kamu pilih ♡</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-regular fa-clock" style="color:var(--blue-dark);"></i> Slot Waktu Pengiriman *</label>
                        <select name="delivery_time" class="form-control" required>
                            <option value="Slot 1: 09:00 - 12:00 WIB (Pagi)">Slot Pagi: 09:00 - 12:00 WIB (Fresh Morning)</option>
                            <option value="Slot 2: 12:00 - 15:00 WIB (Siang)">Slot Siang: 12:00 - 15:00 WIB (Afternoon Treats)</option>
                            <option value="Slot 3: 15:00 - 18:00 WIB (Sore)">Slot Sore: 15:00 - 18:00 WIB (Golden Hour)</option>
                            <option value="Slot 4: 18:00 - 21:00 WIB (Malam)">Slot Malam: 18:00 - 21:00 WIB (Night Cravings)</option>
                        </select>
                        <small style="color:var(--text-muted); font-size:0.8rem;">Kue dikirim dengan kurir motor & thermal box.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-pen-fancy" style="color:var(--pink-dark);"></i> Ucapan di Atas Kue / Kartu (Opsional)</label>
                        <input type="text" name="cake_message" class="form-control" placeholder="Contoh: Happy Birthday Zahra! 🎂 (Maks. 30 huruf)" maxlength="60" value="<?= htmlspecialchars($_POST['cake_message'] ?? ''); ?>">
                        <small style="color:var(--text-muted); font-size:0.8rem;">Dituliskan dengan chocolate piping di atas cake / topper.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-wand-magic-sparkles" style="color:#f59e0b;"></i> Pilihan Lilin Ulang Tahun</label>
                        <select name="candle_request" class="form-control">
                            <option value="Lilin Spiral Aesthetic (Free)">Lilin Spiral Pastel Aesthetic (Gratis 1 Set)</option>
                            <option value="Lilin Angka Pastel">Lilin Angka Pastel (Tulis angka di catatan)</option>
                            <option value="Lilin Kembang Api Sparkler">Lilin Kembang Api Sparkler (Aesthetic)</option>
                            <option value="Tanpa Lilin">Tanpa Lilin (Konsumsi Pribadi)</option>
                        </select>
                        <small style="color:var(--text-muted); font-size:0.8rem;">Disediakan gratis untuk melengkapi perayaanmu!</small>
                    </div>
                </div>

                <h3 style="font-size:1.3rem; margin: 30px 0 20px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <i class="fa-solid fa-credit-card" style="color:var(--blue-dark);"></i> Metode Pembayaran
                </h3>

                <!-- Payment Methods Grid: QRIS, BCA, BRI, BNI, Mandiri, BSI, COD -->
                <div class="payment-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:12px; margin-bottom: 22px;">
                    <!-- 1. QRIS (Pilihan Utama) -->
                    <label class="payment-method-card active" id="card-qris" onclick="selectPaymentMethod('QRIS')" style="background:#fff6fa; border:2px solid var(--pink); border-radius:var(--radius-md); padding:14px; display:flex; align-items:flex-start; gap:12px; cursor:pointer; transition:all 0.25s ease;">
                        <input type="radio" name="payment_method" value="QRIS" checked style="margin-top:4px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:1.02rem; color:var(--text);"><i class="fa-solid fa-qrcode" style="color:var(--pink);"></i> QRIS</strong>
                                <span style="background:#ecfdf5; color:#047857; font-size:0.7rem; padding:2px 7px; border-radius:12px; font-weight:700;">Instan</span>
                            </div>
                            <small style="display:block; color:var(--text-muted); margin-top:3px; font-size:0.8rem;">GoPay, OVO, ShopeePay, m-Banking</small>
                        </div>
                    </label>

                    <!-- 2. Bank BCA -->
                    <label class="payment-method-card" id="card-bca" onclick="selectPaymentMethod('BCA')" style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; display:flex; align-items:flex-start; gap:12px; cursor:pointer; transition:all 0.25s ease;">
                        <input type="radio" name="payment_method" value="BCA" style="margin-top:4px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:1.02rem; color:var(--text);"><i class="fa-solid fa-building-columns" style="color:#0066AE;"></i> Bank BCA</strong>
                                <span style="background:#eff6ff; color:#0066AE; font-size:0.7rem; padding:2px 7px; border-radius:12px; font-weight:700;">VA</span>
                            </div>
                            <small style="display:block; color:var(--text-muted); margin-top:3px; font-size:0.8rem;">BCA Virtual Account (Otomatis)</small>
                        </div>
                    </label>

                    <!-- 3. Bank BRI -->
                    <label class="payment-method-card" id="card-bri" onclick="selectPaymentMethod('BRI')" style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; display:flex; align-items:flex-start; gap:12px; cursor:pointer; transition:all 0.25s ease;">
                        <input type="radio" name="payment_method" value="BRI" style="margin-top:4px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:1.02rem; color:var(--text);"><i class="fa-solid fa-building-columns" style="color:#00529C;"></i> Bank BRI</strong>
                                <span style="background:#eff6ff; color:#00529C; font-size:0.7rem; padding:2px 7px; border-radius:12px; font-weight:700;">BRIVA</span>
                            </div>
                            <small style="display:block; color:var(--text-muted); margin-top:3px; font-size:0.8rem;">BRI Virtual Account (Otomatis)</small>
                        </div>
                    </label>

                    <!-- 4. Bank BNI -->
                    <label class="payment-method-card" id="card-bni" onclick="selectPaymentMethod('BNI')" style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; display:flex; align-items:flex-start; gap:12px; cursor:pointer; transition:all 0.25s ease;">
                        <input type="radio" name="payment_method" value="BNI" style="margin-top:4px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:1.02rem; color:var(--text);"><i class="fa-solid fa-building-columns" style="color:#005E54;"></i> Bank BNI</strong>
                                <span style="background:#f0fdf4; color:#005E54; font-size:0.7rem; padding:2px 7px; border-radius:12px; font-weight:700;">VA</span>
                            </div>
                            <small style="display:block; color:var(--text-muted); margin-top:3px; font-size:0.8rem;">BNI Virtual Account (Otomatis)</small>
                        </div>
                    </label>

                    <!-- 5. Bank Mandiri -->
                    <label class="payment-method-card" id="card-mandiri" onclick="selectPaymentMethod('Mandiri')" style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; display:flex; align-items:flex-start; gap:12px; cursor:pointer; transition:all 0.25s ease;">
                        <input type="radio" name="payment_method" value="Mandiri" style="margin-top:4px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:1.02rem; color:var(--text);"><i class="fa-solid fa-building-columns" style="color:#003d79;"></i> Mandiri</strong>
                                <span style="background:#eff6ff; color:#003d79; font-size:0.7rem; padding:2px 7px; border-radius:12px; font-weight:700;">Bill</span>
                            </div>
                            <small style="display:block; color:var(--text-muted); margin-top:3px; font-size:0.8rem;">Mandiri Bill Payment (Otomatis)</small>
                        </div>
                    </label>

                    <!-- 6. Transfer Bank BSI -->
                    <label class="payment-method-card" id="card-bank" onclick="selectPaymentMethod('Bank Transfer')" style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; display:flex; align-items:flex-start; gap:12px; cursor:pointer; transition:all 0.25s ease;">
                        <input type="radio" name="payment_method" value="Bank Transfer" style="margin-top:4px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:1.02rem; color:var(--text);"><i class="fa-solid fa-building-columns" style="color:#00A39D;"></i> Bank BSI</strong>
                                <span style="background:#f0fdfa; color:#00A39D; font-size:0.7rem; padding:2px 7px; border-radius:12px; font-weight:700;">Transfer</span>
                            </div>
                            <small style="display:block; color:var(--text-muted); margin-top:3px; font-size:0.8rem;">Bank Syariah Indonesia</small>
                        </div>
                    </label>

                    <!-- 7. COD -->
                    <label class="payment-method-card" id="card-cod" onclick="selectPaymentMethod('COD')" style="background:#f8fafc; border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; display:flex; align-items:flex-start; gap:12px; cursor:pointer; transition:all 0.25s ease;">
                        <input type="radio" name="payment_method" value="COD" style="margin-top:4px;">
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:1.02rem; color:var(--text);"><i class="fa-solid fa-hand-holding-dollar" style="color:#f59e0b;"></i> COD</strong>
                                <span style="background:#fffbeb; color:#d97706; font-size:0.7rem; padding:2px 7px; border-radius:12px; font-weight:700;">Ongkir 10rb</span>
                            </div>
                            <small style="display:block; color:var(--text-muted); margin-top:3px; font-size:0.8rem;">Bayar tunai • Ongkir otomatis Rp 10.000</small>
                        </div>
                    </label>
                </div>

                <!-- Dynamic Payment Instructions Box -->
                <div id="payment-details-wrapper" style="margin-bottom: 20px;">
                    <!-- Detail 1: QRIS -->
                    <div id="detail-qris" class="payment-detail-box" style="display:block; background:#fff8fb; border:1.5px solid #fbcfe8; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#fce7f3; color:var(--pink); display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-qrcode"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:var(--text);">Pembayaran Instan QRIS SweetPop</h4>
                                    <small style="color:var(--text-muted);">Mendukung semua aplikasi e-Wallet & m-Banking berlogo QRIS</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#ecfdf5; color:#047857; border-color:#a7f3d0; font-size:0.75rem;">
                                <i class="fa-solid fa-bolt"></i> Verifikasi Otomatis
                            </span>
                        </div>

                        <div style="text-align:center; padding:20px; background:#ffffff; border-radius:16px; border:2px dashed #f472b6; max-width:320px; margin:0 auto 18px; box-shadow: 0 8px 24px rgba(244, 114, 182, 0.12);">
                            <img src="assets/img/qris_sweetpop.png" alt="QRIS SweetPop" style="width:230px; height:230px; object-fit:contain; border-radius:10px; display:block; margin:0 auto;">
                            <div style="margin-top:12px; font-weight:800; color:var(--text); font-size:0.95rem;">
                                ✿ SWEETPOP BAKERY YOGYAKARTA ✿
                            </div>
                            <small style="color:var(--text-muted); display:block; font-size:0.75rem;">NMID: ID1020021332450 • Standar QRIS Nasional</small>
                            <a href="assets/img/qris_sweetpop.png" download="QRIS_SweetPop.png" class="btn-sweet btn-sweet-light" style="padding:6px 14px; font-size:0.8rem; margin-top:10px; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-download"></i> Unduh Barcode QRIS
                            </a>
                        </div>

                        <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #fce7f3; font-size:0.88rem; color:var(--text);">
                            <strong style="color:var(--pink-dark); display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-question"></i> Cara Pembayaran:</strong>
                            <ol style="margin:0; padding-left:20px; line-height:1.6; color:#475569;">
                                <li>Buka aplikasi <strong>GoPay, OVO, ShopeePay, Dana, BCA Mobile, Livin, BRImo</strong>, atau m-Banking Anda.</li>
                                <li>Pilih menu <strong>Scan / Bayar QRIS</strong>.</li>
                                <li>Arahkan kamera ke barcode di atas dan masukkan nominal sesuai total tagihan (<span class="display-current-total" style="font-weight:700; color:var(--pink-dark);"><?= format_rupiah($grandTotal); ?></span>).</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Detail 2: BCA Virtual Account -->
                    <div id="detail-bca" class="payment-detail-box" style="display:none; background:#f0f7ff; border:1.5px solid #bae6fd; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#e0f2fe; color:#0066AE; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:#0369a1;">BCA Virtual Account (Midtrans)</h4>
                                    <small style="color:#0284c7;">Verifikasi otomatis 24 jam via m-BCA, myBCA, KlikBCA & ATM</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#e0f2fe; color:#0066AE; border-color:#bae6fd; font-size:0.75rem;">
                                <i class="fa-solid fa-bolt"></i> Otomatis & Cepat
                            </span>
                        </div>

                        <div style="background:#ffffff; border:1.5px solid #bae6fd; border-radius:14px; padding:18px; margin-bottom:16px;">
                            <div style="color:#0369a1; font-weight:700; font-size:0.95rem; margin-bottom:6px;">
                                <i class="fa-solid fa-circle-info"></i> Nomor Virtual Account Otomatis
                            </div>
                            <p style="margin:0; color:#334155; font-size:0.88rem; line-height:1.5;">
                                Nomor <strong>BCA Virtual Account</strong> unik untuk pesanan Anda akan otomatis diterbitkan setelah menekan tombol <strong>Konfirmasi & Bayar Sekarang</strong>. Anda juga dapat melakukan uji coba pelunasan instan di <strong>Midtrans BCA Simulator</strong>.
                            </p>
                        </div>

                        <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #e0f2fe; font-size:0.88rem; color:#0369a1;">
                            <strong style="color:#0066AE; display:block; margin-bottom:6px;"><i class="fa-solid fa-mobile-screen"></i> Cara Pembayaran m-BCA:</strong>
                            <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                                <li>Buka aplikasi <strong>BCA mobile</strong> > pilih <strong>m-Transfer</strong>.</li>
                                <li>Pilih <strong>BCA Virtual Account</strong> dan masukkan nomor VA yang tertera pada faktur pesanan.</li>
                                <li>Pastikan tagihan bernilai <span class="display-current-total" style="font-weight:700; color:#0066AE;"><?= format_rupiah($grandTotal); ?></span> dan selesaikan transaksi.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Detail 3: BRI Virtual Account -->
                    <div id="detail-bri" class="payment-detail-box" style="display:none; background:#f0f9ff; border:1.5px solid #bfdbfe; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#dbeafe; color:#00529C; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:#1e40af;">BRI Virtual Account (BRIVA)</h4>
                                    <small style="color:#1d4ed8;">Verifikasi otomatis 24 jam via BRImo, Internet Banking, & ATM BRI</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#dbeafe; color:#00529C; border-color:#bfdbfe; font-size:0.75rem;">
                                <i class="fa-solid fa-bolt"></i> Otomatis & Cepat
                            </span>
                        </div>

                        <div style="background:#ffffff; border:1.5px solid #bfdbfe; border-radius:14px; padding:18px; margin-bottom:16px;">
                            <div style="color:#1e40af; font-weight:700; font-size:0.95rem; margin-bottom:6px;">
                                <i class="fa-solid fa-circle-info"></i> Nomor BRIVA Otomatis
                            </div>
                            <p style="margin:0; color:#334155; font-size:0.88rem; line-height:1.5;">
                                Nomor <strong>BRIVA (BRI Virtual Account)</strong> resmi akan diterbitkan seketika setelah Anda menekan tombol <strong>Konfirmasi & Bayar Sekarang</strong>.
                            </p>
                        </div>

                        <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #dbeafe; font-size:0.88rem; color:#1e40af;">
                            <strong style="color:#00529C; display:block; margin-bottom:6px;"><i class="fa-solid fa-mobile-screen"></i> Cara Pembayaran BRImo:</strong>
                            <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                                <li>Buka aplikasi <strong>BRImo</strong> > pilih menu <strong>Tagihan / BRIVA</strong>.</li>
                                <li>Masukkan nomor Virtual Account pesanan Anda.</li>
                                <li>Periksa total nominal (<span class="display-current-total" style="font-weight:700; color:#00529C;"><?= format_rupiah($grandTotal); ?></span>) dan masukkan PIN Anda.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Detail 4: BNI Virtual Account -->
                    <div id="detail-bni" class="payment-detail-box" style="display:none; background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#dcfce7; color:#005E54; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:#166534;">BNI Virtual Account (Midtrans)</h4>
                                    <small style="color:#15803d;">Verifikasi otomatis via BNI Mobile Banking, ATM BNI, & Bank Lain</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#dcfce7; color:#005E54; border-color:#bbf7d0; font-size:0.75rem;">
                                <i class="fa-solid fa-bolt"></i> Otomatis & Cepat
                            </span>
                        </div>

                        <div style="background:#ffffff; border:1.5px solid #bbf7d0; border-radius:14px; padding:18px; margin-bottom:16px;">
                            <div style="color:#166534; font-weight:700; font-size:0.95rem; margin-bottom:6px;">
                                <i class="fa-solid fa-circle-info"></i> Nomor BNI Virtual Account Otomatis
                            </div>
                            <p style="margin:0; color:#334155; font-size:0.88rem; line-height:1.5;">
                                Nomor <strong>BNI Virtual Account</strong> resmi akan muncul di halaman konfirmasi setelah Anda menyelesaikan checkout.
                            </p>
                        </div>

                        <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #dcfce7; font-size:0.88rem; color:#166534;">
                            <strong style="color:#005E54; display:block; margin-bottom:6px;"><i class="fa-solid fa-mobile-screen"></i> Cara Pembayaran BNI Mobile:</strong>
                            <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                                <li>Buka aplikasi <strong>BNI Mobile Banking</strong> > pilih <strong>Transfer</strong>.</li>
                                <li>Pilih <strong>Virtual Account Billing</strong> > masukkan nomor VA Anda.</li>
                                <li>Konfirmasi nominal (<span class="display-current-total" style="font-weight:700; color:#005E54;"><?= format_rupiah($grandTotal); ?></span>) dan masukkan Password Transaksi.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Detail 5: Mandiri Bill Payment -->
                    <div id="detail-mandiri" class="payment-detail-box" style="display:none; background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#dbeafe; color:#003d79; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:#1e3a8a;">Bank Mandiri (Bill Payment / E-Channel)</h4>
                                    <small style="color:#1d4ed8;">Verifikasi otomatis via Livin' by Mandiri & ATM Mandiri</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#dbeafe; color:#003d79; border-color:#bfdbfe; font-size:0.75rem;">
                                <i class="fa-solid fa-bolt"></i> Otomatis & Cepat
                            </span>
                        </div>

                        <div style="background:#ffffff; border:1.5px solid #bfdbfe; border-radius:14px; padding:18px; margin-bottom:16px;">
                            <div style="color:#1e3a8a; font-weight:700; font-size:0.95rem; margin-bottom:6px;">
                                <i class="fa-solid fa-circle-info"></i> Kode Perusahaan & Nomor Tagihan Otomatis
                            </div>
                            <p style="margin:0; color:#334155; font-size:0.88rem; line-height:1.5;">
                                Kode Perusahaan (<strong>70012</strong>) dan <strong>Bill Key (Nomor Tagihan)</strong> unik pesanan akan langsung diterbitkan untuk pembayaran melalui aplikasi <strong>Livin' by Mandiri</strong>.
                            </p>
                        </div>

                        <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #dbeafe; font-size:0.88rem; color:#1e3a8a;">
                            <strong style="color:#003d79; display:block; margin-bottom:6px;"><i class="fa-solid fa-mobile-screen"></i> Cara Pembayaran Livin' by Mandiri:</strong>
                            <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                                <li>Buka aplikasi <strong>Livin' by Mandiri</strong> > pilih menu <strong>Bayar</strong>.</li>
                                <li>Ketik penyedia jasa <strong>70012</strong> (atau Midtrans).</li>
                                <li>Masukkan <strong>Nomor Tagihan / Bill Key</strong> dari faktur pesanan.</li>
                                <li>Periksa total (<span class="display-current-total" style="font-weight:700; color:#003d79;"><?= format_rupiah($grandTotal); ?></span>) dan masukkan PIN Livin'.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Detail 6: Transfer Bank BSI -->
                    <div id="detail-bank" class="payment-detail-box" style="display:none; background:#f0fdfa; border:1.5px solid #99f6e4; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#ccfbf1; color:#00A39D; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-building-columns"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:#134e4a;">Transfer Bank BSI (Bank Syariah Indonesia)</h4>
                                    <small style="color:#0f766e;">Transfer aman via ATM, BSI Mobile, atau Bank Lain</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#ccfbf1; color:#0f766e; border-color:#5eead4; font-size:0.75rem;">
                                Kode Bank: 451
                            </span>
                        </div>

                        <!-- Account Box -->
                        <div style="background:#ffffff; border:1.5px solid #5eead4; border-radius:14px; padding:20px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow: 0 4px 15px rgba(0, 163, 157, 0.08);">
                            <div>
                                <small style="color:#0f766e; font-weight:700; text-transform:uppercase; font-size:0.75rem; display:block;">Nomor Rekening BSI</small>
                                <div style="font-family:'Courier New', monospace; font-size:1.8rem; font-weight:800; color:#134e4a; letter-spacing:1px;" id="bsi-acc-number">7326007751</div>
                                <div style="font-size:0.95rem; color:#047857; font-weight:600; margin-top:2px;">
                                    <i class="fa-solid fa-user-check"></i> Atas Nama: <strong>Zahra Rokhadatul Aisy</strong>
                                </div>
                            </div>
                            <button type="button" class="btn-sweet" onclick="copyText('7326007751', this)" style="background:#00A39D; color:#ffffff; padding:10px 18px; border:none; font-size:0.92rem;">
                                <i class="fa-regular fa-copy"></i> Salin No. Rekening
                            </button>
                        </div>

                        <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #ccfbf1; font-size:0.88rem; color:#134e4a;">
                            <strong style="color:#0f766e; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Transfer:</strong>
                            <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                                <li>Pilih transfer antar rekening BSI (atau Antar Bank dengan kode <strong>451</strong>).</li>
                                <li>Masukkan no. rekening <strong>7326007751</strong> a.n <strong>Zahra Rokhadatul Aisy</strong>.</li>
                                <li>Transfer tepat sesuai nominal tagihan (<span class="display-current-total" style="font-weight:700; color:#0f766e;"><?= format_rupiah($grandTotal); ?></span>).</li>
                                <li>Simpan tangkapan layar / bukti transfer untuk verifikasi cepat melalui WhatsApp.</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Detail 7: COD (Bayar di Tempat) -->
                    <div id="detail-cod" class="payment-detail-box" style="display:none; background:#fffbeb; border:1.5px solid #fde68a; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-hand-holding-dollar"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:#92400e;">Cash on Delivery (Bayar Tunai di Tempat)</h4>
                                    <small style="color:#b45309;">Khusus Area Yogyakarta & Sekitarnya</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#fef3c7; color:#b45309; border-color:#fcd34d; font-size:0.75rem;">
                                <i class="fa-solid fa-motorcycle"></i> Kurir SweetPop
                            </span>
                        </div>

                        <!-- Flat Ongkir COD Rp 10.000 Notice -->
                        <div style="background:#ffffff; border:1.5px solid #fde68a; border-radius:14px; padding:18px 20px; margin-bottom:16px; display:flex; align-items:center; gap:14px; box-shadow:0 4px 14px rgba(245, 158, 11, 0.06);">
                            <div style="width:46px; height:46px; border-radius:12px; background:#fffbeb; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:1.4rem; flex-shrink:0;">
                                <i class="fa-solid fa-truck-fast"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px;">
                                    <span style="font-weight:800; color:#92400e; font-size:1.02rem;">
                                        Ongkos Kirim COD: <span style="color:#b45309;">Rp 10.000</span>
                                    </span>
                                    <span style="background:#ecfdf5; color:#047857; font-size:0.72rem; padding:2px 8px; border-radius:12px; font-weight:700;">
                                        Otomatis Diterapkan
                                    </span>
                                </div>
                                <small style="display:block; color:#78350f; font-size:0.84rem; margin-top:3px; line-height:1.4;">
                                    Khusus metode pembayaran COD, biaya ongkir otomatis menjadi <strong>Rp 10.000</strong> ke seluruh area jangkauan pengiriman SweetPop.
                                </small>
                            </div>
                        </div>

                        <!-- Important Exact Cash Warning -->
                        <div style="background:#fffbeb; border-left:4px solid #f59e0b; border-radius:8px; padding:14px 18px;">
                            <strong style="color:#b45309; font-size:0.95rem; display:block; margin-bottom:4px;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Pengingat Uang Pas:
                            </strong>
                            <p style="margin:0; color:#78350f; font-size:0.9rem; line-height:1.5;">
                                Mohon siapkan <strong>uang pas</strong> sebesar <span class="display-current-total" style="font-weight:800; color:#b45309; font-size:1.05rem;"><?= format_rupiah($grandTotal); ?></span> ketika kurir tiba mengantarkan pesanan kue kamu agar transaksi berlangsung cepat & praktis ♡
                            </p>
                        </div>
                    </div>

                    <!-- Detail 4: ShopeePay -->
                    <div id="detail-shopeepay" class="payment-detail-box" style="display:none; background:#fff7ed; border:1.5px solid #fed7aa; border-radius:var(--radius-lg); padding:24px; animation:fadeIn 0.3s ease;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#ffedd5; color:#ee4d2d; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                    <i class="fa-solid fa-wallet"></i>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:1.15rem; color:#9a3412;">Pembayaran ShopeePay E-Wallet</h4>
                                    <small style="color:#c2410c;">Transfer instan tanpa biaya admin antar ShopeePay</small>
                                </div>
                            </div>
                            <span class="hero-badge" style="background:#ffedd5; color:#c2410c; border-color:#fdba74; font-size:0.75rem;">
                                Bebas Admin
                            </span>
                        </div>

                        <!-- ShopeePay Account Box -->
                        <div style="background:#ffffff; border:1.5px solid #fdba74; border-radius:14px; padding:20px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow: 0 4px 15px rgba(238, 77, 45, 0.08);">
                            <div>
                                <small style="color:#c2410c; font-weight:700; text-transform:uppercase; font-size:0.75rem; display:block;">Nomor ShopeePay / WhatsApp</small>
                                <div style="font-family:'Courier New', monospace; font-size:1.8rem; font-weight:800; color:#9a3412; letter-spacing:1px;" id="shopee-phone-number">0895327517570</div>
                                <div style="font-size:0.95rem; color:#ea580c; font-weight:600; margin-top:2px;">
                                    <i class="fa-solid fa-user-check"></i> Atas Nama: <strong>Zahra Rokhadatul Aisy</strong>
                                </div>
                            </div>
                            <button type="button" class="btn-sweet" onclick="copyText('0895327517570', this)" style="background:#ee4d2d; color:#ffffff; padding:10px 18px; border:none; font-size:0.92rem;">
                                <i class="fa-regular fa-copy"></i> Salin No. ShopeePay
                            </button>
                        </div>

                        <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #fed7aa; font-size:0.88rem; color:#9a3412;">
                            <strong style="color:#c2410c; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Transfer ShopeePay:</strong>
                            <ol style="margin:0; padding-left:20px; line-height:1.6; color:#475569;">
                                <li>Buka aplikasi <strong>Shopee</strong> dan masuk ke menu <strong>ShopeePay</strong>.</li>
                                <li>Pilih opsi <strong>Transfer</strong> lalu pilih <strong>Transfer ke Kontak / No. HP</strong>.</li>
                                <li>Masukkan nomor <strong>0895327517570</strong> (Pastikan akun: <strong>Zahra Rokhadatul Aisy</strong>).</li>
                                <li>Ketik nominal tagihan (<span class="display-current-total" style="font-weight:700; color:#ea580c;"><?= format_rupiah($grandTotal); ?></span>) dan selesaikan pembayaran.</li>
                            </ol>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Order Items Summary & Dynamic Pricing -->
            <div>
                <div class="card-box" style="position:sticky; top:100px;">
                    <h3 style="font-size:1.3rem; margin-bottom: 20px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                        Pesanan Kamu
                    </h3>

                    <div style="max-height: 280px; overflow-y:auto; margin-bottom:20px; padding-right:6px;">
                        <?php foreach ($cartItems as $it): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; font-size:0.92rem;">
                                <div>
                                    <strong><?= htmlspecialchars($it['product']['product_title']); ?></strong>
                                    <div style="color:var(--text-muted); font-size:0.8rem;"><?= $it['qty']; ?> × <?= format_rupiah($it['product']['product_price']); ?></div>
                                </div>
                                <strong style="color:var(--text);"><?= format_rupiah($it['subtotal']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="border-top:1px solid var(--border); padding-top:16px; margin-bottom:12px; display:flex; justify-content:space-between; font-size:0.95rem;">
                        <span style="color:var(--text-muted);">Subtotal:</span>
                        <strong><?= format_rupiah($subtotal); ?></strong>
                    </div>

                    <?php if ($discountAmount > 0): ?>
                        <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:0.95rem; color:#047857;">
                            <span>Diskon Kupon (<?= $_SESSION['coupon']['code']; ?>):</span>
                            <strong>-<?= format_rupiah($discountAmount); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:0.95rem;">
                        <span style="color:var(--text-muted);">Ongkos Kirim:</span>
                        <strong id="shipping-cost-display"><?= $shippingCost === 0 ? '<span style="color:#047857;">GRATIS</span>' : format_rupiah($shippingCost); ?></strong>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:24px; padding-top:14px; border-top:2px dashed var(--border); font-size:1.2rem;">
                        <strong>Total Tagihan:</strong>
                        <strong id="grand-total-amount" style="color:var(--pink-dark); font-family:'Baloo 2', cursive; font-size:1.65rem;"><?= format_rupiah($grandTotal); ?></strong>
                    </div>

                    <button type="submit" id="btn-submit-order" class="btn-sweet btn-sweet-pink" style="width:100%; font-size:1.1rem; padding:14px 28px;">
                        Konfirmasi & Bayar Sekarang <i class="fa-solid fa-lock"></i>
                    </button>

                    <div style="text-align:center; margin-top:14px; font-size:0.8rem; color:var(--text-muted);">
                        <i class="fa-solid fa-shield-halved" style="color:#10b981;"></i> Transaksi terenkripsi & data pembeli terjaga aman
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Base order variables from PHP
const baseSubtotal = <?= (float)$subtotal; ?>;
const discountAmount = <?= (float)$discountAmount; ?>;
const baseShipping = <?= (float)$shippingCost; ?>;
const baseGrandTotal = <?= (float)$grandTotal; ?>;

let currentPaymentMethod = 'QRIS';

function formatRupiahJs(number) {
    return 'Rp ' + Math.round(number).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function selectPaymentMethod(method) {
    currentPaymentMethod = method;

    // Reset all card styles
    const cards = {
        'QRIS': document.getElementById('card-qris'),
        'BCA': document.getElementById('card-bca'),
        'BRI': document.getElementById('card-bri'),
        'BNI': document.getElementById('card-bni'),
        'Mandiri': document.getElementById('card-mandiri'),
        'Bank Transfer': document.getElementById('card-bank'),
        'COD': document.getElementById('card-cod'),
        'ShopeePay': document.getElementById('card-shopeepay')
    };

    const details = {
        'QRIS': document.getElementById('detail-qris'),
        'BCA': document.getElementById('detail-bca'),
        'BRI': document.getElementById('detail-bri'),
        'BNI': document.getElementById('detail-bni'),
        'Mandiri': document.getElementById('detail-mandiri'),
        'Bank Transfer': document.getElementById('detail-bank'),
        'COD': document.getElementById('detail-cod'),
        'ShopeePay': document.getElementById('detail-shopeepay')
    };

    // Update radio inputs & card active visual
    for (const [key, card] of Object.entries(cards)) {
        if (!card) continue;
        const radio = card.querySelector('input[type="radio"]');
        if (key === method) {
            radio.checked = true;
            card.style.background = '#fff6fa';
            card.style.borderColor = 'var(--pink)';
            card.style.boxShadow = '0 6px 18px rgba(255, 117, 151, 0.18)';
        } else {
            radio.checked = false;
            card.style.background = '#f8fafc';
            card.style.borderColor = 'var(--border)';
            card.style.boxShadow = 'none';
        }
    }

    // Show selected detail box, hide others
    for (const [key, box] of Object.entries(details)) {
        if (!box) continue;
        box.style.display = (key === method) ? 'block' : 'none';
    }

    // Update totals and shipping display based on payment method
    updateTotalsDisplay();
}

function updateTotalsDisplay() {
    let currentShipping = baseShipping;
    const shippingEl = document.getElementById('shipping-cost-display');

    if (currentPaymentMethod === 'COD') {
        currentShipping = 10000;
        if (shippingEl) {
            shippingEl.innerHTML = '<span style="color:#b45309; font-weight:700;">Rp 10.000</span> <small style="color:var(--text-muted); font-size:0.75rem;">(Tarif COD)</small>';
        }
    } else {
        if (shippingEl) {
            shippingEl.innerHTML = (baseShipping === 0) ? '<span style="color:#047857;">GRATIS</span>' : formatRupiahJs(baseShipping);
        }
    }

    const finalTotal = Math.max(0, baseSubtotal - discountAmount + currentShipping);
    const totalEl = document.getElementById('grand-total-amount');
    if (totalEl) {
        totalEl.textContent = formatRupiahJs(finalTotal);
    }

    // Update all dynamic displays inside detail boxes
    document.querySelectorAll('.display-current-total').forEach(el => {
        el.textContent = formatRupiahJs(finalTotal);
    });
}

function copyText(text, btn) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showCopiedFeedback(btn));
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try {
            document.execCommand('copy');
            showCopiedFeedback(btn);
        } catch(e) {}
        document.body.removeChild(ta);
    }
}

function showCopiedFeedback(btn) {
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin! ✓';
    btn.style.opacity = '0.9';
    btn.style.transform = 'scale(1.05)';
    setTimeout(() => {
        btn.innerHTML = origHtml;
        btn.style.opacity = '1';
        btn.style.transform = 'none';
    }, 2000);
}

// On page load, set up correct initial state
document.addEventListener('DOMContentLoaded', () => {
    const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (checkedRadio) {
        selectPaymentMethod(checkedRadio.value);
    } else {
        selectPaymentMethod('QRIS');
    }
});
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
.payment-method-card:hover {
    border-color: var(--pink) !important;
    background: #fffbfe !important;
}
</style>

<?php include 'includes/footer.php'; ?>

