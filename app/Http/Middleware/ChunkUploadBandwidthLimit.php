<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ChunkUploadBandwidthLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $chunk = $request->file('chunk');

        if (! $chunk) {
            return $next($request);
        }

        $limitMb = max(1, (int) Setting::getValue('chunk_upload_max_mb_per_minute', '80'));
        $limitBytes = $limitMb * 1024 * 1024;
        $userId = Auth::id() ?: 0;
        $bucket = now()->format('YmdHi');
        $cacheKey = 'chunk_upload_bw:'.$userId.':'.$bucket;

        Cache::add($cacheKey, 0, now()->addMinutes(2));

        $usedBytes = (int) Cache::increment($cacheKey, (int) $chunk->getSize());

        if ($usedBytes <= $limitBytes) {
            return $next($request);
        }

        return new JsonResponse([
            'ok' => false,
            'message' => __('messages.chunk.bandwidth_limit', ['limit' => $limitMb]),
        ], 429);
    }
}
