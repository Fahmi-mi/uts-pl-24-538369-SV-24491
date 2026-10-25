# WishRadar

Wishlist Gadget dengan Pelacakan Riwayat Harga.

Proyek Ujian Tengah Semester (UTS) Konstruksi dan Evolusi Perangkat Lunak (KEPL).

## Identitas
- Nama: Fahmi
- NIM: 24/538369/SV/24491
- Repository: https://github.com/Fahmi-mi/uts-pl-24-538369-SV-24491

## Arsitektur & Teknologi
- Backend: Laravel 12, Laravel Sanctum, FrankenPHP (PHP 8.3)
- Frontend: Vue 3, Vite, Vue Router, Tailwind CSS, Axios, Nginx Reverse Proxy
- Basis Data: MySQL 8.4 LTS
- CI/CD: GitHub Actions (Testing, Building, Continuous Deployment ke GitHub Packages / GHCR)
- Orkestrasi: Multi-Container Docker Compose

## Panduan Menjalankan Sistem

1. Salin berkas lingkungan:
   ```bash
   cp .env.example .env
   ```

2. Jalankan seluruh service dengan satu perintah:
   ```bash
   docker compose up -d --build
   ```

3. Akses aplikasi:
   - Frontend SPA: http://localhost:8080
   - Backend API: http://localhost:8000/api
   - Akun Demo: `demo@wishradar.test` / `password123`

## Pengujian & Koleksi Postman
- Backend Feature Test: `cd backend && php artisan test`
- Frontend Build Check: `cd frontend && npm run build`
- Postman Collection: `backend/postman/wishradar.postman_collection.json`
