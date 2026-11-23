<?php
$pageTitle = "Order Confirmed!";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/midtrans.php';

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if (!$orderId) {
    header("Location: index.php");
    exit;
}

// Fetch Order
$stmt = mysqli_prepare($con, "SELECT * FROM orders WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    header("Location: index.php");
    exit;
}

// Fetch Items
$itemStmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE order_id = ?");
mysqli_stmt_bind_param($itemStmt, "i", $orderId);
mysqli_stmt_execute($itemStmt);
$itemsRes = mysqli_stmt_get_result($itemStmt);
$items = [];
while ($it = mysqli_fetch_assoc($itemsRes)) {
    $items[] = $it;
}

// Check Payment Status & Expiry
$pm = $order['payment_method'];
$isQris    = stripos($pm, 'QRIS') !== false;
$isBca     = stripos($pm, 'BCA') !== false;
$isBri     = stripos($pm, 'BRI') !== false;
$isBni     = stripos($pm, 'BNI') !== false;
$isMandiri = stripos($pm, 'Mandiri') !== false;
$isBsi     = (stripos($pm, 'BSI') !== false) || (stripos($pm, 'Bank') !== false && !$isBca && !$isBri && !$isBni && !$isMandiri);
$isCod     = stripos($pm, 'COD') !== false;
$isShopee  = stripos($pm, 'Shopee') !== false;

$pStatus = $order['p_status'] ?? ($isCod ? 'Menunggu Kurir' : 'Pending');
$isPaid    = ($pStatus === 'Paid');
$isExpired = ($pStatus === 'Expired');
$isFailed  = ($pStatus === 'Failed');

$remainingSeconds = 0;
if (!$isCod && $pStatus === 'Pending' && !empty($order['payment_expiry'])) {
    $remainingSeconds = max(0, strtotime($order['payment_expiry']) - time());
    if ($remainingSeconds <= 0) {
        $pStatus = 'Expired';
        $isExpired = true;
        @mysqli_query($con, "UPDATE orders SET p_status = 'Expired', status = 'cancelled' WHERE order_id = $orderId AND p_status = 'Pending'");
    }
}

// Generate Midtrans Charges for QRIS or Banks if server key is configured
$midtransQrUrl = null;
$midtransCharge = null;
if (!empty(get_midtrans_server_key())) {
    if ($isQris) {
        $midtransQrUrl = create_midtrans_qris_charge($order);
    } elseif ($isBca) {
        $midtransCharge = create_midtrans_bank_charge($order, 'bca');
    } elseif ($isBri) {
        $midtransCharge = create_midtrans_bank_charge($order, 'bri');
    } elseif ($isBni) {
        $midtransCharge = create_midtrans_bank_charge($order, 'bni');
    } elseif ($isMandiri) {
        $midtransCharge = create_midtrans_bank_charge($order, 'mandiri');
    } elseif ($isBsi) {
        $midtransCharge = create_midtrans_bank_charge($order, 'bsi');
    }
}

// Resolved VA, Bill Key & Biller Code
$vaNumber = $midtransCharge['va_number'] ?? (!empty($order['midtrans_va_number']) ? $order['midtrans_va_number'] : '');
$billKey = $midtransCharge['bill_key'] ?? (!empty($order['midtrans_bill_key']) ? $order['midtrans_bill_key'] : '');
$billerCode = $midtransCharge['biller_code'] ?? (!empty($order['midtrans_biller_code']) ? $order['midtrans_biller_code'] : '70012');

// Format WhatsApp Confirmation Message
$settings = get_store_settings();
$adminWa = format_wa_number($settings['whatsapp']);

if (!function_exists('generate_order_wa_text')) {
    function generate_order_wa_text($order, $items, $pStatus) {
        $waText = "Halo SweetPop! 🍰\n";
        if ($pStatus === 'Paid') {
            $waText .= "✦ *KONFIRMASI PEMBAYARAN LUNAS (SETTLEMENT)* ✦\n";
        } else {
            $waText .= "Saya ingin konfirmasi pesanan saya:\n";
        }
        $waText .= "• *No. Faktur:* #SP-" . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) . "\n";
        $waText .= "• *Nama:* " . $order['customer_name'] . "\n";
        $waText .= "• *Jadwal Kirim:* " . (!empty($order['delivery_date']) ? date('d F Y', strtotime($order['delivery_date'])) : date('d F Y')) . " (" . ($order['delivery_time'] ?? 'Pagi') . ")\n";
        $waText .= "• *Metode Bayar:* " . $order['payment_method'] . " (" . ($pStatus === 'Paid' ? 'LUNAS ✓' : $pStatus) . ")\n";
        $waText .= "• *Total:* " . format_rupiah($order['total_amount']) . "\n";
        if (!empty($order['trx_id'])) {
            $waText .= "• *Ref Transaksi:* " . $order['trx_id'] . "\n";
        }
        if (!empty($order['cake_message'])) {
            $waText .= "• *Ucapan Kue:* \"" . $order['cake_message'] . "\"\n";
        }
        if (!empty($order['candle_request'])) {
            $waText .= "• *Lilin:* " . $order['candle_request'] . "\n";
        }
        $waText .= "\n*Pesanan:*\n";
        foreach ($items as $it) {
            $waText .= "- " . $it['product_name'] . " (x" . $it['quantity'] . ")\n";
        }
        $waText .= "\nAlamat: " . $order['shipping_address'] . ", " . $order['shipping_city'] . "\n";
        if ($pStatus === 'Paid') {
            $waText .= "Pesanan sudah LUNAS dan terverifikasi otomatis. Mohon segera dipanggang di bakehouse yaa kak, terima kasih! ♡";
        } else {
            $waText .= "Mohon diproses yaa kak, terima kasih! ♡";
        }
        return $waText;
    }
}

$waUrl = "https://api.whatsapp.com/send?phone=" . $adminWa . "&text=" . urlencode(generate_order_wa_text($order, $items, $pStatus));
$midtransClientKey = get_midtrans_client_key();
$isMidtransActive = is_midtrans_enabled();

include 'includes/header.php';
?>

<!-- Midtrans Snap JS (Sandbox) -->
<?php if ($isMidtransActive && !empty($midtransClientKey)): ?>
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?= htmlspecialchars($midtransClientKey); ?>"></script>
<?php endif; ?>

<!-- Confetti Library -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
<script>
function triggerSweetConfetti() {
    try {
        const count = 200;
        const defaults = { origin: { y: 0.7 } };
        function fire(particleRatio, opts) {
            confetti(Object.assign({}, defaults, opts, {
                particleCount: Math.floor(count * particleRatio),
                colors: ['#ff7597', '#7bc7ff', '#fcd34d', '#a78bfa', '#ffb3c6', '#10b981']
            }));
        }
        fire(0.25, { spread: 26, startVelocity: 55 });
        fire(0.2, { spread: 60 });
        fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
        fire(0.1, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
        fire(0.1, { spread: 120, startVelocity: 45 });
    } catch(e) {}
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if ($isPaid || $order['status'] === 'processing'): ?>
        triggerSweetConfetti();
    <?php endif; ?>
});
</script>

