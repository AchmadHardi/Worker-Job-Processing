<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NotificationJob;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function enqueue(Request $request)
    {
        $v = Validator::make($request->all(), [
            'recipient' => 'required|string',
            'channel' => 'required|in:email,sms',
            'message' => 'required|string',
            'idempotency_key' => 'nullable|string|max:255'
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $payload = $request->only(['recipient', 'channel', 'message']);
        $idempotencyKey = $request->input('idempotency_key');

        return DB::transaction(function () use ($payload, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = NotificationJob::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return response()->json([
                        'job_id' => $existing->id,
                        'status' => $existing->status
                    ], 201);
                }
            }


            $job = NotificationJob::create([
                'channel' => $payload['channel'],
                'recipient' => $payload['recipient'],
                'message' => $payload['message'],
                'status' => 'PENDING',
                'attempts' => 0,
                'max_attempts' => 5,
                'next_run_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);


            $success = rand(1, 100) <= 70;
            $attempts = $job->attempts + 1;

            if ($success) {
                $job->update([
                    'status' => 'SUCCESS',
                    'attempts' => $attempts,
                    'processed_at' => now(),
                ]);
            } else {
                if ($attempts >= $job->max_attempts) {
                    $job->update([
                        'status' => 'FAILED',
                        'attempts' => $attempts,
                        'last_error' => 'Simulated failure after max attempts',
                    ]);
                } else {
                    $delay = pow(2, $attempts);
                    $job->update([
                        'status' => 'RETRY',
                        'attempts' => $attempts,
                        'next_run_at' => now()->addSeconds($delay),
                        'last_error' => 'Temporary failure, retry scheduled',
                    ]);
                }
            }


            $final = $job->fresh();

            if ($final->status === 'SUCCESS') {
                return response()->json([
                    'job_id' => $final->id,
                    'status' => $final->status,
                    'attempts' => $final->attempts,
                ], 200);
            }

            if ($final->status === 'RETRY') {
                return response()->json([
                    'job_id' => $final->id,
                    'status' => $final->status,
                    'attempts' => $final->attempts,
                    'next_run_at' => $final->next_run_at,
                ], 202);
            }

            if ($final->status === 'FAILED') {
                return response()->json([
                    'job_id' => $final->id,
                    'status' => $final->status,
                    'attempts' => $final->attempts,
                    'last_error' => $final->last_error,
                ], 500);
            }



            return response()->json([
                'job_id' => $job->id,
                'status' => $job->status
            ], 201);
        });
    }


    public function stats()
    {
        $rows = DB::table('notification_jobs')
            ->selectRaw("status, COUNT(*) as c, AVG(attempts) as avg_attempts")
            ->groupBy('status')
            ->get();

        $map = ['PENDING' => 0, 'RETRY' => 0, 'PROCESSING' => 0, 'SUCCESS' => 0, 'FAILED' => 0];
        $avgSuccess = 0.0;

        foreach ($rows as $r) {
            $map[$r->status] = (int) $r->c;
            if ($r->status === 'SUCCESS') $avgSuccess = (float) $r->avg_attempts;
        }

        return response()->json([
            'pending' => $map['PENDING'],
            'retry' => $map['RETRY'],
            'processing' => $map['PROCESSING'],
            'success' => $map['SUCCESS'],
            'failed' => $map['FAILED'],
            'avg_attempts_success' => round($avgSuccess, 2)
        ]);
    }
}
