# Product Requirements Document (PRD)
## STOKARA — Sistem Manajemen Bisnis UMKM
### Kelola Stok, Kendalikan Bisnis.
*Inventory Management · POS · Business Intelligence · AI Assistant*

- **Versi Produk**: 1.0.0
- **Nama Produk**: STOKARA (Stokara)
- **Kategori**: Sistem Manajemen Bisnis UMKM
- **Status Dokumen**: Final (Production Ready)
- **Target Pengguna**: Pemilik Toko Retail, UMKM Sembako/Kelontong, Kasir, dan Pengelola Gudang

---

## 1. Latar Belakang & Masalah

Banyak pemilik toko kelontong dan UMKM retail menghadapi tantangan operasional harian:
1. **Kehabisan Stok Mendadak (*Stockout*)**: Barang laris sering tiba-tiba habis tanpa sempat dipesan ulang lebih awal, menyebabkan hilangnya potensi penjualan.
2. **Modal Tertahan (*Dead Stock*)**: Barang lambat laku menumpuk di gudang dan menyerap arus kas tanpa terdeteksi tepat waktu.
3. **Pencatatan Satuan Rumit**: Barang sering dibeli dalam satuan karton/dus (misal isi 24 atau 48 pcs), tetapi dijual eceran per bungkus/pcs. Kesalahan konversi menyebabkan selisih stok fisik dan sistem.
4. **Keterbatasan Analisis Manual**: Pemilik toko tidak memiliki waktu atau keahlian menganalisis ribuan baris data penjualan di spreadsheet untuk mengetahui proyeksi kebutuhan stok minggu depan.
5. **Kebutuhan Sistem yang Siap Pakai & Aman**: Memerlukan antarmuka kasir yang cepat, multi-peran (karyawan tidak boleh melihat laba kotor), serta keamanan data antar cabang/toko yang terisolasi.

---

## 2. Visi & Tujuan Produk

**STOKARA** dirancang sebagai solusi persediaan dan Point of Sale (POS) modern yang menggabungkan:
- Kecepatan dan kemudahan sistem POS kasir harian.
- Akurasi buku besar mutasi stok otomatis dengan konversi kemasan multi-satuan.
- Kecerdasan buatan (**Google Gemini AI**) sebagai konsultan virtual toko yang mampu menjawab pertanyaan bisnis, memproyeksikan sisa hari persediaan, dan menyusun saran restock secara otomatis.

---

## 3. Profil Pengguna & Peran (User Personas)

### A. Pemilik Toko (Role: `admin`)
- **Tanggung Jawab**: Mengawasi operasional seluruh toko, mengelola akun staf, memantau modal dan margin laba, menganalisis stok, serta berdiskusi dengan AI.
- **Kebutuhan**: Dashboard performa, laporan laba kotor, ekspor Excel/PDF, fitur Analisis AI, dan AI Stock Assistant.

### B. Kasir / Staf Toko (Role: `karyawan`)
- **Tanggung Jawab**: Melayani transaksi pembeli di kasir, mencatat barang masuk dari pemasok, dan mencatat barang rusak/keluar.
- **Kebutuhan**: Antarmuka kasir yang cepat dan ringkas, tombol cetak struk instan, pencarian barang instan, tanpa akses ke data finansial sensitif toko.

---

## 4. Ruang Lingkup Fitur (Feature Scope)

### 4.1. Autentikasi & Multi-Tenancy Mandiri
- Registrasi mandiri pemilik toko baru langsung membuat tenant toko (`stores`) dan akun admin pertama.
- Seluruh tabel operasional (`products`, `categories`, `suppliers`, `stock_ins`, `stock_outs`, `sales`, dll.) wajib memiliki `store_id`.
- Global Scope Eloquent via trait `BelongsToStore` menjamin pengguna hanya dapat mengakses dan memodifikasi data toko miliknya sendiri.
- Proteksi mass-assignment pada `store_id` untuk mencegah manipulasi ID toko via HTTP request.
- Pembatasan laju (*rate limiting*) pada endpoint registrasi (`throttle:6,1`) untuk mencegah spam pendaftaran akun.

### 4.2. Manajemen Master Data (Barang, Kategori, Pemasok)
- **Barang (`products`)**: Kode barang unik per toko, nama barang, kategori, pemasok utama, satuan terkecil (`unit`), isi per kemasan (`units_per_pack`), harga beli, harga jual, dan stok minimum (`min_stock`).
- Fitur *soft delete* pada produk agar riwayat transaksi masa lalu tetap valid jika barang dinonaktifkan.
- **Kategori & Pemasok**: Manajemen data mitra pemasok dan klasifikasi produk.