<div class="container" style="padding: 50px 20px 100px; max-width: 840px;">
    <div class="card-box" style="text-align:center; padding: 48px 36px; box-shadow: 0 15px 35px rgba(255, 117, 151, 0.12);">
        
        <div id="mainStatusIcon" style="width:84px; height:84px; border-radius:50%; background:<?= $isPaid ? '#ecfdf5' : ($isExpired || $isFailed ? '#fef2f2' : '#fdf2f8'); ?>; color:<?= $isPaid ? '#047857' : ($isExpired || $isFailed ? '#dc2626' : 'var(--pink)'); ?>; display:inline-flex; align-items:center; justify-content:center; font-size:2.6rem; margin-bottom:20px; box-shadow: 0 4px 15px rgba(255, 117, 151, 0.15);">
            <i id="mainStatusIconFa" class="fa-solid <?= $isPaid ? 'fa-circle-check' : ($isExpired || $isFailed ? 'fa-circle-xmark' : 'fa-receipt'); ?>"></i>
        </div>

        <span class="hero-badge" id="heroBadgeText">✦ <?= $isPaid ? 'Pembayaran Lunas Diverifikasi' : ($isExpired ? 'Pesanan Kadaluwarsa' : ($isFailed ? 'Pembayaran Dibatalkan' : 'Pesanan Berhasil Dibuat')); ?> ✦</span>
        <h1 id="mainTitleHeading" style="font-size:2.4rem; margin-bottom:10px;">
            <?= $isPaid ? 'Yay, Pembayaran Sukses Diterima! ♡' : ($isExpired ? 'Waktu Pembayaran Telah Habis' : ($isFailed ? 'Pembayaran Dibatalkan' : 'Thank You for Your Sweet Order! ♡')); ?>
        </h1>
        <p id="mainSubtitle" style="color:var(--text-muted); font-size:1.02rem; margin-bottom: 24px;">
            <?= $isPaid 
                ? 'Pembayaran telah diverifikasi secara otomatis oleh sistem. Pesananmu kini sedang disiapkan di dapur bakehouse SweetPop.' 
                : ($isExpired 
                    ? 'Batas waktu pembayaran untuk transaksi ini telah lewat. Pesanan otomatis dibatalkan sistem.' 
                    : ($isFailed 
                        ? 'Transaksi pembayaran ditolak atau dibatalkan. Silakan lakukan pemesanan ulang.' 
                        : 'Pesananmu telah dicatat di sistem. Silakan selesaikan pembayaran sebelum batas waktu berakhir.')); ?>
        </p>

        <!-- Visual Order Progress Stepper -->
        <div style="background:#ffffff; border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px 20px; margin-bottom:28px; text-align:left;">
            <div style="font-size:0.9rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:16px;">
                <i class="fa-solid fa-truck-fast" style="color:var(--pink);"></i> Status Pelacakan Pesanan:
            </div>
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; position:relative; text-align:center;">
                <!-- Step 1 -->
                <div id="stepperStep1" style="position:relative; z-index:2;">
                    <div style="width:40px; height:40px; border-radius:50%; background:#10b981; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; margin-bottom:6px; box-shadow:0 2px 8px rgba(16,185,129,0.3);">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div style="font-size:0.82rem; font-weight:700; color:#10b981;">1. Diterima</div>
                    <small style="color:var(--text-muted); font-size:0.75rem;">Sistem Mencatat</small>
                </div>
                <!-- Step 2 -->
                <div id="stepperStep2" style="position:relative; z-index:2; <?= ($isPaid || $order['status'] === 'processing') ? '' : 'opacity:0.6;'; ?>">
                    <div style="width:40px; height:40px; border-radius:50%; background:<?= ($isPaid || $order['status'] === 'processing') ? 'var(--pink)' : '#e2e8f0'; ?>; color:<?= ($isPaid || $order['status'] === 'processing') ? '#fff' : '#64748b'; ?>; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; margin-bottom:6px; <?= ($isPaid || $order['status'] === 'processing') ? 'animation: pulse 2s infinite;' : ''; ?>">
                        <i class="fa-solid fa-bowl-rice"></i>
                    </div>
                    <div style="font-size:0.82rem; font-weight:700; color:<?= ($isPaid || $order['status'] === 'processing') ? 'var(--pink-dark)' : '#64748b'; ?>;">2. Dapur Bakehouse</div>
                    <small style="color:var(--text-muted); font-size:0.75rem;"><?= ($isPaid || $order['status'] === 'processing') ? 'Sedang Dipanggang' : 'Menunggu Bayar'; ?></small>
                </div>
                <!-- Step 3 -->
                <div id="stepperStep3" style="position:relative; z-index:2; opacity:0.6;">
                    <div style="width:40px; height:40px; border-radius:50%; background:#e2e8f0; color:#64748b; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; margin-bottom:6px;">
                        <i class="fa-solid fa-motorcycle"></i>
                    </div>
                    <div style="font-size:0.82rem; font-weight:600; color:#64748b;">3. Pengantaran</div>
                    <small style="color:var(--text-muted); font-size:0.75rem;">Thermal Cool Box</small>
                </div>
                <!-- Step 4 -->
                <div id="stepperStep4" style="position:relative; z-index:2; opacity:0.6;">
                    <div style="width:40px; height:40px; border-radius:50%; background:#e2e8f0; color:#64748b; display:inline-flex; align-items:center; justify-content:center; font-size:1rem; margin-bottom:6px;">
                        <i class="fa-solid fa-cake-candles"></i>
                    </div>
                    <div style="font-size:0.82rem; font-weight:600; color:#64748b;">4. Tiba di Rumah</div>
                    <small style="color:var(--text-muted); font-size:0.75rem;">Siap Dinikmati</small>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- LIVE PAYMENT STATUS BANNERS                              -->
        <!-- ======================================================== -->
        
        <!-- Paid Banner -->
        <div id="statusBannerPaid" class="payment-status-banner paid" style="<?= $isPaid ? '' : 'display:none;'; ?>">
            <div style="display:flex; align-items:center; gap:16px; width:100%; text-align:left;">
                <div class="status-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div style="flex:1;">
                    <h3 style="margin:0; font-size:1.25rem; color:#065f46;">Pembayaran Berhasil & Lunas (Settlement) ✓</h3>
                    <p style="margin:4px 0 0; font-size:0.92rem; color:#047857;">
                        Transaksi telah diverifikasi otomatis oleh Payment Gateway. ID Referensi: <strong><?= htmlspecialchars($order['trx_id']); ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <!-- Expired Banner -->
        <div id="statusBannerExpired" class="payment-status-banner expired" style="<?= $isExpired ? '' : 'display:none;'; ?>">
            <div style="display:flex; align-items:center; gap:16px; width:100%; text-align:left;">
                <div class="status-icon"><i class="fa-solid fa-clock"></i></div>
                <div style="flex:1;">
                    <h3 style="margin:0; font-size:1.25rem; color:#991b1b;">Waktu Pembayaran Habis (Expired)</h3>
                    <p style="margin:4px 0 0; font-size:0.92rem; color:#b91c1c;">
                        Batas waktu pembayaran telah terlampaui. Pesanan dibatalkan otomatis dan stok telah dikembalikan ke katalog.
                    </p>
                </div>
            </div>
        </div>

        <!-- Failed Banner -->
        <div id="statusBannerFailed" class="payment-status-banner failed" style="<?= $isFailed ? '' : 'display:none;'; ?>">
            <div style="display:flex; align-items:center; gap:16px; width:100%; text-align:left;">
                <div class="status-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                <div style="flex:1;">
                    <h3 style="margin:0; font-size:1.25rem; color:#991b1b;">Pembayaran Ditolak / Dibatalkan</h3>
                    <p style="margin:4px 0 0; font-size:0.92rem; color:#b91c1c;">
                        Transaksi pembayaran tidak berhasil. Kakak dapat memesan ulang atau memilih metode pembayaran lain.
                    </p>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- COUNTDOWN TIMER CONTAINER (FOR NON-COD & PENDING)        -->
        <!-- ======================================================== -->
        <?php if (!$isCod): ?>
            <div id="paymentTimerContainer" class="payment-timer-box" style="<?= ($isPaid || $isExpired || $isFailed) ? 'display:none;' : ''; ?>">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <span style="font-size:0.82rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#9d174d;">
                            <i class="fa-regular fa-clock"></i> Batas Waktu Pembayaran:
                        </span>
                        <div id="countdownDisplay" style="font-family:'Baloo 2', cursive; font-size:2.5rem; font-weight:800; color:#ba007c; line-height:1.1; margin-top:2px;">
                            14:59
                        </div>
                        <small style="color:var(--text-muted); font-size:0.82rem;">Selesaikan pembayaran sebelum timer habis agar pesanan tidak dibatalkan otomatis</small>
                    </div>
                    <div>
                        <button type="button" class="btn-sweet btn-sweet-light" onclick="checkPaymentStatusNow(true)" style="padding:9px 16px; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-arrows-rotate" id="checkIcon"></i> Cek Status Pembayaran
                        </button>
                    </div>
                </div>
                <div style="height:7px; background:#fce7f3; border-radius:10px; margin-top:14px; overflow:hidden;">
                    <div id="timeProgressBar" style="height:100%; width:100%; background:linear-gradient(90deg, #ff7597, #ba007c); transition: width 1s linear;"></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ======================================================== -->
        <!-- ORDER SUMMARY BOX                                        -->
        <!-- ======================================================== -->
        <div style="background:#fff8fc; border:1px solid var(--border); border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:16px; border-bottom:1px solid var(--border-light); padding-bottom:14px; flex-wrap:wrap; gap:12px;">
                <div>
                    <small style="color:var(--text-muted); font-weight:600;">NOMOR FAKTUR</small>
                    <div style="font-size:1.4rem; font-weight:800; color:var(--pink-dark);">#SP-<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div style="text-align:right;">
                    <small style="color:var(--text-muted); font-weight:600;">METODE & STATUS BAYAR</small>
                    <div style="font-weight:700; color:var(--text); font-size:1.05rem;">
                        <?= htmlspecialchars($order['payment_method']); ?> 
                        <span id="badgePaymentState" class="hero-badge" style="font-size:0.75rem; margin-left:6px; background:<?= $isPaid ? '#ecfdf5' : ($isExpired || $isFailed ? '#fef2f2' : '#fffbeb'); ?>; color:<?= $isPaid ? '#047857' : ($isExpired || $isFailed ? '#dc2626' : '#d97706'); ?>;">
                            <?= $isPaid ? 'Lunas ✓' : ($isExpired ? 'Expired ✕' : ($isFailed ? 'Gagal ✕' : ($isCod ? 'COD (Kurir)' : 'Menunggu Bayar'))); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Delivery & Customization Specs -->
            <div style="background:#ffffff; border-radius:12px; padding:16px; border:1px solid var(--border); margin-bottom:18px; display:grid; grid-template-columns:1fr 1fr; gap:14px; font-size:0.92rem;">
                <div>
                    <strong style="color:var(--pink-dark);"><i class="fa-regular fa-calendar"></i> Tanggal Pengantaran:</strong>
                    <div><?= !empty($order['delivery_date']) ? date('l, d F Y', strtotime($order['delivery_date'])) : date('l, d F Y'); ?></div>
                </div>
                <div>
                    <strong style="color:var(--blue-dark);"><i class="fa-regular fa-clock"></i> Slot Waktu:</strong>
                    <div><?= htmlspecialchars($order['delivery_time'] ?? 'Slot 1: 09:00 - 12:00 WIB'); ?></div>
                </div>
                <?php if (!empty($order['cake_message'])): ?>
                    <div style="grid-column: span 2; border-top:1px dashed var(--border); padding-top:10px;">
                        <strong style="color:#d97706;"><i class="fa-solid fa-pen-fancy"></i> Tulisan di Atas Cake:</strong>
                        <div style="font-style:italic; font-weight:600;">"<?= htmlspecialchars($order['cake_message']); ?>"</div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($order['candle_request'])): ?>
                    <div style="grid-column: span 2; border-top:1px dashed var(--border); padding-top:10px;">
                        <strong style="color:#10b981;"><i class="fa-solid fa-wand-magic-sparkles"></i> Pilihan Lilin:</strong>
                        <div><?= htmlspecialchars($order['candle_request']); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 16px;">
                <small style="color:var(--text-muted); display:block; margin-bottom:10px; font-weight:700;">RINCIAN MENU KUE:</small>
                <?php foreach ($items as $it): ?>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.95rem;">
                        <span>• <?= htmlspecialchars($it['product_name']); ?> × <?= $it['quantity']; ?></span>
                        <strong><?= format_rupiah($it['subtotal']); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="border-top:2px dashed var(--border); padding-top:14px; display:flex; justify-content:space-between; align-items:center; font-size:1.2rem;">
                <strong>Total Tagihan:</strong>
                <strong style="color:var(--pink-dark); font-family:'Baloo 2', cursive; font-size:1.6rem;"><?= format_rupiah($order['total_amount']); ?></strong>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- PAYMENT SECTION: SINGLE BOX (REAL & SIMULATION)          -->
        <!-- ======================================================== -->
        <?php 
        $payCodeDisplay = '8808' . str_pad($order['order_id'], 4, '0', STR_PAD_LEFT);
        ?>
        <div id="paymentInstructionSection" style="position:relative;">
            <?php if ($isQris): ?>
                <!-- DETAIL QRIS: SINGLE UNIFIED BOX -->
                <div style="background:#fff8fc; border:2px solid #fbcfe8; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(255, 117, 151, 0.1);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#fce7f3; color:var(--pink); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem;">Scan Barcode QRIS & E-Wallet</h3>
                                <small style="color:var(--text-muted);">Selesaikan pembayaran instan melalui GoPay, OVO, ShopeePay, BCA, Dana, dll.</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#ecfdf5; color:#047857; font-size:0.75rem;">
                            <i class="fa-solid fa-bolt"></i> Realtime QR
                        </span>
                    </div>

                    <!-- 1. PEMBAYARAN RIIL: Scan Barcode Asli -->
                    <div style="position:relative; max-width:320px; margin:0 auto 20px;">
                        <div style="background:#ffffff; border:2px dashed #f472b6; border-radius:18px; padding:22px; text-align:center; box-shadow:0 6px 20px rgba(0,0,0,0.06);">
                            <?php if (!empty($midtransQrUrl)): ?>
                                <img src="<?= htmlspecialchars($midtransQrUrl); ?>" alt="QRIS Midtrans Sandbox" style="width:230px; height:230px; object-fit:contain; border-radius:10px; display:block; margin:0 auto; background:#fff; padding:4px;">
                            <?php else: ?>
                                <img src="assets/img/qris_sweetpop.png" alt="QRIS SweetPop" style="width:230px; height:230px; object-fit:contain; border-radius:10px; display:block; margin:0 auto;">
                            <?php endif; ?>
                            <div style="margin-top:12px; font-weight:800; font-size:0.95rem; color:var(--text);">
                                ✿ SWEETPOP BAKERY ✿
                            </div>
                            <small style="color:var(--text-muted); display:block; font-size:0.75rem;">
                                <?= !empty($midtransQrUrl) ? 'Midtrans Dynamic QRIS Sandbox • Total ' . format_rupiah($order['total_amount']) : 'NMID: ID1020021332450 • Standar Nasional QRIS'; ?>
                            </small>
                            <?php if (!empty($midtransQrUrl)): ?>
                                <a href="<?= htmlspecialchars($midtransQrUrl); ?>" target="_blank" download="QRIS_Midtrans_SP-<?= $order['order_id']; ?>.png" class="btn-sweet btn-sweet-light" style="padding:6px 14px; font-size:0.8rem; margin-top:10px; display:inline-flex; align-items:center; gap:6px;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Gambar QRIS Asli
                                </a>
                            <?php else: ?>
                                <a href="assets/img/qris_sweetpop.png" download="QRIS_SweetPop_SP-<?= $order['order_id']; ?>.png" class="btn-sweet btn-sweet-light" style="padding:6px 14px; font-size:0.8rem; margin-top:10px; display:inline-flex; align-items:center; gap:6px;">
                                    <i class="fa-solid fa-download"></i> Simpan / Unduh QR Code
                                </a>
                            <?php endif; ?>
                        </div>
                        <!-- Watermark Overlay when Paid -->
                        <div id="qrisPaidOverlay" style="display:<?= $isPaid ? 'flex' : 'none'; ?>; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(236, 253, 245, 0.92); border-radius:18px; align-items:center; justify-content:center; flex-direction:column; backdrop-filter:blur(2px);">
                            <div style="width:64px; height:64px; border-radius:50%; background:#10b981; color:#fff; display:flex; align-items:center; justify-content:center; font-size:2rem; box-shadow:0 6px 20px rgba(16,185,129,0.4); margin-bottom:10px;">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <div style="font-size:1.3rem; font-weight:800; color:#047857; letter-spacing:1px;">LUNAS / SETTLED</div>
                            <small style="color:#065f46; font-weight:600;">Terverifikasi Otomatis</small>
                        </div>
                    </div>

                    <!-- 2. PEMBAYARAN SIMULASI: QRIS Midtrans Sandbox -->
                    <?php if (!$isPaid): ?>
                        <div id="simCodeSubcard" style="background:#ffffff; border:1.5px solid #38bdf8; border-radius:14px; padding:16px 20px; margin-bottom:18px; box-shadow:0 4px 14px rgba(56, 189, 248, 0.08);">
                            <?php if (!empty($midtransQrUrl)): ?>
                                <span class="hero-badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; margin-bottom:6px;">
                                    <i class="fa-solid fa-link"></i> QR CODE IMAGE URL (MIDTRANS SANDBOX)
                                </span>
                                <div style="display:flex; gap:10px; margin-top:6px; flex-wrap:wrap;">
                                    <input type="text" value="<?= htmlspecialchars($midtransQrUrl); ?>" readonly class="form-control" style="font-family:'JetBrains Mono', monospace; font-size:0.85rem; color:#0284c7; background:#f8fafc;" id="inputMidtransQrUrl">
                                    <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= htmlspecialchars($midtransQrUrl); ?>', this)" style="background:#0284c7; color:#ffffff; padding:8px 16px; font-size:0.85rem; border:none; white-space:nowrap;">
                                        <i class="fa-regular fa-copy"></i> Salin URL QRIS
                                    </button>
                                </div>
                                <small style="color:var(--text-muted); font-size:0.82rem; display:block; margin-top:6px;">
                                    Salin URL ini lalu paste di kolom <strong>QR Code Image Url</strong> pada website resmi Midtrans: 
                                    <a href="https://simulator.sandbox.midtrans.com/qris/index" target="_blank" style="color:#0284c7; font-weight:700; text-decoration:underline;">simulator.sandbox.midtrans.com/qris/index</a>
                                </small>
                            <?php else: ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                    <div>
                                        <span class="hero-badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem; margin-bottom:4px;">
                                            <i class="fa-solid fa-flask"></i> KODE SIMULASI SANDBOX
                                        </span>
                                        <div style="font-family:'JetBrains Mono', monospace; font-size:1.7rem; font-weight:800; color:#0284c7; letter-spacing:1px; margin-top:2px;">
                                            <?= $payCodeDisplay; ?>
                                        </div>
                                        <small style="color:var(--text-muted); font-size:0.82rem; display:block; margin-top:2px;">
                                            Salin kode ini untuk simulasi di <a href="payment_simulator.php?method=qris&code=<?= $payCodeDisplay; ?>" target="_blank" style="color:#0284c7; font-weight:700; text-decoration:underline;">Payment Gateway Simulator</a> (atau pasang Server Key Midtrans di Admin Settings).
                                        </small>
                                    </div>
                                    <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= $payCodeDisplay; ?>', this)" style="background:#0284c7; color:#ffffff; padding:10px 18px; font-size:0.88rem; border:none;">
                                        <i class="fa-regular fa-copy"></i> Salin Kode
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #fce7f3; font-size:0.9rem;">
                        <strong style="color:var(--pink-dark); display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran:</strong>
                        <ol style="margin:0; padding-left:20px; line-height:1.6; color:#475569;">
                            <li><strong>Pembayaran Riil:</strong> Scan QR code di atas lewat aplikasi GoPay, OVO, ShopeePay, Dana, BCA Mobile, Livin, BRImo seharga <strong><?= format_rupiah($order['total_amount']); ?></strong>.</li>
                            <li><strong>Simulasi Midtrans Sandbox:</strong> Paste URL / Kode di atas pada <a href="https://simulator.sandbox.midtrans.com/qris/index" target="_blank" style="color:#0284c7; font-weight:700;">simulator.sandbox.midtrans.com/qris</a> untuk simulasi pembayaran resmi.</li>
                        </ol>
                    </div>
                </div>

            <?php elseif ($isBca): ?>
                <!-- DETAIL BCA VIRTUAL ACCOUNT: SINGLE UNIFIED BOX -->
                <div style="background:#f0f7ff; border:2px solid #bae6fd; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(0, 102, 174, 0.08);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#e0f2fe; color:#0066AE; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem; color:#0369a1;">Bank Central Asia (BCA) Virtual Account</h3>
                                <small style="color:#0284c7;">Verifikasi otomatis 24 jam via m-BCA, myBCA, KlikBCA & ATM BCA.</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#e0f2fe; color:#0066AE; border-color:#bae6fd; font-size:0.75rem;">
                            <i class="fa-solid fa-bolt"></i> Otomatis Midtrans
                        </span>
                    </div>

                    <!-- Virtual Account Box -->
                    <div style="background:#ffffff; border:1.5px solid #0284c7; border-radius:14px; padding:18px 20px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow:0 4px 14px rgba(2, 132, 199, 0.08);">
                        <div>
                            <span class="hero-badge" style="background:#e0f2fe; color:#0369a1; font-size:0.72rem; margin-bottom:4px;">
                                <i class="fa-solid fa-hashtag"></i> NOMOR BCA VIRTUAL ACCOUNT (SANDBOX)
                            </span>
                            <div style="font-family:'JetBrains Mono', monospace; font-size:1.65rem; font-weight:800; color:#0066AE; letter-spacing:1px; margin-top:2px;" id="bca-va-display">
                                <?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>
                            </div>
                            <div style="font-size:0.88rem; color:#334155; margin-top:3px;">
                                Total Tagihan: <strong><?= format_rupiah($order['total_amount']); ?></strong>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>', this)" style="background:#0066AE; color:#ffffff; padding:10px 18px; border:none; font-size:0.88rem;">
                                <i class="fa-regular fa-copy"></i> Salin Nomor VA
                            </button>
                            <a href="https://simulator.sandbox.midtrans.com/bca/va/index" target="_blank" class="btn-sweet btn-sweet-light" style="padding:10px 16px; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Simulator BCA
                            </a>
                        </div>
                    </div>

                    <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #bae6fd; font-size:0.9rem; color:#0369a1;">
                        <strong style="color:#0066AE; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran & Simulasi:</strong>
                        <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                            <li><strong>Simulasi Midtrans:</strong> Buka <a href="https://simulator.sandbox.midtrans.com/bca/va/index" target="_blank" style="color:#0066AE; font-weight:700;">simulator.sandbox.midtrans.com/bca/va/index</a>, tempel nomor VA di atas lalu klik <strong>Inquire</strong> & <strong>Pay</strong>. Status pesanan akan seketika berubah menjadi <strong>LUNAS ✓</strong>.</li>
                            <li><strong>m-BCA (BCA mobile):</strong> Pilih menu <strong>m-Transfer</strong> > <strong>BCA Virtual Account</strong> > masukkan nomor VA di atas > konfirmasi nominal <strong><?= format_rupiah($order['total_amount']); ?></strong> > masukkan PIN.</li>
                            <li><strong>myBCA:</strong> Pilih <strong>Transfer</strong> > <strong>Virtual Account</strong> > masukkan nomor VA > konfirmasi dan bayar.</li>
                        </ol>
                    </div>
                </div>

            <?php elseif ($isBri): ?>
                <!-- DETAIL BRI VIRTUAL ACCOUNT (BRIVA): SINGLE UNIFIED BOX -->
                <div style="background:#f0f9ff; border:2px solid #bfdbfe; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(0, 82, 156, 0.08);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#dbeafe; color:#00529C; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem; color:#1e40af;">Bank BRI (BRIVA Virtual Account)</h3>
                                <small style="color:#1d4ed8;">Verifikasi otomatis 24 jam via BRImo, Internet Banking BRI & ATM BRI.</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#dbeafe; color:#00529C; border-color:#bfdbfe; font-size:0.75rem;">
                            <i class="fa-solid fa-bolt"></i> Otomatis Midtrans
                        </span>
                    </div>

                    <!-- BRIVA Box -->
                    <div style="background:#ffffff; border:1.5px solid #1d4ed8; border-radius:14px; padding:18px 20px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow:0 4px 14px rgba(29, 78, 216, 0.08);">
                        <div>
                            <span class="hero-badge" style="background:#dbeafe; color:#1e40af; font-size:0.72rem; margin-bottom:4px;">
                                <i class="fa-solid fa-hashtag"></i> NOMOR BRIVA (BRI VIRTUAL ACCOUNT)
                            </span>
                            <div style="font-family:'JetBrains Mono', monospace; font-size:1.65rem; font-weight:800; color:#00529C; letter-spacing:1px; margin-top:2px;" id="bri-va-display">
                                <?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>
                            </div>
                            <div style="font-size:0.88rem; color:#334155; margin-top:3px;">
                                Total Tagihan: <strong><?= format_rupiah($order['total_amount']); ?></strong>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>', this)" style="background:#00529C; color:#ffffff; padding:10px 18px; border:none; font-size:0.88rem;">
                                <i class="fa-regular fa-copy"></i> Salin Nomor BRIVA
                            </button>
                            <a href="https://simulator.sandbox.midtrans.com/bri/va/index" target="_blank" class="btn-sweet btn-sweet-light" style="padding:10px 16px; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Simulator BRI
                            </a>
                        </div>
                    </div>

                    <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #bfdbfe; font-size:0.9rem; color:#1e40af;">
                        <strong style="color:#00529C; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran & Simulasi:</strong>
                        <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                            <li><strong>Simulasi Midtrans:</strong> Buka <a href="https://simulator.sandbox.midtrans.com/bri/va/index" target="_blank" style="color:#00529C; font-weight:700;">simulator.sandbox.midtrans.com/bri/va/index</a>, masukkan nomor BRIVA lalu klik <strong>Inquire</strong> & <strong>Pay</strong>. Transaksi otomatis LUNAS.</li>
                            <li><strong>Aplikasi BRImo:</strong> Buka BRImo > pilih menu <strong>Tagihan / BRIVA</strong> > masukkan nomor BRIVA > konfirmasi tagihan <strong><?= format_rupiah($order['total_amount']); ?></strong> > masukkan PIN BRImo.</li>
                            <li><strong>ATM BRI:</strong> Pilih Transaksi Lain > Pembayaran > Lainnya > BRIVA > masukkan nomor BRIVA.</li>
                        </ol>
                    </div>
                </div>

            <?php elseif ($isBni): ?>
                <!-- DETAIL BNI VIRTUAL ACCOUNT: SINGLE UNIFIED BOX -->
                <div style="background:#f0fdf4; border:2px solid #bbf7d0; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(0, 94, 84, 0.08);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#dcfce7; color:#005E54; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem; color:#166534;">Bank BNI Virtual Account</h3>
                                <small style="color:#15803d;">Verifikasi otomatis 24 jam via BNI Mobile Banking, Internet Banking & ATM BNI.</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#dcfce7; color:#005E54; border-color:#bbf7d0; font-size:0.75rem;">
                            <i class="fa-solid fa-bolt"></i> Otomatis Midtrans
                        </span>
                    </div>

                    <!-- BNI VA Box -->
                    <div style="background:#ffffff; border:1.5px solid #16a34a; border-radius:14px; padding:18px 20px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow:0 4px 14px rgba(22, 163, 74, 0.08);">
                        <div>
                            <span class="hero-badge" style="background:#dcfce7; color:#166534; font-size:0.72rem; margin-bottom:4px;">
                                <i class="fa-solid fa-hashtag"></i> NOMOR BNI VIRTUAL ACCOUNT
                            </span>
                            <div style="font-family:'JetBrains Mono', monospace; font-size:1.65rem; font-weight:800; color:#005E54; letter-spacing:1px; margin-top:2px;" id="bni-va-display">
                                <?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>
                            </div>
                            <div style="font-size:0.88rem; color:#334155; margin-top:3px;">
                                Total Tagihan: <strong><?= format_rupiah($order['total_amount']); ?></strong>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>', this)" style="background:#005E54; color:#ffffff; padding:10px 18px; border:none; font-size:0.88rem;">
                                <i class="fa-regular fa-copy"></i> Salin Nomor VA
                            </button>
                            <a href="https://simulator.sandbox.midtrans.com/bni/va/index" target="_blank" class="btn-sweet btn-sweet-light" style="padding:10px 16px; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Simulator BNI
                            </a>
                        </div>
                    </div>

                    <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #bbf7d0; font-size:0.9rem; color:#166534;">
                        <strong style="color:#005E54; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran & Simulasi:</strong>
                        <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                            <li><strong>Simulasi Midtrans:</strong> Buka <a href="https://simulator.sandbox.midtrans.com/bni/va/index" target="_blank" style="color:#005E54; font-weight:700;">simulator.sandbox.midtrans.com/bni/va/index</a>, paste nomor VA lalu klik <strong>Inquire</strong> & <strong>Pay</strong>.</li>
                            <li><strong>BNI Mobile Banking:</strong> Buka aplikasi BNI Mobile > pilih <strong>Transfer</strong> > pilih <strong>Virtual Account Billing</strong> > tempel nomor VA di atas > konfirmasi tagihan <strong><?= format_rupiah($order['total_amount']); ?></strong> > masukkan Password Transaksi.</li>
                            <li><strong>ATM BNI:</strong> Menu Lain > Pembayaran > Menu Berikutnya > Virtual Account Billing > masukkan nomor VA.</li>
                        </ol>
                    </div>
                </div>

            <?php elseif ($isMandiri): ?>
                <!-- DETAIL MANDIRI BILL PAYMENT: SINGLE UNIFIED BOX -->
                <div style="background:#eff6ff; border:2px solid #bfdbfe; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(0, 61, 121, 0.08);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#dbeafe; color:#003d79; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem; color:#1e3a8a;">Bank Mandiri (Bill Payment / E-Channel)</h3>
                                <small style="color:#1d4ed8;">Verifikasi otomatis 24 jam via Livin' by Mandiri (Kuning), Kopra, & ATM Mandiri.</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#dbeafe; color:#003d79; border-color:#bfdbfe; font-size:0.75rem;">
                            <i class="fa-solid fa-bolt"></i> Otomatis Midtrans
                        </span>
                    </div>

                    <!-- Mandiri Biller Code & Bill Key Dual Box -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:14px; margin-bottom:16px;">
                        <!-- Box 1: Biller Code -->
                        <div style="background:#ffffff; border:1.5px solid #003d79; border-radius:14px; padding:18px 20px; box-shadow:0 4px 14px rgba(0, 61, 121, 0.06);">
                            <span class="hero-badge" style="background:#eff6ff; color:#1e3a8a; font-size:0.72rem; margin-bottom:4px;">
                                <i class="fa-solid fa-building"></i> KODE PERUSAHAAN (BILLER CODE)
                            </span>
                            <div style="font-family:'JetBrains Mono', monospace; font-size:1.65rem; font-weight:800; color:#003d79; letter-spacing:1px; margin-top:2px;">
                                <?= htmlspecialchars($billerCode); ?>
                            </div>
                            <small style="color:var(--text-muted); display:block; margin:2px 0 8px;">Penyedia Jasa: Midtrans / SweetPop</small>
                            <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= htmlspecialchars($billerCode); ?>', this)" style="background:#003d79; color:#ffffff; padding:7px 14px; border:none; font-size:0.82rem;">
                                <i class="fa-regular fa-copy"></i> Salin Kode Perusahaan
                            </button>
                        </div>

                        <!-- Box 2: Bill Key -->
                        <div style="background:#ffffff; border:1.5px solid #f59e0b; border-radius:14px; padding:18px 20px; box-shadow:0 4px 14px rgba(245, 158, 11, 0.08);">
                            <span class="hero-badge" style="background:#fffbeb; color:#b45309; font-size:0.72rem; margin-bottom:4px;">
                                <i class="fa-solid fa-receipt"></i> NOMOR TAGIHAN (BILL KEY)
                            </span>
                            <div style="font-family:'JetBrains Mono', monospace; font-size:1.65rem; font-weight:800; color:#b45309; letter-spacing:1px; margin-top:2px;" id="mandiri-billkey-display">
                                <?= !empty($billKey) ? htmlspecialchars($billKey) : $payCodeDisplay; ?>
                            </div>
                            <small style="color:var(--text-muted); display:block; margin:2px 0 8px;">Nomor Pembayaran Unik Pesanan</small>
                            <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= !empty($billKey) ? htmlspecialchars($billKey) : $payCodeDisplay; ?>', this)" style="background:#d97706; color:#ffffff; padding:7px 14px; border:none; font-size:0.82rem;">
                                <i class="fa-regular fa-copy"></i> Salin Bill Key
                            </button>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #bfdbfe; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                        <div>
                            <span style="font-size:0.85rem; color:#475569;">Total yang harus dibayar:</span>
                            <div style="font-size:1.25rem; font-weight:800; color:#003d79;"><?= format_rupiah($order['total_amount']); ?></div>
                        </div>
                        <a href="https://simulator.sandbox.midtrans.com/mandiri/bill/index" target="_blank" class="btn-sweet btn-sweet-light" style="padding:10px 18px; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Simulator Mandiri Midtrans
                        </a>
                    </div>

                    <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #bfdbfe; font-size:0.9rem; color:#1e3a8a;">
                        <strong style="color:#003d79; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran & Simulasi:</strong>
                        <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                            <li><strong>Simulasi Midtrans:</strong> Buka <a href="https://simulator.sandbox.midtrans.com/mandiri/bill/index" target="_blank" style="color:#003d79; font-weight:700;">simulator.sandbox.midtrans.com/mandiri/bill/index</a>, masukkan Company Code <strong><?= htmlspecialchars($billerCode); ?></strong> dan Bill Key di atas, lalu klik <strong>Inquire</strong> & <strong>Pay</strong>. Status pesanan akan otomatis terverifikasi <strong>LUNAS ✓</strong>.</li>
                            <li><strong>Livin' by Mandiri (Kuning):</strong> Masuk ke Livin' > pilih menu <strong>Bayar</strong> > cari penyedia jasa <strong>70012</strong> (atau Midtrans) > masukkan Nomor Tagihan (Bill Key) > konfirmasi total tagihan > masukkan PIN Livin'.</li>
                            <li><strong>ATM Mandiri:</strong> Pilih menu Bayar / Beli > Lainnya > Multi Payment > masukkan Kode Perusahaan <strong>70012</strong> > masukkan Bill Key > Konfirmasi.</li>
                        </ol>
                    </div>
                </div>

            <?php elseif ($isBsi): ?>
                <!-- DETAIL TRANSFER BANK: SINGLE UNIFIED BOX -->
                <div style="background:#f0fdfa; border:2px solid #99f6e4; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(0, 163, 157, 0.08);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#ccfbf1; color:#00A39D; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem; color:#134e4a;">Rekening Transfer Bank & Virtual Account BSI</h3>
                                <small style="color:#0f766e;">Transfer langsung ke rekening Bank BSI toko atau simulasi Virtual Account Midtrans.</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#ccfbf1; color:#0f766e; border-color:#5eead4; font-size:0.75rem;">
                            Bank Syariah Indonesia
                        </span>
                    </div>

                    <!-- 1. PEMBAYARAN RIIL: Rekening Toko Asli -->
                    <div style="background:#ffffff; border:1.5px solid #5eead4; border-radius:14px; padding:18px 20px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                        <div>
                            <span class="hero-badge" style="background:#e6fffa; color:#0d9488; font-size:0.72rem; margin-bottom:4px;">
                                <i class="fa-solid fa-money-bill-transfer"></i> NOMOR REKENING TOKO (RIIL)
                            </span>
                            <div style="font-family:'Courier New', monospace; font-size:1.6rem; font-weight:800; color:#134e4a; letter-spacing:1px; margin-top:2px;">7326007751</div>
                            <div style="font-size:0.9rem; color:#047857; font-weight:600; margin-top:2px;">
                                Bank Syariah Indonesia (BSI) • a/n <strong>Zahra Rokhadatul Aisy</strong>
                            </div>
                            <div style="font-size:0.85rem; color:#0f766e; margin-top:2px;">
                                Total Transfer: <strong><?= format_rupiah($order['total_amount']); ?></strong>
                            </div>
                        </div>
                        <button type="button" class="btn-sweet" onclick="copyTextOrder('7326007751', this)" style="background:#00A39D; color:#ffffff; padding:10px 18px; border:none; font-size:0.88rem;">
                            <i class="fa-regular fa-copy"></i> Salin Rekening
                        </button>
                    </div>

                    <!-- 2. PEMBAYARAN SIMULASI: Kode VA untuk Midtrans Simulator -->
                    <?php if (!$isPaid): ?>
                        <div style="background:#ffffff; border:1.5px solid #38bdf8; border-radius:14px; padding:18px 20px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:gap; box-shadow:0 4px 14px rgba(56, 189, 248, 0.08);">
                            <div>
                                <span class="hero-badge" style="background:#e0f2fe; color:#0369a1; font-size:0.72rem; margin-bottom:4px;">
                                    <i class="fa-solid fa-flask"></i> <?= !empty($vaNumber) ? 'NOMOR BSI VIRTUAL ACCOUNT (MIDTRANS SANDBOX)' : 'KODE SIMULASI VIRTUAL ACCOUNT'; ?>
                                </span>
                                <div style="font-family:'JetBrains Mono', monospace; font-size:1.7rem; font-weight:800; color:#0284c7; letter-spacing:1px; margin-top:2px;">
                                    <?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>
                                </div>
                                <small style="color:var(--text-muted); font-size:0.82rem; display:block; margin-top:2px;">
                                    Gunakan nomor ini untuk simulasi di <a href="https://simulator.sandbox.midtrans.com/openapi/va/index?bank=bsi" target="_blank" style="color:#0284c7; font-weight:700; text-decoration:underline;">simulator.sandbox.midtrans.com (BSI VA)</a>.
                                </small>
                            </div>
                            <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= !empty($vaNumber) ? htmlspecialchars($vaNumber) : $payCodeDisplay; ?>', this)" style="background:#0284c7; color:#ffffff; padding:10px 18px; font-size:0.88rem; border:none;">
                                <i class="fa-regular fa-copy"></i> Salin Nomor VA
                            </button>
                        </div>
                    <?php endif; ?>

                    <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #ccfbf1; font-size:0.9rem; color:#134e4a;">
                        <strong style="color:#0f766e; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran:</strong>
                        <ol style="margin:0; padding-left:20px; line-height:1.6; color:#334155;">
                            <li><strong>Transfer Riil:</strong> Transfer tepat sebesar <strong><?= format_rupiah($order['total_amount']); ?></strong> ke rekening BSI di atas lalu konfirmasi via WhatsApp.</li>
                            <li><strong>Simulasi Midtrans:</strong> Salin nomor VA di atas untuk dimasukkan ke <a href="https://simulator.sandbox.midtrans.com/openapi/va/index?bank=bsi" target="_blank" style="color:#00A39D; font-weight:700;">simulator.sandbox.midtrans.com (BSI VA)</a>. Status pesanan akan otomatis terverifikasi LUNAS.</li>
                        </ol>
                    </div>
                </div>

            <?php elseif ($isShopee): ?>
                <!-- DETAIL SHOPEEPAY: SINGLE UNIFIED BOX -->
                <div style="background:#fff7ed; border:2px solid #fed7aa; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(238, 77, 45, 0.08);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#ffedd5; color:#ee4d2d; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem; color:#9a3412;">Pembayaran ShopeePay & E-Wallet</h3>
                                <small style="color:#c2410c;">Transfer saldo ShopeePay riil atau gunakan kode simulasi Midtrans Sandbox.</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#ffedd5; color:#c2410c; border-color:#fdba74; font-size:0.75rem;">
                            Gratis Admin
                        </span>
                    </div>

                    <!-- 1. PEMBAYARAN RIIL: Akun ShopeePay Asli -->
                    <div style="background:#ffffff; border:1.5px solid #fdba74; border-radius:14px; padding:18px 20px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                        <div>
                            <span class="hero-badge" style="background:#fff7ed; color:#ea580c; font-size:0.72rem; margin-bottom:4px;">
                                <i class="fa-solid fa-wallet"></i> NOMOR SHOPEEPAY TOKO (RIIL)
                            </span>
                            <div style="font-family:'Courier New', monospace; font-size:1.6rem; font-weight:800; color:#9a3412; letter-spacing:1px; margin-top:2px;">0895327517570</div>
                            <div style="font-size:0.9rem; color:#ea580c; font-weight:600; margin-top:2px;">
                                ShopeePay • a/n <strong>Zahra Rokhadatul Aisy</strong>
                            </div>
                            <div style="font-size:0.85rem; color:#c2410c; margin-top:2px;">
                                Total Tagihan: <strong><?= format_rupiah($order['total_amount']); ?></strong>
                            </div>
                        </div>
                        <button type="button" class="btn-sweet" onclick="copyTextOrder('0895327517570', this)" style="background:#ee4d2d; color:#ffffff; padding:10px 18px; border:none; font-size:0.88rem;">
                            <i class="fa-regular fa-copy"></i> Salin No. HP
                        </button>
                    </div>

                    <!-- 2. PEMBAYARAN SIMULASI: Kode untuk Midtrans Simulator -->
                    <?php if (!$isPaid): ?>
                        <div style="background:#ffffff; border:1.5px solid #38bdf8; border-radius:14px; padding:18px 20px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow:0 4px 14px rgba(56, 189, 248, 0.08);">
                            <div>
                                <span class="hero-badge" style="background:#e0f2fe; color:#0369a1; font-size:0.72rem; margin-bottom:4px;">
                                    <i class="fa-solid fa-flask"></i> KODE SIMULASI MIDTRANS (SANDBOX)
                                </span>
                                <div style="font-family:'JetBrains Mono', monospace; font-size:1.7rem; font-weight:800; color:#0284c7; letter-spacing:1px; margin-top:2px;">
                                    <?= $payCodeDisplay; ?>
                                </div>
                                <small style="color:var(--text-muted); font-size:0.82rem; display:block; margin-top:2px;">
                                    Salin kode ini untuk simulasi di <a href="payment_simulator.php?method=deeplink&code=<?= $payCodeDisplay; ?>" target="_blank" style="color:#0284c7; font-weight:700; text-decoration:underline;">Payment Gateway Simulator</a> (buka tab terpisah untuk uji coba pelunasan).
                                </small>
                            </div>
                            <button type="button" class="btn-sweet" onclick="copyTextOrder('<?= $payCodeDisplay; ?>', this)" style="background:#0284c7; color:#ffffff; padding:10px 18px; font-size:0.88rem; border:none;">
                                <i class="fa-regular fa-copy"></i> Salin Kode
                            </button>
                        </div>
                    <?php endif; ?>

                    <div style="background:#ffffff; border-radius:12px; padding:14px 18px; border:1px solid #fed7aa; font-size:0.9rem; color:#9a3412;">
                        <strong style="color:#c2410c; display:block; margin-bottom:6px;"><i class="fa-solid fa-circle-info"></i> Petunjuk Pembayaran:</strong>
                        <ol style="margin:0; padding-left:20px; line-height:1.6; color:#475569;">
                            <li><strong>Pembayaran Riil:</strong> Transfer saldo ShopeePay sebesar <strong><?= format_rupiah($order['total_amount']); ?></strong> ke nomor di atas lalu kirim bukti via WhatsApp.</li>
                            <li><strong>Simulasi Midtrans:</strong> Masukkan kode <code><?= $payCodeDisplay; ?></code> ke Midtrans Simulator untuk mencoba verifikasi lunas instan.</li>
                        </ol>
                    </div>
                </div>

            <?php elseif ($isCod): ?>
                <!-- DETAIL COD (BAYAR DI TEMPAT) -->
                <div style="background:#fffbeb; border:2px solid #fde68a; border-radius:var(--radius-lg); padding:26px; text-align:left; margin-bottom:28px; box-shadow:0 8px 25px rgba(245, 158, 11, 0.08);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:50%; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
                                <i class="fa-solid fa-hand-holding-dollar"></i>
                            </div>
                            <div>
                                <h3 style="margin:0; font-size:1.25rem; color:#92400e;">Cash on Delivery (Bayar Tunai di Tempat)</h3>
                                <small style="color:#b45309;">Pembayaran langsung saat kurir SweetPop mengantarkan kue</small>
                            </div>
                        </div>
                        <span class="hero-badge" style="background:#fef3c7; color:#b45309; border-color:#fcd34d; font-size:0.75rem;">
                            <i class="fa-solid fa-motorcycle"></i> Kurir Motor
                        </span>
                    </div>

                    <div style="background:#fef3c7; border-left:4px solid #f59e0b; border-radius:10px; padding:16px 20px; margin-bottom:14px;">
                        <strong style="color:#b45309; font-size:1rem; display:block; margin-bottom:4px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Pengingat Uang Pas:
                        </strong>
                        <p style="margin:0; color:#78350f; font-size:0.92rem; line-height:1.5;">
                            Mohon siapkan <strong>uang pas</strong> sebesar <strong style="color:#b45309; font-size:1.15rem;"><?= format_rupiah($order['total_amount']); ?></strong> saat kurir SweetPop tiba mengantarkan pesanan ke alamat Anda agar transaksi berjalan cepat, aman, dan higienis ♡
                        </p>
                    </div>

                    <small style="display:block; color:#78350f; font-size:0.85rem; line-height:1.4;">
                        <i class="fa-solid fa-circle-check" style="color:#10b981;"></i> Kurir kami akan mengabari melalui WhatsApp sebelum berangkat menuju lokasi Anda.
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <!-- ======================================================== -->
        <!-- ACTION CTAs                                              -->
        <!-- ======================================================== -->
        <div style="display:flex; flex-direction:column; gap:12px; max-width:500px; margin:0 auto;">
            <!-- Instant WhatsApp Confirmation -->
            <a id="btnWhatsAppConfirm" href="<?= $waUrl; ?>" target="_blank" class="btn-sweet" style="background:#25D366; color:#ffffff; font-size:1.05rem; padding:14px 24px; box-shadow:0 6px 18px rgba(37,211,102,0.3); border:none;">
                <i class="fa-brands fa-whatsapp" style="font-size:1.4rem;"></i> <span id="waBtnText"><?= $isPaid ? 'Kirim Konfirmasi LUNAS ke WhatsApp Admin' : 'Konfirmasi Pesanan ke WhatsApp Admin'; ?></span>
            </a>

            <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-top:6px;">
                <a id="btnInvoiceOfficial" href="invoice.php?order_id=<?= $order['order_id']; ?><?= is_logged_in() ? '' : '&email=' . urlencode($order['customer_email']); ?>" target="_blank" class="btn-sweet btn-sweet-light" style="padding:10px 18px; font-size:0.92rem;">
                    <i class="fa-solid fa-file-invoice"></i> Cetak / Simpan Invoice Resmi
                </a>
                <?php if (is_logged_in()): ?>
                    <a href="profile.php?tab=laporan" class="btn-sweet btn-sweet-light" style="padding:10px 18px; font-size:0.92rem;">
                        <i class="fa-solid fa-receipt"></i> Riwayat Pesanan Saya
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn-sweet btn-sweet-pink" style="padding:10px 18px; font-size:0.92rem;">
                    <i class="fa-solid fa-house"></i> Beranda Toko
                </a>
            </div>
        </div>

    </div>
