# RuangHadir — static preview

RuangHadir adalah prototipe antarmuka absensi berbasis HTML, CSS, dan JavaScript biasa. Situs ini tidak membutuhkan PHP, database, proses build, atau dependency.

## Publish ke GitHub Pages

1. Unggah isi folder ini ke root repository GitHub.
2. Buka **Settings → Pages**.
3. Pada **Build and deployment**, pilih **Deploy from a branch**, lalu pilih branch utama dan folder **/(root)**.
4. Simpan dan tunggu deployment selesai. Buka URL Pages repository; `index.html` adalah halaman awal.

Pastikan folder `assets/` dan semua file `.html` ikut diunggah agar styling dan navigasi tersedia. Situs dapat diuji lokal dengan membuka `index.html` di browser atau menjalankan Apache XAMPP; Apache tidak diperlukan untuk GitHub Pages.

## Batasan prototipe statis

- Halaman dashboard dan tabel menampilkan data contoh.
- Tidak ada login, akun, database, penyimpanan data bersama, proses absensi, pengajuan izin, atau perubahan data yang benar-benar tersimpan.
- Jangan masukkan kata sandi atau data pribadi sungguhan. GitHub Pages hanya menyajikan file statis; data yang dibutuhkan lintas pengguna/perangkat memerlukan backend/API terpisah.