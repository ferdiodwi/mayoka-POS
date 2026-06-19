# Software Requirements Specification (SRS)
## Proyek: Aplikasi Kasir (Point of Sale) Toko Fotokopi & ATK — **MAYOKA POS**

**Versi:** 2.0  
**Tanggal:** 20 Juni 2026  
**Tim Pengembang:** Mayoka Team

---

## 1. Pendahuluan

### 1.1 Tujuan Dokumen
Dokumen SRS ini mendefinisikan secara rinci kebutuhan fungsional dan non-fungsional dalam pengembangan Aplikasi Kasir (Point of Sale) khusus untuk bisnis fotokopi dan penjualan Alat Tulis Kantor (ATK). Dokumen ini berfungsi sebagai panduan teknis bagi tim pengembang dan acuan validasi fitur bagi Business Development/Marketing serta klien pemilik toko.

### 1.2 Ruang Lingkup Produk
Aplikasi kasir berbasis Web (SPA) dengan pendekatan *Offline-First* / *Localhost Hybrid*. Dirancang untuk:
- Menyelesaikan masalah pencatatan transaksi kompleks pada bisnis fotokopi.
- Mencegah kebocoran keuangan akibat salah hitung variasi jasa cetak.
- Mengotomatisasi manajemen stok barang fisik (ATK) dan bahan baku kertas secara real-time.

### 1.3 Definisi dan Akronim

| Akronim | Definisi |
|---------|----------|
| **POS** | *Point of Sale*, sistem tempat kasir melakukan transaksi pembayaran |
| **ATK** | Alat Tulis Kantor (pulpen, buku, map, dll) |
| **SPA** | *Single Page Application*, aplikasi web dinamis tanpa reload halaman |
| **HPP** | Harga Pokok Penjualan, biaya dasar untuk menghitung laba |
| **ESCPOS** | *ESC/POS Protocol*, standar komunikasi printer thermal |

### 1.4 Referensi
- Dokumentasi Laravel 13: https://laravel.com/docs
- Dokumentasi Vue.js 3: https://vuejs.org
- Dokumentasi PrimeVue 4: https://primevue.org
- Standar IEEE 830 untuk SRS

---

## 2. Deskripsi Umum

### 2.1 Perspektif Produk
Aplikasi beroperasi menggunakan arsitektur **SPA Monolith Modern**:
- **Backend:** Laravel 13 menyediakan REST API dan meng-*serve* halaman Blade tunggal sebagai entry point.
- **Frontend:** Vue.js 3 berjalan sebagai SPA penuh dengan **Vue Router** mengontrol seluruh navigasi di sisi klien.
- **Build Tool:** Vite 8 dengan `laravel-vite-plugin` untuk integrasi asset bundling.
- **UI Library:** PrimeVue 4 (tema Aura) + Tailwind CSS v4 untuk komponen UI yang konsisten dan responsif.

Aplikasi ditargetkan berjalan pada PC/Laptop standar di ruko fotokopi, terhubung dengan *thermal printer* dan *barcode scanner*.

### 2.2 Karakteristik Pengguna (User Roles)

| Role | Hak Akses | Batasan |
|------|-----------|---------|
| **Kasir** | POS utama, buka/tutup shift, rekonsiliasi kas | Tidak boleh lihat laporan keuangan, ubah harga master |
| **Owner** | Akses penuh (Superadmin): master data, dashboard analitik, manajemen karyawan | - |

### 2.3 Batasan Operasional
- Dipasang di server lokal (Laragon/XAMPP) — tanpa ketergantungan internet.
- Layar kasir dioptimalkan untuk resolusi minimal **1366×768** landscape.
- Database lokal MySQL — single-instance, bukan distributed.

### 2.4 Asumsi dan Ketergantungan
- PC toko memiliki minimal 4GB RAM dan prosesor dual-core.
- Thermal printer mendukung protokol ESC/POS (58mm atau 80mm).
- Barcode scanner berfungsi sebagai keyboard emulator (HID mode).