</div>

<script>
const ORDER_ID = <?= (int)$order['order_id']; ?>;
let remainingSeconds = <?= (int)$remainingSeconds; ?>;
let currentPStatus = '<?= htmlspecialchars($pStatus); ?>';
const totalAmountFormatted = '<?= format_rupiah($order['total_amount']); ?>';
const adminWaNumber = '<?= $adminWa; ?>';
const orderData = <?= json_encode([
    'order_id' => (int)$order['order_id'],
    'customer_name' => $order['customer_name'],
    'delivery_date' => $order['delivery_date'],
    'delivery_time' => $order['delivery_time'],
    'payment_method' => $order['payment_method'],
    'total_amount' => $order['total_amount'],
    'trx_id' => $order['trx_id'],
    'cake_message' => $order['cake_message'],
    'candle_request' => $order['candle_request'],
    'shipping_address' => $order['shipping_address'],
    'shipping_city' => $order['shipping_city'],
    'items' => $items
]); ?>;

let timerInterval = null;

// Copy text helper
function copyTextOrder(text, btn) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showOrderCopied(btn));
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
            showOrderCopied(btn);
        } catch(e) {}
        document.body.removeChild(ta);
    }
}
function showOrderCopied(btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin! ✓';
    btn.style.opacity = '0.9';
    setTimeout(() => {
        btn.innerHTML = orig;
        btn.style.opacity = '1';
    }, 2000);
}

