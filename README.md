# Worker Job Processor (Laravel)

Sistem sederhana untuk memproses notifikasi secara asynchronous menggunakan Laravel.

---

## 🚀 Cara Menjalankan

### 1. Clone project
```bash
git clone <repo-url>
cd worker-job-processor

2. Copy konfigurasi environment

cp .env.example .env
php artisan key:generate


3. Migrasi database

php artisan migrate

4. Jalankan API server

php artisan serve
Endpoint tersedia di:
POST http://127.0.0.1:8000/api/notifications

5. Jalankan Worker
php artisan jobs:worker

📡 Endpoint API

POST /api/notifications
Request Body

{
  "recipient": "user@example.com",
  "channel": "email",
  "message": "Halo dari sistem worker!",
  "idempotency_key": "abc123"
}

Response

{
    "job_id": "581afe44-73ae-487d-bb22-7d96af5bf915",
    "status": "PENDING"
}

GET /internal/queue/stats
Menampilkan statistik antrian:

{
  "pending": 0,
  "retry": 0,
  "processing": 0,
  "success": 4,
  "failed": 0,
  "avg_attempts_success": 0
}

🧠 Penjelasan Keputusan Teknis

1 Tanpa Message Broker (SQS/RabbitMQ):
Worker menggunakan database sebagai queue store, agar tetap sederhana dan tidak memerlukan service eksternal.

2 Locking via DB::transaction() dan lockForUpdate():
Digunakan untuk memastikan satu job hanya diambil oleh satu worker pada waktu yang sama.


3. Status Lifecycle:

PENDING → PROCESSING → SUCCESS / FAILED
              
RETRY (jika gagal)

4 UUID sebagai Primary Key:
Menghindari prediksi ID, memudahkan integrasi sistem terdistribusi.

5 Worker Loop:
Worker terus berjalan (while(true)) dengan delay kecil untuk efisiensi CPU.

🔁 Strategi Retry / Backoff / Jitter
Jika job gagal, worker akan mencoba ulang dengan exponential backoff:
Delay = 2^attempts + jitter

Contoh:

Attempt 1 → 2 detik

Attempt 2 → 4 detik

Attempt 3 → 8 detik

Maksimal percobaan (max_attempts) = 5 kali
Setelah itu job akan ditandai FAILED.

⏳ Mekanisme Visibility Timeout & Anti Double-Processing
Visibility Timeout (60 detik)

Setiap job yang sedang PROCESSING diberi batas waktu (60 detik).

Jika worker crash atau berhenti di tengah jalan, job yang “macet” akan dipulihkan otomatis oleh fungsi recoverStuckJobs().

Fungsi Recovery:
$stuckJobs = DB::table('notification_jobs')
  ->where('status', 'PROCESSING')
  ->where('updated_at', '<', now()->subSeconds(60))
  ->get();

Job yang terdeteksi timeout:

Akan diubah ke RETRY jika masih bisa dicoba lagi.

Akan diubah ke FAILED jika sudah melebihi batas max_attempts.

Anti Double Processing

Gunakan lockForUpdate() dalam transaksi database.

Job diambil oleh worker hanya ketika statusnya PENDING/RETRY dan langsung dikunci (update ke PROCESSING).

Tidak ada dua worker yang bisa mengambil job yang sama.

📋 Contoh Hasil Worker

Worker started...
✅ Job 3f7e4f... success
🔁 Job a09b9c... retry in 4.3s
⚠️ Job c12fa2... marked as FAILED due to timeout

🧩 Kesimpulan

✅ Tidak pakai broker eksternal
✅ Retry otomatis dengan exponential backoff
✅ Visibility timeout untuk pemulihan crash
✅ Idempotent dan anti double-processing
✅ Monitoring sederhana lewat endpoint stats
