# Pemulihan database lokal saat persiapan E2E

Pada 18 Oktober 2026 pukul 19:58:04 WIB, perintah migrasi awal dari agent mengenai `marwis_sweet`, bukan schema E2E. Override environment bertitik tersedia melalui `getenv()`, tetapi tidak terisi ke `$_ENV`; nilai `.env` kemudian dipakai CodeIgniter. Pemeriksaan target sebelum reset belum tersedia pada percobaan awal tersebut. Ini kesalahan pelaksanaan pengujian oleh agent.

Pengujian dihentikan setelah masalah ditemukan. Pemulihan menggunakan cadangan `writable/backups/pre-clean-20261013-203138.sql` dan binary log MySQL sampai tepat sebelum transaksi migrasi pukul 19:58:04. Riwayat perubahan direplay terlebih dahulu pada instance MySQL sementara di port 3307. Rekonstruksi selesai tanpa melewati error SQL; database lokal kemudian dipulihkan dari hasil tersebut.

Hasil pemulihan:

| Pemeriksaan | Hasil |
| --- | --- |
| Tabel | 41 |
| Penjualan | 156 |
| Item penjualan | 326 |
| Jurnal | 159 |
| Isi tabel | Jumlah dan SHA-256 seluruh baris tiap tabel cocok dengan rekonstruksi sebelum kejadian |
| Jurnal posted | Seimbang pada hasil rekonstruksi |
| Pemeriksaan setelah pengujian | Fingerprint seluruh 41 tabel masih cocok; tidak ada perbedaan |
| Instance sementara | Dihentikan setelah verifikasi |

Cadangan sebelum dan sesudah pemulihan, binary log terpilih, log import, serta fingerprint disimpan secara lokal di `writable/e2e/recovery/` yang diabaikan Git. Berkas tersebut memuat data usaha dan tidak disertakan dalam laporan yang dilacak Git.

Runner akhir menambahkan bootstrap override khusus E2E, guard schema pada bootstrap, serta pemeriksaan CLI dan HTTP sebelum migrasi. Pemeriksaan menolak reset jika schema, base URL, atau konfigurasi CSRF tidak sesuai. Server dan semua test berikutnya menggunakan `marwis_sweet_e2e`.
