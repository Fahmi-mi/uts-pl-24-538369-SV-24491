<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $status = 'healthy';
        $services = [];
        $errors = [];

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $services['database'] = 'healthy';
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $services['database'] = 'unhealthy';
            $errors['database'] = $e->getMessage();
            Log::error('Health check database failed', [
                'user' => 'system',
                'action' => 'health_check_database',
                'reason' => $e->getMessage(),
            ]);
        }

        try {
            $testKey = 'health_check_'.bin2hex(random_bytes(8));
            Cache::put($testKey, true, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved !== true) {
                throw new \RuntimeException('Cache verification mismatch');
            }

            $services['cache'] = 'healthy';
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $services['cache'] = 'unhealthy';
            $errors['cache'] = $e->getMessage();
            Log::error('Health check cache failed', [
                'user' => 'system',
                'action' => 'health_check_cache',
                'reason' => $e->getMessage(),
            ]);
        }

        $statusCode = ($status === 'healthy') ? 200 : 503;

        $response = [
            'status' => $status,
            'services' => $services,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}
