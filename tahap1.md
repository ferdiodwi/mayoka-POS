# Tahap 1 — Autentikasi, CRUD User & Manajemen Shift
**Estimasi:** Minggu 1–2  
**Referensi SRS:** RF-AU-01 s/d RF-AU-05, RF-SH-01 s/d RF-SH-04

---

## Tujuan
Membangun fondasi sistem: autentikasi login/logout, manajemen akun pengguna oleh Owner, serta fitur buka/tutup shift kasir dengan rekonsiliasi kas.

---

## Checklist Pekerjaan

### 1. Backend — Database & Migration

- [ ] **Migration `users`**
  - Kolom: `id`, `name`, `username` (unique), `password`, `role` (enum: kasir/owner), `is_active` (boolean), `timestamps`
  - Hapus migration default `create_users_table` yang sudah ada, buat ulang sesuai skema SRS
- [ ] **Migration `shifts`**
  - Kolom: `id`, `user_id` (FK), `started_at`, `ended_at` (nullable), `cash_start`, `cash_end` (nullable), `cash_expected` (nullable), `cash_difference` (nullable), `status` (enum: open/closed), `notes` (nullable text), `timestamps`
- [ ] **Seeder `UserSeeder`**
  - Buat 1 akun Owner default: `username: admin`, `password: password`, `role: owner`
  - Buat 1 akun Kasir demo: `username: kasir1`, `password: password`, `role: kasir`

### 2. Backend — Model Eloquent

- [ ] **Model `User`**
  - Fillable: `name`, `username`, `password`, `role`, `is_active`
  - Hidden: `password`
  - Cast: `is_active` → boolean, `password` → hashed
  - Relasi: `hasMany(Shift::class)`
- [ ] **Model `Shift`**
  - Fillable: `user_id`, `started_at`, `ended_at`, `cash_start`, `cash_end`, `cash_expected`, `cash_difference`, `status`, `notes`
  - Cast: `started_at` → datetime, `ended_at` → datetime, `cash_start` → decimal, `cash_end` → decimal
  - Relasi: `belongsTo(User::class)`
  - Scope: `scopeActive($query)` → filter status = 'open'

### 3. Backend — API Routes & Controllers

File: `routes/api.php` (buat baru, daftarkan di `bootstrap/app.php`)

#### AuthController
| Method | Endpoint | Fungsi | Middleware |
|--------|----------|--------|------------|
| POST | `/api/login` | Login (validasi username+password, return token/session + user data) | Guest |
| POST | `/api/logout` | Logout (invalidate session) | Auth |
| GET | `/api/me` | Get current user info | Auth |

#### UserController (Owner only)
| Method | Endpoint | Fungsi | Middleware |
|--------|----------|--------|------------|
| GET | `/api/users` | List semua user | Auth + Role:owner |
| POST | `/api/users` | Tambah user baru | Auth + Role:owner |
| PUT | `/api/users/{id}` | Update user | Auth + Role:owner |
| DELETE | `/api/users/{id}` | Soft delete / nonaktifkan user | Auth + Role:owner |

#### ShiftController
| Method | Endpoint | Fungsi | Middleware |
|--------|----------|--------|------------|
| GET | `/api/shifts/active` | Cek shift aktif user saat ini | Auth |
| POST | `/api/shifts/open` | Buka shift baru (input cash_start) | Auth |
| PUT | `/api/shifts/{id}/close` | Tutup shift (input cash_end, hitung selisih) | Auth |
| GET | `/api/shifts` | List riwayat shift (filter by date) | Auth + Role:owner |

### 4. Backend — Middleware

- [ ] **Middleware `RoleMiddleware`**
  - Cek `$request->user()->role` terhadap parameter yang diberikan
  - Return 403 jika role tidak sesuai
  - Daftarkan sebagai alias `role` di `bootstrap/app.php`

### 5. Backend — Validasi & Logic

- [ ] **Login:** Validasi `username` exists + `is_active` = true + password match
- [ ] **Buka Shift:** Cek tidak ada shift aktif (status=open) untuk user tsb → jika ada, tolak
- [ ] **Tutup Shift:**
  - `cash_expected` = `cash_start` + total transaksi tunai selama shift
  - `cash_difference` = `cash_end` - `cash_expected`
  - Update `status` → 'closed', `ended_at` → now()

