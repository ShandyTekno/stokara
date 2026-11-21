# Dokumentasi Fitur STOKARA

Dokumen ini menjelaskan alur kerja, logika bisnis, dan aturan teknis dari seluruh modul yang ada pada aplikasi **STOKARA** (Sistem Manajemen Bisnis UMKM).

---

## 1. Multi-Tenancy & Manajemen Pengguna

### 1.1. Isolasi Data Toko (`BelongsToStore`)
- Setiap pengguna terikat pada satu `store_id`.
- Setiap model bisnis (Produk, Kategori, Pemasok, Penjualan, Barang Masuk/Keluar, Percakapan AI) menggunakan trait `App\Models\Concerns\BelongsToStore`.
- Trait ini menyematkan **Global Scope** otomatis yang menambahkan klausa `WHERE store_id = auth()->user()->store_id` pada setiap query Eloquent.
- Pada saat pembuatan record baru (`creating`), nilai `store_id` otomatis diisi dari akun pengguna yang sedang login.
- Atribut `store_id` sengaja **tidak dimasukkan** ke dalam array `$fillable` model untuk mencegah *mass-assignment vulnerability*.

### 1.2. Peran & Hak Akses (Role-Based Access)
Aplikasi membedakan hak akses berdasarkan kolom `role` pada tabel `users`:
- **`admin` (Pemilik Toko)**:
  - Mengelola kategori, pemasok, produk.
  - Mengelola data karyawan (tambah, edit, nonaktifkan akun).
  - Akses seluruh laporan finansial, margin laba kotor, dan ekspor.
  - Akses modul AI (AI Assistant dan Analisis Stok).
- **`karyawan` (Kasir / Staf Operasional)**:
  - Mengakses halaman Kasir (Penjualan), Barang Masuk, Barang Keluar, dan Monitoring Stok.
  - Jika mencoba mengakses menu laporan (`/laporan`), manajemen pengguna (`/pengguna`), atau menu admin lainnya, sistem langsung melempar respon `403 Forbidden`.

---

## 2. Manajemen Barang & Multi-Satuan

### 2.1. Satuan Dasar vs Satuan Kemasan
- **Satuan Dasar (`unit`)**: Satuan terkecil saat barang dijual eceran (misalnya: `pcs`, `botol`, `bungkus`, `kg`).
- **Isi per Kemasan (`units_per_pack`)**: Jumlah satuan dasar dalam 1 kemasan grosir (misal: 1 dus = 24 pcs, maka `units_per_pack = 24`).
- **Contoh Kasus**:
  - Barang: *Mie Goreng Spesial*
  - Satuan dasar: `bungkus`
  - Isi per dus: `40`
  - Jika toko membeli 5 dus, sistem otomatis mencatat penambahan stok sebesar `5 x 40 = 200 bungkus`.

### 2.2. Batas Minimum Stok (`min_stock`)
- Digunakan sebagai ambang batas peringatan (*reorder point*).
- Jika `stock <= min_stock`, barang otomatis ditandai berstatus **Kritis (Mendekati Habis)** pada dashboard dan monitoring.

---

## 3. Transaksi Penjualan & Kasir (POS)

### 3.1. Alur Transaksi
1. Kasir memilih barang dari daftar atau mengetik kode/nama barang pada kolom pencarian.
2. Kasir menentukan jumlah (*quantity*). Sistem memvalidasi apakah `quantity <= products.stock`.
3. Kasir memilih metode pembayaran:
   - **Tunai**: Mengisi nominal uang yang diterima (`amount_paid`). Sistem menghitung kembalian (`change_amount = amount_paid - total_amount`).
   - **QRIS**: Nominal bayar disamakan persis dengan total belanja (`change_amount = 0`).
4. Klik **Selesaikan Transaksi**:
   - Record penjualan disimpan ke tabel `sales` dan detailnya ke `sale_details`.
   - Stok barang otomatis dipotong.
   - Snapshot harga beli (`unit_cost`) dan harga jual (`unit_price`) saat itu disimpan ke `sale_details` agar laporan margin laba di masa mendatang tidak berubah meskipun harga barang naik/turun di kemudian hari.
   - Record mutasi stok tercatat di `stock_movements`.

