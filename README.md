# Kelompok 3
- Nuh hasif iftihar
- M.iswahyudi
- M.fahri alfando
- Brayend rolan ferlando
- Risqon jadid pratama
- Zahira nuril maulina




# FacilityWatch

Aplikasi web monitoring dan pemeliharaan sarana/prasarana sekolah. Dibuat dengan **PHP 8.1+ (native + PDO)** dan **MySQL/MariaDB**, tanpa Composer.

Dokumen produk: [`docs/PRD.md`](docs/PRD.md)

## Fitur

- Landing page, dashboard (statistik + grafik), ruangan, aset, tiket pemeliharaan
- Warga sekolah melapor tanpa login; admin mengelola data dan memproses tiket
- Nomor tiket otomatis `MTC-YYYYMMDD-XXX`, timeline status, upload foto
- Tiket **Selesai** otomatis mengembalikan kondisi aset ke **Baik**
- Keamanan: prepared statements, escape output, CSRF token, bcrypt, validasi upload

## Struktur

```
facilitywatch/
├── config/config.php        # konfigurasi DB (bisa lewat env var)
├── database/schema.sql      # DDL + seed data
├── includes/                # bootstrap (helper, auth, CSRF), header, footer
├── public/                  # document root
│   ├── index.php            # landing
│   ├── dashboard.php  rooms.php  room.php  assets.php  asset.php
│   ├── tickets.php  ticket_new.php  ticket.php
│   ├── login.php  logout.php
│   ├── assets/css  assets/js
│   └── uploads/             # foto upload (harus bisa ditulis web server)
└── docs/PRD.md
```

## Instalasi

**Prasyarat:** PHP 8.1+ dengan ekstensi `pdo_mysql` dan `fileinfo`, serta MySQL 5.7+ / MariaDB 10.4+.

1. **Impor database**
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   Skrip membuat database `facilitywatch`, semua tabel, dan data contoh (5 ruangan, 15 aset, 5 tiket, 1 admin).

2. **Atur koneksi** di `config/config.php`, atau lewat environment variable:
   ```bash
   export DB_HOST=127.0.0.1 DB_NAME=facilitywatch DB_USER=root DB_PASS=rahasia
   ```

3. **Jalankan**
   ```bash
   php -S localhost:8000 -t public
   ```
   Buka http://localhost:8000

4. **Login admin:** `admin` / `admin123` di `/login.php`. **Ganti password segera** (lihat di bawah).

### Ganti password admin

```bash
php -r 'echo password_hash("PasswordBaruAnda", PASSWORD_DEFAULT), PHP_EOL;'
# lalu:
mysql -u root -p facilitywatch -e "UPDATE users SET password='<hash>' WHERE username='admin';"
```

## Deploy ke Apache/Nginx

- Arahkan **document root ke folder `public/`** (folder `config/`, `includes/`, `database/` tidak boleh bisa diakses web).
- Pastikan `public/uploads/` writable oleh user web server.
- Produksi: aktifkan HTTPS dan set `session.cookie_secure=1`.

Contoh Nginx:

```nginx
root /var/www/facilitywatch/public;
index index.php;
location / { try_files $uri $uri/ =404; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
location /uploads/ { location ~ \.php$ { deny all; } }
```

## Alur penggunaan

1. Admin login → tambah ruangan dan aset.
2. Guru/siswa membuka **Aset** atau **Ruangan** → klik **Laporkan** → isi formulir → dapat nomor tiket.
3. Admin membuka tiket → status **Ditinjau** → isi teknisi, status **Dalam Perbaikan**.
4. Setelah selesai: isi catatan resolusi, status **Selesai** → kondisi aset otomatis **Baik**.

## Catatan

- Halaman baca (dashboard, ruangan, aset, tiket) dan pembuatan tiket bersifat publik. Bila ingin semua halaman wajib login, panggil `require_admin()` di awal halaman terkait.
- Tabel `users` ditambahkan di luar tiga tabel utama untuk autentikasi admin.
