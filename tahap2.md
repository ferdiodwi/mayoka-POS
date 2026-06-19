# Tahap 2 — Master Produk, Kategori & Matriks Harga Cetak
**Estimasi:** Minggu 3–4  
**Referensi SRS:** RF-INV-01, RF-INV-03 s/d RF-INV-06, RF-PRC-01 s/d RF-PRC-03  
**Prasyarat:** Tahap 1 selesai (Auth & Role sudah jalan)

---

## Tujuan
Membangun modul master data yang menjadi tulang punggung POS: produk (barang & jasa), kategori, matriks harga cetak dinamis (ukuran × tinta × sisi), tier harga grosir, dan daftar jasa tambahan (addon).

---

## Checklist Pekerjaan

### 1. Backend — Database & Migration

- [ ] **Migration `categories`**
  - Kolom: `id`, `name` (varchar 100), `timestamps`
- [ ] **Migration `products`**
  - Kolom: `id`, `category_id` (FK), `name`, `barcode` (nullable unique), `type` (enum: barang/jasa), `price`, `cost_price`, `stock` (default 0), `min_stock` (default 0), `unit` (varchar 20), `is_active` (boolean), `timestamps`
- [ ] **Migration `print_prices`**
  - Kolom: `id`, `paper_size` (enum: A4/F4/A3), `color_type` (enum: bw/color), `side_type` (enum: single/duplex), `price_per_sheet`, `cost_per_sheet`, `timestamps`
  - Unique constraint: `paper_size + color_type + side_type`
- [ ] **Migration `print_price_tiers`**
  - Kolom: `id`, `print_price_id` (FK), `min_qty`, `price_per_sheet`, `timestamps`
- [ ] **Migration `addon_services`**
  - Kolom: `id`, `name`, `price`, `is_active` (boolean), `timestamps`
- [ ] **Migration `stock_movements`**
  - Kolom: `id`, `product_id` (FK), `type` (enum: in/out/adjustment), `qty`, `reference`, `notes` (nullable), `user_id` (FK), `timestamps`
- [ ] **Seeder `CategorySeeder`** — Buat kategori default: ATK, Kertas, Jasa Cetak, Jasa Jilid, Lainnya
- [ ] **Seeder `PrintPriceSeeder`** — Isi matriks harga cetak contoh (12 kombinasi)
- [ ] **Seeder `AddonServiceSeeder`** — Isi data contoh: Jilid Lakban, Jilid Mika, Laminating A4, Laminating F4

### 2. Backend — Model Eloquent

- [ ] **Model `Category`** — `hasMany(Product::class)`
- [ ] **Model `Product`** — `belongsTo(Category::class)`, `hasMany(StockMovement::class)`, scope `scopeActive`, scope `scopeBarang`, scope `scopeJasa`, scope `scopeLowStock`
- [ ] **Model `PrintPrice`** — `hasMany(PrintPriceTier::class)`, method `getPriceForQty($qty)` yang cek tier
- [ ] **Model `PrintPriceTier`** — `belongsTo(PrintPrice::class)`
- [ ] **Model `AddonService`** — scope `scopeActive`
- [ ] **Model `StockMovement`** — `belongsTo(Product::class)`, `belongsTo(User::class)`

### 3. Backend — API Routes & Controllers

#### CategoryController (Owner only)
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/categories` | List semua kategori |
| POST | `/api/categories` | Tambah kategori |
| PUT | `/api/categories/{id}` | Update kategori |
| DELETE | `/api/categories/{id}` | Hapus kategori (cek relasi) |

#### ProductController (Owner only untuk CUD, Kasir bisa Read)
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/products` | List produk (support filter: kategori, tipe, search, low_stock) |
| GET | `/api/products/search?q=` | Pencarian cepat untuk POS (nama/barcode) |
| POST | `/api/products` | Tambah produk + catat stock_movement awal (type: in) |
| PUT | `/api/products/{id}` | Update produk |
| DELETE | `/api/products/{id}` | Soft deactivate produk |
| POST | `/api/products/{id}/stock-adjust` | Adjustment stok manual (owner) |

#### PrintPriceController (Owner only)
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/print-prices` | List semua matriks harga cetak (include tiers) |
| POST | `/api/print-prices` | Tambah/update harga cetak |
| PUT | `/api/print-prices/{id}` | Update harga per kombinasi |
| DELETE | `/api/print-prices/{id}` | Hapus kombinasi harga |
| GET | `/api/print-prices/calculate?size=&color=&side=&qty=` | Kalkulasi harga realtime untuk POS |

#### PrintPriceTierController (Owner only)
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/api/print-prices/{id}/tiers` | Tambah tier harga grosir |
| PUT | `/api/print-price-tiers/{id}` | Update tier |
| DELETE | `/api/print-price-tiers/{id}` | Hapus tier |