---

## 3. Kebutuhan Fungsional (Functional Requirements)

### 3.1 Modul Autentikasi & Otorisasi

| ID | Requirement | Prioritas |
|----|-------------|-----------|
| **RF-AU-01** | Halaman login dengan username & password | Tinggi |
| **RF-AU-02** | Middleware role-based access control (Kasir vs Owner) | Tinggi |
| **RF-AU-03** | Auto-redirect kasir ke halaman POS setelah login | Tinggi |
| **RF-AU-04** | Owner dapat CRUD akun kasir (nama, username, password, status aktif) | Tinggi |
| **RF-AU-05** | Logout otomatis setelah periode inaktif (konfigurabel) | Sedang |

### 3.2 Modul Transaksi & Kasir Utama (POS)

| ID | Requirement | Prioritas |
|----|-------------|-----------|
| **RF-POS-01** | Kalkulator Jasa Cetak Dinamis: hitung biaya berdasarkan kombinasi Ukuran Kertas (A4/F4/A3), Jenis Tinta (BW/Warna), Sisi Cetak (1 sisi/bolak-balik) | Tinggi |
| **RF-POS-02** | Harga Jasa Bertingkat/Grosir: harga per lembar berubah otomatis sesuai kuantitas (misal: 1-50 = Rp200, >50 = Rp150) | Tinggi |
| **RF-POS-03** | Bundling Jasa Tambahan: kasir dapat menambahkan jilid lakban, jilid mika, laminating ke baris item transaksi cetak | Tinggi |
| **RF-POS-04** | Input ATK Cepat: pencarian teks instan atau scan barcode langsung ke keranjang | Tinggi |
| **RF-POS-05** | Hold & Resume: simpan transaksi ke antrean sementara, panggil kembali kapan saja | Tinggi |
| **RF-POS-06** | Kalkulator Kembalian & Multi-Metode Bayar: tunai (hitung kembalian), QRIS, transfer | Tinggi |
| **RF-POS-07** | Cetak Struk Thermal: kirim instruksi cetak 58mm/80mm tanpa dialog cetak browser | Tinggi |
| **RF-POS-08** | Diskon per item atau per transaksi (nominal atau persentase) | Sedang |
| **RF-POS-09** | Pembatalan item individual dari keranjang | Tinggi |
| **RF-POS-10** | Riwayat transaksi hari ini dapat dilihat kasir untuk keperluan reprint struk | Sedang |

### 3.3 Modul Manajemen Inventaris & Produk

| ID | Requirement | Prioritas |
|----|-------------|-----------|
| **RF-INV-01** | Diferensiasi Tipe Produk: "Barang Fisik" (stok terbatas) vs "Jasa" (tanpa limit stok) | Tinggi |
| **RF-INV-02** | Pengurangan Stok Bahan Baku Kertas otomatis per transaksi cetak (konversi lembar → rim) | Tinggi |
| **RF-INV-03** | Notifikasi Stok Kritis: peringatan visual jika stok di bawah batas minimum (safety stock) | Tinggi |
| **RF-INV-04** | CRUD Produk: nama, barcode, kategori, harga jual, HPP, stok awal, minimal stok | Tinggi |
| **RF-INV-05** | Kategori produk: ATK, Kertas, Jasa Cetak, Jasa Jilid, Lainnya | Sedang |
| **RF-INV-06** | Riwayat pergerakan stok (stock in / stock out / adjustment) | Sedang |
| **RF-INV-07** | Import produk massal via file CSV/Excel | Rendah |

### 3.4 Modul Manajemen Shift & Keamanan Keuangan

