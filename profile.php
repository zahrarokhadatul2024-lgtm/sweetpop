<?php
$pageTitle = "Dashboard Akun Pengguna";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$userId = current_user_id();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $mobile    = trim($_POST['mobile'] ?? '');
        $address1  = trim($_POST['address1'] ?? '');
        $address2  = trim($_POST['address2'] ?? '');

        if ($firstName === '') {
            set_flash('danger', 'Nama depan wajib diisi.');
        } else {
            $uStmt = mysqli_prepare($con, "UPDATE user_info SET first_name = ?, last_name = ?, mobile = ?, address1 = ?, address2 = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($uStmt, "sssssi", $firstName, $lastName, $mobile, $address1, $address2, $userId);
            if (mysqli_stmt_execute($uStmt)) {
                $_SESSION['user_name'] = $firstName;
                set_flash('success', 'Data pribadi Anda berhasil diperbarui!');
            } else {
                set_flash('danger', 'Gagal memperbarui profil: ' . mysqli_error($con));
            }
        }
        header("Location: profile.php?tab=data-pribadi");
        exit;
    }

    if ($_POST['action'] === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 6) {
            set_flash('danger', 'Password baru minimal 6 karakter.');
        } elseif ($newPass !== $confPass) {
            set_flash('danger', 'Konfirmasi password baru tidak cocok.');
        } else {
            // Verify old password
            $pStmt = mysqli_prepare($con, "SELECT password FROM user_info WHERE user_id = ?");
            mysqli_stmt_bind_param($pStmt, "i", $userId);
            mysqli_stmt_execute($pStmt);
            $pRes = mysqli_stmt_get_result($pStmt);
            $currUser = mysqli_fetch_assoc($pRes);

            if (password_verify($oldPass, $currUser['password']) || $oldPass === $currUser['password']) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $upStmt = mysqli_prepare($con, "UPDATE user_info SET password = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($upStmt, "si", $hash, $userId);
                mysqli_stmt_execute($upStmt);
                set_flash('success', 'Password Anda berhasil diperbarui!');
            } else {
                set_flash('danger', 'Password lama tidak sesuai.');
            }
        }
        header("Location: profile.php?tab=data-pribadi");
        exit;
    }

    if ($_POST['action'] === 'submit_service_request') {
        $serviceType = $_POST['service_type'] ?? 'Custom Cake';
        $notes = trim($_POST['service_notes'] ?? '');
        $date = $_POST['event_date'] ?? date('Y-m-d');
        set_flash('success', "Permintaan layanan '$serviceType' untuk tanggal " . date('d M Y', strtotime($date)) . " berhasil dikirim! Tim chef SweetPop akan segera menghubungi via WhatsApp.");
        header("Location: profile.php?tab=layanan");
        exit;
    }
}