### 3.2. Cetak Struk Belanja
- Pada halaman detail transaksi (`Sales/Show.vue`), kasir dapat menekan tombol **Cetak Struk**.
- Fungsi menggunakan `window.print()` yang didesain dengan CSS khusus media cetak (`@media print` / kelas Tailwind `print:`):
  - Menyembunyikan sidebar, navbar, dan tombol aksi.
  - Merapikan font struk agar muat di kertas thermal printer standar (58mm / 80mm) maupun printer desktop.

---

## 4. Barang Masuk & Barang Keluar

### 4.1. Barang Masuk (`StockIn`)
- Digunakan untuk mencatat restock dari pemasok.
- Kasir/admin memilih apakah pembelian dilakukan dalam satuan eceran atau dus.
- Kasir dapat memperbarui harga beli dan harga jual terbaru.
- Sistem otomatis menambah kolom `stock` pada tabel `products` dan mencatat buku besar `stock_movements`.

### 4.2. Barang Keluar (`StockOut`)
- Digunakan untuk pengeluaran barang selain penjualan, seperti:
  - Barang rusak / bocor (*damaged*).
  - Barang kadaluarsa (*expired*).
  - Keperluan internal toko / susut.
- Stok langsung dikurangi dan mutasi dicatat dengan keterangan alasan terkait.

---

## 5. Monitoring & Laporan

### 5.1. Monitoring Stok Kritis
Menampilkan ringkasan visual dan tabel filter cepat:
- **Habis**: Barang dengan stok 0.
- **Kritis**: Barang dengan stok di bawah atau sama dengan `min_stock`.
- **Aman**: Barang dengan stok melimpah.

### 5.2. Laporan Laba Kotor & Penjualan
- Menghitung **Total Omset Penjualan** (`total_amount`).
- Menghitung **Harga Pokok Penjualan (HPP / COGS)** dari `unit_cost * quantity`.
- Menghitung **Laba Kotor**: `Omset - HPP`.
- Menampilkan grafik tren penjualan dan daftar produk paling laris (*top selling products*).

### 5.3. Ekspor Data
- **PDF**: Menghasilkan dokumen siap cetak/arsip dengan layout tabel rapi melalui library DomPDF.
- **Excel (.xlsx)**: Menghasilkan spreadsheet terstruktur melalui Laravel Excel untuk audit dan pembukuan eksternal.

---

## 6. Modul Kecerdasan Buatan (AI)

### 6.1. AI Stock Assistant (Chatbot)
- Menggunakan integrasi resmi **Google Gemini API** (`gemini-flash-lite-latest`).
- **Injeksi Konteks Dinamis**:
  - Saat pengguna mengirim pesan, sistem mengambil ringkasan data toko aktual (katalog, omset hari ini, barang menipis, barang terlaris).
  - Ringkasan data ini disisipkan ke dalam instruksi sistem (*system prompt*) asisten AI.
  - Data sensitif seperti kata sandi, email, dan hash kriptografi **tidak pernah dikirim ke AI**.
- **Indikator Loading Interaktif**:
  - Progress bar atas (Inertia) dimatikan (`showProgress: false`).
  - Menampilkan bubble interaktif dengan animasi **lingkaran berputar (*circular rotating spinner*)** bertuliskan *"AI sedang berpikir…"* di bawah pesan pengguna.
- **Mode Demo (Simulasi)**:
  - Jika pengguna belum mengisi `GEMINI_API_KEY` pada `.env`, sistem beralih ke `DemoProvider`.
  - Jawaban disusun secara simulasi cerdas oleh server lokal dengan aturan berbasis kata kunci data toko, ditandai dengan badge khusus `SIMULASI`.

### 6.2. Analisis AI & Rekomendasi Persediaan
- Menganalisis riwayat penjualan dalam periode tertentu (misal 30 hari terakhir).
- Menghitung rata-rata penjualan per hari untuk setiap produk.
- Menentukan **Estimasi Hari Habis**: `Stok Sekarang / Rata-rata Penjualan Harian`.
- Memberikan **Rekomendasi Jumlah Pesan Ulang**: Berapa pcs yang harus dipesan agar stok aman untuk 14, 30, atau 60 hari ke depan.
- Mendeteksi barang yang tidak laku sama sekali dalam 60 hari terakhir (*slow-moving dead stock*).
- Tombol **Jelaskan Hasil Analisis**: Meminta Google Gemini merangkum tabel data rumit menjadi narasi saran belanja dalam bahasa Indonesia yang ringkas dan mudah dipahami pemilik toko.
