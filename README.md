# CAKRA - Central Academic Knowledge & Record Application

A modern, responsive, and secure Academic Management and Teaching Journal system built with PHP, MySQL, and Tailwind CSS.

---

## Panduan Migrasi & Pemindahan Server (Berpindah Server)

Jika Anda memindahkan aplikasi ini ke server baru di mana database belum terbuat, ikuti langkah-langkah sistematis berikut untuk mengaktifkan aplikasi sampai berjalan dengan sempurna.

### 1. Persyaratan Sistem (Prerequisites)
Pastikan server baru Anda memenuhi persyaratan minimum berikut:
* **PHP 8.1 atau lebih tinggi**
* **MySQL 5.7+ / MariaDB 10.3+**
* Web server Apache dengan modul **mod_rewrite** aktif (untuk mendukung URL ramah tanpa ekstensi `.php` melalui berkas `.htaccess`)
* Ekstensi PHP wajib diaktifkan:
  * **ZIP** (untuk Backup/Restore kompresi & import foto)
  * **cURL** (untuk integrasi Web Service Dapodik)
  * **GD / ImageMagick** (untuk pengolahan berkas foto)

---

### 2. Langkah-Langkah Instalasi & Deployment

#### **Langkah A: Menyalin Berkas Kode**
1. Unggah seluruh berkas dan folder aplikasi CAKRA ke direktori publik web server baru Anda (misalnya `public_html/` atau `htdocs/`).
2. Pastikan izin akses (permissions) folder `uploads/` diatur agar dapat ditulis oleh server (misalnya chmod `775` atau `777`).

#### **Langkah B: Membuat Database Baru**
1. Masuk ke panel manajemen database MySQL server baru Anda (seperti **phpMyAdmin** atau panel hosting).
2. Buat database baru dengan nama pilihan Anda (misalnya `jurnal_mengajar`).
3. Set collation database ke `utf8mb4_general_ci` untuk mendukung karakter modern.

#### **Langkah C: Mengimpor Skema Dasar**
1. Impor berkas SQL dasar **`jurnal_mengajar.sql`** yang terletak di folder root aplikasi Anda ke dalam database baru yang baru saja dibuat.
2. Proses ini akan menginisialisasi tabel-tabel utama seperti `users`, `siswa`, `guru`, `kelas`, dan data master dasar lainnya.

#### **Langkah D: Mengonfigurasi Koneksi Aplikasi**
1. Buka berkas konfigurasi database di **`config/database.php`** menggunakan editor teks.
2. Sesuaikan parameter koneksi database berikut dengan kredensial database server baru Anda:
   ```php
   $db_host = 'localhost';      // Server database (biasanya localhost)
   $db_user = 'root';           // Username database baru Anda
   $db_pass = 'password_baru';  // Password database baru Anda
   $db_name = 'jurnal_mengajar';// Nama database baru Anda
   ```
3. **Konfigurasi Folder/Subfolder (Opsional):**
   Jika aplikasi ditempatkan dalam subfolder (misalnya `http://nama-domain.com/jurnal/`), Anda dapat memaksa URL dasar secara manual dengan mengedit variabel `$base_url_config` di bagian atas berkas `config/database.php`:
   ```php
   $base_url_config = 'http://nama-domain.com/jurnal/';
   ```
   Jika diletakkan langsung di domain utama, biarkan variabel ini kosong `""` agar sistem mendeteksi URL secara otomatis.

#### **Langkah E: Sinkronisasi & Migrasi Tabel Relasional Otomatis**
1. Buka browser Anda dan masuk ke sistem CAKRA sebagai Administrator.
2. Jalankan skrip sinkronisasi skema otomatis dengan mengakses URL berikut di browser Anda:
   ```
   http://domain-anda.com/admin/update_db.php
   ```
   *(Atau `http://localhost/jurnal/admin/update_db.php` jika dijalankan di server lokal).*
3. Skrip migrasi ini akan mendeteksi, membuat, dan memigrasikan seluruh tabel relasional tingkat lanjut seperti `mood_survey`, `panic_button`, `kritik_saran`, `kategori_perangkat`, dan `perangkat_kelas` yang belum tercakup di SQL dasar, serta menyinkronkan default system settings.
4. Anda akan melihat log status berwarna hijau menandakan migrasi database berhasil 100%!

---

### 3. Akun Akses Default Sistem (Kredensial)

Gunakan akun administrator default berikut untuk login pertama kali:
* **Username:** `admin`
* **Password:** `admin`

*(Setelah berhasil login, harap segera ganti password administrator melalui menu Edit Profil demi keamanan).*

---

### 4. Fitur Tambahan: Pemulihan Sistem Total (Restore ZIP)
Jika Anda memiliki file backup ZIP penuh (`cakra_full_backup_XYZ.zip`) dari server lama, Anda tidak perlu mengimpor berkas SQL dasar secara manual. Cukup:
1. Hubungkan database baru Anda di `config/database.php`.
2. Login sebagai Administrator, lalu masuk ke menu **Sistem -> Backup & Restore**.
3. Di kolom **Pulihkan Sistem (ZIP)**, unggah berkas ZIP backup Anda lalu klik **PULIHKAN SELURUH SISTEM**.
4. Sistem akan otomatis memulihkan seluruh database dan menyinkronkan berkas-berkas foto/dokumen Anda kembali ke tempat asalnya secara otomatis!

---

### 5. Integrasi API Eksternal
Aplikasi CAKRA menyediakan endpoint API eksternal yang aman di `/api/external_data.php` untuk integrasi dengan aplikasi lain di lingkungan sekolah Anda.

* **API Endpoint:** `http://domain-anda.com/api/external_data.php`
* **Metode Otentikasi:** Header `X-API-KEY` atau Query Parameter `api_key`
* **API Key Default:** `CAKRA_SECURE_API_KEY_2026`

#### Parameter Resource yang Tersedia:
* `resource=students` : Mengambil data seluruh siswa terdaftar.
* `resource=teachers` : Mengambil daftar seluruh guru pengampu.
* `resource=classes` : Mengambil daftar kelas dan wali kelasnya.
* `resource=attendance` : Mengambil rekap kehadiran harian siswa (mendukung filter tanggal tambahan `&date=YYYY-MM-DD`).
