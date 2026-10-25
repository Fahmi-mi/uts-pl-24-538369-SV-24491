# WishRadar: Wishlist Gadget dengan Pelacakan Harga

Konsepnya: pengguna mencatat gadget incaran beserta **harga target**. Setiap kali harga terbaru diperbarui, sistem menyimpan riwayatnya dan menandai gadget yang sudah mencapai harga target. Harga diperbarui manual, tanpa scraping, jadi tidak overengineering.

Catatan: versi paket dan action di bawah berasal dari pengetahuan saya, jadi cek versi terbarunya saat mengerjakan.

---

## 1. Struktur Repository

```
uts-pl-NIM/
├── .github/workflows/ci.yml
├── .gitignore
├── .env.example            # contoh untuk docker-compose
├── docker-compose.yml
├── README.md
├── backend/                # Laravel 12 (PHP 8.3)
│   ├── Dockerfile
│   ├── .dockerignore
│   ├── .env.example
│   ├── docker/entrypoint.sh
│   ├── postman/wishradar.postman_collection.json
│   └── ...
└── frontend/               # Vue 3 + Vite
    ├── Dockerfile
    ├── .dockerignore
    ├── nginx.conf
    ├── .env.example
    └── ...
```

---

## 2. Database

Tiga tabel, dengan **satu resource CRUD utama (`gadgets`)**.

| Tabel | Kolom |
|---|---|
| `users` | id, name, email (unique), password, timestamps |
| `personal_access_tokens` | dibuat otomatis oleh Sanctum |
| `gadgets` | id, user_id (FK, cascade), name, brand, category, store (nullable), product_url (nullable), target_price, current_price, priority, status, notes (nullable), timestamps |
| `price_histories` | id, gadget_id (FK, cascade), price, recorded_at |

Relasi: `User 1—N Gadget 1—N PriceHistory`.

Keputusan desain:
- Harga disimpan sebagai `unsignedBigInteger` (Rupiah bulat), bukan float, supaya tidak ada masalah pembulatan.
- `category`: smartphone, laptop, audio, wearable, camera, accessory, other.
- `priority`: low, medium, high (default medium).
- `status`: wishlist, purchased, dropped (default wishlist).
- Index pada `(user_id, status)` karena itu filter yang paling sering dipakai.

```php
Schema::create('gadgets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name', 150);
    $table->string('brand', 100);
    $table->enum('category', ['smartphone','laptop','audio','wearable','camera','accessory','other']);
    $table->string('store', 100)->nullable();
    $table->string('product_url', 2048)->nullable();
    $table->unsignedBigInteger('target_price');
    $table->unsignedBigInteger('current_price');
    $table->enum('priority', ['low','medium','high'])->default('medium');
    $table->enum('status', ['wishlist','purchased','dropped'])->default('wishlist');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'status']);
});

Schema::create('price_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('gadget_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('price');
    $table->timestamp('recorded_at')->useCurrent();
});
```

Siapkan juga `GadgetFactory` (untuk test) dan `DatabaseSeeder` (satu user demo plus 5–8 gadget contoh). Email dan password user demo **dibaca dari env** (`SEED_USER_EMAIL`, `SEED_USER_PASSWORD`), bukan ditulis di kode, supaya tidak melanggar aturan "tidak ada nilai rahasia di repository".

---

## 3. Desain API

Semua endpoint berawalan `/api`.

| Method | Endpoint | Auth | Fungsi | Sukses | Gagal |
|---|---|---|---|---|---|
| POST | `/login` | tidak | Login, dapat token | 200 | 422 |
| POST | `/logout` | ya | Hapus token aktif | 200 | 401 |
| GET | `/gadgets` | ya | Tampil semua (filter + paginasi) | 200 | 401 |
| GET | `/gadgets/{id}` | ya | Tampil satu | 200 | 401, 404 |
| POST | `/gadgets` | ya | Tambah | **201** | 401, **422** |
| PUT/PATCH | `/gadgets/{id}` | ya | Ubah | 200 | 401, 404, 422 |
| DELETE | `/gadgets/{id}` | ya | Hapus | 200 (body JSON) | 401, 404 |
| GET | `/gadgets/{id}/price-histories` | ya | Riwayat harga | 200 | 401, 404 |