// Generate dynamic WhatsApp URL
function updateWhatsAppLink(pStatus) {
    let waText = "Halo SweetPop! 🍰\n";
    if (pStatus === 'Paid') {
        waText += "✦ *KONFIRMASI PEMBAYARAN LUNAS (SETTLEMENT)* ✦\n";
    } else {
        waText += "Saya ingin konfirmasi pesanan saya:\n";
    }
    const orderFormattedId = '#SP-' + String(orderData.order_id).padStart(4, '0');
    waText += "• *No. Faktur:* " + orderFormattedId + "\n";
    waText += "• *Nama:* " + orderData.customer_name + "\n";
    waText += "• *Metode Bayar:* " + orderData.payment_method + " (" + (pStatus === 'Paid' ? 'LUNAS ✓' : pStatus) + ")\n";
    waText += "• *Total:* " + totalAmountFormatted + "\n";
    if (orderData.trx_id) {
        waText += "• *Ref Transaksi:* " + orderData.trx_id + "\n";
    }
    waText += "\n*Pesanan:*\n";
    if (orderData.items && orderData.items.length) {
        orderData.items.forEach(it => {
            waText += "- " + it.product_name + " (x" + it.quantity + ")\n";
        });
    }
    waText += "\nAlamat: " + orderData.shipping_address + ", " + orderData.shipping_city + "\n";
    if (pStatus === 'Paid') {
        waText += "Pesanan sudah LUNAS terverifikasi otomatis. Mohon segera diproses di bakehouse ya kak, terima kasih! ♡";
    } else {
        waText += "Mohon diproses yaa kak, terima kasih! ♡";
    }

    const waBtn = document.getElementById('btnWhatsAppConfirm');
    if (waBtn) {
        waBtn.href = "https://api.whatsapp.com/send?phone=" + adminWaNumber + "&text=" + encodeURIComponent(waText);
    }
    const waTextElem = document.getElementById('waBtnText');
    if (waTextElem) {
        waTextElem.innerText = (pStatus === 'Paid') ? 'Kirim Konfirmasi LUNAS ke WhatsApp Admin' : 'Konfirmasi Pesanan ke WhatsApp Admin';
    }
}

