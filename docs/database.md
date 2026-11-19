# Dokumentasi Database StokCerdas AI

Semua tabel bisnis memiliki `store_id` agar data antar toko tidak tercampur.
Stok disimpan dalam satuan terkecil (`products.unit`); kemasan dikonversi lewat
`products.units_per_pack`.

```mermaid
erDiagram
    stores ||--o{ users : memiliki
    stores ||--o{ categories : memiliki
    stores ||--o{ suppliers : memiliki
    stores ||--o{ products : memiliki
    categories ||--o{ products : mengelompokkan
    suppliers ||--o{ products : memasok
    suppliers ||--o{ stock_ins : mengirim
    users ||--o{ stock_ins : mencatat
    stock_ins ||--|{ stock_in_details : berisi
    stock_outs ||--|{ stock_out_details : berisi
    sales ||--|{ sale_details : berisi
    products ||--o{ stock_in_details : "dibeli di"
    products ||--o{ stock_out_details : "dikeluarkan di"
    products ||--o{ sale_details : "dijual di"
    products ||--o{ stock_movements : "riwayat stok"
    users ||--o{ ai_conversations : memulai
    ai_conversations ||--o{ ai_messages : berisi
```

## Catatan penting
- `stock_movements` adalah buku besar stok: `stock_after` entri terakhir sama dengan `products.stock`.
- `sale_details.unit_price` dan `unit_cost` adalah snapshot harga saat transaksi.
- `products` memakai soft delete; kode barang unik per toko (termasuk yang sudah dihapus).
- `ai_messages.is_simulation = true` menandai jawaban mode demo (bukan AI sungguhan).
