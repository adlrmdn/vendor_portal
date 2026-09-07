<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Portal Admin Subkontraktor — Panduan Pengguna</title>
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

        .formula {
            background: #fbf7ee;
            border: 1px solid #eadfc4;
            border-radius: 4px;
            padding: 8px 12px;
            margin: 8px 0;
            font-size: 12px;
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
        <h1>Portal Admin Subkontraktor (CMT)</h1>
        <h2>Panduan Pengguna untuk Admin</h2>
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
            <li>3. Sinkronisasi Order dari D365</li>
            <li>4. Manajemen Vendor</li>
            <li>5. Melihat Daftar &amp; Detail Work Order</li>
            <li>6. Menyetujui Cutting Report (Kalkulasi Konsumsi Kain)</li>
            <li>7. Menyetujui Gramasi &amp; Kapasitas Blister</li>
            <li>8. Persetujuan via Email (Tautan Tanpa Login)</li>
            <li>9. Waiting Distribution &amp; Pembuatan Label</li>
            <li>10. Mencetak Label, Kapasitas &amp; Status Order</li>
            <li>11. Pengaturan Alur Kerja (Workflow Settings)</li>
            <li>12. Log &amp; Riwayat Persetujuan</li>
            <li>13. Mendapatkan Bantuan</li>
        </ol>
    </div>

    <div class="page-break"></div>

    <h1 class="section">1. Memulai &amp; Masuk (Login)</h1>
    <p>
        Portal Admin Subkontraktor digunakan tim internal Mega Perintis untuk mengawasi seluruh
        work order CMT (Cut-Make-Trim) milik vendor subkontraktor: menyinkronkan order dari D365,
        menyetujui laporan cutting &amp; gramasi, serta mengelola label kemasan dan distribusi.
    </p>
    <ol>
        <li>Buka <a href="https://vendor-portal.megaperintis.co.id/login">https://vendor-portal.megaperintis.co.id/login</a>
            dan masuk dengan akun admin subkontraktor Anda.</li>
        <li>Setelah masuk, Anda akan melihat halaman <strong>Dashboard</strong>.</li>
    </ol>

    <h2 class="sub">1.1 Tautan Cepat</h2>
    <table class="links-table">
        <tr><td>Dashboard</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/admin/dashboard">https://vendor-portal.megaperintis.co.id/subcon/admin/dashboard</a></td></tr>
        <tr><td>Daftar Work Order</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/admin/orders">https://vendor-portal.megaperintis.co.id/subcon/admin/orders</a></td></tr>
        <tr><td>Approvals</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/admin/approvals">https://vendor-portal.megaperintis.co.id/subcon/admin/approvals</a></td></tr>
        <tr><td>Waiting Distribution</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/admin/orders-waiting-distribution">https://vendor-portal.megaperintis.co.id/subcon/admin/orders-waiting-distribution</a></td></tr>
        <tr><td>Vendors</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/admin/vendors">https://vendor-portal.megaperintis.co.id/subcon/admin/vendors</a></td></tr>
        <tr><td>Workflow Settings</td><td><a href="https://vendor-portal.megaperintis.co.id/subcon/admin/workflow">https://vendor-portal.megaperintis.co.id/subcon/admin/workflow</a></td></tr>
    </table>

    <img src="{{ public_path('images/subcon-guide/login.jpg') }}" class="screenshot">
    <div class="caption">Halaman login portal.</div>

    <h1 class="section">2. Ringkasan Dashboard</h1>
    <p>Dashboard menampilkan ringkasan lintas-vendor:</p>
    <ul>
        <li><strong>Pending Approvals</strong> — jumlah work order yang menunggu keputusan Anda
            (kartu ini berkedip merah bila ada yang menunggu). Klik untuk langsung membuka halaman
            Approvals.</li>
        <li><strong>Active Orders</strong> — work order yang masih berjalan.</li>
        <li><strong>Completed Orders</strong> — work order yang sudah selesai.</li>
        <li><strong>Active Vendors</strong> — jumlah vendor subkontraktor yang berstatus aktif.</li>
    </ul>
    <p>
        Tombol <strong>Sync POs</strong> di header memicu sinkronisasi order dari D365 secara
        manual (lihat Bagian 3). Tabel <strong>Recent Work Orders</strong> menampilkan order
        terbaru dari seluruh vendor, lengkap dengan tahap alur kerja dan status.
    </p>

    <img src="{{ public_path('images/subcon-guide/admin-dashboard.jpg') }}" class="screenshot">
    <div class="caption">Dashboard admin — ringkasan lintas-vendor &amp; tombol Sync POs.</div>

    <h1 class="section">3. Sinkronisasi Order dari D365</h1>
    <p>
        Work order subkontraktor berasal dari Purchase Order (PO) di D365 yang disinkronkan ke
        portal. Sinkronisasi dapat dipicu dari dua tempat:
    </p>
    <ul>
        <li><strong>Dashboard</strong> — tombol <strong>Sync POs</strong>: memicu sinkronisasi untuk
            seluruh vendor, dengan status berjalan (progress) yang di-poll otomatis di layar.</li>
        <li><strong>Command line</strong> (oleh tim teknis) — perintah
            <code>php artisan d365:sync-subcon-orders --company=mpg</code> untuk sinkronisasi
            terjadwal/manual dari server.</li>
    </ul>
    <div class="note">
        Nomor PO D365 dipakai apa adanya sebagai nomor work order di portal — tidak ada penomoran
        ulang.
    </div>

    <h1 class="section">4. Manajemen Vendor</h1>
    <p>Buka menu <strong>Vendors</strong> untuk:</p>
    <ul>
        <li>Menambahkan vendor subkontraktor baru beserta akun login vendornya.</li>
        <li>Mengubah data vendor yang sudah ada.</li>
        <li>Mengaktifkan/menonaktifkan (toggle status) sebuah vendor.</li>
        <li>Menghapus vendor yang tidak lagi digunakan.</li>
    </ul>
    <div class="warn">
        Menonaktifkan vendor akan menghentikan akses login vendor tersebut ke portal — pastikan
        tidak ada work order aktif yang masih memerlukan input dari vendor tersebut.
    </div>

    <img src="{{ public_path('images/subcon-guide/admin-vendors.jpg') }}" class="screenshot">
    <div class="caption">Halaman Subcon Vendors.</div>

    <h1 class="section">5. Melihat Daftar &amp; Detail Work Order</h1>
    <p>
        Menu <strong>Work Orders</strong> menampilkan seluruh order dari semua vendor: nomor order,
        production group, nama vendor, style, tanggal jatuh tempo, tahap alur kerja, dan status.
        Klik <strong>View</strong> untuk membuka detail sebuah order, yang menampilkan:
    </p>

    <img src="{{ public_path('images/subcon-guide/admin-orders.jpg') }}" class="screenshot">
    <div class="caption">Daftar Work Orders lintas-vendor, dengan filter Vendor &amp; Workflow Stage.</div>

    <ul>
        <li>Daftar item order (nomor item, deskripsi, jumlah ukuran, qty, unit, status).</li>
        <li>Rincian per ukuran (dari data produksi PLM/VSM) dan hasil cutting/gramasi yang sudah
            dilaporkan vendor.</li>
        <li>Kartu <strong>Order Info</strong> berisi vendor, tanggal order/jatuh tempo, status,
            tahap alur kerja, serta input <strong>Blister Capacity</strong> dan
            <strong>Sack (Karung) Capacity</strong> yang bisa diubah admin kapan saja lewat tombol
            <strong>Save capacity</strong>.</li>
        <li>Catatan (<strong>Remarks</strong>) yang diisi vendor, bila ada.</li>
        <li>Kartu <strong>Approval Required</strong> ketika order sedang menunggu keputusan
            (Bagian 6 &amp; 7).</li>
        <li>Kartu <strong>Status</strong> untuk membatalkan (<strong>Cancel Order</strong>) atau
            mengaktifkan kembali (<strong>Reactivate Order</strong>) sebuah work order — satu-satunya
            perubahan status yang dilakukan manual; selebihnya mengikuti tahap alur kerja otomatis.</li>
    </ul>
    <p>
        Tombol <strong>Export Cutting Report</strong> di bagian atas mengunduh laporan cutting
        dalam format Excel.
    </p>

    <div class="page-break"></div>

    <p>
        Cara tercepat menemukan order yang menunggu keputusan adalah menu <strong>Approvals</strong>
        — daftar seluruh cutting report, laporan gramasi, dan final submission yang menunggu Anda:
    </p>

    <img src="{{ public_path('images/subcon-guide/admin-approvals.jpg') }}" class="screenshot">
    <div class="caption">Halaman Requested Approvals.</div>

    <h1 class="section">6. Menyetujui Cutting Report (Kalkulasi Konsumsi Kain)</h1>
    <p>
        Persetujuan cutting <strong>bukan sekadar klik setuju</strong> — admin juga memasukkan data
        konsumsi kain, dan portal akan menghitung otomatis nilai overconsumption serta potongan
        (deduction) yang harus dibayar vendor bila konsumsi kain melebihi rencana.
    </p>
    <p>Saat work order berada di tahap <strong>Cutting Approval</strong>, tabel <strong>Fabric
        Reconciliation &amp; Consumption</strong> menjadi bisa diedit. Untuk setiap jenis kain, isi:</p>
    <table>
        <tr><th>Kolom</th><th>Diisi oleh</th><th>Keterangan</th></tr>
        <tr><td>Short Roll / Sisa Kain (Utuh) / Kepala Kain / Retur Kain</td><td>Admin (opsional override)</td><td>Terisi otomatis dari laporan cutting vendor — admin dapat menimpa (override) nilainya di sini jika perlu.</td></tr>
        <tr><td>Fabric Sent</td><td><strong>Admin (wajib)</strong></td><td>Jumlah kain yang dikirim ke vendor untuk fabric ini.</td></tr>
        <tr><td>Cons. Plan</td><td><strong>Admin (wajib)</strong></td><td>Rencana konsumsi kain per pieces.</td></tr>
        <tr><td>Cutt Plan</td><td>Otomatis</td><td>Dihitung sistem — lihat rumus di bawah.</td></tr>
        <tr><td>Actual Cons.</td><td>Otomatis</td><td>Dihitung sistem.</td></tr>
        <tr><td>Overconsumption</td><td>Otomatis</td><td>Dihitung sistem, dalam persen.</td></tr>
        <tr><td>Fabric Price (IDR)</td><td>Admin (prefilled, bisa diedit)</td><td>Harga per satuan kain dalam Rupiah — otomatis terisi dari data PO, ubah manual bila sumbernya bukan IDR.</td></tr>
        <tr><td>Deduction</td><td>Otomatis</td><td>Potongan biaya (Rupiah) bila overconsumption melebihi toleransi 3%.</td></tr>
    </table>
    <div class="formula">
        <strong>Cutt Plan</strong> = ROUNDDOWN((Fabric Sent − Retur Kain) ÷ Cons. Plan)<br>
        <strong>Actual Cons.</strong> = (Fabric Sent − Retur Kain) ÷ Total Qty Cut<br>
        <strong>Overconsumption</strong> = (Actual Cons. − Cons. Plan) ÷ Cons. Plan<br>
        <strong>Deduction</strong> = MAX(0, Actual Cons. − Cons. Plan × 1.03) × Total Qty Cut × Fabric Price
        — <em>hanya dikenakan jika Overconsumption &gt; 3%</em>. Konsumsi di bawah rencana tidak pernah
        dikenakan potongan.
    </div>
    <div class="note">
        Hanya kolom <strong>Retur Kain</strong> yang mengurangi Actual Cons. — tiga kolom limbah
        lainnya (Short Roll/Sisa Kain/Kepala Kain) tetap dicatat namun tidak mengurangi hasil
        perhitungan konsumsi.
    </div>
    <p>
        Semua angka di atas terhitung otomatis secara langsung (live) di layar saat Anda mengetik.
        Setelah data lengkap, klik <strong>Approve</strong> pada kartu <strong>Approval
        Required</strong> — data konsumsi yang Anda masukkan akan tersimpan <strong>bersamaan</strong>
        dengan persetujuan (satu aksi, tidak ada langkah "simpan" terpisah).
    </p>
    <p>
        Jika laporan perlu diperbaiki vendor, klik <strong>Reject</strong> — order dikembalikan ke
        vendor untuk direvisi.
    </p>
    <div class="warn">
        Jika laporan cutting vendor ditandai <strong>Partial</strong> (badge merah "Partial" akan
        terlihat), menyetujui laporan ini akan mengembalikan order ke tahap Cutting (bukan lanjut ke
        Gramasi) agar vendor bisa melanjutkan mengisi sisa jumlah cutting.
    </div>

    <img src="{{ public_path('images/subcon-guide/admin-order-cutting-approval.jpg') }}" class="screenshot">
    <div class="caption">Halaman detail order pada tahap Cutting Approval — tabel Fabric Reconciliation &amp; Consumption dan kartu Approval Required.</div>

    <h1 class="section">7. Menyetujui Gramasi &amp; Kapasitas Blister</h1>
    <p>
        Saat work order berada di tahap <strong>Gramasi Approval</strong>, kartu <strong>Approval
        Required</strong> menampilkan gramasi dan kapasitas blister yang dikirim vendor. Berbeda
        dari cutting, persetujuan gramasi adalah <strong>satu klik</strong> tanpa perlu mengisi data
        tambahan:
    </p>
    <ul>
        <li>Klik <strong>Approve</strong> untuk menyetujui — order lanjut ke tahap
            <strong>Waiting Distribution</strong> dan pencetakan label akan terbuka setelah label
            kemasan dibuat.</li>
        <li>Klik <strong>Reject</strong> untuk mengembalikan ke vendor bila datanya perlu diperbaiki.</li>
    </ul>

    <h1 class="section">8. Persetujuan via Email (Tautan Tanpa Login)</h1>
    <p>
        Setiap kali vendor mengirim laporan cutting atau gramasi, email persetujuan otomatis
        terkirim ke approver yang terdaftar (lihat Bagian 11). Email ini berisi tautan
        <strong>Approve</strong> dan <strong>Decline</strong> yang bisa langsung diklik
        <strong>tanpa perlu login</strong> ke portal:
    </p>
    <ul>
        <li><strong>Gate Cutting</strong> — tautan Approve membuka formulir konsumsi kain yang sama
            seperti Bagian 6 (isi Fabric Sent &amp; Cons. Plan, lalu submit untuk menyetujui
            sekaligus).</li>
        <li><strong>Gate Gramasi</strong> — tautan Approve langsung menyetujui (satu klik, tanpa
            formulir tambahan), sama seperti panel dalam aplikasi.</li>
    </ul>
    <div class="note">
        Tautan ini bertanda tangan digital (signed URL) dan hanya berlaku untuk order &amp; gate
        yang bersangkutan — tidak bisa dipakai untuk order lain.
    </div>

    <div class="page-break"></div>

    <h1 class="section">9. Waiting Distribution &amp; Pembuatan Label</h1>
    <p>
        Menu <strong>Waiting Distribution</strong> menampilkan seluruh order yang sudah lolos
        persetujuan gramasi dan sedang menunggu label kemasan (Packing Instruction / PI) dibuatkan.
    </p>

    <img src="{{ public_path('images/subcon-guide/admin-waiting-distribution.jpg') }}" class="screenshot">
    <div class="caption">Halaman Manual Label Generation (Waiting Distribution) — termasuk contoh label yang gagal dibuat dengan tombol Retry Generation.</div>

    <p>
        Pada halaman detail order, tombol pembuatan label muncul setelah gramasi disetujui:
    </p>
    <ul>
        <li><strong>Generate Labels</strong> — memicu proses pembuatan Packing Instruction lewat
            layanan RPA/DTT (dapat memakan waktu hingga satu menit).</li>
        <li><strong>Recalculate Labels</strong> — muncul menggantikan tombol di atas setelah label
            berhasil dibuat; menghitung ulang jumlah Coli/Blister sesuai kapasitas blister/karung
            terbaru <strong>tanpa</strong> memicu ulang proses DTT.</li>
        <li><strong>Retry Generation</strong> — muncul (tombol kuning) bila proses pembuatan label
            sebelumnya gagal; pesan error ditampilkan di atas untuk membantu diagnosis.</li>
    </ul>
    <div class="note">
        Label hanya bisa dibuat/dihitung ulang setelah gramasi &amp; kapasitas blister disetujui.
        Selama proses berjalan, tombol menampilkan status "Generating…".
    </div>

    <h1 class="section">10. Mencetak Label, Kapasitas &amp; Status Order</h1>
    <h2 class="sub">10.1 Mencetak Label</h2>
    <p>Setelah label berhasil dibuat, dua tombol cetak tersedia di halaman detail order:</p>
    <ul>
        <li><strong>Print Store Labels</strong> — label untuk pengiriman ke toko/store.</li>
        <li><strong>Print WH Labels</strong> — label untuk pengiriman ke gudang (termasuk gudang
            berbasis karung/sack).</li>
    </ul>
    <h2 class="sub">10.2 Mengubah Kapasitas Blister/Karung</h2>
    <p>
        Admin dapat mengubah <strong>Blister Cap.</strong> dan <strong>Sack (Karung) Cap.</strong>
        kapan saja dari kartu Order Info, lalu klik <strong>Save capacity</strong>. Jika label sudah
        pernah dibuat, gunakan <strong>Recalculate Labels</strong> agar jumlah Coli/Blister pada
        label mengikuti kapasitas yang baru.
    </p>
    <h2 class="sub">10.3 Membatalkan / Mengaktifkan Kembali Order</h2>
    <p>
        Pada kartu <strong>Status</strong>, admin dapat <strong>Cancel Order</strong> untuk
        membatalkan work order, atau <strong>Reactivate Order</strong> untuk mengaktifkan kembali
        order yang sebelumnya dibatalkan. Ini adalah satu-satunya perubahan status yang dilakukan
        manual — status lainnya mengikuti tahap alur kerja secara otomatis.
    </p>

    <h1 class="section">11. Pengaturan Alur Kerja (Workflow Settings)</h1>
    <p>Menu <strong>Workflow</strong> mengatur konfigurasi email &amp; notifikasi subkontraktor,
        antara lain:</p>
    <ul>
        <li>Email approver untuk gate cutting dan gramasi (yang menerima email persetujuan).</li>
        <li>Email penerima notifikasi umum (fallback bila approver spesifik belum diatur).</li>
        <li>Email/nomor kontak untuk notifikasi tahap distribusi/label.</li>
    </ul>
    <div class="note">
        Bila belum diatur, email approval akan jatuh ke alamat default yang dipakai untuk keperluan
        pengujian — pastikan alamat sudah diperbarui ke penerima yang sesuai sebelum digunakan di
        produksi.
    </div>

    <img src="{{ public_path('images/subcon-guide/admin-workflow.jpg') }}" class="screenshot">
    <div class="caption">Halaman Workflow Mail Settings (nilai email/nomor pada contoh ini disamarkan).</div>

    <h1 class="section">12. Log &amp; Riwayat Persetujuan</h1>
    <p>Beberapa halaman tambahan membantu penelusuran &amp; audit:</p>
    <ul>
        <li><strong>Logs</strong> — log sinkronisasi D365 dan proses latar belakang lainnya.</li>
        <li><strong>Approval Logs</strong> — riwayat setiap keputusan persetujuan/penolakan yang
            pernah dibuat, per order.</li>
        <li><strong>Report Validations</strong> &amp; <strong>Director Approvals</strong> — halaman
            terkait alur validasi laporan QC/Director untuk proyek yang terhubung dengan QC Console.</li>
    </ul>

    <h1 class="section">13. Mendapatkan Bantuan</h1>
    <p>
        Jika sinkronisasi D365 gagal, email persetujuan tidak terkirim, atau ditemukan kejanggalan
        pada perhitungan konsumsi/deduction, hubungi tim teknis dengan menyertakan nomor order
        (Order #) dan production group (PRG) terkait agar dapat ditelusuri dengan cepat.
    </p>

    <div class="footer-note">
        Panduan ini mengacu pada Portal Admin Subkontraktor per {{ now()->format('F Y') }}.
        Tampilan dan opsi dapat berubah seiring waktu.
    </div>

</body>

</html>