// Apply UI state updates
function applyUIState(pStatus) {
    currentPStatus = pStatus;
    const isPaid = (pStatus === 'Paid');
    const isExpired = (pStatus === 'Expired');
    const isFailed = (pStatus === 'Failed');

    // Update Banners
    const bannerPaid = document.getElementById('statusBannerPaid');
    const bannerExpired = document.getElementById('statusBannerExpired');
    const bannerFailed = document.getElementById('statusBannerFailed');
    const timerBox = document.getElementById('paymentTimerContainer');
    const qrisOverlay = document.getElementById('qrisPaidOverlay');
    const paymentCodeBox = document.getElementById('paymentCodeBox');
    const simCodeSubcard = document.getElementById('simCodeSubcard');

    if (bannerPaid) bannerPaid.style.display = isPaid ? 'flex' : 'none';
    if (bannerExpired) bannerExpired.style.display = isExpired ? 'flex' : 'none';
    if (bannerFailed) bannerFailed.style.display = isFailed ? 'flex' : 'none';
    if (timerBox) timerBox.style.display = (isPaid || isExpired || isFailed) ? 'none' : 'block';
    if (qrisOverlay) qrisOverlay.style.display = isPaid ? 'flex' : 'none';
    if (paymentCodeBox) paymentCodeBox.style.display = isPaid ? 'none' : 'block';
    if (simCodeSubcard) simCodeSubcard.style.display = isPaid ? 'none' : 'block';

    // Update Badges & Titles
    const badgeState = document.getElementById('badgePaymentState');
    if (badgeState) {
        if (isPaid) {
            badgeState.style.background = '#ecfdf5';
            badgeState.style.color = '#047857';
            badgeState.innerText = 'Lunas ✓';
        } else if (isExpired) {
            badgeState.style.background = '#fef2f2';
            badgeState.style.color = '#dc2626';
            badgeState.innerText = 'Expired ✕';
        } else if (isFailed) {
            badgeState.style.background = '#fef2f2';
            badgeState.style.color = '#dc2626';
            badgeState.innerText = 'Gagal ✕';
        } else {
            badgeState.style.background = '#fffbeb';
            badgeState.style.color = '#d97706';
            badgeState.innerText = 'Menunggu Bayar';
        }
    }

    const mainIconFa = document.getElementById('mainStatusIconFa');
    const mainIconBox = document.getElementById('mainStatusIcon');
    const heading = document.getElementById('mainTitleHeading');
    const subtitle = document.getElementById('mainSubtitle');

    if (isPaid) {
        if (mainIconFa) mainIconFa.className = 'fa-solid fa-circle-check';
        if (mainIconBox) {
            mainIconBox.style.background = '#ecfdf5';
            mainIconBox.style.color = '#047857';
        }
        if (heading) heading.innerText = 'Yay, Pembayaran Sukses Diterima! ♡';
        if (subtitle) subtitle.innerText = 'Pembayaran telah diverifikasi secara otomatis oleh sistem. Pesananmu kini sedang disiapkan di dapur bakehouse SweetPop.';
        
        // Update Stepper to step 2 active
        const step2 = document.getElementById('stepperStep2');
        if (step2) {
            step2.style.opacity = '1';
            const iconWrap = step2.querySelector('div');
            if (iconWrap) {
                iconWrap.style.background = 'var(--pink)';
                iconWrap.style.color = '#fff';
                iconWrap.style.animation = 'pulse 2s infinite';
            }
            const smallLabel = step2.querySelector('small');
            if (smallLabel) smallLabel.innerText = 'Sedang Dipanggang';
        }
        triggerSweetConfetti();

    } else if (isExpired) {
        if (mainIconFa) mainIconFa.className = 'fa-solid fa-clock';
        if (mainIconBox) {
            mainIconBox.style.background = '#fef2f2';
            mainIconBox.style.color = '#dc2626';
        }
        if (heading) heading.innerText = 'Waktu Pembayaran Telah Habis';
        if (subtitle) subtitle.innerText = 'Batas waktu pembayaran telah terlampaui. Pesanan otomatis dibatalkan sistem.';
    } else if (isFailed) {
        if (mainIconFa) mainIconFa.className = 'fa-solid fa-circle-xmark';
        if (mainIconBox) {
            mainIconBox.style.background = '#fef2f2';
            mainIconBox.style.color = '#dc2626';
        }
        if (heading) heading.innerText = 'Pembayaran Ditolak / Dibatalkan';
        if (subtitle) subtitle.innerText = 'Transaksi pembayaran tidak berhasil. Kakak dapat memesan ulang atau memilih metode pembayaran lain.';
    }

    updateWhatsAppLink(pStatus);
}