Query filter untuk `GET /gadgets`: `?status=wishlist&category=laptop&search=macbook&page=2`.

Format respons seragam:

```json
{ "message": "Gadget berhasil ditambahkan", "data": { "id": 1, "name": "...", "target_reached": false } }
```

Error validasi memakai format bawaan Laravel (`message` + `errors`), status 422.

Aturan bisnis:
1. Saat gadget dibuat, harga awal otomatis dicatat ke `price_histories`.
2. Saat `current_price` berubah lewat update, satu baris riwayat baru dicatat otomatis. Tidak ada endpoint tulis untuk riwayat.
3. `GadgetResource` menambahkan field turunan `target_reached` (`current_price <= target_price`) dan `price_gap` (`current_price - target_price`).
4. Data bersifat per-user. Gunakan `$request->user()->gadgets()->findOrFail($id)` supaya milik orang lain otomatis 404.

Aturan validasi (`StoreGadgetRequest`):

```
name          required|string|max:150
brand         required|string|max:100
category      required|in:(daftar kategori)
store         nullable|string|max:100
product_url   nullable|url|max:2048
target_price  required|integer|min:1
current_price required|integer|min:1
priority      sometimes|in:low,medium,high
status        sometimes|in:wishlist,purchased,dropped
notes         nullable|string|max:1000
```

`UpdateGadgetRequest` memakai aturan yang sama dengan `sometimes` di tiap field.

---

## 4. Implementasi Backend (poin penting)

Setup: `composer create-project laravel/laravel backend`, lalu `php artisan install:api` (memasang Sanctum dan `routes/api.php`).

**Routes** (`routes/api.php`):

```php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/gadgets/{gadget}/price-histories', [GadgetController::class, 'priceHistories']);
    Route::apiResource('gadgets', GadgetController::class);
});
```

**Wajib JSON untuk semua error** (`bootstrap/app.php`). Tanpa ini, request tanpa header `Accept: application/json` akan di-redirect ke halaman login, bukan 401:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) =>
        $request->is('api/*') || $request->expectsJson());
})
```

**AuthController**
- `login`: validasi email dan password, cek `Hash::check`. Jika salah, `throw ValidationException::withMessages(...)` (422). Jika benar, kembalikan `$user->createToken('web')->plainTextToken`.
- `logout`: `$request->user()->currentAccessToken()->delete()`.

**GadgetController**
- `index`: `->when(...)` untuk filter, `->latest()->paginate(10)`, dibungkus `GadgetResource::collection`.
- `store`: dalam `DB::transaction`, buat gadget dan riwayat pertama, lalu `response()->json([...], 201)`.
- `update`: `fill` + `save`, lalu `if ($gadget->wasChanged('current_price'))` buat riwayat baru.
- `destroy`: hapus dan kembalikan `{"message": "Gadget berhasil dihapus"}` dengan status 200. Saya hindari 204 karena tidak punya body, sedangkan soal meminta semua balasan JSON.

---

## 5. Feature Test (minimal 2, saran 7)

Pakai `RefreshDatabase` dan `Sanctum::actingAs($user)`. phpunit bawaan Laravel memakai SQLite in-memory, jadi CI tidak butuh MySQL.

1. Login berhasil mengembalikan token.
2. Login password salah mengembalikan 422.
3. `GET /api/gadgets` tanpa token mengembalikan 401.
4. `POST /api/gadgets` valid mengembalikan 201 dan data masuk ke database.
5. `POST /api/gadgets` tanpa `name` mengembalikan 422.
6. Update `current_price` menambah satu baris `price_histories`.
7. Mengakses gadget milik user lain mengembalikan 404.

```php
public function test_create_gadget_returns_201(): void
{
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/gadgets', [
        'name' => 'Sony WH-1000XM5', 'brand' => 'Sony', 'category' => 'audio',
        'target_price' => 4000000, 'current_price' => 4800000,
    ])->assertCreated()->assertJsonPath('data.name', 'Sony WH-1000XM5');

    $this->assertDatabaseHas('gadgets', ['name' => 'Sony WH-1000XM5']);
}
```

---

## 6. Postman

- Buat variabel collection `base_url` (`http://localhost:8000/api`) dan `token`.
- Auth tingkat collection: Bearer `{{token}}`. Header collection: `Accept: application/json`.
- Request Login diberi Tests script supaya token tersimpan otomatis:
  ```js
  pm.collectionVariables.set("token", pm.response.json().data.token);
  ```
