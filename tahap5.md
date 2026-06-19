# Tahap 5 — Dashboard Owner, Laporan & Grafik
**Estimasi:** Minggu 10–11  
**Referensi SRS:** RF-RPT-01 s/d RF-RPT-05  
**Prasyarat:** Tahap 4 selesai (Transaksi sudah berjalan & tercatat di DB)

---

## Tujuan
Membangun dashboard analitik untuk Owner: ringkasan keuangan real-time (omzet, HPP, laba), grafik penjualan, laporan produk terlaris, rekap per kasir/shift, rekap stok, dan fitur export laporan.

---

## Checklist Pekerjaan

### 1. Backend — ReportController (Owner only)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/reports/dashboard` | Data ringkasan dashboard (hari ini + bulan ini) |
| GET | `/api/reports/sales?from=&to=` | Laporan penjualan per periode |
| GET | `/api/reports/products/top?from=&to=&limit=10` | Produk/jasa terlaris |
| GET | `/api/reports/cashier-performance?from=&to=` | Rekap per kasir |
| GET | `/api/reports/shifts?from=&to=` | Rekap per shift (omzet, selisih kas) |
| GET | `/api/reports/stock-summary` | Rekap stok: awal, masuk, keluar, akhir |
| GET | `/api/reports/profit-loss?from=&to=` | Laba rugi detail (omzet - HPP = laba) |
| GET | `/api/reports/export/sales?from=&to=&format=pdf` | Export PDF/Excel |

### 2. Backend — Logic Dashboard (`GET /api/reports/dashboard`)

Response data:
```json
{
  "today": {
    "total_transactions": 45,
    "total_revenue": 1250000,
    "total_cost": 480000,
    "net_profit": 770000,
    "payment_breakdown": { "cash": 900000, "qris": 250000, "transfer": 100000 }
  },
  "this_month": {
    "total_transactions": 890,
    "total_revenue": 28500000,
    "total_cost": 11200000,
    "net_profit": 17300000
  },
  "low_stock_alerts": [
    { "id": 5, "name": "Kertas A4 70gsm", "stock": 2, "min_stock": 5, "unit": "rim" }
  ],
  "recent_transactions": [ /* 5 transaksi terakhir */ ],
  "daily_revenue_chart": [ /* 30 hari terakhir: { date, revenue, profit } */ ]
}
```

### 3. Backend — Export Service

- [ ] Buat `App\Services\ReportExportService`
- [ ] Export PDF: gunakan `barryvdh/laravel-dompdf` atau `spatie/laravel-pdf`
- [ ] Export Excel: gunakan `maatwebsite/excel`
- [ ] Template laporan: header toko, periode, tabel data, total

### 4. Frontend — Dashboard Owner (`views/Dashboard.vue`)

Redesign dashboard (ganti widget template Sakai) menjadi:

#### Baris 1: Stat Cards (4 kolom)
- [ ] **Omzet Hari Ini** — ikon `pi-dollar`, warna hijau, angka besar
- [ ] **Transaksi Hari Ini** — ikon `pi-shopping-cart`, warna biru
- [ ] **Laba Bersih Hari Ini** — ikon `pi-chart-line`, warna ungu
- [ ] **Omzet Bulan Ini** — ikon `pi-calendar`, warna orange

#### Baris 2: Charts (2 kolom)
- [ ] **Grafik Omzet Harian** (30 hari terakhir) — Line Chart (Chart.js)
  - 2 lines: Omzet (biru) & Laba (hijau)
- [ ] **Breakdown Metode Bayar** — Doughnut Chart
  - Tunai, QRIS, Transfer dengan warna berbeda

#### Baris 3: Tabel (2 kolom)
- [ ] **Produk Terlaris** — Tabel top 10: Nama, Qty Terjual, Total Revenue
- [ ] **Peringatan Stok Rendah** — Tabel produk dengan stok di bawah minimum
  - Badge merah, link ke halaman produk

#### Baris 4: Transaksi Terakhir
- [ ] Tabel 5 transaksi terakhir: Invoice, Waktu, Kasir, Total, Metode

### 5. Frontend — Halaman Laporan

#### Halaman Laporan Penjualan (`views/pages/reports/Sales.vue`)
- [ ] DatePicker range (dari — sampai) menggunakan PrimeVue Calendar
- [ ] Tabel: Tanggal, Jumlah Transaksi, Omzet, HPP, Laba, Avg per Transaksi
- [ ] Baris total di footer tabel
- [ ] Tombol Export PDF & Export Excel

