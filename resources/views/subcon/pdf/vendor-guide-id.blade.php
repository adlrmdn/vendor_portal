<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Portal Vendor Subkontraktor — Panduan Pengguna</title>
    <style>
        @page {
            margin: 40px 35px;
        }

        body {
            font-family: Arial, sans-serif;
            color: #2b2b2b;
            font-size: 13px;
            line-height: 1.5;
        }

        .cover {
            text-align: center;
            padding-top: 160px;
        }

        .cover h1 {
            font-size: 30px;
            margin-bottom: 6px;
            color: #1a1a1a;
        }

        .cover h2 {
            font-size: 15px;
            font-weight: normal;
            color: #666;
            margin-top: 0;
        }

        .cover .meta {
            margin-top: 40px;
            font-size: 12px;
            color: #888;
        }

        .page-break {
            page-break-before: always;
        }

        h1.section {
            font-size: 19px;
            color: #1a1a1a;
            border-bottom: 2px solid #333;
            padding-bottom: 6px;
            margin-top: 0;
            margin-bottom: 14px;
        }

        h2.sub {
            font-size: 15px;
            color: #222;
            margin-top: 22px;
            margin-bottom: 6px;
        }

        p {
            margin: 6px 0;
        }

        ol, ul {
            margin: 6px 0 10px 0;
            padding-left: 20px;
        }

        li {
            margin-bottom: 4px;
        }

        .note {
            background: #f4f6f8;
            border-left: 4px solid #6c8ebf;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 12px;
        }

        .warn {
            background: #fdf3ec;
            border-left: 4px solid #d98c3d;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 14px 0;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 5px 7px;
            text-align: left;
        }

        th {
            background: #efefef;
        }

        code {
            background: #f0f0f0;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 11.5px;
        }

        .toc ol {
            list-style: none;
            padding-left: 0;
        }

        .toc li {
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 11px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }

        a {
            color: #1a56c4;
            text-decoration: none;
        }

        .links-table td:first-child {
            font-weight: bold;
            width: 32%;
        }

        .links-table td:last-child {
            word-break: break-all;
        }

        .stage-badge {
            display: inline-block;
            background: #e7f5ff;
            color: #1c7ed6;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 4px;
        }

        .screenshot {
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin: 10px 0 4px 0;
        }

        .caption {
            font-size: 11px;
            color: #888;
            text-align: center;
            margin-bottom: 14px;
        }
    </style>
</head>