// Countdown timer loop
function startCountdown() {
    if (timerInterval) clearInterval(timerInterval);

    const display = document.getElementById('countdownDisplay');
    const bar = document.getElementById('timeProgressBar');

    timerInterval = setInterval(() => {
        if (currentPStatus === 'Paid' || currentPStatus === 'Expired' || currentPStatus === 'Failed') {
            clearInterval(timerInterval);
            return;
        }

        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            applyUIState('Expired');
            fetch('api_check_payment.php?order_id=' + ORDER_ID).catch(() => {});
            return;
        }

        remainingSeconds--;
        const mins = Math.floor(remainingSeconds / 60);
        const secs = remainingSeconds % 60;
        if (display) {
            display.innerText = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }
        if (bar) {
            const pct = Math.max(0, Math.min(100, (remainingSeconds / 900) * 100));
            bar.style.width = pct + '%';
        }
    }, 1000);
}

// Check status now via AJAX
function checkPaymentStatusNow(isManual = false) {
    const icon = document.getElementById('checkIcon');
    if (icon) icon.classList.add('fa-spin');

    fetch('api_check_payment.php?order_id=' + ORDER_ID)
        .then(res => res.json())
        .then(data => {
            if (icon) icon.classList.remove('fa-spin');
            if (data && data.status === 'success') {
                if (data.p_status !== currentPStatus) {
                    applyUIState(data.p_status);
                }
                if (isManual) {
                    alert('Status Pembayaran Saat Ini: ' + data.p_status + (data.is_paid ? ' (Lunas ✓)' : ''));
                }
            }
        })
        .catch(() => {
            if (icon) icon.classList.remove('fa-spin');
        });
}