| ID | Requirement | Prioritas |
|----|-------------|-----------|
| **RF-SH-01** | Buka Shift: input uang modal awal (cash drawer) — wajib sebelum transaksi | Tinggi |
| **RF-SH-02** | Tutup Shift: rekonsiliasi uang fisik akhir vs total transaksi tunai, deteksi selisih | Tinggi |
| **RF-SH-03** | Laporan per shift: ringkasan jumlah transaksi, total omzet, breakdown per metode bayar | Tinggi |
| **RF-SH-04** | Tidak boleh ada 2 shift aktif bersamaan untuk 1 kasir | Tinggi |

### 3.5 Modul Pelaporan & Dashboard Owner

| ID | Requirement | Prioritas |
|----|-------------|-----------|
| **RF-RPT-01** | Dashboard Laba-Rugi: omzet harian/mingguan/bulanan, HPP, laba bersih | Tinggi |
| **RF-RPT-02** | Analitik Produk Terlaris: grafik/tabel item paling laku | Sedang |
| **RF-RPT-03** | Laporan penjualan per kasir per shift | Sedang |
| **RF-RPT-04** | Rekap stok: stok awal, masuk, keluar, stok akhir per periode | Sedang |
| **RF-RPT-05** | Export laporan ke PDF atau Excel | Rendah |

### 3.6 Modul Pengaturan Harga Jasa Cetak (Khusus Owner)

| ID | Requirement | Prioritas |
|----|-------------|-----------|
| **RF-PRC-01** | CRUD matriks harga cetak berdasarkan: ukuran kertas × jenis tinta × sisi cetak | Tinggi |
| **RF-PRC-02** | Konfigurasi tier harga grosir per kombinasi (jumlah minimal → harga diskon) | Tinggi |
| **RF-PRC-03** | CRUD harga jasa tambahan (jilid, laminating, dll) | Tinggi |

---

## 4. Kebutuhan Non-Fungsional

| ID | Kategori | Requirement |
|----|----------|-------------|
| **NF-01** | Performa | Respons UI keranjang < 100ms (Vue.js reactivity) |
| **NF-02** | Performa | Checkout hingga DB commit < 1 detik (server lokal) |
| **NF-03** | Keamanan | Semua mutasi DB transaksi dibungkus `DB::transaction()` (rollback on failure) |
| **NF-04** | Keamanan | Middleware Laravel memblokir akses URL kasir ke halaman owner |
| **NF-05** | Keamanan | Password di-hash menggunakan bcrypt (12 rounds) |
| **NF-06** | Usability | Keyboard shortcut: `F5` = bayar, `ESC` = batal, auto-focus ke search bar setelah transaksi |
| **NF-07** | Usability | UI responsif minimal 1366×768, optimasi untuk layar landscape |
| **NF-08** | Reliability | Data transaksi tidak boleh hilang saat browser crash (auto-save keranjang ke localStorage) |
| **NF-09** | Maintainability | Kode mengikuti standar PSR-12 (PHP) dan Vue.js Composition API |

---

## 5. Arsitektur Teknologi & Tech Stack

| Layer | Teknologi | Keterangan |
|-------|-----------|------------|
| **Backend** | Laravel 13 (PHP 8.3+) | Routing, Eloquent ORM, DB Transaction, Auth middleware |
| **Frontend** | Vue.js 3.5 + Vue Router 5 | SPA dengan Composition API (`<script setup>`) |
| **UI Components** | PrimeVue 4 (Tema Aura) | DataTable, Dialog, Toast, Form inputs, Charts |
| **Styling** | Tailwind CSS v4 | Utility-first CSS framework |
| **Build Tool** | Vite 8 + laravel-vite-plugin | HMR, asset bundling |
| **Database** | MySQL 8 | Relational DB lokal |
| **Charts** | Chart.js 3 | Grafik dashboard owner |
| **Printer** | `mike42/escpos-php` | Komunikasi ESC/POS thermal printer |
| **Server Lokal** | Laragon / XAMPP | Environment development & production lokal |

### 5.1 Diagram Arsitektur Sederhana

