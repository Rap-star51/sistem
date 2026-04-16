# Sistem Kulit Garaman TPS Lini 2 — v2.0

## Stack
- **Backend:** PHP 8.0+ dengan SQLite3
- **Database:** SQLite (zero-config, satu file)
- **Frontend:** Vanilla HTML/CSS/JS + Chart.js 4.4
- **Font:** Plus Jakarta Sans (Google Fonts)
- **Icons:** Font Awesome 6.5

## Fitur Lengkap
1. **Login multi-role** + forgot password dengan kode verifikasi 6-digit
2. **Dark mode** + toggle realtime, tersimpan per-user
3. **Fully responsive mobile** (Android & iPhone)
4. **Dashboard** dengan 6 stat card + 4 chart interaktif (Chart.js)
5. **New Booking** dengan upload dokumen (PDF/DOC/JPG/PNG) + drag & drop
6. **Approvals** → Approve / Reject / Minta Upload Ulang + viewer dokumen
7. **Customs** (Bea Cukai) → update status, lihat dokumen
8. **Quarantine** (Karantina) → update status, sertifikat
9. **Invoices** dengan Virtual Account otomatis (BCA/Mandiri/BNI/BRI)
10. **Status Booking** (User PT) → progress bar per booking + tampilan tagihan VA
11. **Notifikasi realtime** per role (admin, user PT, customs, karantina)
12. **Manajemen User** (CRUD + toggle aktif/nonaktif)
13. **Laporan & Statistik** bulanan dengan export tabel
14. **Profil** dengan color picker avatar + ganti password

## Role Akses
| Username   | Password | Role       | Dapat Akses |
|-----------|---------|-----------|------------|
| admin     | password | Admin      | Semua fitur |
| operator  | password | Operator   | Booking, Approval, Invoice |
| customs   | password | Bea Cukai  | Customs + Dashboard |
| quarantine| password | Karantina  | Quarantine + Dashboard |
| ptmakmur  | password | User PT    | New Booking, Status Booking |
| ptsumber  | password | User PT    | New Booking, Status Booking |
| ptalam    | password | User PT    | New Booking, Status Booking |

## Setup di XAMPP
1. Extract ke `C:\xampp\htdocs\sistem-kulit-v2\`
2. Aktifkan `extension=pdo_sqlite` dan `extension=sqlite3` di php.ini
3. Buka: `http://localhost/sistem-kulit-v2/init_db.php`
4. Login: `http://localhost/sistem-kulit-v2/`

## Setup di VPS/Hosting (Domain)
1. Upload ke direktori public web (mis. `/var/www/html/kulit/`)
2. Set `BASE_URL` di `includes/config.php` ke domain Anda: `https://kulit.tps.co.id`
3. Pastikan PHP 8.0+ dengan ext-pdo_sqlite aktif
4. Jalankan `init_db.php` sekali
5. Set permission folder uploads: `chmod 755 assets/uploads/documents/`
6. Gunakan SSL (HTTPS) — uncomment baris di .htaccess

## Struktur
```
sistem-kulit-v2/
├── index.php          Login + Forgot Password
├── logout.php
├── init_db.php        Inisialisasi DB (jalankan 1x)
├── database.sqlite    Database (auto-generated)
├── .htaccess
├── includes/
│   ├── config.php     DB, helpers, functions
│   ├── layout.php     Header + Sidebar template
│   └── layout_footer.php
├── pages/
│   ├── dashboard.php
│   ├── new_booking.php
│   ├── approvals.php
│   ├── booking_detail.php
│   ├── customs.php
│   ├── quarantine.php
│   ├── invoices.php
│   ├── my_bookings.php   (User PT — status + invoice)
│   ├── users.php
│   ├── reports.php
│   ├── notifications.php
│   └── profile.php
├── api/
│   ├── notif.php      AJAX notifications read
│   ├── docs.php       AJAX document list
│   └── dark_mode.php  AJAX dark mode toggle
└── assets/
    ├── css/theme.css  Master CSS (light + dark mode)
    └── uploads/documents/  File uploads
```
