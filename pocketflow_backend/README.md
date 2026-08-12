# PocketFlow Backend

Backend Laravel REST API untuk PocketFlow.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

## Database

Default memakai MySQL.

```sql
CREATE DATABASE pocketflow_db;
```

## Auth

Backend memakai Laravel Sanctum token API. Setelah login/register, simpan token di frontend dan kirim header:

```http
Authorization: Bearer TOKEN_KAMU
```

## Upload Gambar

Field upload transaksi:

```txt
proof_image
```

Maksimal 4 MB, format jpg/jpeg/png/webp.

## SQL Manual

File `database/pocketflow_schema.sql` disediakan kalau kamu ingin melihat struktur MySQL secara langsung. Untuk Laravel, tetap disarankan memakai `php artisan migrate --seed`.