#### Halaman Laporan Per Kasir (`views/pages/reports/CashierReport.vue`)
- [ ] Filter: periode + dropdown kasir (opsional)
- [ ] Tabel: Nama Kasir, Jumlah Shift, Total Transaksi, Total Omzet, Rata-rata Selisih Kas

#### Halaman Laporan Shift (`views/pages/reports/ShiftReport.vue`)
- [ ] Filter: periode
- [ ] Tabel: Kasir, Tanggal, Waktu Buka-Tutup, Modal Awal, Uang Akhir, Selisih, Status

#### Halaman Rekap Stok (`views/pages/reports/StockReport.vue`)
- [ ] Filter: kategori, periode
- [ ] Tabel: Produk, Stok Awal, Masuk, Keluar, Adjustment, Stok Akhir
- [ ] Highlight merah jika stok akhir < min_stock

### 6. Frontend — Routing & Menu

- [ ] Route baru: `/reports/sales`, `/reports/cashier`, `/reports/shifts`, `/reports/stock`
- [ ] Update AppMenu.vue — tambah grup "Laporan" (Owner only):
  - Penjualan (`pi-chart-bar`)
  - Per Kasir (`pi-users`)
  - Per Shift (`pi-clock`)
  - Rekap Stok (`pi-box`)

### 7. Frontend — Composables

- [ ] **`composables/useReports.js`**:
  - `fetchDashboard()` — data dashboard
  - `fetchSalesReport(from, to)`
  - `fetchTopProducts(from, to, limit)`
  - `fetchCashierReport(from, to)`
  - `fetchShiftReport(from, to)`
  - `fetchStockSummary()`
  - `exportReport(type, format, from, to)` — trigger download

### 8. Frontend — Dashboard Components

- [ ] `components/dashboard/StatCard.vue` — kartu statistik reusable
- [ ] `components/dashboard/RevenueChart.vue` — grafik omzet line chart
- [ ] `components/dashboard/PaymentBreakdownChart.vue` — doughnut chart
- [ ] `components/dashboard/TopProductsTable.vue` — tabel produk terlaris
- [ ] `components/dashboard/LowStockAlert.vue` — tabel peringatan stok
- [ ] `components/dashboard/RecentTransactions.vue` — tabel transaksi terakhir

---

## Kriteria Selesai (Definition of Done)

- [ ] Dashboard Owner menampilkan stat cards (omzet, transaksi, laba hari ini & bulan ini)
- [ ] Grafik omzet harian 30 hari terakhir tampil dengan Chart.js
- [ ] Grafik breakdown metode bayar tampil
- [ ] Tabel produk terlaris & peringatan stok rendah tampil di dashboard
- [ ] Halaman laporan penjualan bisa filter per periode dan menampilkan data akurat
- [ ] Halaman laporan per kasir dan per shift berfungsi
- [ ] Halaman rekap stok menampilkan pergerakan stok per periode
- [ ] Export laporan ke PDF dan Excel berfungsi
- [ ] Semua halaman laporan hanya bisa diakses oleh role Owner

---

## File yang Dibuat/Dimodifikasi

### Baru:
- `app/Http/Controllers/ReportController.php`
- `app/Services/ReportExportService.php`
- `resources/js/views/pages/reports/Sales.vue`
- `resources/js/views/pages/reports/CashierReport.vue`
- `resources/js/views/pages/reports/ShiftReport.vue`
- `resources/js/views/pages/reports/StockReport.vue`
- `resources/js/components/dashboard/StatCard.vue`
- `resources/js/components/dashboard/RevenueChart.vue`
- `resources/js/components/dashboard/PaymentBreakdownChart.vue`
- `resources/js/components/dashboard/TopProductsTable.vue`
- `resources/js/components/dashboard/LowStockAlert.vue`
- `resources/js/components/dashboard/RecentTransactions.vue`
- `resources/js/composables/useReports.js`

### Dimodifikasi:
- `routes/api.php` (endpoint laporan)
- `resources/js/router/index.js` (route laporan)
- `resources/js/layout/AppMenu.vue` (menu laporan)
- `resources/js/views/Dashboard.vue` (redesign total)
- `composer.json` (tambah dompdf & maatwebsite/excel)
