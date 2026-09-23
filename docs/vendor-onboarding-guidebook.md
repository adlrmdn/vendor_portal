# Panduan Onboarding Mitra Subkontraktor / Subcontractor Onboarding Guidebook

Buku panduan ini mendokumentasikan alur operasional Portal Subkontraktor, pencatatan produksi harian, integrasi laporan pemotongan (cutting), rekonsiliasi bahan, serta petunjuk antarmuka pengguna dwibahasa (Bahasa Indonesia & English).

---

## 🇮🇩 Ringkasan Bahasa Indonesia

### 1. Alur Utama 3 Tahap Kerja Order
Setiap Work Order yang diterima oleh mitra subkontraktor memiliki 3 tahapan utama yang terstruktur:
1. **Tahap 1: Laporan Cutting (Pemotongan)**
   - Vendor mengisi jumlah potong aktual untuk setiap ukuran pakaian.
   - Tersedia opsi **Laporan Sebagian (Partial report)** jika proses potong dilakukan bertahap.
   - Hasil potong dapat diisi manual, diimpor via Excel, atau memanfaatkan angka acuan dari **Produksi Harian**.
2. **Tahap 2: Gramasi & Kapasitas Kemasan Blister**
   - Setelah laporan cutting disetujui tim admin, form gramasi terbuka.
   - Vendor menginput berat gramasi pakaian dalam satuan gram (g) per ukuran.
   - Vendor menentukan kapasitas isi per blister dan karung (sack).
3. **Tahap 3: Cetak Label Kemasan & Penyelesaian Order**
   - Setelah gramasi disetujui, tombol **Cetak Label Toko** dan **Cetak Label Gudang** terbuka.
   - Vendor mencetak dan menempelkan barcode label pada kemasan, kemudian menekan tombol **Selesaikan Order**.

---

### 2. Papan Pencatatan Produksi Harian (Daily Production)
Papan Produksi Harian (`/subcon/vendor/daily-production`) berfungsi memantau progres pergerakan pabrik per hari:
- **Tahapan yang dicatat:**
  - `Cutting` (Potong per lay/rasio dan ukuran)
  - `Sewing` (Jahit harian)
  - `Finishing` (Setrika dan kelengkapan)
  - `QC` (Pemeriksaan kualitas)
  - `Packing` (Kemas ke polybag/blister)
- **Catatan Harian:**
  - **Catatan per Style:** Kolom input catatan di samping baris tahapan untuk mencatat kendala atau rincian model tertentu.
  - **Catatan Umum Hari Ini (Overall Day Remarks):** Kartu di bagian atas halaman untuk catatan ringkasan vendor pada tanggal tersebut.
- **Status Style:**
  - Style dapat ditandai **Closed** jika proses produksi selesai agar tampilan tetap ringkas, dan dapat dibuka kembali (**Reopen**) sewaktu-waktu.

---

### 3. Sinkronisasi Cutting & Kebebasan Edit (Override Unlocked)
- **Pengisian Otomatis (Prefill Placeholder):**
  - Total hasil potong yang dicatat pada Papan Produksi Harian secara otomatis terhubung ke form Laporan Cutting dan muncul sebagai petunjuk `"Dari Produksi Harian: X"`.
- **Kebebasan Edit (Override):**
  - Kolom input **TIDAK DIKUNCI**. Jika terdapat revisi jumlah nyata atau perbedaan fisik, vendor bebas mengubah angka tersebut secara langsung sebelum mengirimkan laporan persetujuan.

---

### 4. Rekonsiliasi Material & Surat Jalan (Tab 2)
Untuk mencegah kebingungan antarmuka, rekonsiliasi material dan surat jalan diletakkan di tab mandiri:
- **Kategori Kain Sisa:**
  - `Short Roll`: Gulungan pendek atau cacat bahan.
  - `Sisa Kain (Utuh)`: Kain sisa utuh yang masih layak pakai.
  - `Kepala Kain`: Perca atau potongan ujung kain tidak beraturan.
  - `Retur Kain`: Total terhitung otomatis dari ketiga kategori di atas.
- **Surat Jalan / Delivery Notes:**
  - Vendor dapat mengunggah foto atau PDF surat jalan pengiriman kapan saja selama status order berjalan, tanpa harus menunggu persetujuan tahapan produksi.

---

### 5. Dukungan Bahasa & Ikon Informasi (i)
- **Bahasa Baku:**
  - Sistem portal menggunakan Bahasa Inggris secara baku untuk administrasi, namun otomatis **beralih ke Bahasa Indonesia** bagi akun mitra vendor subkontraktor.
  - Pengguna dapat beralih bahasa kapan saja melalui pemilih bahasa `🇮🇩 ID` | `🇬🇧 EN` di bilah navigasi atas.
- **Ikon Bantuan (i):**
  - Ikon `(i)` tersemat di samping setiap formulir dan judul bagian penting (Cutting, Gramasi, Rekonsiliasi, Produksi Harian) untuk memberikan petunjuk ringkas seketika.

---

## 🇬🇧 English Summary

### 1. 3-Step Work Order Lifecycle
1. **Stage 1: Cutting Report**
   - Vendors enter actual cut quantities per size.
   - Use the **Partial report** switch if cutting is executed in batches.
   - Values can be entered directly, imported via Excel, or pre-filled from **Daily Production**.
2. **Stage 2: Gramasi & Blister Packaging**
   - Unlocked after cutting report approval.
   - Enter fabric garment weight in grams (g) per size.
   - Enter piece capacity per blister and sack.
3. **Stage 3: Packaging Labels & Work Order Completion**
   - Unlocked after gramasi approval.
   - Print Store and Warehouse barcode labels.
   - Affix labels and submit **Complete PO**.

### 2. Daily Production Board
- Track operational progress across **Cutting**, **Sewing**, **Finishing**, **QC**, and **Packing**.
- Provide per-style remarks as well as overall day remarks.
- Close completed styles to keep boards clutter-free, with the option to reopen anytime.

### 3. Cutting Prefill & Vendor Override
- Quantities logged in Daily Production automatically flow into the Work Order Cutting Report as guidance placeholders.
- Input fields remain **unlocked**, allowing vendors to freely override numbers if discrepancies arise.

### 4. Material Reconciliation & Delivery Notes
- Accessible under Tab 2 of the Work Order.
- Can be saved independently at any time without blocking stage approvals.
- Categorizes remnants into Short Rolls, Remnants (Utuh), Head Ends (Kepala Kain), and auto-tallied Return Fabric.
