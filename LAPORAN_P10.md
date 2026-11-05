LAPORAN KONSTRUKSI DAN EVOLUSI PERANGKAT LUNAK


Nama: Fahmi Haqqul Ihsan
NIM: 24/538369/SV/24491
Kelas: PL5BB
Dosen Pengampu: Galih Malela Damaraji
Mata Kuliah: Konstruksi dan Evolusi Perangkat Lunak


INFORMASI REPOSITORI

Tautan Repositori: https://github.com/Fahmi-mi/uts-pl-24-538369-SV-24491
Branch Pengerjaan: feature/logging-healthcheck


1. PENGALIHAN LOG LARAVEL KE STDOUT (SOAL 1)

Pengaturan logging pada kerangka kerja Laravel telah diubah dari penyimpanan berbasis berkas lokal di dalam kontainer (storage/logs/laravel.log) menjadi keluaran standar (stdout). Hal ini dilakukan dengan menambahkan channel stdout pada backend/config/logging.php menggunakan Monolog StreamHandler yang mengarah ke php://stdout. Variabel lingkungan LOG_CHANNEL=stdout dan LOG_LEVEL=debug disematkan pada berkas konfigurasi lingkungan serta pada definisi service backend di docker-compose.yml.

Dengan konfigurasi ini, seluruh pesan log yang dihasilkan oleh aplikasi Laravel langsung ditangkap oleh Docker daemon dan dapat dipantau secara terpusat melalui perintah docker compose logs tanpa perlu mengakses sistem berkas kontainer.

[Gambar: Output terminal docker compose logs backend yang membuktikan log aplikasi Laravel keluar ke stdout]


2. IMPLEMENTASI TIGA LOG TERSTRUKTUR DAN BERKONTEKS (SOAL 2)

Sistem mengimplementasikan tiga kategori log terstruktur dengan tingkat log (severity level) yang disesuaikan pada alur-alur penting. Setiap entri log memuat konteks yang jelas mengenai siapa yang melakukan aksi (user), aksi yang sedang dikerjakan (action), alasan terjadinya pencatatan (reason), serta data non-sensitif pendukung. Sistem menjamin tidak ada password, token autentikasi, maupun data pribadi seperti alamat email mentah yang dicatat.

A. Alur Penyimpanan Data Gadget (Tingkat INFO)
- Alasan Pemilihan Alur:
Penyimpanan gadget merupakan transaksi bisnis utama pada aplikasi WishRadar. Pencatatan log pada alur ini penting untuk audit jejak aktivitas pembuatan data pengguna dan verifikasi bahwa data baru berhasil masuk ke dalam sistem.
- Potongan Log Terstruktur:
[2026-11-05 10:04:56] production.INFO: Gadget stored successfully {"user":"1","action":"store_gadget","reason":"gadget created by user","gadget_id":1,"gadget_name":"Sony WH-1000XM5"}
- Penjelasan Konteks:
Aktor dicatat berupa user ID (1), aksi berupa store_gadget, alasan berupa konfirmasi pembuatan oleh pengguna, serta disertakan identifier gadget_id dan gadget_name tanpa memuat data sensitif.

B. Alur Kegagalan Validasi dan Autentikasi (Tingkat WARNING)
- Alasan Pemilihan Alur:
Kegagalan validasi form dan kegagalan login perlu dipantau untuk mendeteksi adanya anomali pengiriman payload oleh klien atau potensi percobaan serangan brute force secara dini.
- Potongan Log Terstruktur:
[2026-11-05 10:06:47] production.WARNING: Validation failed for login {"user":"guest","action":"login_validation","reason":{"email":["The email field must be a valid email address."]}}
[2026-11-05 10:04:56] production.WARNING: Validation failed for storing gadget {"user":"1","action":"store_gadget_validation","reason":{"name":["The name field is required."]}}
- Penjelasan Konteks:
Aktor dicatat sebagai guest pada permintaan unauthenticated atau ID pengguna yang sedang login. Alasan memuat daftar field yang tidak memenuhi kriteria validasi. Data rahasia seperti password maupun alamat email tidak dicatat ke dalam log.

C. Alur Kegagalan Koneksi Basis Data dan Cache (Tingkat ERROR)
- Alasan Pemilihan Alur:
Kegagalan komunikasi dengan infrastruktur penyimpanan data merupakan masalah kritis yang mengganggu ketersediaan layanan. Log ini memberikan peringatan instan bagi tim operasional untuk segera melakukan penanganan saat database atau cache tidak dapat diakses.
- Potongan Log Terstruktur:
[2026-11-05 10:05:00] production.ERROR: Health check database failed {"user":"system","action":"health_check_database","reason":"SQLSTATE[HY000] [2002] Connection refused"}
- Penjelasan Konteks:
Aktor dicatat sebagai system karena dieksekusi oleh healthcheck berkala, aksi berupa health_check_database, dan alasan memuat pesan galat teknis kegagalan socket database.

[Gambar: Tangkapan layar terminal yang menampilkan ketiga log terstruktur di atas pada docker compose logs]


3. IMPLEMENTASI ENDPOINT /HEALTH DENGAN PEMERIKSAAN RIIL (SOAL 3)

