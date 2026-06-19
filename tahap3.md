# Tahap 3 — Halaman POS: Keranjang, Kalkulasi & Hold/Resume
**Estimasi:** Minggu 5–7  
**Referensi SRS:** RF-POS-01 s/d RF-POS-05, RF-POS-08, RF-POS-09  
**Prasyarat:** Tahap 1 & 2 selesai (Auth + Master Data sudah siap)

---

## Tujuan
Membangun halaman kasir utama (POS) — jantung dari aplikasi. Kasir dapat menambahkan item cetak/fotokopi (dengan kalkulasi dinamis), input ATK via barcode/search, menambahkan addon ke item cetak, mengelola keranjang, dan menggunakan fitur Hold/Resume transaksi.

---

## Checklist Pekerjaan

### 1. Frontend — Halaman POS (`views/pages/Pos.vue`)

Layout halaman POS dibagi menjadi **2 panel utama** (landscape-optimized):

```
┌─────────────────────────────────┬────────────────────┐
│        PANEL KIRI (60%)         │  PANEL KANAN (40%) │
│                                 │                    │
│  [Search/Barcode Input]         │  KERANJANG BELANJA │
│  ─────────────────────          │  ──────────────────│
│  [Tab: Cetak | ATK | Addon]    │  Item 1   Rp xxxxx │
│                                 │    + Addon          │
│  Form cetak / Grid produk /    │  Item 2   Rp xxxxx │
│  List addon tergantung tab     │  ...                │
│                                 │  ──────────────────│
│                                 │  Subtotal  Rp xxxx │
│                                 │  Diskon    Rp xxxx │
│                                 │  TOTAL     Rp xxxx │
│                                 │  ──────────────────│
│                                 │  [Hold] [Bayar F5] │
└─────────────────────────────────┴────────────────────┘
```

### 2. Frontend — Panel Kiri: Input Item

#### Tab "Cetak/Fotokopi"
- [ ] Dropdown/select: Ukuran Kertas (A4/F4/A3)
- [ ] Radio button: Tinta (Hitam Putih / Warna)
- [ ] Radio button: Sisi (1 Sisi / Bolak-balik)
- [ ] InputNumber: Jumlah Lembar
- [ ] **Preview harga realtime**: saat user mengubah kombinasi/qty, panggil `GET /api/print-prices/calculate` atau hitung di frontend dari data matriks yang sudah di-cache
- [ ] Tampilkan info tier: "Harga normal: Rp200/lbr — Anda dapat harga grosir: Rp150/lbr (>50 lbr)"
- [ ] Tombol "Tambah ke Keranjang"
- [ ] Setelah ditambahkan, tampilkan tombol kecil "Tambah Addon" di baris item cetak di keranjang

#### Tab "ATK / Barang"
- [ ] Input pencarian nama produk (autocomplete/debounce 300ms)
- [ ] Atau scan barcode → otomatis cari dan tambahkan ke keranjang
- [ ] Tampilkan hasil pencarian sebagai list: Nama, Harga, Stok tersedia
- [ ] Klik produk → masuk ke keranjang (qty default = 1, bisa diubah)
- [ ] Jika stok = 0, produk tetap muncul tapi tombol disabled + badge "Habis"

#### Tab "Addon / Jasa Tambahan"
- [ ] Grid/list addon aktif dari `addon_services`
- [ ] Klik addon → pilih item cetak parent di keranjang → addon terkait ditambahkan sebagai sub-item

### 3. Frontend — Panel Kanan: Keranjang Belanja

- [ ] List item keranjang dengan struktur:
  ```
  1. A4 Warna Bolak-balik × 100 lbr     Rp 50.000
     ├── Jilid Lakban × 1                 Rp  3.000
     └── Laminating A4 × 1                Rp  5.000
  2. Pulpen Pilot × 3                     Rp 15.000
  ```
- [ ] Setiap item punya tombol: Edit Qty, Hapus, Tambah Diskon
- [ ] Addon ditampilkan indented di bawah item parent
- [ ] Hapus item parent → addon ikut terhapus
- [ ] **Ringkasan bawah keranjang:**
  - Subtotal (sebelum diskon)
  - Diskon total
  - **Grand Total** (font besar, bold)
- [ ] Tombol "Hold (F8)" — simpan keranjang
- [ ] Tombol "Bayar (F5)" — buka modal pembayaran (dikerjakan di Tahap 4)
- [ ] Tombol "Kosongkan Keranjang" dengan konfirmasi

### 4. Frontend — Fitur Hold & Resume

- [ ] **Hold:** Simpan state keranjang ke `localStorage` dengan key unik
  - Data tersimpan: items[], customer_label (opsional), timestamp
  - Kasir bisa memberi label opsional (misal: "Pak Budi")
