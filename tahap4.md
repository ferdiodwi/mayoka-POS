# Tahap 4 — Checkout, Pembayaran & Cetak Struk
**Estimasi:** Minggu 8–9  
**Referensi SRS:** RF-POS-06, RF-POS-07, RF-POS-10, RF-INV-02, RF-SH-03  
**Prasyarat:** Tahap 3 selesai (POS & Keranjang sudah jalan)

---

## Tujuan
Menyelesaikan alur transaksi end-to-end: modal pembayaran (tunai/QRIS/transfer), kalkulasi kembalian, penyimpanan transaksi ke database (dengan rollback safety), pengurangan stok otomatis, dan cetak struk thermal.

---

## Checklist Pekerjaan

### 1. Backend — Database & Migration

- [ ] **Migration `transactions`**
  - Kolom: `id`, `user_id` (FK), `shift_id` (FK), `invoice_number` (unique), `subtotal`, `discount_amount`, `total_price`, `cash_paid`, `cash_change`, `payment_method` (enum: cash/qris/transfer), `status` (enum: completed/voided), `created_at`
- [ ] **Migration `transaction_items`**
  - Kolom: `id`, `transaction_id` (FK), `item_type` (enum: product/print/addon), `product_id` (FK nullable), `print_price_id` (FK nullable), `addon_service_id` (FK nullable), `description`, `qty`, `unit_price`, `subtotal`, `discount` (default 0), `parent_item_id` (FK nullable → self reference)

### 2. Backend — Model Eloquent

- [ ] **Model `Transaction`**
  - Fillable: semua kolom
  - Relasi: `belongsTo(User)`, `belongsTo(Shift)`, `hasMany(TransactionItem)`
  - Method: `generateInvoiceNumber($shiftId)` → format `INV-YYYYMMDD-{SHIFT}-{SEQ}`
- [ ] **Model `TransactionItem`**
  - Relasi: `belongsTo(Transaction)`, `belongsTo(Product)`, `belongsTo(PrintPrice)`, `belongsTo(AddonService)`, `hasMany(TransactionItem, 'parent_item_id')` (addon children), `belongsTo(TransactionItem, 'parent_item_id')` (parent)

### 3. Backend — TransactionController

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/api/transactions/checkout` | Proses checkout (simpan transaksi + items + kurangi stok) |
| GET | `/api/transactions/today` | List transaksi hari ini (untuk kasir reprint) |
| GET | `/api/transactions/{id}` | Detail transaksi lengkap (untuk reprint struk) |
| POST | `/api/transactions/{id}/void` | Void transaksi (owner only, kembalikan stok) |

#### Logic Checkout (`POST /api/transactions/checkout`)

```php
DB::transaction(function () {
    // 1. Validasi shift aktif
    // 2. Generate invoice number
    // 3. Simpan transaction record
    // 4. Loop setiap cart item:
    //    a. Simpan transaction_item
    //    b. Jika item_type = 'product' dan product.type = 'barang':
    //       - Kurangi products.stock
    //       - Catat stock_movements (type: out, reference: invoice)
    //    c. Jika item_type = 'print':
    //       - Hitung lembar kertas terpakai (qty × sisi)
    //       - Cari product kertas terkait (berdasarkan paper_size)
    //       - Kurangi stok kertas di products
    //       - Catat stock_movements
    //    d. Simpan addon items (parent_item_id = id item cetak parent)
    // 5. Return transaction dengan items
});
```

- [ ] Validasi: `cash_paid >= total_price` jika metode = cash
- [ ] Validasi: shift aktif harus ada
- [ ] Rollback otomatis jika ada error di tengah proses

### 4. Backend — Cetak Struk (PrintController)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/api/print/receipt/{transactionId}` | Kirim perintah cetak ke thermal printer |

- [ ] Install package `mike42/escpos-php` via composer
- [ ] Buat `PrintController` dengan method `receipt($id)`:
  - Load transaction + items
  - Format struk: Header toko, tanggal, nomor invoice, list item, subtotal, diskon, total, bayar, kembalian, footer
  - Kirim ke printer via USB/Network connector
- [ ] Buat `config/pos.php` untuk konfigurasi:
  - `printer_connector` (USB / Network IP)
  - `printer_name` (device name)
  - `receipt_header` (nama toko, alamat, telp)
  - `receipt_footer` (pesan terima kasih)
  - `receipt_width` (58 / 80 mm)

