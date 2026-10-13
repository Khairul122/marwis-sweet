# Rencana Perbaikan — Audit Marwis Sweet

## Tujuan

Menutup temuan dalam `AUDIT_REPORT_2026-10-14.md` dengan urutan yang mengurangi risiko akses data dan salah pembukuan lebih dulu, lalu melengkapi alur PRD, tampilan, dan kesiapan rilis. Implementasi berjalan bertahap; status per tugas dicatat di `tasks/todo.md`.

## Urutan fase

### Fase 1 — Akses dan integritas shift

Kerjakan role PDF, cakupan riwayat shift, penutupan shift atomik, dan review selisih sebelum mengubah jalur POS. Tujuannya memastikan data biaya tidak terlihat lintas role dan tidak ada transaksi yang lolos saat shift ditutup.

### Checkpoint 1

- [ ] Kasir tidak dapat membaca HPP/nilai stok atau data shift kasir lain.
- [ ] Tutup shift dan pembayaran terserialisasi; selisih memiliki status review owner.

### Fase 2 — Rekonsiliasi kas, stok, dan buku besar

Posting mutasi kas ke akun pasangan, koreksi penyajian prive, mencegah saldo awal ganda, lalu samakan biaya restock dan pembulatan dengan baris transaksi/jurnal.

### Checkpoint 2

- [ ] Nilai kas laci, kartu stok, HPP transaksi, dan jurnal dapat direkonsiliasi dengan skenario angka.
- [ ] Setiap retry atau posting sumber yang sama tidak membuat posting keuangan kedua.

### Fase 3 — Laporan dan transaksi POS

Perbaiki filter laporan terlebih dahulu, kemudian aturan topping, pembayaran, diskon/pajak/layanan, retry tautan ulasan, dan fitur keranjang/order yang diminta PRD.

### Checkpoint 3

- [ ] Total laporan sama dengan baris yang ditampilkan untuk setiap filter.
- [ ] Satu transaksi memiliki hitungan harga, pembayaran, stok, jurnal, dan tautan ulasan yang konsisten.

### Fase 4 — Kelengkapan fitur operasional

Tambahkan ketersediaan stok, akses dan persetujuan penerimaan kasir, saldo awal/koreksi/kerusakan stok, riwayat audit, cetak ulang struk, dan CRUD media.

### Fase 5 — UI dan kesiapan rilis

Perbaiki perilaku keyboard/fokus dan layout tablet/mobile, paginasi/ekspor laporan besar, konfigurasi host serta HTTP/HTTPS, data demo, prosedur backup/restore, panduan, dan UAT.

### Checkpoint akhir — Tahap 6 PRD

- [ ] Skenario AC yang terkait lulus di MySQL, termasuk dua kasir bersamaan dan kegagalan rollback.
- [ ] HTTP lokal/LAN dan HTTPS memakai URL yang benar; file upload dan PDF tetap berjalan.
- [ ] PDF multi-halaman dan ekspor dataset besar telah diukur.
- [ ] Backup dapat dipulihkan dan data penting direkonsiliasi.
- [ ] Tidak ada data contoh yang tampil sebagai fakta usaha pada katalog publik.

## Keputusan yang perlu ditetapkan sebelum implementasi terkait

- Akun lawan untuk kas masuk/keluar laci: tentukan apakah selalu transfer kas utama ↔ laci atau boleh ada sumber/tujuan lain.
- Batas nominal/persentase diskon dan apakah diskon kasir di atas batas membutuhkan persetujuan langsung owner.
- Jika pajak/biaya layanan diaktifkan, tetapkan tarif dan dasar hitung; default tetap nonaktif sesuai PRD.
- Pilihan penyimpanan token ulasan yang aman agar tautan dapat dicetak ulang tanpa menyimpan token dalam bentuk teks biasa.
- Review selisih shift: status pending/approved/rejected; jurnal penyesuaian hanya diposting setelah persetujuan owner.

## Risiko dan pengendalian

| Risiko | Dampak | Pengendalian |
|---|---|---|
| Data jurnal lama sudah salah sebelum perbaikan | Saldo historis tetap salah walau bug kode telah ditutup | Buat rekonsiliasi dan jurnal koreksi berjejak audit; jangan mengubah histori diam-diam |
| Migrasi schema memengaruhi transaksi aktif | Request POS atau laporan gagal saat rilis | Buat migrasi kompatibel, backup, dan jalankan pada salinan database sebelum produksi |
| Perubahan pembulatan mengubah hasil historis | Total lama dapat berbeda dari rumus baru | Berlakukan rumus baru untuk transaksi baru; pertahankan snapshot transaksi lama |
| Export tahunan terlalu berat untuk request web | Timeout atau penggunaan memori tinggi | Paginasi/streaming atau pekerjaan latar dengan unduhan privat |

## Daftar tugas

Checklist implementasi rinci dan kriteria penerimaan ada di `tasks/todo.md`.
