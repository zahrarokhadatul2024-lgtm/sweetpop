<?php
$pageTitle = "Pengaturan Toko";
include 'header.php';

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'store_name' => trim($_POST['store_name'] ?? 'SWEETPOP'),
        'tagline' => trim($_POST['tagline'] ?? 'Dessert, Cake & Sweet Things for Your Main-Character Era'),
        'whatsapp' => trim($_POST['whatsapp'] ?? '+62 895-3275-17570'),
        'email_support' => trim($_POST['email_support'] ?? 'halo@sweetpop.com'),
        'operating_hours' => trim($_POST['operating_hours'] ?? '09:00 - 21:00 WIB (Setiap Hari)'),
        'flat_shipping' => (int)($_POST['flat_shipping'] ?? 15000),
        'announcement' => trim($_POST['announcement'] ?? '🍓 Free delivery min. order Rp50K! • Freshly baked daily in Yogyakarta with premium ingredients ♡'),
        'store_status' => $_POST['store_status'] ?? 'open'
    ];
    file_put_contents(__DIR__ . '/../system_settings.json', json_encode($settings, JSON_PRETTY_PRINT));

    set_flash('success', 'Pengaturan toko berhasil diperbarui!');
    header("Location: settings.php");
    exit;
}

$settingsFile = __DIR__ . '/../system_settings.json';
$currentSettings = [
    'store_name' => 'SWEETPOP',
    'tagline' => 'Dessert, Cake & Sweet Things for Your Main-Character Era',
    'whatsapp' => '+62 895-3275-17570',
    'email_support' => 'halo@sweetpop.com',
    'operating_hours' => '09:00 - 21:00 WIB (Setiap Hari)',
    'flat_shipping' => 15000,
    'announcement' => '🍓 Free delivery min. order Rp50K! • Freshly baked daily in Yogyakarta with premium ingredients ♡',
    'store_status' => 'open'
];
if (file_exists($settingsFile)) {
    $loaded = json_decode(file_get_contents($settingsFile), true);
    if (is_array($loaded)) {
        $currentSettings = array_merge($currentSettings, $loaded);
    }
}
?>

<div class="admin-header">
    <div>
        <span class="hero-badge" style="margin-bottom:6px;">✦ Konfigurasi Toko</span>
        <h1 style="font-size: 2.2rem;">Pengaturan Toko SweetPop</h1>
        <p style="color:var(--text-muted);">Sesuaikan parameter identitas toko, kontak operasional, tarif pengiriman, dan teks banner pengumuman.</p>
    </div>
</div>

<div class="card-box" style="max-width: 850px; padding: 32px;">
    <form method="POST" action="settings.php">
        <h3 style="font-size: 1.25rem; margin-bottom: 20px; color: var(--pink-dark); border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            <i class="fa-solid fa-store"></i> Identitas Toko SweetPop
        </h3>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Nama Aplikasi / Toko</label>
                <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($currentSettings['store_name']); ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Status Penerimaan Pesanan</label>
                <select name="store_status" class="form-control">
                    <option value="open" <?= ($currentSettings['store_status'] ?? 'open') === 'open' ? 'selected' : ''; ?>>🟢 Buka (Menerima Pesanan)</option>
                    <option value="closed" <?= ($currentSettings['store_status'] ?? '') === 'closed' ? 'selected' : ''; ?>>🔴 Tutup Sementara (Maintenance / Sold Out)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Slogan / Tagline Toko</label>
            <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($currentSettings['tagline']); ?>" required>
        </div>

        <h3 style="font-size: 1.25rem; margin: 30px 0 20px; color: var(--blue-dark); border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            <i class="fa-solid fa-headset"></i> Kontak & Operasional Toko
        </h3>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">No. WhatsApp Customer Care</label>
                <input type="text" name="whatsapp" class="form-control" value="<?= htmlspecialchars($currentSettings['whatsapp']); ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Email Support</label>
                <input type="email" name="email_support" class="form-control" value="<?= htmlspecialchars($currentSettings['email_support']); ?>" required>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Jam Operasional Pengiriman</label>
                <input type="text" name="operating_hours" class="form-control" value="<?= htmlspecialchars($currentSettings['operating_hours']); ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Tarif Ongkir Standar (Flat)</label>
                <input type="number" name="flat_shipping" class="form-control" value="<?= htmlspecialchars($currentSettings['flat_shipping']); ?>" required>
            </div>
        </div>

        <!-- ANNOUNCEMENT BANNER -->
        <h3 style="font-size: 1.25rem; margin: 30px 0 20px; color: #d97706; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            <i class="fa-solid fa-bullhorn"></i> Banner Pengumuman Halaman Depan
        </h3>

        <div class="form-group">
            <label class="form-label">Teks Pengumuman Berjalan (Top Announcement)</label>
            <textarea name="announcement" class="form-control" rows="3" required><?= htmlspecialchars($currentSettings['announcement']); ?></textarea>
            <small style="color:var(--text-muted); display:block; margin-top:4px;">Pesan ini akan ditampilkan di bagian paling atas seluruh halaman web SweetPop.</small>
        </div>

        <div style="margin-top: 30px; display:flex; gap:12px;">
            <button type="submit" class="btn-sweet btn-sweet-pink" style="padding: 12px 32px;">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan
            </button>
            <a href="index.php" class="btn-sweet btn-sweet-light" style="padding: 12px 24px;">Batal</a>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