### 6. Frontend — Halaman & Komponen Vue

#### Halaman Login (`views/pages/auth/Login.vue`)
- [ ] Redesign halaman login yang sudah ada dari template Sakai
- [ ] Form: input `username`, input `password`, tombol "Masuk"
- [ ] Panggil `POST /api/login`, simpan token/session
- [ ] Redirect ke `/` (dashboard) jika owner, atau ke `/pos` jika kasir
- [ ] Tampilkan Toast error jika gagal login

#### Halaman Manajemen User (`views/pages/Users.vue`) — Owner only
- [ ] DataTable PrimeVue dengan kolom: No, Nama, Username, Role, Status Aktif, Aksi
- [ ] Tombol "Tambah User" → buka Dialog form (nama, username, password, role)
- [ ] Tombol Edit per baris → Dialog form (tanpa field password wajib)
- [ ] Tombol Toggle Aktif/Nonaktif per baris
- [ ] Konfirmasi sebelum nonaktifkan user

#### Komponen Shift (`components/shift/ShiftDialog.vue`)
- [ ] Dialog "Buka Shift" muncul otomatis setelah login kasir jika belum ada shift aktif
- [ ] Form input: Nominal uang modal awal (InputNumber PrimeVue)
- [ ] Dialog "Tutup Shift" — tampilkan ringkasan: modal awal, total transaksi, uang seharusnya, input uang fisik akhir, tampilkan selisih (warna merah jika minus, hijau jika sesuai)

### 7. Frontend — Routing & Navigation

- [ ] Tambahkan route `/users` → `Users.vue` (di dalam AppLayout, hanya owner)
- [ ] Tambahkan **Navigation Guard** di `router/index.js`:
  - Jika belum login → redirect ke `/auth/login`
  - Jika kasir akses halaman owner → redirect ke `/auth/access` (Access Denied)
- [ ] Update **AppMenu.vue** — ganti menu template Sakai menjadi menu aplikasi:
  - Owner: Dashboard, Manajemen User, (placeholder menu lain)
  - Kasir: POS (placeholder)

### 8. Frontend — State Management

- [ ] Buat `composables/useAuth.js`:
  - `login(username, password)` — POST /api/login
  - `logout()` — POST /api/logout
  - `user` — reactive ref data user saat ini
  - `isOwner` / `isKasir` — computed
  - `fetchUser()` — GET /api/me
- [ ] Buat `composables/useShift.js`:
  - `activeShift` — reactive ref
  - `openShift(cashStart)`
  - `closeShift(shiftId, cashEnd)`
  - `checkActiveShift()`

---

## Kriteria Selesai (Definition of Done)

- [ ] User bisa login dengan username & password
- [ ] Owner bisa CRUD akun kasir
- [ ] Kasir di-redirect ke halaman POS dan diminta buka shift
- [ ] Kasir bisa buka shift (input modal awal) dan tutup shift (rekonsiliasi kas)
- [ ] Role kasir tidak bisa akses halaman owner (403 di backend + redirect di frontend)
- [ ] Tidak boleh ada 2 shift aktif bersamaan untuk 1 kasir
- [ ] Menu sidebar sudah disesuaikan untuk POS app (bukan template Sakai default)

---

## File yang Dibuat/Dimodifikasi

### Baru:
- `routes/api.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/ShiftController.php`
- `app/Http/Middleware/RoleMiddleware.php`
- `app/Models/Shift.php`
- `database/seeders/UserSeeder.php`
- `database/migrations/xxxx_create_shifts_table.php`
- `resources/js/views/pages/Users.vue`
- `resources/js/components/shift/ShiftDialog.vue`
- `resources/js/composables/useAuth.js`
- `resources/js/composables/useShift.js`

### Dimodifikasi:
- `database/migrations/0001_01_01_000000_create_users_table.php` (sesuaikan kolom)
- `app/Models/User.php` (tambah relasi & cast)
- `bootstrap/app.php` (daftarkan api routes + middleware)
- `resources/js/router/index.js` (route baru + navigation guard)
- `resources/js/layout/AppMenu.vue` (menu baru)
- `resources/js/views/pages/auth/Login.vue` (redesign)
