<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JobsWorker extends Command
{
    protected $signature = 'jobs:worker';
    protected $description = 'Process notification jobs asynchronously';

    public function handle()
    {
        $this->info("Worker started...");

        while (true) {

            $this->recoverStuckJobs();

            DB::beginTransaction();

            $job = DB::table('notification_jobs')
                ->whereIn('status', ['PENDING', 'RETRY'])
                ->where('next_run_at', '<=', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$job) {
                DB::commit();
                sleep(1);
                continue;
            }

            DB::table('notification_jobs')->where('id', $job->id)
                ->update([
                    'status' => 'PROCESSING',
                    'updated_at' => now(),
                    'next_run_at' => now()->addSeconds(60),
                ]);

            DB::commit();

            try {
                $this->processJob($job);
            } catch (Throwable $e) {
                Log::error("Job {$job->id} failed: " . $e->getMessage());
            }
        }
    }

    private function recoverStuckJobs()
    {
        $timeoutSeconds = 60;

        $stuckJobs = DB::table('notification_jobs')
            ->where('status', 'PROCESSING')
            ->where('updated_at', '<', now()->subSeconds($timeoutSeconds))
            ->get();

        foreach ($stuckJobs as $job) {
            $attempts = $job->attempts + 1;
            $max = $job->max_attempts;

            if ($attempts >= $max) {
                DB::table('notification_jobs')->where('id', $job->id)->update([
                    'status' => 'FAILED',
                    'last_error' => 'Timeout after PROCESSING crash',
                    'attempts' => $attempts,
                    'updated_at' => now(),
                ]);
                $this->error("⚠️ Job {$job->id} marked as FAILED due to timeout");
            } else {
                DB::table('notification_jobs')->where('id', $job->id)->update([
                    'status' => 'RETRY',
                    'attempts' => $attempts,
                    'next_run_at' => now()->addSeconds(10),
                    'last_error' => 'Recovered from PROCESSING timeout',
                    'updated_at' => now(),
                ]);
                $this->warn("🔁 Job {$job->id} recovered to RETRY");
            }
        }
    }

    private function processJob($job)
    {
        $current = DB::table('notification_jobs')->where('id', $job->id)->first();
        $attempts = $current->attempts + 1;
        $max = $current->max_attempts;

        $success = rand(1, 100) <= 70;

        if ($success) {
            DB::table('notification_jobs')->where('id', $job->id)->update([
                'status' => 'SUCCESS',
                'attempts' => $attempts,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("✅ Job {$job->id} success (attempt {$attempts})");
            return;
        }

        if ($attempts >= $max) {
            DB::table('notification_jobs')->where('id', $job->id)->update([
                'status' => 'FAILED',
                'attempts' => $attempts,
                'last_error' => 'Simulated failure after max attempts',
                'updated_at' => now(),
            ]);
            $this->error("❌ Job {$job->id} failed permanently");
            return;
        }

        $baseDelay = pow(2, $attempts);
        $jitter = $baseDelay * (rand(0, 30) / 100);
        $delay = $baseDelay + $jitter;

        DB::table('notification_jobs')->where('id', $job->id)->update([
            'status' => 'RETRY',
            'attempts' => $attempts,
            'next_run_at' => now()->addSeconds($delay),
            'last_error' => 'Temporary failure, retry scheduled',
            'updated_at' => now(),
        ]);

        $this->warn("🔁 Job {$job->id} retry in {$delay}s (attempt {$attempts})");
    }
}