### 4.3. Mutasi Stok (Barang Masuk & Keluar)
- **Barang Masuk (`stock-ins`)**: Input pembelian dari pemasok dengan opsi satuan eceran atau kemasan/dus. Sistem otomatis mengalikan jumlah dengan `units_per_pack`. Update otomatis pada harga beli terakhir produk.
- **Barang Keluar (`stock-outs`)**: Pencatatan pengurangan stok non-penjualan (rusak, kedaluwarsa, dipakai sendiri, susut) disertai alasan.
- **Buku Besar Stok (`stock_movements`)**: Setiap perubahan stok dicatat dengan nilai sebelum (`stock_before`), perubahan (`quantity`), dan sesudah (`stock_after`).

### 4.4. Transaksi Penjualan & Kasir (POS)
- Pemilihan barang cepat dengan pencarian nama atau kode barcode.
- Validasi stok waktu-nyata (mencegah penjualan melebihi stok yang tersedia).
- Kalkulasi otomatis total belanja, diskon (opsional), nominal bayar, dan uang kembalian.
- Metode pembayaran: **Tunai** dan **QRIS**.
- Cetak struk belanja thermal/kertas langsung via browser print (`window.print()`).

### 4.5. Monitoring Persediaan Real-Time
- Panel status stok otomatis yang mengelompokkan barang ke dalam 3 status:
  1. **Habis (`out_of_stock`)**: Stok = 0 unit.
  2. **Kritis / Menipis (`low_stock`)**: Stok > 0 tetapi <= batas `min_stock`.
  3. **Aman (`safe`)**: Stok berada di atas batas minimum.

### 4.6. Laporan & Ekspor Data
- Laporan Penjualan (Harian, Mingguan, Bulanan, atau rentang tanggal kustom).
- Laporan Margin Laba Kotor (dihitung dari selisih `unit_price` dan `unit_cost` saat transaksi terjadi).
- Ekspor laporan dalam format **PDF** (layout rapi untuk dicetak) dan **Excel (.xlsx)** untuk analisis lanjutan.

### 4.7. AI Stock Assistant (Chatbot Pintar)
- Asisten interaktif berbasis **Google Gemini AI** (`gemini-flash-lite-latest`).
- Menginjeksi ringkasan data toko terkini (katalog terlaris, stok menipis, barang habis, omset) ke dalam prompt sistem secara aman (tanpa menyertakan data sensitif seperti password/email).
- Riwayat obrolan tersimpan per sesi percakapan.
- UI modern dengan indikator loading berputar (*circular rotating spinner*) dan status berpikir.
- Fallback cerdas: Jika API Key belum disetel, sistem otomatis berjalan dalam **Mode Demo (Simulasi)** tanpa menimbulkan error 500.

### 4.8. Analisis AI & Rekomendasi Persediaan
- Kalkulasi statistik rata-rata kecepatan penjualan per hari (`avg_daily_sales`).
- Perhitungan estimasi sisa hari stok sebelum habis (`days_left = current_stock / avg_daily_sales`).
- Rekomendasi otomatis jumlah restock untuk target cakupan hari tertentu (misal 14 atau 30 hari ke depan).
- Deteksi barang perputaran lambat (*slow-moving* / *overstock*).
- Penjelasan naratif otomatis dari AI yang merangkum rekomendasi prioritas belanja toko.

---

## 5. Kebutuhan Non-Fungsional (Non-Functional Requirements)

1. **Keamanan (Security)**:
   - Header keamanan HTTP ketat: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`.
   - Isolasi tenant mutlak antar pengguna.
   - Deteksi dan blokir akses route sensitif untuk role non-admin (403 Forbidden).
   - Sanitasi input dan proteksi CSRF di seluruh form.
2. **Kinerja (Performance)**:
   - Waktu respons halaman rata-rata < 300 ms untuk katalog hingga 2.000 barang.
   - Pencegahan N+1 query lazy loading aktif di lingkungan pengembangan untuk mendeteksi query berulang lebih dini.
   - Paginasi data di seluruh tabel daftar.
3. **Kompatibilitas & Tampilan Responsif (Mobile-Friendly)**:
   - Menggunakan satuan viewport dinamis `100dvh` agar nyaman digunakan di smartphone tanpa terpotong address bar.
   - Tabel dapat digeser horizontal (*scrollable*) di layar kecil (375 px) tanpa merusak layout.
4. **Reliabilitas AI**:
   - Model yang dipilih (`gemini-flash-lite-latest`) memiliki latency cepat (3-4 detik) dengan konsumsi kuota hemat di tier gratis Google AI Studio.
   - Batas waktu koneksi cURL diatur aman (timeout 60 detik) dengan penanganan pesan error yang ramah pengguna.
