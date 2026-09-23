<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Portal Mitra Subkontraktor (CMT) — Panduan Pengguna Vendor</title>
    <style>
        @page {
            margin: 36px 32px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.5;
        }

        .cover {
            text-align: center;
            padding-top: 130px;
        }

        .cover .logo-badge {
            display: inline-block;
            background: #1e3a8a;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1.5px;
            padding: 6px 18px;
            border-radius: 4px;
            margin-bottom: 24px;
            text-transform: uppercase;
        }

        .cover h1 {
            font-size: 26px;
            margin-bottom: 8px;
            color: #0f172a;
            font-weight: bold;
        }

        .cover h2 {
            font-size: 15px;
            font-weight: normal;
            color: #475569;
            margin-top: 0;
            margin-bottom: 6px;
        }

        .cover .subtitle-tags {
            font-size: 12px;
            color: #0284c7;
            font-weight: 600;
            margin-top: 8px;
        }

        .cover .meta {
            margin-top: 60px;
            font-size: 11px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 14px;
            display: inline-block;
            width: 70%;
        }

        .page-break {
            page-break-before: always;
        }

        h1.section {
            font-size: 17px;
            color: #0f172a;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 5px;
            margin-top: 0;
            margin-bottom: 12px;
        }

        h2.sub {
            font-size: 13.5px;
            color: #1e293b;
            margin-top: 18px;
            margin-bottom: 6px;
            border-left: 3px solid #0284c7;
            padding-left: 8px;
        }

        p {
            margin: 5px 0;
        }

        ol, ul {
            margin: 5px 0 8px 0;
            padding-left: 18px;
        }

        li {
            margin-bottom: 4px;
        }

        .note {
            background: #f0f9ff;
            border-left: 4px solid #0284c7;
            padding: 8px 12px;
            margin: 9px 0;
            font-size: 11.5px;
            color: #0369a1;
        }

        .tip {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
            padding: 8px 12px;
            margin: 9px 0;
            font-size: 11.5px;
            color: #15803d;
        }

        .warn {
            background: #fffbeb;
            border-left: 4px solid #d97706;
            padding: 8px 12px;
            margin: 9px 0;
            font-size: 11.5px;
            color: #b45309;
        }

        .important {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 8px 12px;
            margin: 9px 0;
            font-size: 11.5px;
            color: #b91c1c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 12px 0;
            font-size: 11.5px;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
        }

        code {
            background: #f1f5f9;
            color: #0f172a;
            padding: 1px 4px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 11px;
        }

        .toc ol {
            list-style: none;
            padding-left: 0;
        }

        .toc li {
            padding: 5px 0;
            border-bottom: 1px dotted #cbd5e1;
        }

        .toc li a {
            color: #0f172a;
            text-decoration: none;
            font-weight: 500;
        }

        .footer-note {
            margin-top: 24px;
            font-size: 10.5px;
            color: #64748b;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            text-align: center;
        }

        a {
            color: #0284c7;
            text-decoration: none;
        }

        .badge-step {
            display: inline-block;
            background: #1e3a8a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 7px;
            border-radius: 3px;
            margin-right: 4px;
        }

        .badge-tab {
            display: inline-block;
            background: #0d9488;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 7px;
            border-radius: 3px;
            margin-right: 4px;
        }

        .badge-stage {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
        }

        /* Diagram / Card Table in pure HTML for DomPDF */
        .diagram-box {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 10px 0;
        }

        .diagram-cell {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 8px;
            text-align: center;
            vertical-align: middle;
            font-size: 11px;
        }

        .diagram-cell strong {
            display: block;
            margin-bottom: 3px;
            color: #0f172a;
        }

        .diagram-active {
            background: #eff6ff;
            border-color: #3b82f6;
        }
    </style>
</head>

