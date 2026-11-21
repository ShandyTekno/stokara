# STOKARA
### Kelola Stok, Kendalikan Bisnis.
*Inventory Management · POS · Business Intelligence · AI Assistant*

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.x-4FC08D?logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?logo=inertia&logoColor=white)](https://inertiajs.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Tests Passing](https://img.shields.io/badge/Tests-248%20Passed-brightgreen)](tests/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**STOKARA** (Sistem Manajemen Bisnis UMKM) adalah platform manajemen persediaan, kasir (POS), dan analisis bisnis pintar berbasis web untuk toko dan UMKM retail. Dibangun dengan integrasi **Google Gemini AI**, Stokara membantu pemilik toko memantau pergerakan stok, mengotomatisasi kasir, menghitung kebutuhan restock, serta berdialog langsung dengan asisten AI mengenai kondisi persediaan toko.

---

## 🌟 Fitur Utama

- 🏪 **Arsitektur Multi-Tenant Mandiri**: Setiap toko memiliki data yang terisolasi secara ketat (*data isolation*) melalui global scope Eloquent dan proteksi `store_id`.
- 👥 **Role-Based Access Control (RBAC)**:
  - **Admin / Pemilik Toko**: Akses penuh ke master data, manajemen pengguna/karyawan, laporan laba-rugi, ekspor, dan modul AI.
  - **Karyawan / Kasir**: Akses kasir (POS), riwayat penjualan, pencatatan barang masuk/keluar, dan pengecekan stok barang.
- 📦 **Manajemen Barang & Konversi Kemasan**: Mendukung pencatatan stok dalam satuan terkecil (pcs, kg, dll.) dengan konversi otomatis dari kemasan grosir/dus (`units_per_pack`).
- 🛒 **Kasir Cepat & Cetak Struk (POS)**: Antarmuka kasir responsif dengan pencarian instan, kalkulasi kembalian, dukungan metode pembayaran tunai & QRIS, serta fitur cetak struk via browser print.
- 📈 **Buku Besar Stok (*Stock Movements*)**: Setiap penambahan, pengurangan, penjualan, atau penyesuaian dicatat secara mutlak dalam audit log persediaan.
- ⚠️ **Monitoring Stok Kritis**: Dashboard peringatan otomatis untuk barang yang sudah habis, mendekati batas minimum stok, dan barang lambat laku (*dead stock*).
- 📊 **Laporan & Ekspor Lengkap**: Rekap penjualan harian, mingguan, bulanan, margin keuntungan kotor, serta ekspor file dalam format **PDF** dan **Excel (.xlsx)**.
- 🤖 **AI Stock Assistant**: Chatbot asisten bisnis interaktif berbasis **Google Gemini** (`gemini-flash-lite-latest`) yang membaca ringkasan data toko aktual dan dilengkapi animasi loading berputar (*circular spinner*).
- 🧠 **Analisis & Rekomendasi Persediaan AI**: Proyeksi sisa hari stok barang berdasarkan riwayat kecepatan penjualan, rekomendasi jumlah pesanan ulang (*restock*), dan penjelasan naratif otomatis dari AI.
- 🛡️ **Hardening & Keamanan Produksi**: Dilengkapi header keamanan (`nosniff`, `SAMEORIGIN`, `strict-origin-when-cross-origin`, `Permissions-Policy`), rate limiting registrasi, serta pencegahan otomatis N+1 query lazy loading.

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.3+, Laravel 12
- **Frontend**: Vue 3 (Composition API), Inertia.js v2, Tailwind CSS, Heroicons
- **Database**: SQLite (default lokal/testing) atau MySQL 8.0+
- **AI Engine**: Google Gemini API REST (`gemini-flash-lite-latest`), dilengkapi mode simulasi demo lokal (*fallback*) jika API key belum diatur.
- **Reporting**: DomPDF (ekspor PDF), Laravel Excel / PhpSpreadsheet (ekspor Excel)
- **Pengujian**: PHPUnit 12 (248 tests, 100% green)

---

## 🚀 Panduan Memulai Cepat

### 1. Klon Repositori & Pasang Dependensi
```bash
git clone https://github.com/username/stokara.git
cd stokara

composer install
npm install
```

### 2. Konfigurasi Lingkungan
Salin berkas `.env.example` menjadi `.env` lalu buat application key:
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Migrasi & Data Awal (Demo)
Jalankan migrasi database beserta seeder katalog demo:
```bash
php artisan migrate --seed
```
*Seeder akan membuat akun demo:*
- **Admin**: `admin@stokara.test` (password: `password`)
- **Karyawan**: `karyawan@stokara.test` (password: `password`)

### 4. Konfigurasi Google Gemini AI
Dapatkan API Key gratis di [Google AI Studio](https://aistudio.google.com/app/apikey), lalu atur di `.env`:
```env
GEMINI_API_KEY=AIzaSy...
GEMINI_MODEL=gemini-flash-lite-latest
GEMINI_TIMEOUT=60
GEMINI_MAX_TOKENS=1024
```
*(Bila `GEMINI_API_KEY` dikosongkan, aplikasi otomatis berjalan dalam **Mode Demo** berbasis simulasi lokal tanpa error).*

### 5. Build Aset & Jalankan Server
```bash
npm run build
php artisan serve
```
Buka aplikasi di peramban: `http://localhost:8000`

---

## 🧪 Pengujian Otomatis (Tests)

Proyek ini memiliki cakupan pengujian menyeluruh (248 automated tests):
```bash
php artisan test
```

Uji spesifik untuk modul keamanan dan pembatasan akses:
```bash
php artisan test tests/Feature/HardeningTest.php
```

---

## 📚 Dokumentasi Terperinci

Dokumentasi lengkap proyek dapat dibaca pada folder [`docs/`](docs/):
- 📋 [**Product Requirements Document (PRD.md)**](PRD.md) - Spesifikasi visi, ruang lingkup, dan kebutuhan produk.
- 🗄️ [**Dokumentasi Database (docs/database.md)**](docs/database.md) - Skema tabel, diagram relasi ERD, dan aturan integritas data.
- 🎯 [**Panduan Fitur (docs/fitur.md)**](docs/fitur.md) - Rincian alur kerja setiap fitur dari kasir, stok, hingga AI.
- 💻 [**Panduan Instalasi & Deployment (docs/instalasi.md)**](docs/instalasi.md) - Langkah instalasi lokal, konfigurasi server produksi, dan troubleshooting.

---

## 📄 Lisensi

Proyek ini dilisensikan di bawah [Lisensi MIT](LICENSE).
