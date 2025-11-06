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
  "job_id": 1,
  "status": "PENDING"
}

GET /internal/queue/stats
Menampilkan statistik antrian:

{
  "pending": 2,
  "retry": 1,
  "processing": 0,
  "success": 5,
  "failed": 0,
  "avg_attempts_success": 1.3
}

