# 🎂 SweetPop Bakery - E-Commerce & Transaction Processing System

Sistem Informasi E-Commerce Toko Kue Artisan modern yang terintegrasi dengan **Midtrans Core API Payment Gateway**, **Sweetie AI Virtual Assistant (Gemini)**, dan **Manajemen Transaksi Kasir Toko**.

---

## ✨ Fitur Utama

1. **Katalog Kue Artisan Interaktif:**
   - Eksplorasi menu kue, varian rasa, harga, dan ketersediaan stok secara real-time.
   - Filter cepat berdasarkan kategori (*Cake, Dessert Box, Cookies, Brownies, Pudding, Drinks*).
   - Pencarian langsung (*live search*).

2. **Keranjang Belanja & Checkout Multi-Channel:**
   - Keranjang interaktif dengan kalkulasi otomatis subtotal dan pajak.
   - Pilihan metode pengiriman **Cash On Delivery (COD)** dengan penyesuaian otomatis flat ongkos kirim Rp 10.000.

3. **Integrasi Midtrans Payment Gateway:**
   - Dukungan pembayaran otomatis via **QRIS** (Gopay, ShopeePay, Dana, LinkAja, BCA, dll).
   - Dukungan **Virtual Account (VA)** perbankan nasional: **BCA**, **BRI**, **BNI**, dan **Mandiri Bill Key**.
   - Verifikasi pembayaran instan dan simulasi pembayaran otomatis sandbox.

4. **Sweetie AI Assistant (Google Gemini):**
   - Widget chatbot ramah di pojok halaman untuk membantu pelanggan berkonsultasi rasa kue favorit, rekomendasi menu untuk acara/ulang tahun, serta panduan pemesanan 24/7.

5. **Dashboard Back-Office Admin:**
   - Manajemen produk (Tambah, Edit, Hapus kue & kelola stok).
   - Manajemen pesanan masuk secara real-time (*Pending, Processing, Completed, Cancelled*).
   - Rekap transaksi penjualan dan laporan pendapatan toko.

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.x (Native MVC-style Architecture)
- **Database:** MySQL / MariaDB (RDBMS)
- **Frontend:** HTML5, CSS3, Tailwind CSS, JavaScript (Vanilla & jQuery)
- **Payment Gateway:** Midtrans Core API (Sandbox / Production)
- **Artificial Intelligence:** Google Gemini AI Interactions API
- **Local Server Environment:** Laragon / XAMPP / Apache

---

## 🚀 Panduan Instalasi Lokal

### 1. Clone Repository
```bash
git clone https://github.com/zahrarokhadatul2024-lgtm/sweetpop.git
cd sweetpop
```

### 2. Konfigurasi Database
1. Buka **phpMyAdmin** atau GUI database MySQL Anda (misal via Laragon / XAMPP).
2. Buat database baru bernama `sweetpop`:
   ```sql
   CREATE DATABASE sweetpop;
   ```
3. Import file `schema.sql` ke dalam database `sweetpop`.

### 3. Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Sesuaikan konfigurasi kredensial database dan API key Anda pada file `.env`:
```ini
# Google Gemini API Key
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-2.5-flash

# Database MySQL
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=sweetpop

# Midtrans Payment Gateway (Sandbox)
MIDTRANS_SERVER_KEY=your_midtrans_server_key_here
```

### 4. Jalankan Aplikasi
Akses website melalui browser:
- **Toko Pelanggan:** `http://localhost/sweetpop/`
- **Dashboard Admin:** `http://localhost/sweetpop/admin/`

---

## 👤 Penyusun Proyek
- **Nama:** Zahra Rokhadatul Aisy Ramadhani
- **NIM:** 24050530021
- **Program Studi:** Sistem Informasi
- **Mata Kuliah:** Manajemen Sistem Informasi