- Folder: Auth (Login, Logout), Gadgets (List, Show, Create, Update, Delete, Price Histories).
- Tambahkan satu request "List tanpa token" untuk bukti 401, dan "Create invalid" untuk bukti 422.
- Export ke `backend/postman/wishradar.postman_collection.json`, lalu commit. Jangan commit file environment yang berisi token.

---

## 7. Frontend (Vue 3 + Vite + Vue Router + Axios)

Tanpa Pinia, cukup satu modul kecil untuk token. Tanpa library UI atau chart, cukup CSS sederhana.

Halaman:

| Route | Isi |
|---|---|
| `/login` | Form login, simpan token ke `localStorage` |
| `/gadgets` | Daftar kartu atau tabel, filter status/kategori, kolom cari, tombol tambah, edit, hapus, badge "Target tercapai" |
| `/gadgets/new` dan `/gadgets/:id/edit` | Satu komponen form untuk tambah dan ubah, menampilkan error 422 per field |
| `/gadgets/:id` | Detail dan tabel riwayat harga |

**Axios instance** (`src/api/http.js`). Alamat API tidak ditulis mati, tapi dari env:

```js
import axios from 'axios'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: { Accept: 'application/json' },
})

http.interceptors.request.use((cfg) => {
  const token = localStorage.getItem('token')
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  return cfg
})

http.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  },
)
export default http
```

Lainnya:
- `router.beforeEach`: halaman dengan `meta.auth` ditolak jika tidak ada token.
- `frontend/.env.example`: `VITE_API_BASE_URL=/api`.
- `vite.config.js` pada `server.proxy`: `'/api'` ke `http://localhost:8000`. Dengan begitu mode dev tidak butuh konfigurasi CORS, dan nilai env sama persis dengan di Docker.
- Tampilkan harga dengan `Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR'})`.

---

## 8. Docker

### Backend Dockerfile (soal 5a)

Urutan yang dinilai:
1. `FROM` dengan tag versi jelas, misalnya `php:8.3-cli`. Bukan `latest`.
2. Pasang ekstensi PHP dan Composer (copy dari image `composer:2`).
3. `COPY composer.json composer.lock ./`
4. `RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction`
5. **Baru** `COPY . .`, lalu `composer dump-autoload --optimize`.
6. Pastikan `storage/` dan `bootstrap/cache/` writable.
7. `ENTRYPOINT` ke `docker/entrypoint.sh`: jalankan `php artisan migrate --force`, lalu start server.

Jangan ada `ENV APP_KEY=...` atau password di Dockerfile. `.dockerignore` backend: `.env`, `vendor`, `node_modules`, `.git`, `storage/logs/*`, `tests`.

Untuk bonus FrankenPHP, ganti base image ke `dunglas/frankenphp:<versi>-php8.3` (pilih tag spesifik dari Docker Hub). Bagian lain tetap sama.

### Frontend Dockerfile (soal 5b, multi-stage)

```dockerfile
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
ARG VITE_API_BASE_URL=/api
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
RUN npm run build

FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
```

Image akhir hanya berisi hasil build dan Nginx, tanpa `node_modules`. `.dockerignore` frontend: `node_modules`, `dist`, `.git`, `.env`.

### Kunci soal 6b: browser tidak bisa me-resolve nama service

Kode Vue berjalan **di browser laptop**, bukan di dalam container, jadi `http://backend:8000` tidak akan terbaca dari browser. Solusinya: Nginx di container frontend bertindak sebagai reverse proxy. Browser hanya memanggil `/api/...` ke Nginx, lalu Nginx meneruskannya ke `backend` lewat jaringan Docker. Inilah "melalui nama service".

`frontend/nginx.conf`:

