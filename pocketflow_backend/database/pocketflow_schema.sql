CREATE DATABASE IF NOT EXISTS pocketflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pocketflow_db;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  email_verified_at TIMESTAMP NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL,
  type ENUM('income', 'expense') NOT NULL,
  icon VARCHAR(30) NULL,
  is_default BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX categories_user_type_index (user_id, type),
  CONSTRAINT categories_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  type ENUM('income', 'expense') NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  merchant VARCHAR(120) NULL,
  transaction_date DATE NOT NULL,
  transaction_time TIME NULL,
  note VARCHAR(500) NULL,
  image_path VARCHAR(255) NULL,
  ocr_text LONGTEXT NULL,
  source ENUM('manual', 'photo', 'email', 'bank_proof', 'other') NOT NULL DEFAULT 'manual',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX transactions_user_type_date_index (user_id, type, transaction_date),
  INDEX transactions_category_date_index (category_id, transaction_date),
  CONSTRAINT transactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT transactions_category_id_foreign FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

INSERT INTO categories (user_id, name, type, icon, is_default, created_at, updated_at) VALUES
(NULL, 'Makanan', 'expense', '🍽️', 1, NOW(), NOW()),
(NULL, 'Transportasi', 'expense', '🚌', 1, NOW(), NOW()),
(NULL, 'Belanja', 'expense', '🛒', 1, NOW(), NOW()),
(NULL, 'Tagihan', 'expense', '🧾', 1, NOW(), NOW()),
(NULL, 'Pendidikan', 'expense', '🎓', 1, NOW(), NOW()),
(NULL, 'Kesehatan', 'expense', '🏥', 1, NOW(), NOW()),
(NULL, 'Hiburan', 'expense', '🎮', 1, NOW(), NOW()),
(NULL, 'Lainnya', 'expense', '📦', 1, NOW(), NOW()),
(NULL, 'Gaji', 'income', '💼', 1, NOW(), NOW()),
(NULL, 'Bonus', 'income', '🎁', 1, NOW(), NOW()),
(NULL, 'Transfer Masuk', 'income', '🏦', 1, NOW(), NOW()),
(NULL, 'Freelance', 'income', '💻', 1, NOW(), NOW()),
(NULL, 'Uang Jajan', 'income', '💰', 1, NOW(), NOW()),
(NULL, 'Lainnya', 'income', '✨', 1, NOW(), NOW());
