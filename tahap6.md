# Tahap 6 — Testing, Bug Fix & UAT
**Estimasi:** Minggu 12–13  
**Referensi SRS:** Bagian 10 (Rencana Pengujian)  
**Prasyarat:** Tahap 1–5 selesai

---

## Tujuan
Memastikan seluruh fitur bekerja sesuai SRS melalui pengujian unit, integrasi, dan UAT bersama klien. Memperbaiki bug, mengoptimasi performa, dan menyiapkan aplikasi untuk deployment produksi di server lokal toko.

---

## Checklist Pekerjaan

### 1. Unit Testing (Backend — PHPUnit)

#### Auth & User
- [ ] TC-01: Login berhasil dengan kredensial valid
- [ ] TC-02: Login gagal dengan password salah
- [ ] TC-03: Login gagal jika user `is_active = false`
- [ ] TC-04: Role middleware memblokir kasir dari endpoint owner
- [ ] TC-05: CRUD user: create, update, deactivate

#### Shift
- [ ] TC-06: Buka shift sukses (shift baru dengan status open)
- [ ] TC-07: Gagal buka shift jika sudah ada shift aktif
- [ ] TC-08: Tutup shift menghitung `cash_expected` dan `cash_difference` dengan benar
- [ ] TC-09: Tidak bisa transaksi tanpa shift aktif

#### Produk & Stok
- [ ] TC-10: CRUD produk barang (stok terisi)
- [ ] TC-11: CRUD produk jasa (stok diabaikan)
- [ ] TC-12: Adjustment stok tercatat di `stock_movements`
- [ ] TC-13: Notifikasi stok kritis muncul saat stok < min_stock

#### Harga Cetak
- [ ] TC-14: Kalkulasi harga cetak semua 12 kombinasi (ukuran × tinta × sisi)
- [ ] TC-15: Tier grosir — qty di bawah tier = harga normal
- [ ] TC-16: Tier grosir — qty melebihi tier = harga diskon
- [ ] TC-17: Multiple tiers — sistem memilih tier yang paling menguntungkan

#### Transaksi
- [ ] TC-18: Checkout transaksi lengkap (cetak + ATK + addon) tersimpan dengan benar
- [ ] TC-19: Stok ATK berkurang setelah checkout
- [ ] TC-20: Stok kertas berkurang setelah transaksi cetak
- [ ] TC-21: `stock_movements` tercatat dengan reference invoice
- [ ] TC-22: Invoice number di-generate sesuai format
- [ ] TC-23: Rollback jika error di tengah checkout (stok tidak berkurang)
- [ ] TC-24: Validasi `cash_paid >= total_price` untuk metode tunai
- [ ] TC-25: Kembalian dihitung benar (`cash_paid - total_price`)

#### Laporan
- [ ] TC-26: Dashboard menampilkan omzet hari ini dengan benar
- [ ] TC-27: Laba bersih = omzet - HPP
- [ ] TC-28: Filter laporan per periode berfungsi akurat

### 2. Integration Testing (Alur End-to-End)

- [ ] IT-01: Login → Buka Shift → Tambah item cetak ke keranjang → Tambah addon → Checkout tunai → Struk tercetak → Tutup Shift → Cek rekonsiliasi
- [ ] IT-02: Login → Buka Shift → Tambah ATK → Checkout QRIS → Cek stok berkurang
- [ ] IT-03: Hold transaksi A → Buat transaksi B → Checkout B → Resume A → Checkout A
- [ ] IT-04: Kasir login → coba akses `/users` (403) → coba akses `/reports/sales` (403)
- [ ] IT-05: Owner login → Dashboard menampilkan data terkini → Export laporan PDF

### 3. Frontend Testing (Manual Checklist)

#### Halaman Login
- [ ] Form validasi: field kosong menampilkan error
- [ ] Login sukses → redirect sesuai role
- [ ] Login gagal → Toast error muncul