// Fetch user info
$stmt = mysqli_prepare($con, "SELECT * FROM user_info WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Fetch user orders
$orderStmt = mysqli_prepare($con, "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($orderStmt, "i", $userId);
mysqli_stmt_execute($orderStmt);
$ordersRes = mysqli_stmt_get_result($orderStmt);

$allOrders = [];
$totalSpent = 0;
$completedCount = 0;
while ($ord = mysqli_fetch_assoc($ordersRes)) {
    $allOrders[] = $ord;
    if ($ord['status'] !== 'cancelled') {
        $totalSpent += $ord['total_amount'];
    }
    if ($ord['status'] === 'completed') {
        $completedCount++;
    }
}

$activeTab = $_GET['tab'] ?? 'beranda';
include 'includes/header.php';
?>

<div class="container" style="padding: 40px 20px 80px;">
    
    <!-- User Profile Hero Banner Card -->
    <div class="card-box" style="margin-bottom: 30px; background: linear-gradient(135deg, #ffffff 0%, #fff0f5 100%); border: 2px solid var(--border-light); padding: 30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
            <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
                <!-- Profile Avatar / Photo -->
                <div style="position:relative;">
                    <div style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--pink); box-shadow: 0 8px 24px rgba(255, 117, 151, 0.25); overflow:hidden; background: var(--pink-light); display:flex; align-items:center; justify-content:center;">
                        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=250&q=80" alt="Foto Profil Pengguna" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <span style="position:absolute; bottom:0; right:0; background:#10b981; border:2px solid white; width:18px; height:18px; border-radius:50%;" title="Akun Aktif"></span>
                </div>

                <div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h1 style="font-size:2.2rem; margin:0;"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                        <span class="hero-badge" style="background:var(--pink); color:white; border:none; font-size:0.75rem;">
                            <i class="fa-solid fa-crown"></i> VIP Member
                        </span>
                    </div>
                    <p style="color:var(--text-muted); margin:4px 0 0; font-size:0.95rem;">
                        <i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($user['email']); ?> &nbsp;•&nbsp; 
                        <i class="fa-brands fa-whatsapp"></i> <?= htmlspecialchars($user['mobile'] ?: 'Belum diatur'); ?> &nbsp;•&nbsp;
                        <i class="fa-regular fa-calendar-check"></i> Member sejak <?= !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : date('d M Y'); ?>
                    </p>
                </div>
            </div>

            <div style="display:flex; gap:10px; align-items:center;">
                <a href="store.php" class="btn-sweet btn-sweet-pink">
                    <i class="fa-solid fa-cookie-bite"></i> Belanja Kue Lagi
                </a>
                <a href="logout.php" class="btn-sweet btn-sweet-light" style="color:#ef4444; border-color:#fecaca;" title="Keluar dari akun">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- User Navigation Tab Bar (Beranda, Data Pribadi, Layanan, Laporan) -->
    <div style="margin-bottom: 30px; display:flex; gap:12px; border-bottom: 2px solid var(--border); padding-bottom: 12px; flex-wrap:wrap;">
        <a href="profile.php?tab=beranda" class="cat-pill <?= $activeTab === 'beranda' ? 'active' : ''; ?>" style="font-size:1rem; padding: 10px 22px;">
            <i class="fa-solid fa-house-chimney"></i> Beranda Pengguna
        </a>
        <a href="profile.php?tab=data-pribadi" class="cat-pill <?= $activeTab === 'data-pribadi' ? 'active' : ''; ?>" style="font-size:1rem; padding: 10px 22px;">
            <i class="fa-solid fa-id-card"></i> Data Pribadi
        </a>
        <a href="profile.php?tab=layanan" class="cat-pill <?= $activeTab === 'layanan' ? 'active' : ''; ?>" style="font-size:1rem; padding: 10px 22px;">
            <i class="fa-solid fa-bell-concierge"></i> Layanan Khusus
        </a>
        <a href="profile.php?tab=laporan" class="cat-pill <?= $activeTab === 'laporan' ? 'active' : ''; ?>" style="font-size:1rem; padding: 10px 22px;">
            <i class="fa-solid fa-receipt"></i> Laporan & Riwayat Pesanan
        </a>
    </div>

    <!-- TAB 1: BERANDA USER -->
    <?php if ($activeTab === 'beranda'): ?>
        <!-- Quick Stats Cards -->
        <div class="stats-grid" style="margin-bottom: 30px;">
            <div class="stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">TOTAL PESANAN SAYA</div>
                    <div class="stat-val" style="color:var(--pink-dark);"><?= count($allOrders); ?> Kali</div>
                </div>
                <div style="width:48px; height:48px; border-radius:var(--radius-md); background:var(--pink-light); color:var(--pink-dark); display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
            </div>

            <div class="stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">TOTAL PENGELUARAN</div>
                    <div class="stat-val" style="color:var(--blue-dark);"><?= format_rupiah($totalSpent); ?></div>
                </div>
                <div style="width:48px; height:48px; border-radius:var(--radius-md); background:var(--blue-light); color:var(--blue-dark); display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>

            <div class="stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">POIN SWEETPOP</div>
                    <div class="stat-val" style="color:#10b981;"><?= floor($totalSpent / 1000); ?> Pts</div>
                </div>
                <div style="width:48px; height:48px; border-radius:var(--radius-md); background:#ecfdf5; color:#10b981; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                    <i class="fa-solid fa-star"></i>
                </div>
            </div>

            <div class="stat-card">
                <div>
                    <div style="color:var(--text-muted); font-size:0.85rem; font-weight:600;">VOUCHER DISKON</div>
                    <div class="stat-val" style="color:#8b5cf6;">3 Siap Pakai</div>
                </div>
                <div style="width:48px; height:48px; border-radius:var(--radius-md); background:#f5f3ff; color:#8b5cf6; display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                    <i class="fa-solid fa-ticket"></i>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 2fr 1.2fr; gap:24px;">
            <!-- Welcome Info & Quick Overview -->
            <div class="card-box">
                <h3 style="font-size:1.3rem; margin-bottom:12px; color:var(--pink-dark);">
                    <i class="fa-solid fa-sparkles"></i> Selamat Datang di Member Portal SweetPop!
                </h3>
                <p style="color:var(--text); line-height:1.7;">
                    Sebagai pelanggan terdaftar, kamu mendapatkan berbagai kemudahan: riwayat transaksi tersimpan rapi, fasilitas unduh invoice resmi setiap saat, pengisian otomatis alamat saat checkout, dan akses pre-order cake spesial.
                </p>
                <div style="background:var(--cream); padding:16px 20px; border-radius:14px; border-left:4px solid var(--pink); margin-top:16px;">
                    <strong><i class="fa-solid fa-lightbulb" style="color:#f59e0b;"></i> Tips Manis Hari Ini:</strong>
                    <div style="font-size:0.92rem; color:var(--text-muted); margin-top:4px;">
                        Klaim diskon 20% dengan memasukkan kupon <strong>SWEET20</strong> saat memesan Dessert Box & Cookies favoritmu!
                    </div>
                </div>
            </div>

            <!-- Quick Shortcuts Card -->
            <div class="card-box">
                <h3 style="font-size:1.2rem; margin-bottom:16px;">Aksi Cepat</h3>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <a href="profile.php?tab=data-pribadi" class="btn-sweet btn-sweet-light" style="justify-content:flex-start; padding:10px 16px;">
                        <i class="fa-solid fa-pen-to-square" style="color:var(--pink);"></i> Lengkapi Alamat Pengiriman
                    </a>
                    <a href="profile.php?tab=layanan" class="btn-sweet btn-sweet-light" style="justify-content:flex-start; padding:10px 16px;">
                        <i class="fa-solid fa-cake-candles" style="color:var(--blue-dark);"></i> Ajukan Pre-Order Custom Cake
                    </a>
                    <a href="profile.php?tab=laporan" class="btn-sweet btn-sweet-light" style="justify-content:flex-start; padding:10px 16px;">
                        <i class="fa-solid fa-file-invoice" style="color:#10b981;"></i> Unduh Bukti Invoice Belanja
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($allOrders)): ?>
            <?php $latest = $allOrders[0]; ?>
            <div class="card-box" style="margin-top:24px; border:2px solid var(--pink-light); background:#fffafd;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                    <div>
                        <span class="hero-badge">✦ Pelacakan Pesanan Aktif</span>
                        <h3 style="margin:4px 0 0; font-size:1.3rem;">Pesanan #SP-<?= str_pad($latest['order_id'], 4, '0', STR_PAD_LEFT); ?></h3>
                    </div>
                    <div style="text-align:right;">
                        <small style="color:var(--text-muted);">Jadwal Pengantaran:</small>
                        <div style="font-weight:700; color:var(--pink-dark);"><?= !empty($latest['delivery_date']) ? date('d M Y', strtotime($latest['delivery_date'])) : date('d M Y'); ?> (<?= htmlspecialchars($latest['delivery_time'] ?? 'Pagi'); ?>)</div>
                    </div>
                </div>

                <!-- Stepper Progress Indicator -->
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px; text-align:center; padding:12px 0;">
                    <div>
                        <div style="width:36px; height:36px; border-radius:50%; background:#10b981; color:#fff; display:inline-flex; align-items:center; justify-content:center; margin-bottom:4px; font-size:0.9rem;">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div style="font-size:0.8rem; font-weight:700; color:#10b981;">1. Diterima</div>
                    </div>
                    <div>
                        <div style="width:36px; height:36px; border-radius:50%; background:<?= in_array($latest['status'], ['processing', 'completed']) ? 'var(--pink)' : '#e2e8f0'; ?>; color:<?= in_array($latest['status'], ['processing', 'completed']) ? '#fff' : '#64748b'; ?>; display:inline-flex; align-items:center; justify-content:center; margin-bottom:4px; font-size:0.9rem;">
                            <i class="fa-solid fa-bowl-rice"></i>
                        </div>
                        <div style="font-size:0.8rem; font-weight:700; color:<?= in_array($latest['status'], ['processing', 'completed']) ? 'var(--pink-dark)' : '#64748b'; ?>;">2. Dapur Bakehouse</div>
                    </div>
                    <div>
                        <div style="width:36px; height:36px; border-radius:50%; background:<?= $latest['status'] === 'completed' ? '#10b981' : '#e2e8f0'; ?>; color:<?= $latest['status'] === 'completed' ? '#fff' : '#64748b'; ?>; display:inline-flex; align-items:center; justify-content:center; margin-bottom:4px; font-size:0.9rem;">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <div style="font-size:0.8rem; font-weight:600; color:<?= $latest['status'] === 'completed' ? '#10b981' : '#64748b'; ?>;">3. Pengantaran</div>
                    </div>
                    <div>
                        <div style="width:36px; height:36px; border-radius:50%; background:<?= $latest['status'] === 'completed' ? '#10b981' : '#e2e8f0'; ?>; color:<?= $latest['status'] === 'completed' ? '#fff' : '#64748b'; ?>; display:inline-flex; align-items:center; justify-content:center; margin-bottom:4px; font-size:0.9rem;">
                            <i class="fa-solid fa-cake-candles"></i>
                        </div>
                        <div style="font-size:0.8rem; font-weight:600; color:<?= $latest['status'] === 'completed' ? '#10b981' : '#64748b'; ?>;">4. Tiba di Tempat</div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px; border-top:1px dashed var(--border); padding-top:12px;">
                    <a href="invoice.php?order_id=<?= $latest['order_id']; ?>" target="_blank" class="btn-sweet btn-sweet-light" style="padding:6px 14px; font-size:0.85rem;">
                        <i class="fa-solid fa-print"></i> Cetak Invoice
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: DATA PRIBADI -->
    <?php elseif ($activeTab === 'data-pribadi'): ?>
        <div style="display:grid; grid-template-columns: 2fr 1.2fr; gap:30px;">
            <!-- Form Edit Profile -->
            <div class="card-box" style="padding:32px;">
                <h3 style="font-size:1.3rem; margin-bottom: 20px; color:var(--pink-dark); border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <i class="fa-solid fa-user-pen"></i> Form Data Pribadi Pengguna
                </h3>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:16px;">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nama Depan *</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Nama Belakang</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']); ?>">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:16px;">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">Alamat Email (Akun)</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" disabled style="background:#f3f4f6; cursor:not-allowed;">
                            <small style="color:var(--text-muted); font-size:0.75rem;">Email akun tidak dapat diubah secara langsung.</small>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label">No. WhatsApp / Handphone *</label>
                            <input type="text" name="mobile" class="form-control" placeholder="081234567890" value="<?= htmlspecialchars($user['mobile'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Alamat Pengiriman Utama (Jalan, Nomor Rumah, RT/RW, Kelurahan) *</label>
                        <textarea name="address1" class="form-control" rows="3" placeholder="Contoh: Jl. Kaliurang KM 5, Gg. Kenanga No. 12, Sleman" required><?= htmlspecialchars($user['address1'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom:24px;">
                        <label class="form-label">Patokan / Catatan Pengiriman Tambahan (Opsional)</label>
                        <input type="text" name="address2" class="form-control" placeholder="Contoh: Pagar hitam, samping mini market" value="<?= htmlspecialchars($user['address2'] ?? ''); ?>">
                    </div>

                    <button type="submit" class="btn-sweet btn-sweet-pink" style="padding:12px 28px;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Profil
                    </button>
                </form>
            </div>

            <!-- Form Ganti Password -->
            <div class="card-box" style="padding:32px;">
                <h3 style="font-size:1.3rem; margin-bottom: 20px; color:var(--blue-dark); border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <i class="fa-solid fa-lock"></i> Keamanan & Password
                </h3>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label">Password Lama *</label>
                        <input type="password" name="old_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password Baru *</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password Baru *</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password baru" required>
                    </div>

                    <button type="submit" class="btn-sweet btn-sweet-light" style="width:100%; border-color:var(--blue-dark); color:var(--blue-dark);">
                        <i class="fa-solid fa-key"></i> Perbarui Password
                    </button>
                </form>
            </div>
        </div>

    <!-- TAB 3: LAYANAN -->
    <?php elseif ($activeTab === 'layanan'): ?>
        <div style="margin-bottom: 28px;">
            <h2 style="font-size:1.8rem; margin-bottom:6px;">Layanan Khusus Pelanggan SweetPop</h2>
            <p style="color:var(--text-muted);">Nikmati kemudahan layanan pesanan custom, hampers perayaan, dan katering dessert manis.</p>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:24px; margin-bottom: 36px;">
            <div class="card-box" style="padding:28px;">
                <div style="width:54px; height:54px; border-radius:14px; background:var(--pink-light); color:var(--pink-dark); display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:16px;">
                    <i class="fa-solid fa-cake-candles"></i>
                </div>
                <h3 style="font-size:1.2rem; margin-bottom:8px;">Custom Cake Pre-Order</h3>
                <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.6; margin-bottom:16px;">
                    Pesan kue ulang tahun atau perayaan khusus dengan kustomisasi diameter, krim pastel, topper tulisan, dan lilin estetik.
                </p>
                <span class="hero-badge" style="background:#ecfdf5; color:#059669; border:none;">H-2 Pengantaran</span>
            </div>

            <div class="card-box" style="padding:28px;">
                <div style="width:54px; height:54px; border-radius:14px; background:var(--blue-light); color:var(--blue-dark); display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:16px;">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <h3 style="font-size:1.2rem; margin-bottom:8px;">Gift Box & Hamper Set</h3>
                <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.6; margin-bottom:16px;">
                    Paket hampers dessert box dan cookies lengkap dengan box cantik pita satin, sleeve custom, serta greeting card bertuliskan pesanmu.
                </p>
                <span class="hero-badge" style="background:#eff6ff; color:#2563eb; border:none;">Gratis Greeting Card</span>
            </div>

            <div class="card-box" style="padding:28px;">
                <div style="width:54px; height:54px; border-radius:14px; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:16px;">
                    <i class="fa-solid fa-champagne-glasses"></i>
                </div>
                <h3 style="font-size:1.2rem; margin-bottom:8px;">Dessert Table Event</h3>
                <p style="color:var(--text-muted); font-size:0.9rem; line-height:1.6; margin-bottom:16px;">
                    Penyediaan booth dessert manis untuk acara wisuda, bridal shower, arisan, maupun katering kantor dengan diskon porsi besar.
                </p>
                <span class="hero-badge" style="background:#fffbeb; color:#b45309; border:none;">Min. 30 Porsi</span>
            </div>
        </div>

        <!-- Form Request Layanan -->
        <div class="card-box" style="max-width:760px; padding:32px;">
            <h3 style="font-size:1.3rem; margin-bottom:16px; color:var(--pink-dark);">
                <i class="fa-solid fa-paper-plane"></i> Form Pengajuan Layanan Khusus
            </h3>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="submit_service_request">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:16px;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Jenis Layanan</label>
                        <select name="service_type" class="form-control" required>
                            <option value="Custom Birthday Cake">Custom Birthday Cake</option>
                            <option value="Gift Hampers Box">Gift Hampers Box</option>
                            <option value="Dessert Table Event">Dessert Table Event</option>
                            <option value="Konsultasi Menu Dessert">Konsultasi Menu Dessert</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Tanggal Rencana Acara / Delivery</label>
                        <input type="date" name="event_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')); ?>" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label">Catatan Request & Kebutuhan Khusus</label>
                    <textarea name="service_notes" class="form-control" rows="3" placeholder="Jelaskan preferensi rasa kue, tulisan topper, atau jumlah tamu acara..." required></textarea>
                </div>

                <button type="submit" class="btn-sweet btn-sweet-pink" style="padding:12px 28px;">
                    <i class="fa-solid fa-paper-plane"></i> Kirim Permintaan Layanan
                </button>
            </form>
        </div>

    <!-- TAB 4: LAPORAN & RIWAYAT -->
    <?php elseif ($activeTab === 'laporan'): ?>
        <div class="card-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; flex-wrap:wrap; gap:16px;">
                <div>
                    <h3 style="font-size:1.4rem; color:var(--text); margin:0;">
                        <i class="fa-solid fa-file-invoice-dollar" style="color:var(--pink-dark);"></i> Laporan Aktivitas & Riwayat Transaksi
                    </h3>
                    <p style="color:var(--text-muted); font-size:0.92rem; margin:4px 0 0;">
                        Daftar lengkap seluruh transaksi kue dan dessert yang pernah kamu pesan di SweetPop.
                    </p>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:0.85rem; color:var(--text-muted);">Total Belanja Terakumulasi:</span>
                    <div style="font-size:1.3rem; font-weight:800; color:var(--pink-dark);"><?= format_rupiah($totalSpent); ?></div>
                </div>
            </div>

            <?php if (empty($allOrders)): ?>
                <div style="text-align:center; padding: 50px 20px;">
                    <div style="font-size: 3.5rem; margin-bottom: 12px;">🛍️</div>
                    <h4 style="font-size:1.2rem;">Belum Ada Transaksi Tercatat</h4>
                    <p style="color:var(--text-muted); margin-bottom: 20px;">Kamu belum pernah melakukan checkout dessert dari toko kami.</p>
                    <a href="store.php" class="btn-sweet btn-sweet-pink">Mulai Belanja Treats Manis ♡</a>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="cart-table">
                        <thead>
                            <tr style="background:#fff0f5;">
                                <th>No. Faktur</th>
                                <th>Waktu Pesanan</th>
                                <th>Menu Dessert</th>
                                <th>Metode Bayar</th>
                                <th>Status</th>
                                <th style="text-align:right;">Total Tagihan</th>
                                <th style="text-align:center;">Bukti / Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allOrders as $ord): ?>
                                <?php
                                $itemStmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE order_id = ?");
                                mysqli_stmt_bind_param($itemStmt, "i", $ord['order_id']);
                                mysqli_stmt_execute($itemStmt);
                                $itemsRes = mysqli_stmt_get_result($itemStmt);
                                ?>
                                <?php
                                $settings = get_store_settings();
                                $adminWa = format_wa_number($settings['whatsapp']);
                                $waText = "Halo SweetPop! 🍰 Saya ingin menanyakan status pesanan Faktur #SP-" . str_pad($ord['order_id'], 4, '0', STR_PAD_LEFT);
                                $waUrl = "https://api.whatsapp.com/send?phone=" . $adminWa . "&text=" . urlencode($waText);
                                ?>
                                <tr>
                                    <td>
                                        <strong>#SP-<?= str_pad($ord['order_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                        <?php if (!empty($ord['delivery_date'])): ?>
                                            <div style="font-size:0.75rem; color:var(--pink-dark); margin-top:4px;">
                                                <i class="fa-regular fa-calendar"></i> Kirim: <?= date('d M', strtotime($ord['delivery_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= date('d M Y, H:i', strtotime($ord['created_at'])); ?> WIB</small></td>
                                    <td>
                                        <ul style="list-style:none; padding:0; margin:0; font-size:0.85rem;">
                                            <?php while ($it = mysqli_fetch_assoc($itemsRes)): ?>
                                                <li>• <strong><?= htmlspecialchars($it['product_name']); ?></strong> × <?= $it['quantity']; ?> (<?= format_rupiah($it['subtotal']); ?>)</li>
                                            <?php endwhile; ?>
                                        </ul>
                                        <?php if (!empty($ord['cake_message'])): ?>
                                            <div style="font-size:0.8rem; color:#d97706; margin-top:4px; font-style:italic;">
                                                <i class="fa-solid fa-pen-fancy"></i> "<?= htmlspecialchars($ord['cake_message']); ?>"
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="background:var(--blue-light); color:var(--blue-dark); padding:3px 8px; border-radius:6px; font-size:0.75rem; font-weight:700;">
                                            <?= htmlspecialchars($ord['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ord['status'] === 'completed'): ?>
                                            <span style="background:#ecfdf5; color:#047857; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">COMPLETED</span>
                                        <?php elseif ($ord['status'] === 'processing'): ?>
                                            <span style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">PROCESSING</span>
                                        <?php elseif ($ord['status'] === 'cancelled'): ?>
                                            <span style="background:#fef2f2; color:#b91c1c; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">CANCELLED</span>
                                        <?php else: ?>
                                            <span style="background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:12px; font-weight:700; font-size:0.75rem;">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <strong style="color:var(--pink-dark);"><?= format_rupiah($ord['total_amount']); ?></strong>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                                            <a href="invoice.php?order_id=<?= $ord['order_id']; ?>" target="_blank" class="btn-sweet btn-sweet-light" style="padding:4px 10px; font-size:0.78rem; width:100px;">
                                                <i class="fa-solid fa-print"></i> Invoice
                                            </a>
                                            <a href="<?= $waUrl; ?>" target="_blank" class="btn-sweet" style="background:#25D366; color:#fff; padding:4px 10px; font-size:0.78rem; border:none; width:100px;">
                                                <i class="fa-brands fa-whatsapp"></i> Chat CS
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