```
┌─────────────────────────────────────────────┐
│              Browser (SPA)                  │
│  ┌────────┐  ┌───────────┐  ┌───────────┐  │
│  │Vue.js 3│  │ PrimeVue  │  │Vue Router │  │
│  └───┬────┘  └───────────┘  └───────────┘  │
│      │ Axios/Fetch (REST API)               │
└──────┼──────────────────────────────────────┘
       │
┌──────▼──────────────────────────────────────┐
│           Laravel 13 (Backend)              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │API Routes│  │Controllers│  │Middleware │  │
│  └────┬─────┘  └─────┬────┘  └──────────┘  │
│       │        ┌──────▼────┐                │
│       │        │ Eloquent  │                │
│       │        │   ORM     │                │
│       │        └─────┬─────┘                │
└───────┼──────────────┼──────────────────────┘
        │              │
   ┌────▼───┐    ┌─────▼─────┐
   │Thermal │    │  MySQL 8  │
   │Printer │    │ (Lokal)   │
   └────────┘    └───────────┘
```

---

## 6. Desain Basis Data (Skema Relasi)

### 6.1 Tabel Inti

#### `users`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | Auto increment |
| name | VARCHAR(100) | Nama lengkap |
| username | VARCHAR(50) UNIQUE | Login username |
| password | VARCHAR(255) | Bcrypt hash |
| role | ENUM('kasir','owner') | Hak akses |
| is_active | BOOLEAN | Status aktif/nonaktif |
| timestamps | | created_at, updated_at |

#### `categories`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | Auto increment |
| name | VARCHAR(100) | Nama kategori (ATK, Kertas, dll) |
| timestamps | | |

#### `products`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | Auto increment |
| category_id | FK → categories | Kategori produk |
| name | VARCHAR(150) | Nama produk |
| barcode | VARCHAR(50) NULLABLE UNIQUE | Kode barcode |
| type | ENUM('barang','jasa') | Tipe produk |
| price | DECIMAL(12,2) | Harga jual |
| cost_price | DECIMAL(12,2) | HPP (Harga Pokok) |
| stock | INT DEFAULT 0 | Stok saat ini (barang fisik) |
| min_stock | INT DEFAULT 0 | Batas minimum stok |
| unit | VARCHAR(20) | Satuan (pcs, rim, lembar) |
| is_active | BOOLEAN | Masih dijual atau tidak |
| timestamps | | |

#### `print_prices` (Matriks Harga Cetak)
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | |
| paper_size | ENUM('A4','F4','A3') | Ukuran kertas |
| color_type | ENUM('bw','color') | Hitam putih / warna |
| side_type | ENUM('single','duplex') | 1 sisi / bolak-balik |
| price_per_sheet | DECIMAL(10,2) | Harga per lembar |
| cost_per_sheet | DECIMAL(10,2) | HPP per lembar |
| timestamps | | |

#### `print_price_tiers` (Harga Grosir Bertingkat)
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | |
| print_price_id | FK → print_prices | Referensi harga cetak |
| min_qty | INT | Jumlah minimum lembar |
| price_per_sheet | DECIMAL(10,2) | Harga diskon per lembar |
| timestamps | | |

#### `addon_services` (Jasa Tambahan)
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | |
| name | VARCHAR(100) | Jilid Lakban, Laminating, dll |
| price | DECIMAL(10,2) | Harga jasa |
| timestamps | | |

#### `shifts`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | |
| user_id | FK → users | Kasir |
| started_at | DATETIME | Waktu buka shift |
| ended_at | DATETIME NULLABLE | Waktu tutup shift |
| cash_start | DECIMAL(12,2) | Modal awal laci |
| cash_end | DECIMAL(12,2) NULLABLE | Uang fisik akhir |
| cash_expected | DECIMAL(12,2) NULLABLE | Uang seharusnya (hitung sistem) |
| cash_difference | DECIMAL(12,2) NULLABLE | Selisih (plus/minus) |
| status | ENUM('open','closed') | |
| notes | TEXT NULLABLE | Catatan penutupan |
| timestamps | | |