<body>

    <div class="cover">
        <div class="logo-badge">MEGA PERINTIS &middot; PORTAL VENDOR CMT</div>
        <h1>Buku Panduan Mitra Subkontraktor</h1>
        <h2>Panduan Operasional Pengelolaan Work Order, Produksi Harian, &amp; Rekonsiliasi Bahan</h2>
        <div class="subtitle-tags">
            Alur 3-Tahap &bull; Sinkronisasi Produksi Harian &bull; Rekonsiliasi Bahan Mandiri &bull; Cetak Label Kemasan
        </div>
        <div class="meta">
            <strong>Versi 2.0 (Terbaru)</strong> &middot; {{ now()->translatedFormat('F Y') }}<br>
            Dokumen resmi untuk seluruh Mitra Vendor CMT PT Mega Perintis Tbk
        </div>
    </div>

    <div class="page-break"></div>

    <h1 class="section">Daftar Isi</h1>
    <div class="toc">
        <ol>
            <li><strong>1. Memulai &amp; Akses Portal</strong> (Login, Tautan Penting, &amp; Standar Bahasa)</li>
            <li><strong>2. Ringkasan Dashboard Vendor</strong> (Metrik Order, Spotlight Panduan, &amp; 5 Bagian Alur)</li>
            <li><strong>3. Struktur Halaman Detail Work Order</strong> (Spesifikasi Style PLM &amp; Arsitektur 3 Tab)</li>
            <li><strong>4. Tab 1 — Tahap 1: Laporan Cutting</strong> (Input Qty, Prefill Harian, Excel, &amp; Partial Report)</li>
            <li><strong>5. Tab 1 — Persetujuan Cutting &amp; Pengajuan Revisi Cutting</strong> (Fitur Revisi Setelah Approval)</li>
            <li><strong>6. Tab 1 — Tahap 2: Gramasi &amp; Kapasitas Kemasan</strong> (Berat Gramasi, Blister 20 pcs, Karung 80 pcs)</li>
            <li><strong>7. Tab 1 — Tahap 3: Cetak Label Kemasan &amp; Penyelesaian Order</strong> (Store Labels, Online Labels)</li>
            <li><strong>8. Tab 2 — Rekonsiliasi Bahan &amp; Bukti Surat Jalan (Independen)</strong> (Kain Sisa &amp; Penghematan)</li>
            <li><strong>9. Tab 2 — Rekonsiliasi Aksesoris &amp; Tambah Aksesoris</strong> (Fitur Tambah Aksesoris Dinamis)</li>
            <li><strong>10. Tab 3 — Papan Produksi Harian (Daily Production)</strong> (5 Stasiun Produksi &amp; Catatan Tanggal)</li>
            <li><strong>11. Form Catatan Terintegrasi &amp; Ikon Panduan Informasi ('i')</strong></li>
            <li><strong>12. Pengaturan Profil &amp; Pusat Bantuan Dukungan</strong></li>
        </ol>
    </div>

    <div class="tip">
        <strong>Pembaruan Versi 2.0:</strong> Portal Vendor kini menggunakan navigasi <strong>3 Tab Mandiri</strong> (Tahapan Produksi, Rekonsiliasi Bahan, Produksi Harian), pencatatan harian yang otomatis mengalir ke Laporan Cutting dengan kebebasan edit, fitur pengajuan <strong>Revisi Cutting</strong> setelah disetujui, dan penambahan <strong>Aksesoris Baru</strong> secara fleksibel.
    </div>

    <div class="page-break"></div>

    <h1 class="section">1. Memulai &amp; Akses Portal</h1>
    <p>
        Portal Vendor Subkontraktor adalah platform resmi PT Mega Perintis Tbk bagi mitra CMT (Cut-Make-Trim) untuk mencatat aktivitas harian, mengirimkan laporan hasil cutting, menginput berat gramasi pakaian, melakukan rekonsiliasi bahan baku, serta mencetak barcode label kemasan sebelum barang jadi dikirim.
    </p>

    <h2 class="sub">1.1 Cara Masuk (Login)</h2>
    <ol>
        <li>Buka browser dan akses alamat: <a href="https://vendor-portal.megaperintis.co.id">https://vendor-portal.megaperintis.co.id</a>.</li>
        <li>Masukkan alamat email dan kata sandi resmi yang telah didaftarkan oleh Mega Perintis.</li>
        <li>Klik tombol <strong>Sign In</strong>. Setelah berhasil masuk, Anda akan diarahkan ke halaman <strong>Dashboard</strong>.</li>
    </ol>
    <div class="note">
        <strong>Keamanan Akun:</strong> Akun Anda terhubung langsung dengan kode vendor perusahaan Anda. Anda hanya dapat melihat dan mengelola Surat Perintah Kerja (Work Order) milik perusahaan Anda sendiri.
    </div>

    <h2 class="sub">1.2 Tautan Navigasi Cepat</h2>
    <table>
        <tr>
            <th style="width: 30%;">Halaman</th>
            <th style="width: 35%;">Alamat URL</th>
            <th style="width: 35%;">Fungsi</th>
        </tr>
        <tr>
            <td><strong>Dashboard</strong></td>
            <td><code>/subcon/vendor/dashboard</code></td>
            <td>Ringkasan statistik, alur kerja, &amp; order terbaru.</td>
        </tr>
        <tr>
            <td><strong>Daftar Work Order</strong></td>
            <td><code>/subcon/vendor/orders</code></td>
            <td>Daftar seluruh Surat Perintah Kerja (aktif &amp; selesai).</td>
        </tr>
        <tr>
            <td><strong>Produksi Harian</strong></td>
            <td><code>/subcon/vendor/daily-production</code></td>
            <td>Papan pencatatan output harian untuk 5 stasiun kerja.</td>
        </tr>
        <tr>
            <td><strong>Buku Panduan Web</strong></td>
            <td><code>/subcon/vendor/guidebook</code></td>
            <td>Panduan onboarding interaktif dengan visualisasi sistem.</td>
        </tr>
        <tr>
            <td><strong>Profil Vendor</strong></td>
            <td><code>/subcon/vendor/profile</code></td>
            <td>Pengaturan akun, kontak, dan penggantian password.</td>
        </tr>
    </table>

    <h2 class="sub">1.3 Standar Bahasa (Bahasa Indonesia &amp; English)</h2>
    <p>
        Portal vendor dirancang dengan <strong>Bahasa Indonesia sebagai standar utama</strong> demi kemudahan operasional tim pabrik di lapangan. Jika dibutuhkan, Anda dapat beralih ke <strong>English</strong> sewaktu-waktu melalui tombol bendera pengalih bahasa di pojok kanan atas bilah navigasi.
    </p>

    <h1 class="section">2. Ringkasan Dashboard Vendor</h1>
    <p>
        Halaman Dashboard merupakan pusat informasi bagi vendor untuk memantau beban kerja secara real-time:
    </p>
    <ul>
        <li><strong>Total Work Orders:</strong> Akumulasi seluruh work order CMT yang pernah ditugaskan kepada vendor.</li>
        <li><strong>Active Orders:</strong> Jumlah work order yang sedang berjalan dan membutuhkan tindakan operasional (Cutting, Gramasi, atau Cetak Label).</li>
        <li><strong>Completed Orders:</strong> Jumlah work order yang telah selesai sepenuhnya dan barang telah diserahkan.</li>
    </ul>

    <h2 class="sub">2.1 Banner Spotlight Buku Panduan</h2>
    <p>
        Di bagian atas dashboard terdapat banner spotlight khusus <strong>Buku Panduan Mitra Subkontraktor</strong>. Anda dapat mengklik tombol <em>"Buka Buku Panduan Interaktif"</em> untuk mempelajari fitur baru, atau tombol <em>"Unduh Panduan PDF"</em> untuk mengunduh dokumen panduan ini.
    </p>

    <h2 class="sub">2.2 Kartu 5-Bagian Alur Kerja</h2>
    <table class="diagram-box">
        <tr>
            <td class="diagram-cell diagram-active" style="width: 20%;">
                <strong>1. Cutting</strong>
                Input qty potong / dari Produksi Harian.
            </td>
            <td class="diagram-cell" style="width: 20%;">
                <strong>2. Gramasi &amp; Blister</strong>
                Isi berat gramasi &amp; kapasitas blister/karung.
            </td>
            <td class="diagram-cell" style="width: 20%;">
                <strong>3. Label &amp; Selesai</strong>
                Cetak barcode label toko/online &amp; selesaikan.
            </td>
            <td class="diagram-cell" style="width: 20%;">
                <strong>4. Produksi Harian</strong>
                Pencatatan 5 stasiun harian per ukuran.
            </td>
            <td class="diagram-cell" style="width: 20%;">
                <strong>5. Rekonsiliasi</strong>
                Hitung sisa kain &amp; unggah surat jalan.
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    <h1 class="section">3. Struktur Halaman Detail Work Order (3 Tab Utama)</h1>
    <p>
        Saat Anda mengklik tombol <strong>View</strong> pada salah satu work order, sistem menampilkan halaman detail terpadu dengan informasi spesifikasi produk dari sistem PLM di bagian atas:
    </p>
    <table>
        <tr>
            <th style="width: 20%;">Data Produk</th>
            <th style="width: 80%;">Keterangan</th>
        </tr>
        <tr>
            <td><strong>Spesifikasi Style</strong></td>
            <td>Menampilkan <code>Season</code>, <code>World</code> (Men/Ladies/Boys), <code>Department</code>, <code>Category</code>, dan <code>Color</code>.</td>
        </tr>
        <tr>
            <td><strong>Informasi PO</strong></td>
            <td>Nomor Surat Perintah Kerja (Order #), Nomor Production Group (PRG), Tanggal Order, &amp; Tanggal Jatuh Tempo (Due Date).</td>
        </tr>
        <tr>
            <td><strong>Indikator Stage</strong></td>
            <td>Badge status alur produksi (Cutting Review, Gramasi, Waiting Distribution, Labels, Completed).</td>
        </tr>
    </table>

    <h2 class="sub">3.1 Arsitektur 3 Tab Navigasi</h2>
    <p>
        Untuk memastikan antarmuka tetap bersih, rapi, dan tidak membingungkan, seluruh formulir dikelompokkan ke dalam 3 Tab Mandiri:
    </p>
    <table>
        <tr>
            <th style="width: 25%;">Tab</th>
            <th style="width: 45%;">Isi Formulir</th>
            <th style="width: 30%;">Sifat Pengisian</th>
        </tr>
        <tr>
            <td><span class="badge-tab">Tab 1</span> <strong>Tahapan Produksi</strong></td>
            <td>Tabel ukuran (Size, Order Qty, Cut Plan, Qty Cut, Gramasi) sesuai stage yang aktif.</td>
            <td>Bertahap (memerlukan approval admin untuk lanjut).</td>
        </tr>
        <tr>
            <td><span class="badge-tab">Tab 2</span> <strong>Rekonsiliasi Bahan</strong></td>
            <td>Rekonsiliasi kain sisa, retur aksesoris, dan lampiran surat jalan (delivery note).</td>
            <td><strong>Mandiri</strong> (dapat disimpan kapan saja tanpa menunggu stage approval).</td>
        </tr>
        <tr>
            <td><span class="badge-tab">Tab 3</span> <strong>Produksi Harian</strong></td>
            <td>Ringkasan output kumulatif harian dan tombol pintas ke Papan Produksi Harian.</td>
            <td>Pencatatan real-time harian.</td>
        </tr>
    </table>

    <h1 class="section">4. Tab 1 — Tahap 1: Laporan Cutting</h1>
    <p>
        Pada saat work order berada di tahap <strong>Cutting</strong>, vendor wajib melaporkan hasil pemotongan bahan nyata untuk setiap ukuran pakaian.
    </p>

    <h2 class="sub">4.1 Tiga Cara Pengisian Hasil Cutting</h2>
    <ol>
        <li><strong>Sinkronisasi Otomatis dari Produksi Harian:</strong> Jika operator Anda telah mencatat hasil cutting pada Tab 3 (Papan Produksi Harian), jumlah tersebut <em>otomatis mengalir dan terisi</em> pada kolom input Qty Cut sebagai acuan awal.</li>
        <li><strong>Ketik Manual Langsung:</strong> Anda bebas mengetik atau mengubah angka potongan secara langsung di layar. Kolom input <strong>TIDAK DIKUNCI</strong>, sehingga jika ada koreksi fisik, Anda dapat meng-override nilainya kapan saja.</li>
        <li><strong>Unggah Template Excel:</strong> Klik tombol <strong>Download Template</strong> untuk mengunduh berkas Excel, isi kolom cutting quantity, lalu unggah kembali melalui tombol <strong>Upload Qty Cut</strong>.</li>
    </ol>

    <h2 class="sub">4.2 Fitur Laporan Parsial (Partial Report)</h2>
    <p>
        Jika pemotongan dilakukan dalam beberapa gelombang (batch) dan Anda ingin mengirim laporan potongan awal terlebih dahulu:
    </p>
    <ul>
        <li>Aktifkan toggle <strong>Partial report (Laporan Sebagian)</strong> di samping tombol submit.</li>
        <li>Setelah admin menyetujui laporan parsial ini, work order <strong>tidak akan langsung loncat ke Gramasi</strong>, melainkan tetap berada di tahap Cutting agar Anda dapat memasukkan sisa kuantitas pada gelombang berikutnya.</li>
        <li>Pada gelombang terakhir (potongan telah tuntas seluruhnya), <strong>matikan toggle Partial report</strong> sebelum submit agar work order maju ke tahap Gramasi.</li>
    </ul>

    <div class="note">
        <strong>Perhitungan Balance:</strong> Sistem secara otomatis menghitung selisih (Balance) antara <code>Qty Cut</code> dengan batas rencana potong (<code>Cut Plan</code> dari alokasi kain) atau <code>Order Qty</code>. Angka positif berwarna hijau menandakan surplus, sedangkan angka negatif berwarna merah menandakan under-cut.
    </div>

    <div class="page-break"></div>

    <h1 class="section">5. Tab 1 — Persetujuan Cutting &amp; Fitur Revisi Cutting</h1>
    <p>
        Setelah Anda menekan tombol <strong>Submit Cutting Report</strong>, laporan akan terkunci sementara dan dikirimkan ke admin produksi untuk diperiksa:
    </p>
    <ul>
        <li>Jika admin <strong>menyetujui (Approve)</strong> laporan final, work order berlanjut ke <strong>Tahap 2: Gramasi &amp; Kapasitas Kemasan</strong>.</li>
        <li>Jika admin <strong>menolak (Reject)</strong>, alasan penolakan akan ditampilkan pada banner merah dan formulir cutting terbuka kembali untuk diperbaiki.</li>
    </ul>

    <h2 class="sub">5.1 Mengajukan Revisi Cutting (Cutting Revision) Setelah Disetujui</h2>
    <div class="warn">
        <strong>PENTING — Fitur Baru untuk Mengatasi Salah Data:</strong><br>
        Sebelumnya, jika laporan cutting sudah terlanjur disetujui admin, vendor tidak bisa lagi mengubah kuantitas cutting meskipun baru menyadari adanya salah hitung atau typo di lapangan.
    </div>
    <p>
        Kini, pada tahap <strong>Gramasi</strong>, <strong>Waiting Distribution</strong>, maupun <strong>Labels</strong> (sebelum order berstatus Selesai/Completed), vendor dapat mengajukan revisi data cutting:
    </p>
    <ol>
        <li>Buka bagian bawah halaman detail work order. Sistem menampilkan kartu kuning bertuliskan: <em>"Perlu merevisi jumlah cutting? / Need to revise cutting quantities?"</em>.</li>
        <li>Klik tombol <strong>Ajukan Revisi Cutting (Revise Cutting Report)</strong> untuk membuka formulir revisi.</li>
        <li>Formulir akan terbuka dengan kuantitas cutting yang saat ini tercatat. Ubah angka pada ukuran yang salah.</li>
        <li>Klik <strong>Kirim Revisi Cutting untuk Persetujuan (Submit Cutting Revision for Approval)</strong>.</li>
    </ol>
    <div class="important">
        <strong>Konsekuensi Pengajuan Revisi:</strong> Mengajukan revisi cutting akan mengembalikan status work order ke tahap <strong>Cutting Review</strong> oleh admin. Admin akan menerima notifikasi revisi untuk meninjau ulang dan menyetujui perubahan konsumsi kain.
    </div>

    <h1 class="section">6. Tab 1 — Tahap 2: Gramasi &amp; Kapasitas Kemasan</h1>
    <p>
        Setelah tahap cutting tuntas, formulir <strong>Gramasi &amp; Blister</strong> akan terbuka bagi vendor untuk memasukkan data berat dan spesifikasi kemasan:
    </p>

    <h2 class="sub">6.1 Berat Gramasi per Ukuran (g)</h2>
    <p>
        Timbang sampel pakaian jadi yang telah selesai disetrika untuk setiap ukuran, lalu masukkan berat riilnya dalam satuan <strong>gram (g)</strong> pada kolom <em>Gramasi</em>. Nilai ini menjadi rujukan toleransi berat pada saat tim Quality Control (QC) memeriksa produk akhir.
    </p>

    <h2 class="sub">6.2 Kapasitas Kemasan (Packaging Capacity)</h2>
    <p>
        Vendor wajib menentukan kapasitas kemasan barang jadi (finish good) yang dapat dimuat ke dalam kemasan blister maupun karung:
    </p>
    <table>
        <tr>
            <th style="width: 30%;">Jenis Kemasan</th>
            <th style="width: 25%;">Contoh Standar</th>
            <th style="width: 45%;">Keterangan &amp; Kegunaan</th>
        </tr>
        <tr>
            <td><strong>Blister Capacity</strong></td>
            <td><code>20 pcs / blister</code></td>
            <td>Kapasitas barang jadi yang dapat dimuat ke dalam 1 pack blister. Berlaku sama rata untuk seluruh ukuran pakaian pada work order ini.</td>
        </tr>
        <tr>
            <td><strong>Sack (Karung) Capacity</strong></td>
            <td><code>80 pcs / sack</code></td>
            <td>Kapasitas barang jadi yang dapat dimuat ke dalam 1 kemasan karung. Digunakan untuk alokasi pengiriman ke gudang <em>WH Replenish</em> dan <em>WH Online</em>.</td>
        </tr>
    </table>
    <div class="tip">
        <strong>Petunjuk Nilai:</strong> Nilai kapasitas kemasan ini menjadi dasar rumus bagi sistem D365 dalam mencetak jumlah lembar dan nomor box label secara otomatis.
    </div>

    <div class="page-break"></div>

    <h1 class="section">7. Tab 1 — Tahap 3: Cetak Label Kemasan &amp; Selesai</h1>
    <p>
        Setelah laporan gramasi dan blister disetujui oleh admin, work order masuk ke tahap <strong>Waiting Distribution</strong>. Pada tahap ini, sistem otomatis atau admin membuatkan Packing Instruction (PI) di D365. Begitu PI terbentuk, tombol cetak label aktif:
    </p>

    <h2 class="sub">7.1 Mencetak Label Kemasan</h2>
    <ul>
        <li><strong>Print Store Labels (Cetak Label Toko):</strong> Menghasilkan dokumen barcode PDF untuk box pakaian yang dialokasikan langsung ke gerai/toko ritel.</li>
        <li><strong>Print Online Labels (Cetak Label Online/Gudang):</strong> Menghasilkan dokumen barcode PDF untuk kemasan pengiriman ke warehouse online dan replenishing gudang pusat.</li>
    </ul>

    <h2 class="sub">7.2 Menyelesaikan Work Order (Complete Work Order)</h2>
    <p>
        Setelah seluruh label dicetak, ditempelkan pada kemasan karton/karung, dan barang siap dikirim ke gudang Mega Perintis:
    </p>
    <ol>
        <li>Tinjau kembali ringkasan produksi pada layar.</li>
        <li>Masukkan catatan penutup pada form Catatan jika diperlukan.</li>
        <li>Klik tombol hijau <strong>Selesaikan Work Order (Complete Work Order)</strong>.</li>
        <li>Sistem akan mengonfirmasi tindakan Anda. Setelah dikonfirmasi, work order resmi ditutup dengan status <code>Completed</code>.</li>
    </ol>

    <h1 class="section">8. Tab 2 — Rekonsiliasi Bahan &amp; Bukti Surat Jalan</h1>
    <p>
        <strong>Kelebihan Tab Rekonsiliasi Bahan:</strong> Anda tidak perlu menunggu tahapan cutting atau gramasi disetujui untuk menyimpan rekonsiliasi. Data pada Tab 2 dapat diisi dan disimpan secara <strong>mandiri kapan saja</strong> (independent save).
    </p>

    <h2 class="sub">8.1 Kategori Kain Sisa (Fabric Reconciliation)</h2>
    <p>
        Untuk setiap jenis kain utama dan kombinasi yang dikirimkan oleh Mega Perintis, vendor wajib mencatat rincian sisa bahan:
    </p>
    <table>
        <tr>
            <th style="width: 25%;">Kolom</th>
            <th style="width: 75%;">Definisi Operasional</th>
        </tr>
        <tr>
            <td><strong>Short Roll</strong></td>
            <td>Sisa gulungan kain pendek yang tidak memenuhi panjang standar gelar potong.</td>
        </tr>
        <tr>
            <td><strong>Sisa Kain (Utuh)</strong></td>
            <td>Kain sisa yang masih utuh dalam gulungan dan dapat digunakan kembali untuk produksi lain.</td>
        </tr>
        <tr>
            <td><strong>Kepala Kain</strong></td>
            <td>Potongan ujung awal atau akhir roll kain yang tidak dapat digunakan (afval/perca).</td>
        </tr>
        <tr>
            <td><strong>Retur Kain</strong></td>
            <td><strong>Dihitung Otomatis:</strong> <code>Short Roll + Sisa Kain + Kepala Kain</code>. Vendor tidak perlu menghitung manual.</td>
        </tr>
    </table>

    <h2 class="sub">8.2 Lampiran Surat Jalan Retur Bahan (Delivery Notes)</h2>
    <p>
        Saat mengembalikan kain sisa atau aksesoris ke gudang Mega Perintis, vendor wajib mengunggah bukti fisik surat jalan pengiriman:
    </p>
    <ul>
        <li>Dukungan berkas: <strong>PDF, JPG, JPEG, PNG</strong> (maksimal 10 MB per berkas).</li>
        <li>Tersedia kolom <strong>Catatan Surat Jalan</strong> untuk mencantumkan nomor surat jalan atau nama supir ekspedisi.</li>
        <li>Bukti surat jalan yang telah diunggah dapat dilihat langsung atau dihapus kembali selama belum diverifikasi oleh tim gudang.</li>
    </ul>

    <div class="page-break"></div>

    <h1 class="section">9. Tab 2 — Aksesoris &amp; Tambah Aksesoris Dinamis</h1>
    <p>
        Selain bahan kain, vendor juga menerima aksesoris garmen (kancing, resleting, label satin, hangtag, polybag, dsb).
    </p>

    <h2 class="sub">9.1 Input Retur Aksesoris Terdaftar</h2>
    <p>
        Pada tabel <em>Accessory Reconciliation</em>, sistem menampilkan daftar aksesoris yang tercatat di Surat Perintah Kerja. Vendor cukup menginput jumlah sisa pada kolom <strong>Retur Qty</strong> beserta satuannya (misal: <code>PCS</code>, <code>GROSS</code>, <code>METER</code>).
    </p>

    <h2 class="sub">9.2 Fitur Baru: + Tambah Aksesoris (Add Accessory)</h2>
    <div class="tip">
        <strong>Kemudahan Penambahan Mandiri:</strong><br>
        Jika di lapangan terdapat aksesoris pengganti, aksesoris tambahan, atau barang titipan yang tidak tercantum dalam daftar awal SPK, vendor tidak perlu menghubungi IT untuk membukakan data.
    </div>
    <ol>
        <li>Pada bagian bawah tabel aksesoris di Tab 2, klik tombol <strong>+ Tambah aksesoris (+ Add accessory)</strong>.</li>
        <li>Baris baru akan muncul secara otomatis.</li>
        <li>Ketikkan deskripsi aksesoris (contoh: <em>"Kancing Cadangan Tambahan Putih"</em>).</li>
        <li>Isi jumlah yang dikembalikan pada kolom <em>Retur Qty</em> dan ketikkan satuan (misal: <code>PCS</code>).</li>
        <li>Klik tombol biru <strong>Simpan Rekonsiliasi Bahan</strong> untuk menyimpan seluruh data kain dan aksesoris secara permanen.</li>
    </ol>

    <h1 class="section">10. Tab 3 — Papan Produksi Harian (Daily Production)</h1>
    <p>
        Tab 3 menyediakan integrasi ke <strong>Papan Produksi Harian</strong> (<code>/subcon/vendor/daily-production</code>). Fitur ini membantu vendor memantau progres jahitan dan potongan setiap hari secara akurat.
    </p>

    <h2 class="sub">10.1 Lima Stasiun Alur Produksi Harian</h2>
    <table>
        <tr>
            <th style="width: 20%;">Stasiun Kerja</th>
            <th style="width: 80%;">Aktivitas yang Dicatat per Hari</th>
        </tr>
        <tr>
            <td><strong>1. Cutting</strong></td>
            <td>Jumlah lembar kain yang selesai dipotong per ukuran untuk tanggal tersebut.</td>
        </tr>
        <tr>
            <td><strong>2. Sewing</strong></td>
            <td>Jumlah baju yang selesai dirakit/dijahit pada lini jahit harian.</td>
        </tr>
        <tr>
            <td><strong>3. Finishing</strong></td>
            <td>Jumlah pakaian yang selesai diproses gosok/setrika uap dan pasang kancing.</td>
        </tr>
        <tr>
            <td><strong>4. QC</strong></td>
            <td>Jumlah pakaian yang telah lolos uji kualitas dan inspeksi cacat jahit.</td>
        </tr>
        <tr>
            <td><strong>5. Packing</strong></td>
            <td>Jumlah pakaian yang telah selesai dibungkus polybag dan siap dimasukkan ke kemasan akhir.</td>
        </tr>
    </table>

    <h2 class="sub">10.2 Catatan Harian Terisolasi per Tanggal (Date-Isolated Notes)</h2>
    <p>
        Setiap tanggal kerja memiliki kolom <strong>Catatan Harian</strong> mandiri. Catatan yang Anda buat pada tanggal 23 September (misal: <em>"Mesin obras line 2 gangguan 2 jam, target dialihkan ke besok"</em>) akan tersimpan khusus untuk tanggal tersebut dan tidak akan menimpa catatan pada tanggal lainnya.
    </p>

    <h2 class="sub">10.3 Penutupan Style (Close / Reopen Style)</h2>
    <p>
        Ketika suatu style pakaian telah tuntas dikerjakan seluruhnya di pabrik, vendor dapat mengklik tombol <strong>Tandai Selesai (Close Style)</strong> agar papan kerja harian tetap ringkas dan hanya menampilkan model yang sedang aktif berproduksi. Style yang ditutup dapat dibuka kembali kapan saja lewat tombol <strong>Reopen</strong>.
    </p>

    <div class="page-break"></div>

    <h1 class="section">11. Form Catatan Terintegrasi &amp; Ikon Panduan Informasi ('i')</h1>

    <h2 class="sub">11.1 Penempatan Form Catatan ("Sebelum Aksi Terakhir")</h2>
    <p>
        Untuk menjaga keterbacaan data, formulir <strong>Catatan (Remarks)</strong> diletakkan secara teratur tepat <em>sebelum tombol tindakan akhir</em>:
    </p>
    <ul>
        <li>Pada <strong>Tahap Cutting</strong>: Muncul tepat sebelum kartu tombol Download, Upload, dan Kirim Laporan Cutting.</li>
        <li>Pada <strong>Tahap Gramasi</strong>: Muncul tepat sebelum tombol Kirim Gramasi &amp; Blister.</li>
        <li>Pada <strong>Tahap Label</strong>: Muncul tepat sebelum tombol Selesaikan Work Order.</li>
        <li>Pada <strong>Tab Rekonsiliasi</strong>: Terletak di antara tabel rekonsiliasi dan daftar lampiran surat jalan.</li>
    </ul>
    <p>
        Catatan yang Anda simpan dapat dibaca langsung oleh tim admin Mega Perintis tanpa mengganggu alur persetujuan.
    </p>

    <h2 class="sub">11.2 Ikon Panduan Informasi ('i' / Guidance)</h2>
    <p>
        Di setiap tahapan kerja dan tab formulir, terdapat kotak panduan dengan ikon informasi <span class="badge-stage">i</span>. Mengarahkan kursor atau mengklik tombol <strong>Panduan (Guidance)</strong> akan memunculkan penjelasan singkat mengenai apa saja data yang perlu diisi pada tahap tersebut, sehingga operator baru dapat langsung bekerja tanpa kebingungan.
    </p>

    <h1 class="section">12. Pengaturan Profil &amp; Pusat Bantuan</h1>

    <h2 class="sub">12.1 Mengubah Data Profil &amp; Password</h2>
    <p>
        Melalui menu <strong>Profile</strong> di pojok kanan atas, vendor dapat:
    </p>
    <ul>
        <li>Memperbarui nama penanggung jawab dan nomor telepon vendor.</li>
        <li>Mengganti kata sandi akun secara berkala demi menjaga keamanan data perusahaan.</li>
    </ul>

    <h2 class="sub">12.2 Pusat Bantuan &amp; Kontak Admin Mega Perintis</h2>
    <p>
        Apabila Anda menemui kendala teknis dalam menggunakan portal, data work order tidak muncul, atau membutuhkan konfirmasi status persetujuan, silakan hubungi tim Subcontractor Department PT Mega Perintis Tbk:
    </p>
    <table>
        <tr>
            <th style="width: 30%;">Kebutuhan</th>
            <th style="width: 70%;">Saluran Komunikasi</th>
        </tr>
        <tr>
            <td><strong>Persetujuan Cutting &amp; Konsumsi</strong></td>
            <td>Admin Cutting &amp; Konsumsi Bahan Mega Perintis</td>
        </tr>
        <tr>
            <td><strong>Persetujuan Gramasi &amp; Label PI</strong></td>
            <td>Admin Distribusi &amp; MD Production Mega Perintis</td>
        </tr>
        <tr>
            <td><strong>Verifikasi Surat Jalan Retur</strong></td>
            <td>Tim Gudang Bahan Baku (Warehouse Material) Mega Perintis</td>
        </tr>
        <tr>
            <td><strong>Kendala Akun &amp; Sistem Portal</strong></td>
            <td>Helpdesk IT PT Mega Perintis Tbk (Sertakan nomor SPK &amp; PRG)</td>
        </tr>
    </table>

    <div class="footer-note">
        <strong>PT Mega Perintis Tbk &middot; Subcontractor Management System</strong><br>
        Buku Panduan Pengguna Vendor CMT &middot; Versi 2.0 &middot; Dokumen ini dihasilkan secara otomatis dari sistem portal per {{ now()->translatedFormat('d F Y') }}.
    </div>

</body>

</html>
