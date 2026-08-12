# PocketFlow PWA Frontend

Frontend ini adalah Web PWA murni. Jalankan dengan static server, misalnya VS Code Live Server atau:

```bash
npx serve .
```

Atur endpoint backend di:

```txt
assets/config.js
```

Untuk OCR, aplikasi memakai Tesseract.js dari CDN. OCR berjalan di browser, lalu hasil teks dikirim ke Laravel untuk diparse menjadi nominal, tanggal, waktu, dan merchant.

## Catatan PWA

Service worker hanya meng-cache file statis. API tidak di-cache agar data transaksi tetap akurat.