Endpoint /health diimplementasikan melalui HealthController yang didaftarkan pada rute aplikasi Laravel dan diekspos melalui Nginx reverse proxy. Endpoint ini tidak sekadar mengembalikan string statis, melainkan melakukan pengujian fungsionalitas nyata pada dua komponen utama:
1. Pemeriksaan Basis Data: Mengeksekusi kueri ping koneksi melalui DB::connection()->getPdo() dan DB::select('SELECT 1').
2. Pemeriksaan Cache: Melakukan siklus operasi penyimpanan kunci sementara acak, pembacaan nilai kembali, dan pembersihan kunci melalui Cache::store().

Jika seluruh komponen berfungsi normal, endpoint mengembalikan kode status HTTP 200 OK dengan payload:
{
  "status": "healthy",
  "services": {
    "database": "healthy",
    "cache": "healthy"
  }
}

Jika salah satu atau seluruh komponen mengalami gangguan, endpoint mengembalikan kode status HTTP 503 Service Unavailable, merinci nama komponen yang bermasalah, menyertakan pesan kesalahan teknis, dan secara otomatis mencatat log tingkat ERROR. Contoh balasan saat basis data mati:
{
  "status": "unhealthy",
  "services": {
    "database": "unhealthy",
    "cache": "unhealthy"
  },
  "errors": {
    "database": "SQLSTATE[HY000] [2002] Connection refused",
    "cache": "SQLSTATE[HY000] [2002] Connection refused"
  }
}

[Gambar: Balasan curl -i http://localhost:8000/health saat kondisi sehat menghasilkan HTTP 200]
[Gambar: Balasan curl -i http://localhost:8000/health saat kondisi sakit menghasilkan HTTP 503]


4. KONFIGURASI HEALTHCHECK PADA DOCKER COMPOSE (SOAL 4)

Service backend pada berkas docker-compose.yml telah dikonfigurasi dengan pemeriksaan kesehatan berkala menggunakan perintah curl yang memanggil endpoint internal http://localhost:8000/health:

healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
  interval: 5s
  timeout: 3s
  retries: 3
  start_period: 15s

Parameter start_period sebesar 15 detik memberi waktu bagi proses migrasi dan seeding database selesai sebelum healthcheck aktif. Setelah kontainer berjalan normal, kolom STATUS pada output perintah docker compose ps secara otomatis berubah dari starting menjadi healthy.

[Gambar: Output docker compose ps yang menunjukkan kontainer wishradar-backend dan wishradar-db berstatus healthy]


5. PENGUJIAN KEGAGALAN DUA ARAH (SOAL 5)

Pengujian keandalan healthcheck dilakukan melalui simulasi kerusakan dan pemulihan:
1. Kondisi Normal: Seluruh service berjalan dan berstatus healthy.
2. Mematikan Basis Data: Layanan database dimatikan secara sengaja menggunakan perintah docker compose stop db.
3. Transisi ke Unhealthy: Pemeriksaan healthcheck mendeteksi kegagalan koneksi database, curl keluar dengan kode galat, balasan /health berubah menjadi HTTP 503, dan kolom STATUS kontainer backend pada docker compose ps berubah menjadi (unhealthy). Pada saat yang sama, log kontainer mencatat pesan tingkat ERROR dengan konteks penyebab kegagalan.
4. Pemulihan Layanan: Layanan database dinyalakan kembali menggunakan perintah docker compose start db.
5. Transisi Kembali ke Healthy: Setelah database siap menerima koneksi, healthcheck kembali berhasil, balasan /health pulih menjadi HTTP 200, dan kolom STATUS kontainer backend kembali berubah menjadi (healthy).

[Gambar: Output docker compose ps saat layanan backend berstatus unhealthy setelah db dimatikan]
[Gambar: Cuplikan log docker compose logs backend yang mencatat error kegagalan koneksi saat db dimatikan]
[Gambar: Output docker compose ps saat layanan backend kembali pulih menjadi healthy setelah db dinyalakan]


6. PEMBATASAN UKURAN LOG DENGAN MAX-SIZE DAN MAX-FILE (SOAL 6)

Untuk mencegah berkas log kontainer memenuhi kapasitas ruang penyimpanan host, diterapkan konfigurasi rotasi log pada seluruh service (db, backend, dan frontend) di dalam berkas docker-compose.yml:

logging:
  driver: "json-file"
  options:
    max-size: "10m"
    max-file: "3"

Konfigurasi ini membatasi ukuran maksimal satu berkas log kontainer sebesar 10 Megabyte dan menyimpan maksimal 3 berkas arsip rotasi sebelum berkas tertua ditimpa secara otomatis. Penerapan konfigurasi ini diverifikasi secara langsung pada kontainer yang sedang berjalan melalui perintah docker inspect wishradar-backend --format '{{json .HostConfig.LogConfig}}' yang menghasilkan output valid {"Type":"json-file","Config":{"max-file":"3","max-size":"10m"}}.

[Gambar: Potongan konfigurasi logging pada docker-compose.yml]
[Gambar: Output perintah docker inspect yang membuktikan konfigurasi batas log aktif pada kontainer]