#### AddonServiceController (Owner only)
| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/addon-services` | List semua addon |
| POST | `/api/addon-services` | Tambah addon |
| PUT | `/api/addon-services/{id}` | Update addon |
| DELETE | `/api/addon-services/{id}` | Hapus addon |

### 4. Frontend — Halaman Vue (Owner Area)

#### Halaman Kategori (`views/pages/Categories.vue`)
- [ ] DataTable sederhana: No, Nama Kategori, Jumlah Produk, Aksi
- [ ] Dialog tambah/edit kategori (field: nama)
- [ ] Konfirmasi hapus dengan warning jika ada produk terkait

#### Halaman Produk (`views/pages/Products.vue`)
- [ ] DataTable dengan kolom: No, Nama, Barcode, Kategori, Tipe, Harga Jual, HPP, Stok, Min Stok, Status, Aksi
- [ ] Filter: dropdown Kategori, dropdown Tipe (Barang/Jasa), toggle "Stok Rendah"
- [ ] Search bar di atas tabel
- [ ] Dialog tambah/edit produk:
  - Field: nama, barcode, kategori (dropdown), tipe (radio: barang/jasa), harga jual, HPP, stok awal (hanya saat tambah baru), min stok, satuan
  - Jika tipe = jasa → sembunyikan field stok
- [ ] Dialog adjustment stok: input qty (+/-) dan catatan alasan
- [ ] **Badge stok kritis**: baris produk dengan stok < min_stock ditandai warna merah/warning

#### Halaman Harga Cetak (`views/pages/PrintPrices.vue`)
- [ ] Tampilkan matriks dalam bentuk tabel/grid:
  - Baris: kombinasi ukuran × tinta × sisi
  - Kolom: Harga per Lembar, HPP per Lembar, Jumlah Tier, Aksi
- [ ] Dialog edit harga per kombinasi
- [ ] Sub-tabel/accordion untuk tier grosir per kombinasi:
  - Tampilkan: Min Qty → Harga per Lembar
  - Tombol tambah/edit/hapus tier
- [ ] Tombol "Tambah Kombinasi Baru" jika belum lengkap 12 kombinasi

#### Halaman Addon Services (`views/pages/AddonServices.vue`)
- [ ] DataTable: No, Nama Jasa, Harga, Status Aktif, Aksi
- [ ] Dialog tambah/edit addon (field: nama, harga)
- [ ] Toggle aktif/nonaktif

### 5. Frontend — Routing & Menu

- [ ] Tambah routes: `/categories`, `/products`, `/print-prices`, `/addon-services`
- [ ] Update AppMenu.vue — tambah grup menu "Master Data" (Owner only):
  - Kategori (`pi-tag`)
  - Produk (`pi-box`)
  - Harga Cetak (`pi-print`)
  - Jasa Tambahan (`pi-plus-circle`)

### 6. Frontend — Composables

- [ ] `composables/useProducts.js` — CRUD + search + filter
- [ ] `composables/usePrintPrices.js` — CRUD matriks + tiers + kalkulasi
- [ ] `composables/useCategories.js` — CRUD kategori
- [ ] `composables/useAddonServices.js` — CRUD addon

---

## Kriteria Selesai (Definition of Done)

- [ ] Owner bisa CRUD kategori produk
- [ ] Owner bisa CRUD produk barang (dengan stok) dan jasa (tanpa stok)
- [ ] Owner bisa CRUD matriks harga cetak (12 kombinasi ukuran × tinta × sisi)
- [ ] Owner bisa menambahkan tier harga grosir per kombinasi harga cetak
- [ ] Owner bisa CRUD jasa addon (jilid, laminating, dll)
- [ ] Owner bisa adjustment stok manual dengan catatan alasan
- [ ] Produk dengan stok rendah ditandai visual warning
- [ ] API pencarian produk (nama/barcode) berfungsi cepat untuk kebutuhan POS nanti
- [ ] Semua endpoint master data terproteksi role owner

---

## File yang Dibuat/Dimodifikasi

### Baru:
- `database/migrations/xxxx_create_categories_table.php`
- `database/migrations/xxxx_create_products_table.php`
- `database/migrations/xxxx_create_print_prices_table.php`
- `database/migrations/xxxx_create_print_price_tiers_table.php`
- `database/migrations/xxxx_create_addon_services_table.php`
- `database/migrations/xxxx_create_stock_movements_table.php`
- `database/seeders/CategorySeeder.php`
- `database/seeders/PrintPriceSeeder.php`
- `database/seeders/AddonServiceSeeder.php`
- `app/Models/Category.php`
- `app/Models/Product.php`
- `app/Models/PrintPrice.php`
- `app/Models/PrintPriceTier.php`
- `app/Models/AddonService.php`
- `app/Models/StockMovement.php`
- `app/Http/Controllers/CategoryController.php`
- `app/Http/Controllers/ProductController.php`
- `app/Http/Controllers/PrintPriceController.php`
- `app/Http/Controllers/PrintPriceTierController.php`
- `app/Http/Controllers/AddonServiceController.php`
- `resources/js/views/pages/Categories.vue`
- `resources/js/views/pages/Products.vue`
- `resources/js/views/pages/PrintPrices.vue`
- `resources/js/views/pages/AddonServices.vue`
- `resources/js/composables/useProducts.js`
- `resources/js/composables/usePrintPrices.js`
- `resources/js/composables/useCategories.js`
- `resources/js/composables/useAddonServices.js`

### Dimodifikasi:
- `routes/api.php` (tambah endpoint baru)
- `resources/js/router/index.js` (tambah route halaman master data)
- `resources/js/layout/AppMenu.vue` (tambah grup menu Master Data)
- `database/seeders/DatabaseSeeder.php` (panggil seeder baru)