#### `transactions`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | |
| user_id | FK → users | Kasir yang memproses |
| shift_id | FK → shifts | Shift aktif |
| invoice_number | VARCHAR(30) UNIQUE | Nomor nota (auto-generate) |
| subtotal | DECIMAL(12,2) | Total sebelum diskon |
| discount_amount | DECIMAL(12,2) DEFAULT 0 | Diskon transaksi |
| total_price | DECIMAL(12,2) | Total akhir |
| cash_paid | DECIMAL(12,2) | Uang dibayar |
| cash_change | DECIMAL(12,2) | Kembalian |
| payment_method | ENUM('cash','qris','transfer') | |
| status | ENUM('completed','voided') | |
| created_at | DATETIME | Waktu transaksi |

#### `transaction_items`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | |
| transaction_id | FK → transactions | |
| item_type | ENUM('product','print','addon') | Jenis item |
| product_id | FK → products NULLABLE | Jika tipe barang/jasa |
| print_price_id | FK → print_prices NULLABLE | Jika tipe cetak |
| addon_service_id | FK → addon_services NULLABLE | Jika tipe addon |
| description | VARCHAR(255) | Deskripsi (misal: "A4 Warna Bolak-balik") |
| qty | INT | Jumlah |
| unit_price | DECIMAL(10,2) | Harga satuan (setelah tier) |
| subtotal | DECIMAL(12,2) | qty × unit_price |
| discount | DECIMAL(10,2) DEFAULT 0 | Diskon per item |
| parent_item_id | FK → transaction_items NULLABLE | Referensi item induk (untuk addon) |

#### `stock_movements` (Riwayat Pergerakan Stok)
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | BIGINT PK | |
| product_id | FK → products | |
| type | ENUM('in','out','adjustment') | Jenis pergerakan |
| qty | INT | Jumlah (positif masuk, negatif keluar) |
| reference | VARCHAR(100) | Referensi (invoice, manual, dll) |
| notes | TEXT NULLABLE | |
| user_id | FK → users | Yang melakukan |
| timestamps | | |

### 6.2 Diagram Relasi (ERD)

```
users 1──────M shifts
users 1──────M transactions
shifts 1─────M transactions

categories 1─M products
products 1───M stock_movements
products 1───M transaction_items (item_type='product')

print_prices 1──M print_price_tiers
print_prices 1──M transaction_items (item_type='print')

addon_services 1─M transaction_items (item_type='addon')

transactions 1───M transaction_items
transaction_items 1──M transaction_items (parent_item_id → addon bundling)
```

---

## 7. Use Case Utama

### UC-01: Proses Transaksi Cetak/Fotokopi
**Aktor:** Kasir  
**Alur Utama:**
1. Kasir memilih jenis jasa cetak (ukuran, tinta, sisi).
2. Sistem menampilkan harga per lembar sesuai matriks `print_prices`.
3. Kasir input jumlah lembar.
4. Sistem cek `print_price_tiers` — jika qty melebihi tier, harga otomatis turun.
5. Kasir opsional menambahkan addon (jilid/laminating) yang terikat ke item cetak tsb.
6. Item masuk ke keranjang.

### UC-02: Proses Transaksi ATK (Barang Fisik)
**Aktor:** Kasir  
**Alur Utama:**
1. Kasir scan barcode atau ketik nama produk.
2. Sistem mencari produk di `products` dan menampilkan hasil.
3. Kasir pilih produk → masuk ke keranjang dengan harga dari `products.price`.
4. Saat checkout, `products.stock` berkurang otomatis dan `stock_movements` tercatat.