<body>

    <div class="cover">
        <h1>Portal Vendor Subkontraktor (CMT)</h1>
        <h2>Panduan Pengguna untuk Vendor</h2>
        <div class="meta">
            Versi 1.0 &middot; {{ now()->format('F Y') }}
        </div>
    </div>

    <div class="page-break"></div>

    <h1 class="section">Daftar Isi</h1>
    <div class="toc">
        <ol>
            <li>1. Memulai &amp; Masuk (Login)</li>
            <li>2. Ringkasan Dashboard</li>
            <li>3. Alur Kerja Work Order (6 Tahap)</li>
            <li>4. Melihat Daftar Work Order</li>
            <li>5. Membuka Work Order</li>
            <li>6. Tahap 1 — Mengisi &amp; Mengirim Laporan Cutting</li>
            <li>7. Menunggu Persetujuan Cutting &amp; Laporan Parsial</li>
            <li>8. Tahap 2 — Mengisi Gramasi &amp; Kapasitas Blister</li>
            <li>9. Menunggu Persetujuan Gramasi &amp; Distribusi</li>
            <li>10. Mencetak Label &amp; Menyelesaikan Work Order</li>
            <li>11. Catatan (Remarks) &amp; Profil Akun</li>
            <li>12. Mendapatkan Bantuan</li>
        </ol>
    </div>

    <div class="page-break"></div>

    <h1 class="section">1. Memulai &amp; Masuk (Login)</h1>
    <p>
        Portal Vendor Subkontraktor adalah aplikasi web yang digunakan perusahaan Anda untuk
        melaporkan hasil cutting, gramasi, dan mencetak label kemasan (packing label) untuk setiap
        work order CMT (Cut-Make-Trim) yang diterima dari Mega Perintis.
    </p>
    <ol>
        <li>Buka portal di <a href="https://vendor-portal.megaperintis.co.id">https://vendor-portal.megaperintis.co.id</a>.</li>
        <li>Masuk menggunakan alamat email dan kata sandi yang diberikan kepada Anda. Jika belum
            menerima akun, atau lupa kata sandi, hubungi admin subkontraktor Anda (lihat Bagian 12).</li>
        <li>Setelah berhasil masuk, Anda akan melihat halaman <strong>Dashboard</strong>.</li>
    </ol>
    <div class="note">
        Akun Anda terhubung dengan profil vendor perusahaan Anda. Anda hanya akan melihat work
        order milik perusahaan Anda sendiri.
    </div>

    <h2 class="sub">1.1 Tautan Cepat</h2>
    <table class="links-table">
        <tr><td>Login Portal</td><td><a href="https://vendor-portal.megaperintis.co.id/login">https://vendor-portal.megaperintis.co.id/login</a></td></tr>
        <tr><td>Dashboard</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/vendor/dashboard">https://vendor-portal.megaperintis.co.id/subcon/vendor/dashboard</a></td></tr>
        <tr><td>Daftar Work Order</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/vendor/orders">https://vendor-portal.megaperintis.co.id/subcon/vendor/orders</a></td></tr>
        <tr><td>Profil Akun</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/vendor/profile">https://vendor-portal.megaperintis.co.id/subcon/vendor/profile</a></td></tr>
    </table>

    <img src="{{ public_path('images/subcon-guide/login.jpg') }}" class="screenshot">
    <div class="caption">Halaman login portal.</div>

    <h1 class="section">2. Ringkasan Dashboard</h1>
    <p>Dashboard menampilkan ringkasan pekerjaan Anda:</p>
    <ul>
        <li><strong>Total Work Orders</strong> — jumlah seluruh work order yang pernah diterima.</li>
        <li><strong>Active Orders</strong> — work order yang masih berjalan (belum selesai/dibatalkan).</li>
        <li><strong>Completed Orders</strong> — work order yang sudah selesai sepenuhnya.</li>
    </ul>
    <p>
        Di bawah kartu ringkasan terdapat kartu <strong>6-Step Production Workflow</strong> yang
        mengingatkan tahapan proses (lihat Bagian 3), serta tabel <strong>Recent Work Orders</strong>
        yang berisi work order terbaru beserta tahap alur kerja dan status masing-masing, dengan
        tombol <strong>View</strong> untuk langsung membuka detailnya.
    </p>

    <img src="{{ public_path('images/subcon-guide/vendor-dashboard.jpg') }}" class="screenshot">
    <div class="caption">Dashboard vendor — ringkasan, 6 tahap alur kerja, dan work order terbaru.</div>

    <h1 class="section">3. Alur Kerja Work Order (6 Tahap)</h1>
    <p>
        Setiap work order berjalan melalui alur kerja bertahap berikut. Anda hanya dapat mengisi
        data pada tahap yang sedang aktif — tahap lain terkunci sampai gilirannya tiba.
    </p>
    <table>
        <tr><th>#</th><th>Tahap</th><th>Deskripsi</th></tr>
        <tr><td>1</td><td>Cutting Report</td><td>Vendor mengisi &amp; mengirim jumlah hasil cutting per ukuran.</td></tr>
        <tr><td>2</td><td>Cutting Approval</td><td>Menunggu admin memeriksa &amp; menyetujui.</td></tr>
        <tr><td>3</td><td>Gramasi &amp; Blister</td><td>Vendor mengisi gramasi (berat) per ukuran &amp; kapasitas blister.</td></tr>
        <tr><td>4</td><td>Gramasi Approval</td><td>Menunggu admin memeriksa &amp; menyetujui.</td></tr>
        <tr><td>5</td><td>Waiting Distribution</td><td>Menunggu label kemasan dibuatkan oleh admin.</td></tr>
        <tr><td>6</td><td>Print &amp; Complete</td><td>Vendor mencetak label &amp; menyelesaikan work order.</td></tr>
    </table>
    <div class="note">
        Progres tahap ini juga terlihat sebagai deretan badge di bagian atas halaman detail work
        order — badge hijau berarti tahap sudah selesai, badge biru berarti tahap yang sedang aktif.
    </div>

    <h1 class="section">4. Melihat Daftar Work Order</h1>
    <p>
        Buka menu <strong>Work Orders</strong> untuk melihat seluruh work order milik perusahaan
        Anda, lengkap dengan nomor order, production group, nama style, tanggal jatuh tempo, tahap
        alur kerja, dan status.
    </p>

    <img src="{{ public_path('images/subcon-guide/vendor-orders.jpg') }}" class="screenshot">
    <div class="caption">Daftar work order milik vendor.</div>

    <h1 class="section">5. Membuka Work Order</h1>
    <p>Klik <strong>View</strong> pada salah satu work order untuk membuka detailnya. Halaman detail
        menampilkan:</p>
    <ul>
        <li>Nama style, warna, kategori/sub-kategori, season, dan department (dari data produksi PLM).</li>
        <li>Nomor order, production group (PRG), tanggal order &amp; jatuh tempo, total qty order.</li>
        <li>Deretan badge tahap alur kerja (Bagian 3) — menunjukkan tahap yang sudah selesai dan yang
            sedang berjalan.</li>
        <li>Rincian per ukuran (size) dan tabel rekonsiliasi kain, sesuai tahap yang sedang aktif.</li>
    </ul>

    <div class="page-break"></div>

    <h1 class="section">6. Tahap 1 — Mengisi &amp; Mengirim Laporan Cutting</h1>
    <p>
        Saat work order berada di tahap <strong>Cutting</strong>, formulir cutting report dapat
        diisi. Isi data berikut:
    </p>

    <img src="{{ public_path('images/subcon-guide/vendor-order-cutting2.jpg') }}" class="screenshot">
    <div class="caption">Halaman work order pada tahap Cutting Report — tabel per ukuran, rekonsiliasi kain, dan tombol submit.</div>

    <h2 class="sub">6.1 Jumlah Cutting per Ukuran</h2>
    <p>Isi kolom jumlah hasil cutting (Qty Cut) untuk setiap ukuran (size) yang tercantum.</p>

    <h2 class="sub">6.2 Rekonsiliasi Kain (Fabric Reconciliation)</h2>
    <p>Untuk setiap jenis kain yang terhubung ke style ini, isi 4 kolom sisa/limbah kain berikut
        (dalam satuan meter/satuan asli kain):</p>
    <table>
        <tr><th>Kolom</th><th>Keterangan</th></tr>
        <tr><td>Short Roll</td><td>Sisa kain akibat roll pendek/tidak penuh.</td></tr>
        <tr><td>Sisa Kain (Utuh)</td><td>Sisa gulungan kain yang masih utuh.</td></tr>
        <tr><td>Kepala Kain</td><td>Bagian ujung/kepala kain yang tidak terpakai.</td></tr>
        <tr><td>Retur Kain</td><td><strong>Terkunci</strong> — dihitung otomatis = Short Roll + Sisa Kain + Kepala Kain.</td></tr>
    </table>
    <div class="note">
        Retur Kain otomatis terhitung dan tidak bisa diedit langsung oleh vendor — nilai ini masih
        bisa diubah oleh admin saat proses persetujuan jika diperlukan. Jika ada jenis kain yang
        Anda pakai namun tidak muncul di daftar, klik <strong>Add fabric</strong> untuk
        menambahkannya secara manual.
    </div>

    <h2 class="sub">6.3 Kapasitas Blister &amp; Karung</h2>
    <p>
        Kolom ini terlihat namun masih <strong>terkunci</strong> pada tahap cutting — akan aktif
        untuk diisi pada tahap Gramasi (Bagian 8).
    </p>

    <h2 class="sub">6.4 Menandai Laporan Parsial (Partial Report)</h2>
    <p>
        Jika Anda belum bisa melaporkan seluruh jumlah cutting sekaligus (misal karena proses
        cutting masih berjalan bertahap), aktifkan toggle <strong>Partial report</strong> sebelum
        mengirim. Konsekuensinya:
    </p>
    <ul>
        <li>Status "Partial" akan ditampilkan kepada admin/approver pada email dan panel persetujuan.</li>
        <li>Setelah disetujui, work order akan <strong>kembali ke tahap Cutting</strong> (bukan lanjut
            ke Gramasi), sehingga Anda bisa melanjutkan mengisi sisa jumlah cutting yang belum
            dilaporkan.</li>
        <li>Saat laporan berikutnya sudah mencakup seluruh jumlah (laporan final), <strong>matikan
            toggle Partial report</strong> agar work order lanjut ke tahap Gramasi.</li>
    </ul>

    <h2 class="sub">6.5 Mengisi Data Lewat Excel/PDF (opsional)</h2>
    <p>Selain mengisi manual di tabel, Anda dapat:</p>
    <ol>
        <li>Klik <strong>Download Template</strong> untuk mengunduh template Excel sesuai style ini.</li>
        <li>Isi kolom jumlah cutting per ukuran pada template tersebut.</li>
        <li>Unggah kembali file tersebut lewat tombol <strong>Upload Qty Cut</strong> (mendukung
            format .xlsx, .xls, .csv, dan .pdf).</li>
    </ol>
    <div class="warn">
        Data hasil unggahan akan mengisi tabel di layar — periksa kembali sebelum mengirim. Untuk
        PDF, portal hanya dapat membaca PDF berbasis teks (bukan hasil scan gambar).
    </div>

    <h2 class="sub">6.6 Mengirim Laporan Cutting</h2>
    <p>
        Setelah semua data terisi, klik <strong>Submit Cutting Report</strong>. Anda akan diminta
        konfirmasi (pesan konfirmasi berbeda tergantung status toggle Partial report). Setelah
        dikirim, seluruh data terkunci sampai admin membuat keputusan.
    </p>

    <h1 class="section">7. Menunggu Persetujuan Cutting &amp; Laporan Parsial</h1>
    <p>
        Selama menunggu keputusan admin, work order berada di tahap <strong>Cutting Approval</strong>
        dan sebuah banner kuning akan muncul di halaman detail: <em>"Your cutting report has been
        submitted and is awaiting admin approval."</em>
    </p>
    <ul>
        <li>Jika <strong>disetujui</strong> dan bukan laporan parsial, work order lanjut ke tahap
            Gramasi (Bagian 8).</li>
        <li>Jika <strong>disetujui</strong> namun berstatus parsial, work order kembali ke tahap
            Cutting — sebuah banner biru akan mengingatkan Anda untuk melanjutkan mengisi sisa
            jumlah dan mematikan toggle Partial report pada laporan final.</li>
        <li>Jika <strong>ditolak</strong>, laporan dikembalikan untuk diperbaiki dan diisi ulang.</li>
    </ul>

    <div class="page-break"></div>

    <h1 class="section">8. Tahap 2 — Mengisi Gramasi &amp; Kapasitas Blister</h1>
    <p>
        Setelah cutting report disetujui secara final (bukan parsial), formulir Gramasi terbuka.
        Sebuah banner hijau akan menandai: <em>"Cutting report approved. Enter gramasi (g) per size
        and the blister capacity, then submit for approval."</em>
    </p>

    <img src="{{ public_path('images/subcon-guide/vendor-order-gramasi.jpg') }}" class="screenshot">
    <div class="caption">Halaman work order pada tahap Gramasi &amp; Blister.</div>

    <h2 class="sub">8.1 Gramasi per Ukuran</h2>
    <p>Isi berat gramasi (dalam gram) untuk setiap ukuran (size) yang tercantum.</p>

    <h2 class="sub">8.2 Kapasitas Blister &amp; Karung</h2>
    <table>
        <tr><th>Kolom</th><th>Keterangan</th></tr>
        <tr><td>Blister Capacity</td><td><strong>Wajib diisi.</strong> Jumlah pieces per blister — berlaku untuk semua ukuran pada work order ini.</td></tr>
        <tr><td>Sack (Karung) Capacity</td><td>Opsional, default 50 pieces per karung. Hanya berlaku untuk gudang WH Replenish &amp; WH Online yang mengemas dalam karung, bukan blister.</td></tr>
    </table>
    <div class="note">
        Kapasitas blister/karung ini digunakan untuk menghitung jumlah blister/karung per box pada
        label kemasan nantinya (Bagian 10).
    </div>

    <h2 class="sub">8.3 Mengirim Gramasi &amp; Blister</h2>
    <p>
        Data rekonsiliasi kain pada tahap ini bersifat <strong>hanya-lihat</strong> (sudah dikunci
        sejak tahap cutting). Setelah gramasi dan kapasitas blister terisi, klik
        <strong>Submit Gramasi &amp; Blister</strong> untuk mengirim ke admin untuk disetujui.
    </p>

    <h1 class="section">9. Menunggu Persetujuan Gramasi &amp; Distribusi</h1>
    <p>Setelah gramasi dikirim, work order berada di tahap <strong>Gramasi Approval</strong> —
        banner kuning: <em>"Your gramasi & blister capacity have been submitted and are awaiting
        admin approval."</em></p>
    <p>
        Setelah disetujui admin, work order pindah ke tahap <strong>Waiting Distribution</strong> —
        banner biru akan muncul: <em>"Your gramasi & blister capacity have been approved. Awaiting
        packing label generation. Please check your email for the 'Generate Packing Labels' link
        to unlock printing."</em>
    </p>
    <div class="note">
        Pada tahap ini Anda tidak perlu melakukan apa pun selain menunggu — admin/RPA yang akan
        membuatkan Packing Instruction (PI) dari D365 dan mengaktifkan tombol cetak label. Anda
        akan menerima email berisi tautan saat label siap dicetak.
    </div>

    <img src="{{ public_path('images/subcon-guide/vendor-order-waiting.jpg') }}" class="screenshot">
    <div class="caption">Work order pada tahap Waiting Distribution — menunggu label kemasan dibuatkan admin.</div>

    <div class="page-break"></div>

    <h1 class="section">10. Mencetak Label &amp; Menyelesaikan Work Order</h1>
    <p>
        Setelah label kemasan siap (tombol cetak tidak lagi terkunci), dua jenis label tersedia di
        halaman detail work order:
    </p>
    <ul>
        <li><strong>Print Store Labels</strong> — label untuk pengiriman ke toko/store.</li>
        <li><strong>Print WH Labels</strong> — label untuk pengiriman ke gudang (warehouse), termasuk
            gudang berbasis karung.</li>
    </ul>
    <p>
        Kedua tombol membuka file PDF label yang siap dicetak, berisi jumlah blister/karung per box
        berdasarkan gramasi dan kapasitas yang telah Anda isi sebelumnya.
    </p>

    <img src="{{ public_path('images/subcon-guide/vendor-order-labels.jpg') }}" class="screenshot">
    <div class="caption">Work order dengan label siap dicetak &amp; tombol Complete PO.</div>

    <h2 class="sub">10.1 Menyelesaikan Work Order</h2>
    <p>
        Setelah label dicetak dan proses pengemasan selesai, klik <strong>Complete PO</strong> untuk
        menandai work order sebagai selesai. Sebuah konfirmasi akan muncul sebelum work order
        berpindah ke status <em>Completed</em>.
    </p>

    <img src="{{ public_path('images/subcon-guide/vendor-order-completed.jpg') }}" class="screenshot">
    <div class="caption">Work order yang sudah berstatus Completed.</div>

    <h1 class="section">11. Catatan (Remarks) &amp; Profil Akun</h1>
    <h2 class="sub">11.1 Remarks</h2>
    <p>
        Pada halaman detail work order tersedia kolom <strong>Remarks</strong> — catatan bebas yang
        dapat diisi kapan saja (tidak perlu persetujuan) dan akan terlihat oleh admin/approver. Klik
        <strong>Save Remarks</strong> untuk menyimpan.
    </p>
    <h2 class="sub">11.2 Profil Akun</h2>
    <p>
        Buka menu <strong>Profile</strong> untuk memperbarui data akun Anda atau mengganti kata
        sandi.
    </p>

    <img src="{{ public_path('images/subcon-guide/vendor-profile.jpg') }}" class="screenshot">
    <div class="caption">Halaman Profile Settings vendor.</div>

    <h1 class="section">12. Mendapatkan Bantuan</h1>
    <p>
        Jika mengalami kendala, tidak menemukan work order, atau memerlukan perubahan akses,
        hubungi admin subkontraktor Anda di Mega Perintis. Sertakan nomor order (Order #) dan
        production group (PRG) agar kendala dapat ditelusuri dengan cepat.
    </p>

    <div class="footer-note">
        Panduan ini mengacu pada Portal Vendor Subkontraktor per {{ now()->format('F Y') }}.
        Tampilan dan opsi dapat berubah seiring waktu.
    </div>

</body>

</html>