```nginx
server {
  listen 80;
  root /usr/share/nginx/html;
  index index.html;

  location /api/ {
    proxy_pass http://backend:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
  }

  location / {
    try_files $uri $uri/ /index.html;
  }
}
```

### docker-compose.yml (root)

```yaml
services:
  db:
    image: mysql:8.4
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 5s
      retries: 20

  backend:
    build: ./backend
    restart: unless-stopped
    env_file: .env
    environment:
      DB_HOST: db
    depends_on:
      db:
        condition: service_healthy
    ports:
      - "8000:8000"   # untuk Postman dari laptop

  frontend:
    build: ./frontend
    restart: unless-stopped
    ports:
      - "8080:80"
    depends_on:
      - backend

volumes:
  db_data:
```

`.env.example` di root (nilai placeholder, `.env` asli masuk `.gitignore`):

```
APP_KEY=
APP_ENV=production
DB_CONNECTION=mysql
DB_PORT=3306
DB_DATABASE=wishradar
DB_USERNAME=wishradar
DB_PASSWORD=
DB_ROOT_PASSWORD=
SEED_USER_EMAIL=
SEED_USER_PASSWORD=
```

`APP_KEY` dibuat lokal dengan `php artisan key:generate --show`, lalu ditempel ke `.env`.

Uji persistensi (soal 6d): buat data, jalankan `docker compose down` lalu `docker compose up -d`, dan data harus tetap ada. **Jangan** pakai `down -v` karena itu menghapus volume.

---

## 9. GitHub: Branch, Protection, CI/CD

Alur kerja: `feature/xxx` → PR → `dev` → PR → `main`.

Urutan setup supaya tidak terkunci:
1. Buat repo `uts-pl-NIM` (publik), push commit awal ke `main`, buat `dev`.
2. Push dulu `ci.yml`, biarkan jalan sekali. Nama job baru muncul di daftar status check setelah workflow pernah berjalan.
3. Baru aktifkan branch protection (Settings → Branches atau Rules) di `main` dan `dev`: wajib Pull Request, wajib status check `backend-test` dan `frontend-build` lulus. Screenshot halamannya.
4. Mulai sekarang semua perubahan lewat branch fitur dan PR.

Kerangka `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main, dev, 'feature/**']
  pull_request:
    branches: [main, dev]

jobs:
  backend-test:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: backend
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan test

  frontend-build:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: frontend
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 22
          cache: npm
          cache-dependency-path: frontend/package-lock.json
      - run: npm ci
      - run: npm run build

  deploy:
    needs: [backend-test, frontend-build]
    if: github.event_name == 'push' && github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    steps:
      - uses: actions/checkout@v4
      - uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      - name: Nama image huruf kecil
        run: echo "IMAGE=ghcr.io/${GITHUB_REPOSITORY,,}" >> "$GITHUB_ENV"
      - uses: docker/build-push-action@v6
        with:
          context: ./backend
          push: true
          tags: ${{ env.IMAGE }}/backend:${{ github.sha }}
      - uses: docker/build-push-action@v6
        with:
          context: ./frontend
          push: true
          tags: ${{ env.IMAGE }}/frontend:${{ github.sha }}
```

Poin penting:
- **Nama image GHCR harus huruf kecil.** Organization `KEPL2026` mengandung huruf besar, jadi langkah `${GITHUB_REPOSITORY,,}` wajib ada, kalau tidak push gagal.
- Kondisi `if` pada job `deploy` membuatnya berstatus *skipped* di PR, `dev`, dan branch fitur. Itu bukti soal 4d.
- Tag memakai `github.sha`, bukan `latest`.
- Secrets (`APP_KEY`, `DB_PASSWORD`) didaftarkan di Settings → Secrets and variables → Actions. Supaya benar-benar terpakai, tambahkan satu step smoke test di job deploy: tulis `.env` dari secrets, jalankan `docker compose up -d --build`, lalu `curl` ke `localhost:8000/api/gadgets` dan pastikan hasilnya 401.
- Package GHCR bawaannya private. Pastikan terlihat di tab Packages repo untuk screenshot.

---

## 10. Best Practice Checklist