// Pay with Midtrans Snap
function payWithMidtransSnap() {
    const btn = document.getElementById('btnPayMidtrans');
    const msg = document.getElementById('midtransMsg');
    
    if (btn) btn.disabled = true;
    if (msg) msg.innerText = 'Menghubungkan ke server Midtrans... Mohon tunggu sebentar ⏳';

    fetch('api_get_snap_token.php?order_id=' + ORDER_ID)
        .then(res => res.json())
        .then(data => {
            if (btn) btn.disabled = false;
            if (data && data.status === 'success' && data.snap_token) {
                if (msg) msg.innerText = 'Pop-up Midtrans terbuka. Selesaikan pembayaran di jendela Snap.';
                
                if (window.snap) {
                    window.snap.pay(data.snap_token, {
                        onSuccess: function(result) {
                            fetch('api_midtrans_finish.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ order_id: ORDER_ID, result: result })
                            }).then(() => {
                                applyUIState('Paid');
                            });
                        },
                        onPending: function(result) {
                            checkPaymentStatusNow();
                        },
                        onError: function(result) {
                            alert('Pembayaran Midtrans gagal atau dibatalkan.');
                            checkPaymentStatusNow();
                        },
                        onClose: function() {
                            if (msg) msg.innerText = 'Kamu menutup pop-up Midtrans sebelum menyelesaikan transaksi.';
                        }
                    });
                } else {
                    alert('Script Midtrans Snap belum termuat. Periksa koneksi internet atau Client Key di Admin Settings.');
                }
            } else if (data && data.status === 'config_needed') {
                if (msg) msg.innerHTML = '<span style="color:#e11d48; font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> ' + data.message + '</span>';
                alert(data.message);
            } else {
                const errMsg = (data && data.message) ? data.message : 'Gagal memproses Snap Token Midtrans.';
                if (msg) msg.innerText = errMsg;
                alert(errMsg);
            }
        })
        .catch(err => {
            if (btn) btn.disabled = false;
            if (msg) msg.innerText = 'Gagal menghubungi server.';
            alert('Terjadi kendala koneksi ke server.');
        });
}

// Initial Run
if (remainingSeconds > 0 && currentPStatus === 'Pending') {
    startCountdown();
}

// Polling interval every 4 seconds for live status sync
setInterval(() => {
    if (currentPStatus === 'Pending') {
        checkPaymentStatusNow(false);
    }
}, 4000);
</script>

<?php include 'includes/footer.php'; ?>