- [ ] **Resume:** Tombol "Transaksi Ditahan (F2)" di topbar/toolbar POS
  - Tampilkan Dialog/Drawer dengan daftar transaksi yang di-hold
  - Setiap entry: Label, jumlah item, total sementara, waktu hold
  - Klik entry → restore keranjang, hapus dari hold list
- [ ] **Limit:** Maksimal 10 transaksi ditahan secara bersamaan
- [ ] **Persistence:** Data hold bertahan walau browser di-refresh (localStorage)

### 5. Frontend — Composables & State

- [ ] **`composables/useCart.js`** — State management keranjang:
  - `cartItems` — reactive array of cart items
  - `addPrintItem(printConfig)` — tambah item cetak
  - `addProductItem(product, qty)` — tambah item ATK
  - `addAddonToItem(parentIndex, addon)` — tambah addon ke item cetak
  - `updateItemQty(index, qty)` — update qty
  - `removeItem(index)` — hapus item (+ addon children)
  - `applyItemDiscount(index, discount)` — diskon per item
  - `applyTransactionDiscount(amount)` — diskon per transaksi
  - `clearCart()` — kosongkan
  - `subtotal`, `totalDiscount`, `grandTotal` — computed
  - `cartCount` — computed jumlah item

- [ ] **`composables/useHoldTransactions.js`** — Hold/Resume:
  - `holdList` — reactive array dari localStorage
  - `holdCurrentCart(label)` — simpan cart saat ini
  - `resumeTransaction(index)` — restore cart dari hold
  - `removeHold(index)` — hapus hold tanpa restore
  - Sync otomatis ke/dari localStorage

- [ ] **`composables/usePosData.js`** — Cache data master untuk POS:
  - `printPrices` — load semua matriks harga cetak + tiers sekali saat POS mount
  - `addonServices` — load semua addon aktif
  - `calculatePrintPrice(size, color, side, qty)` — hitung harga dari cache lokal
  - `searchProducts(query)` — panggil API search dengan debounce

### 6. Frontend — Keyboard Shortcuts

- [ ] Implementasi keyboard listener di halaman POS:
  - `F1` → fokus ke search bar
  - `F2` → buka dialog Hold list
  - `F5` → buka modal bayar (placeholder, Tahap 4)
  - `F8` → hold transaksi aktif
  - `ESC` → tutup modal/dialog yang sedang terbuka

### 7. Frontend — Routing

- [ ] Tambah route `/pos` → `Pos.vue` (di dalam AppLayout, kasir & owner)
- [ ] Set sebagai default redirect untuk role kasir setelah login

### 8. Backend — Endpoint Tambahan

- [ ] `GET /api/pos/init-data` — Return semua data yang dibutuhkan POS dalam 1 request:
  - `print_prices` (with tiers)
  - `addon_services` (active only)
  - `active_shift` (shift kasir saat ini)
  - Optimasi: mengurangi jumlah API call saat halaman POS dimuat

---

## Kriteria Selesai (Definition of Done)

- [ ] Kasir bisa menambahkan item cetak ke keranjang dengan kalkulasi harga dinamis (ukuran × tinta × sisi × qty)
- [ ] Harga grosir otomatis teraplikasi saat qty melebihi tier
- [ ] Kasir bisa menambahkan addon (jilid/laminating) ke item cetak sebagai sub-item
- [ ] Kasir bisa mencari produk ATK via nama atau scan barcode dan menambahkan ke keranjang
- [ ] Keranjang menampilkan subtotal, diskon, dan grand total akurat
- [ ] Kasir bisa Hold transaksi, melihat daftar hold, dan Resume kapan saja
- [ ] Data hold bertahan setelah browser refresh
- [ ] Keyboard shortcuts F1, F2, F5, F8, ESC berfungsi
- [ ] Halaman POS responsif dan optimal di resolusi 1366×768 landscape

---

## File yang Dibuat/Dimodifikasi

### Baru:
- `resources/js/views/pages/Pos.vue`
- `resources/js/components/pos/PrintForm.vue`
- `resources/js/components/pos/ProductSearch.vue`
- `resources/js/components/pos/AddonPicker.vue`
- `resources/js/components/pos/CartPanel.vue`
- `resources/js/components/pos/CartItem.vue`
- `resources/js/components/pos/HoldListDialog.vue`
- `resources/js/composables/useCart.js`
- `resources/js/composables/useHoldTransactions.js`
- `resources/js/composables/usePosData.js`

### Dimodifikasi:
- `routes/api.php` (tambah `/api/pos/init-data`)
- `resources/js/router/index.js` (tambah route `/pos`)
- `resources/js/layout/AppMenu.vue` (tambah menu POS untuk kasir)