- **Secret**: `.env` di `.gitignore` dan `.dockerignore`. Hanya `.env.example` yang di-commit. Tidak ada password di Dockerfile, compose, seeder, atau workflow.
- **Keamanan API**: semua route kecuali login ada di `auth:sanctum`. Query selalu di-scope ke `$request->user()`. Gunakan FormRequest, bukan validasi di controller. Jangan pernah mengembalikan field `password` (pakai Resource).
- **Konsistensi**: format JSON seragam lewat `GadgetResource`, dan status code sesuai tabel API.
- **Docker**: tag versi tetap (`php:8.3-cli`, `node:22-alpine`, `nginx:1.27-alpine`, `mysql:8.4`), `restart: unless-stopped`, healthcheck DB, dan DB tidak di-publish ke host.
- **Git**: commit kecil dan bermakna (`feat:`, `fix:`, `chore:`), PR dengan deskripsi singkat, dan jangan commit `vendor/` atau `node_modules/`.
- **Frontend**: loading state dan pesan error per field, konfirmasi sebelum hapus, dan logout memanggil API lalu menghapus token lokal.
- **Yang sengaja tidak dibuat** (supaya tidak overengineering): register, role/admin, upload gambar, scraping harga, grafik, dan notifikasi email.

---

## 11. Pemetaan Soal ke Bukti Laporan

| Soal | Bukti yang di-screenshot / dilampirkan |
|---|---|
| 1a–c | Postman: login, request tanpa token (401), create (201), create invalid (422) |
| 1d | File collection di repo, tautan, dan screenshot Postman |
| 1e | Output `php artisan test` hijau |
| 2 | Halaman login, daftar, form, dan tab Network DevTools yang menunjukkan header Authorization. Tunjukkan `.env` dan penggunaan `import.meta.env` |
| 3a–b | Tautan repo, tab Actions, riwayat PR, dan halaman Branch Protection `main` dan `dev` |
| 3c | Workflow hijau (kedua job) |
| 4a, 4d | Run di `main` (deploy hijau), run di PR atau `dev` (deploy skipped), dan halaman Packages dengan dua image |
| 4b | Halaman Secrets (nama saja, tanpa nilai) |
| 4c | Cuplikan workflow dan tag image berupa SHA |
| 5 | Isi kedua Dockerfile, `.dockerignore`, dan `docker history` atau `docker run ... ls` yang membuktikan tidak ada `node_modules` |
| 6 | `docker compose ps`, browser menampilkan CRUD, uji `down` lalu `up` dengan data tetap ada, `.env.example` |
| 7 (bonus) | `docker compose logs backend` yang menampilkan log FrankenPHP/Caddy dan header respons `Server: Caddy` |
| 8 | Alur data Vue → Nginx `/api` → Laravel → MySQL, plus bagian kendala |

---

## 12. Urutan Pengerjaan yang Disarankan

1. Backend lokal: migration, model, auth, CRUD, lalu test.
2. Postman collection dan ekspor.
3. Frontend lokal dengan Vite proxy.
4. Repo GitHub, branch, `ci.yml` (dua job), lalu branch protection.
5. Dockerfile backend dan frontend, lalu `docker-compose.yml`, lalu uji `down`/`up`.
6. Job deploy ke GHCR dan bukti run.
7. Bonus FrankenPHP (ganti base image dan pastikan lognya).
8. Laporan PDF, dikerjakan sambil jalan. Screenshot langsung diambil setiap soal selesai.

## 13. Kendala yang Hampir Pasti Muncul (bahan bagian laporan)

- 401 berubah menjadi redirect HTML. Penyebabnya header `Accept: application/json` tidak ada, solusinya `shouldRenderJsonWhen`.
- Browser tidak bisa me-resolve `backend`. Solusinya reverse proxy Nginx.
- Backend start sebelum MySQL siap. Solusinya healthcheck dan `depends_on: condition: service_healthy`.
- Push GHCR gagal karena huruf besar di nama image.
- Error izin tulis `storage/` di container.
- `APP_KEY` kosong sehingga muncul error "No application encryption key".
- Status check tidak muncul di branch protection karena workflow belum pernah dijalankan.

Catat kendala yang benar-benar Anda alami. Laporan sebaiknya berisi pengalaman asli, bukan daftar ini.