#### Halaman POS
- [ ] Kalkulasi harga cetak berubah realtime saat ganti kombinasi
- [ ] Barcode scan menambahkan produk ke keranjang
- [ ] Search produk responsive (< 300ms debounce)
- [ ] Addon terikat ke item cetak parent (hapus parent → addon ikut hilang)
- [ ] Keranjang subtotal, diskon, total akurat
- [ ] Hold/Resume: simpan 3 transaksi → resume satu per satu → semua data utuh
- [ ] Keyboard shortcuts: F1, F2, F5, F8, ESC berfungsi
- [ ] Modal bayar: quick amount buttons mengisi nominal
- [ ] Kembalian terhitung realtime
- [ ] Setelah checkout, keranjang kosong & fokus ke search

#### Halaman Owner
- [ ] CRUD user, produk, kategori, harga cetak, addon — semua CRUD flow benar
- [ ] Stok rendah ditandai visual warning
- [ ] Dashboard grafik ter-render dengan data nyata
- [ ] Export PDF/Excel menghasilkan file yang bisa dibuka

### 4. Performance Testing

- [ ] Waktu respons tambah item ke keranjang < 100ms
- [ ] Waktu checkout (POST → response) < 1 detik
- [ ] Halaman POS load time < 2 detik (termasuk init data)
- [ ] Dashboard load time < 3 detik
- [ ] Search produk response < 500ms

### 5. Security Testing

- [ ] Coba akses API endpoint owner tanpa login → 401
- [ ] Coba akses API endpoint owner dengan token kasir → 403
- [ ] SQL injection test pada input search dan form
- [ ] XSS test pada input nama produk dan catatan

### 6. Bug Fix & Polish

- [ ] Fix semua bug yang ditemukan dari testing di atas
- [ ] Validasi error messages user-friendly (bahasa Indonesia)
- [ ] Loading states di semua tombol dan halaman
- [ ] Empty states: tampilan saat tabel kosong, keranjang kosong
- [ ] Responsive check di resolusi 1366×768 dan 1920×1080
- [ ] Dark mode check (jika PrimeVue Aura dark mode diaktifkan)

### 7. UAT (User Acceptance Testing) dengan Klien

- [ ] **Skenario 1:** Klien pemilik toko login sebagai Owner → setup data produk, harga cetak, addon
- [ ] **Skenario 2:** Kasir buka shift → transaksi fotokopi 100 lembar A4 BW + jilid → bayar tunai → struk tercetak
- [ ] **Skenario 3:** Kasir jual 5 pcs ATK → scan barcode → bayar QRIS → cek stok berkurang
- [ ] **Skenario 4:** Kasir hold transaksi → layani pelanggan lain → resume → selesaikan
- [ ] **Skenario 5:** Kasir tutup shift → cek rekonsiliasi kas → Owner verifikasi di laporan
- [ ] **Skenario 6:** Owner lihat dashboard → cek omzet hari ini cocok → export laporan

### 8. Persiapan Deployment Produksi

- [ ] Setup Laragon/XAMPP di PC toko
- [ ] Clone project ke PC toko
- [ ] `composer install --optimize-autoloader --no-dev`
- [ ] `npm run build` (production build)
- [ ] Setup `.env` produksi (APP_DEBUG=false, database credentials)
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed` (data awal)
- [ ] Konfigurasi printer thermal di `config/pos.php`
- [ ] Test koneksi printer
- [ ] Buat shortcut desktop untuk buka browser ke `http://localhost`
- [ ] Dokumentasi singkat penggunaan untuk kasir

---

## Kriteria Selesai (Definition of Done)

- [ ] Semua unit test (TC-01 s/d TC-28) PASS
- [ ] Semua integration test (IT-01 s/d IT-05) PASS
- [ ] Semua checklist frontend manual terverifikasi
- [ ] Performa memenuhi standar NF-01 & NF-02
- [ ] Tidak ada bug critical atau high yang tersisa
- [ ] UAT disetujui oleh klien
- [ ] Aplikasi ter-deploy dan berjalan di PC toko
- [ ] Printer thermal terkoneksi dan struk tercetak benar

---

## Deliverable Akhir

- [ ] Source code lengkap di repository Git
- [ ] Database ter-migrasi dengan data awal (seeder)
- [ ] Dokumentasi SRS final (versi 2.0)
- [ ] Panduan instalasi & penggunaan singkat
- [ ] Aplikasi live di PC toko klien
