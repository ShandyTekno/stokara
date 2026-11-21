# Panduan Instalasi & Deployment StokCerdas AI

Dokumen ini memandu langkah-langkah instalasi, konfigurasi lingkungan, pengujian, serta persiapan deployment produksi untuk aplikasi **StokCerdas AI**.

---

## 1. Prasyarat Sistem

Sebelum memulai instalasi, pastikan sistem komputer/server Anda telah terpasang:
- **PHP**: Versi `8.3` atau lebih baru
  - Ekstensi PHP yang wajib aktif: `pdo_sqlite` (atau `pdo_mysql`), `mbstring`, `xml`, `ctype`, `iconv`, `intl`, `gd`, `fileinfo`.
- **Composer**: Versi `2.x`
- **Node.js**: Versi `20.x` atau LTS terbaru
- **NPM**: Versi `10.x` atau lebih baru
- **Database**:
  - Pengembangan Lokal: SQLite (default tanpa konfigurasi server database) atau MySQL / MariaDB via Laragon / XAMPP.
  - Server Produksi: MySQL 8.0+ atau PostgreSQL 15+.
- **Git**: Untuk manajemen kode sumber.

---

## 2. Langkah Instalasi di Lingkungan Lokal

### Langkah 1: Klon Repositori
```bash
git clone https://github.com/username/stokcerdas-ai.git
cd stokcerdas-ai
```

### Langkah 2: Pasang Dependensi Backend (Composer)
```bash
composer install
```

### Langkah 3: Pasang Dependensi Frontend (NPM)
```bash
npm install
```

### Langkah 4: Salin Berkas Konfigurasi Lingkungan (`.env`)
```bash
cp .env.example .env
```
Lalu generate kunci enkripsi aplikasi:
```bash
php artisan key:generate
```

### Langkah 5: Konfigurasi Database
Buka berkas `.env`:
- **Opsi A: Menggunakan SQLite (Paling Praktis)**
  ```env
  DB_CONNECTION=sqlite
  # Komentari atau kosongkan DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
  ```
  Jika menggunakan SQLite, pastikan berkas `database/database.sqlite` tersedia:
  ```bash
  touch database/database.sqlite
  # atau di Windows PowerShell:
  # New-Item -ItemType File database/database.sqlite -Force
  ```

- **Opsi B: Menggunakan MySQL (Laragon / XAMPP)**
  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=stokcerdas_ai
  DB_USERNAME=root
  DB_PASSWORD=
  ```
  *(Pastikan database `stokcerdas_ai` sudah dibuat di MySQL).*

### Langkah 6: Jalankan Migrasi & Seeder Data Demo
```bash
php artisan migrate --seed
```
Perintah ini akan membuat semua struktur tabel dan mengisi data awal berupa akun toko demo dan katalog sembako.

Akun demo bawaan:
- **Admin**: `admin@stokcerdas.test` (password: `password`)
- **Karyawan**: `karyawan@stokcerdas.test` (password: `password`)

---

## 3. Konfigurasi Google Gemini AI

Aplikasi mendukung integrasi resmi Google Gemini AI untuk asisten persediaan dan penjelasan analisis stok.

1. Buka [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Buat API Key baru (**Create API Key**) pada project standar.
3. Buka file `.env` di project Anda, lalu atur:
   ```env
   GEMINI_API_KEY=AIzaSy...your-actual-api-key...
   GEMINI_MODEL=gemini-flash-lite-latest
   GEMINI_TIMEOUT=60
   GEMINI_MAX_TOKENS=1024
   ```
4. Bersihkan cache konfigurasi Laravel:
   ```bash
   php artisan config:clear
   ```

> **Catatan Mode Demo**: Jika `GEMINI_API_KEY` tidak diisi, aplikasi akan otomatis masuk ke **Mode Demo (Simulasi)**. Jawaban akan dibuat oleh server lokal menggunakan data toko nyata tanpa memerlukan koneksi internet ke Google.

---

## 4. Menjalankan Aplikasi

### Mode Pengembangan (Development)
Jalankan dua terminal terpisah:
- **Terminal 1 (Vite HMR)**:
  ```bash
  npm run dev
  ```
- **Terminal 2 (Server Laravel)**:
  ```bash
  php artisan serve
  ```
Buka browser di: `http://localhost:8000` (atau `http://stokcerdas-ai.test` jika menggunakan Laragon virtual host).

### Mode Produksi (Production Build)
Lakukan kompilasi aset frontend:
```bash
npm run build
```

---

## 5. Menjalankan Pengujian Otomatis (Testing)

Jalankan seluruh rangkaian test unit dan feature:
```bash
php artisan test
```
*Ekspektasi: 248 tests lulus (247 passed, 1 skipped).*

Untuk menguji pengerasan keamanan dan proteksi multi-tenant:
```bash
php artisan test tests/Feature/HardeningTest.php
```

---

## 6. Uji Beban Database (Load Testing - Opsional)

Proyek ini menyediakan seeder khusus untuk mensimulasikan toko besar dengan **2.000 barang** dan **20.000 riwayat transaksi penjualan**:

1. Buat database baru khusus pengujian, misalnya `stokcerdas_ai_load`.
2. Ubah sementara nilai `DB_DATABASE=stokcerdas_ai_load` di file `.env`.
3. Jalankan migrasi dan seeder beban:
   ```bash
   php artisan migrate:fresh --seed
   php artisan db:seed --class=LoadTestSeeder
   ```
4. Uji kecepatan loading halaman Dashboard, Monitoring, dan Analisis AI.
5. Setelah pengujian selesai, kembalikan konfigurasi `DB_DATABASE` di `.env` ke database semula.

---

## 7. Catatan Keamanan Produksi

Saat melakukan deploy ke server produksi (VPS, Cloud, atau Shared Hosting):
1. Pastikan `APP_ENV=production` dan `APP_DEBUG=false` di `.env`.
2. Pastikan file `.env` **tidak dapat diakses publik** lewat browser (hanya folder `public/` yang dijadikan DocumentRoot web server).
3. Jalankan optimasi cache Laravel:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
4. Atur izin kepemilikan direktori `storage/` dan `bootstrap/cache/` agar dapat ditulisi oleh web server (`chmod -R 775 storage bootstrap/cache`).