### UC-03: Hold & Resume Transaksi
**Aktor:** Kasir  
**Alur Utama:**
1. Di tengah transaksi, kasir tekan tombol "Hold".
2. Keranjang saat ini disimpan ke memori/localStorage dengan label (nama pelanggan opsional).
3. Kasir melayani pelanggan baru dengan keranjang kosong.
4. Kasir pilih transaksi dari daftar "Held" → keranjang di-restore, lanjutkan transaksi.

### UC-04: Buka & Tutup Shift
**Aktor:** Kasir  
**Alur Utama:**
1. **Buka:** Kasir login → sistem cek apakah ada shift aktif. Jika tidak, muncul form input modal awal → shift dibuat dengan status `open`.
2. **Tutup:** Kasir klik "Tutup Shift" → input uang fisik akhir → sistem hitung selisih → shift ditutup.

### UC-05: Dashboard Analitik Owner
**Aktor:** Owner  
**Alur Utama:**
1. Owner login → redirect ke dashboard.
2. Dashboard menampilkan: omzet hari ini, omzet bulan ini, laba bersih, produk terlaris.
3. Owner dapat filter per tanggal/periode.

---

## 8. Keyboard Shortcuts (Layar POS)

| Shortcut | Aksi |
|----------|------|
| `F1` | Fokus ke kolom pencarian/barcode |
| `F2` | Buka daftar transaksi ditahan (Hold) |
| `F5` | Buka modal pembayaran |
| `F8` | Hold transaksi aktif |
| `ESC` | Tutup modal / batalkan aksi |
| `Enter` | Konfirmasi pada modal aktif |

---

## 9. Format Nomor Invoice

Format: `INV-{YYYYMMDD}-{SHIFT_ID}-{SEQUENCE}`  
Contoh: `INV-20260620-5-0023`

---

## 10. Rencana Pengujian (Testing Plan)

### 10.1 Unit Testing (Backend)
| Test Case | Deskripsi |
|-----------|-----------|
| TC-01 | Kalkulasi harga cetak sesuai matriks (semua kombinasi ukuran × tinta × sisi) |
| TC-02 | Kalkulasi harga tier grosir (batas qty → harga berubah) |
| TC-03 | Pengurangan stok otomatis setelah transaksi checkout |
| TC-04 | Rollback transaksi jika terjadi error di tengah proses |
| TC-05 | Validasi shift: tidak bisa transaksi tanpa shift aktif |
| TC-06 | Rekonsiliasi shift: selisih kas dihitung benar |

### 10.2 Integration Testing
| Test Case | Deskripsi |
|-----------|-----------|
| TC-07 | Flow lengkap: login → buka shift → transaksi cetak + ATK → bayar → struk tercetak |
| TC-08 | Hold transaksi A → buat transaksi B → resume A → checkout A |
| TC-09 | Role kasir tidak bisa akses endpoint/halaman owner |

### 10.3 UAT (User Acceptance Testing)
- Klien pemilik toko menguji alur transaksi nyata selama 1 hari operasional.
- Validasi akurasi struk thermal dan perhitungan kembalian.
- Validasi laporan harian cocok dengan rekap manual.

---

## 11. Milestone Pengembangan

| Fase | Deliverable | Estimasi |
|------|-------------|----------|
| **Fase 1** | Auth, CRUD User, Shift Management | Minggu 1-2 |
| **Fase 2** | Master Produk, Kategori, Matriks Harga Cetak | Minggu 3-4 |
| **Fase 3** | Halaman POS: keranjang, kalkulasi, Hold/Resume | Minggu 5-7 |
| **Fase 4** | Checkout, Pembayaran, Cetak Struk | Minggu 8-9 |
| **Fase 5** | Dashboard Owner, Laporan, Grafik | Minggu 10-11 |
| **Fase 6** | Testing, Bug Fix, UAT | Minggu 12-13 |

---

*Dokumen Spesifikasi Kebutuhan Perangkat Lunak — Versi 2.0 — MAYOKA POS*