### 5. Frontend — Modal Pembayaran (`components/pos/PaymentDialog.vue`)

- [ ] Dialog fullscreen / large modal, dipicu oleh tombol "Bayar" atau shortcut F5
- [ ] Tampilkan ringkasan:
  - Total item: X item
  - Subtotal: Rp xxx.xxx
  - Diskon: Rp xxx.xxx
  - **Grand Total: Rp xxx.xxx** (font besar)
- [ ] Pilihan metode bayar: 3 tombol besar (Tunai / QRIS / Transfer)
- [ ] **Jika Tunai:**
  - InputNumber besar untuk nominal uang diterima
  - Quick amount buttons: Rp 5.000, Rp 10.000, Rp 20.000, Rp 50.000, Rp 100.000, "Uang Pas"
  - Kalkulasi kembalian realtime (uang diterima - grand total)
  - Tombol "Proses" disabled jika uang kurang
- [ ] **Jika QRIS/Transfer:**
  - Tidak perlu input uang, langsung tombol "Proses"
- [ ] Setelah proses berhasil:
  - Tampilkan dialog sukses dengan info: nomor invoice, kembalian (jika tunai)
  - Tombol "Cetak Struk" → panggil API print
  - Tombol "Transaksi Baru" → clear cart, fokus kembali ke search bar
  - Auto-focus ke search bar setelah dialog ditutup

### 6. Frontend — Riwayat Transaksi Hari Ini (`components/pos/TransactionHistoryDrawer.vue`)

- [ ] Drawer/sidebar yang bisa dibuka dari toolbar POS
- [ ] List transaksi hari ini: Invoice, Waktu, Total, Metode Bayar
- [ ] Klik transaksi → lihat detail item
- [ ] Tombol "Reprint Struk" per transaksi

### 7. Frontend — Composables

- [ ] **`composables/useCheckout.js`**:
  - `processCheckout(cartItems, paymentData)` → POST /api/transactions/checkout
  - `lastTransaction` — reactive ref transaksi terakhir
  - `printReceipt(transactionId)` → POST /api/print/receipt
  - `todayTransactions` — list transaksi hari ini
  - `fetchTodayTransactions()`

### 8. Backend — Update Shift Logic

- [ ] Saat tutup shift, hitung `cash_expected`:
  ```
  cash_expected = cash_start + SUM(transactions.cash_paid WHERE payment_method='cash' AND shift_id=current) - SUM(transactions.cash_change WHERE payment_method='cash' AND shift_id=current)
  ```
- [ ] Endpoint `GET /api/shifts/{id}/summary`:
  - Total transaksi dalam shift
  - Breakdown per metode bayar (tunai, QRIS, transfer)
  - Total omzet shift

---

## Kriteria Selesai (Definition of Done)

- [ ] Kasir bisa menyelesaikan transaksi dengan pembayaran tunai (kembalian dihitung)
- [ ] Kasir bisa menyelesaikan transaksi dengan QRIS atau transfer
- [ ] Invoice number ter-generate otomatis dengan format standar
- [ ] Stok barang ATK berkurang otomatis setelah checkout
- [ ] Stok kertas berkurang otomatis setelah transaksi cetak
- [ ] stock_movements tercatat untuk setiap pengurangan stok
- [ ] Struk tercetak ke thermal printer tanpa dialog browser
- [ ] Kasir bisa lihat riwayat transaksi hari ini dan reprint struk
- [ ] Transaksi dibungkus DB::transaction() — rollback jika gagal
- [ ] Setelah transaksi selesai, keranjang kosong dan fokus kembali ke search bar

---

## File yang Dibuat/Dimodifikasi

### Baru:
- `database/migrations/xxxx_create_transactions_table.php`
- `database/migrations/xxxx_create_transaction_items_table.php`
- `app/Models/Transaction.php`
- `app/Models/TransactionItem.php`
- `app/Http/Controllers/TransactionController.php`
- `app/Http/Controllers/PrintController.php`
- `config/pos.php`
- `resources/js/components/pos/PaymentDialog.vue`
- `resources/js/components/pos/TransactionHistoryDrawer.vue`
- `resources/js/composables/useCheckout.js`

### Dimodifikasi:
- `routes/api.php` (endpoint transaksi & print)
- `app/Http/Controllers/ShiftController.php` (tambah summary & cash_expected logic)
- `composer.json` (tambah `mike42/escpos-php`)
