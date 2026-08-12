# POCKETFLOW

PocketFlow adalah starter project aplikasi keuangan pribadi berbasis **Laravel REST API + MySQL + Web PWA**.

Fokus MVP:

- Login dan register user.
- Dashboard saldo, pemasukan, pengeluaran, dan status keuangan bulanan.
- Input transaksi manual.
- Input transaksi dari foto/bukti transaksi.
- OCR di browser menggunakan Tesseract.js, lalu hasil teks diparse oleh backend.
- Riwayat transaksi, filter, edit sederhana, hapus.
- Manajemen kategori.
- PWA: bisa diinstall dari browser dan punya service worker dasar.

> Catatan arsitektur: brainstorming awal menyebut Flutter + ML Kit. Karena target sekarang adalah website PWA, project ini memakai frontend web PWA murni agar bisa langsung berjalan di browser. Google ML Kit Text Recognition untuk Flutter hanya mendukung Android/iOS, bukan web. Struktur backend tetap Laravel + MySQL, sehingga nanti frontend bisa diganti ke Flutter tanpa mengubah database besar-besaran.

---

## Struktur Folder

```txt
pocketflow_project/
├── pocketflow_backend/      # Laravel REST API + MySQL
├── pocketflow_pwa/          # Frontend Web PWA
└── README.md
```

---

## Cara Menjalankan Backend Laravel

Masuk ke folder backend:

```bash
cd pocketflow_backend
composer install
cp .env.example .env
php artisan key:generate
```

Buat database MySQL:

```sql
CREATE DATABASE pocketflow_db;
```

Atur `.env`:

```env
APP_NAME=PocketFlow
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://127.0.0.1:5500

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pocketflow_db
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan migrasi dan seeder kategori bawaan:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Backend berjalan di:

```txt
http://127.0.0.1:8000
```

---

## Cara Menjalankan Frontend PWA

Masuk ke folder frontend:

```bash
cd pocketflow_pwa
```

Buka file:

```txt
assets/config.js
```

Pastikan API sesuai:

```js
window.POCKETFLOW_CONFIG = {
  API_BASE_URL: 'http://127.0.0.1:8000/api'
};
```

Jalankan static server. Contoh memakai VS Code Live Server, atau:

```bash
npx serve .
```

Buka URL frontend di browser.

---

## Akun dan Data

Aplikasi tidak memakai data dummy transaksi. User harus register sendiri, lalu transaksi disimpan ke MySQL.

Seeder hanya membuat kategori bawaan global seperti Makanan, Transportasi, Gaji, Freelance, dan lain-lain.

---

## Alur OCR Foto Struk

1. User upload foto struk/bukti transaksi.
2. Frontend membaca teks dengan Tesseract.js.
3. Teks OCR dikirim ke endpoint Laravel `/api/ocr/parse`.
4. Backend mencoba mengambil nominal, tanggal, waktu, dan merchant.
5. User koreksi hasilnya.
6. Transaksi disimpan ke database bersama bukti gambar.

---

## Endpoint Utama

```txt
POST   /api/register
POST   /api/login
POST   /api/logout
GET    /api/me
GET    /api/dashboard
GET    /api/categories
POST   /api/categories
PUT    /api/categories/{id}
DELETE /api/categories/{id}
GET    /api/transactions
POST   /api/transactions
GET    /api/transactions/{id}
POST   /api/transactions/{id}       # update multipart
DELETE /api/transactions/{id}
POST   /api/ocr/parse
```

Semua endpoint selain register/login wajib memakai Bearer Token dari login.

---

## Roadmap Lanjutan

V1 sekarang:

- Auth
- Dashboard
- CRUD transaksi
- Kategori
- Upload bukti
- OCR browser dasar
- PWA dasar

V2 berikutnya:

- Grafik kategori
- Export PDF
- Budget bulanan
- Import email transaksi
- OCR backend yang lebih kuat
- Mode offline sync

