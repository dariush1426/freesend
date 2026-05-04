<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkUploadController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $maxFileSizeMb = max(1, (int) Setting::getValue('max_file_size_mb', '20'));

        $validated = $request->validate([
            'original_name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.($maxFileSizeMb * 1024 * 1024)],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:20000'],
        ]);

        $uploadId = (string) Str::uuid();
        $metaPath = $this->metaPath($uploadId);

        Storage::put($metaPath, json_encode([
            'owner_id' => Auth::id(),
            'original_name' => $validated['original_name'],
            'size' => (int) $validated['size'],
            'mime_type' => $validated['mime_type'] ?? null,
            'extension' => mb_strtolower(pathinfo($validated['original_name'], PATHINFO_EXTENSION)),
            'total_chunks' => (int) $validated['total_chunks'],
            'received_chunks' => [],
            'created_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE));

        return response()->json([
            'ok' => true,
            'upload_id' => $uploadId,
            'total_chunks' => (int) $validated['total_chunks'],
        ]);
    }

    public function part(Request $request): JsonResponse
    {
        $maxChunkSizeKb = max(1, (int) Setting::getValue('chunk_upload_size_mb', '2')) * 1024;

        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'chunk' => ['required', 'file', 'max:'.$maxChunkSizeKb],
        ]);

        $uploadId = (string) $validated['upload_id'];
        $meta = $this->readMeta($uploadId);

        if (! $meta || (int) ($meta['owner_id'] ?? 0) !== Auth::id()) {
            return response()->json(['ok' => false, 'message' => __('messages.chunk.invalid_upload')], 422);
        }

        if ((int) $validated['total_chunks'] !== (int) ($meta['total_chunks'] ?? 0)) {
            return response()->json(['ok' => false, 'message' => __('messages.chunk.invalid_total_chunks')], 422);
        }

        $index = (int) $validated['chunk_index'];

        if ($index >= (int) $meta['total_chunks']) {
            return response()->json(['ok' => false, 'message' => __('messages.chunk.invalid_chunk_index')], 422);
        }

        $chunkPath = $this->chunkPath($uploadId, $index);
        $directory = dirname($chunkPath);

        if (! Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        $chunk = $request->file('chunk');
        Storage::put($chunkPath, file_get_contents($chunk->getRealPath()));

        $received = collect($meta['received_chunks'] ?? [])
            ->push($index)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $meta['received_chunks'] = $received;
        Storage::put($this->metaPath($uploadId), json_encode($meta, JSON_UNESCAPED_UNICODE));

        return response()->json([
            'ok' => true,
            'received_chunks' => count($received),
            'total_chunks' => (int) $meta['total_chunks'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'uuid'],
        ]);

        $uploadId = (string) $validated['upload_id'];
        $meta = $this->readMeta($uploadId);

        if (! $meta || (int) ($meta['owner_id'] ?? 0) !== Auth::id()) {
            return response()->json(['ok' => false, 'message' => __('messages.chunk.invalid_upload')], 422);
        }

        return response()->json([
            'ok' => true,
            'upload_id' => $uploadId,
            'total_chunks' => (int) ($meta['total_chunks'] ?? 0),
            'received_chunks' => array_values(array_unique(array_map('intval', $meta['received_chunks'] ?? []))),
        ]);
    }

    public function finish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'uuid'],
        ]);

        $uploadId = (string) $validated['upload_id'];
        $meta = $this->readMeta($uploadId);

        if (! $meta || (int) ($meta['owner_id'] ?? 0) !== Auth::id()) {
            return response()->json(['ok' => false, 'message' => __('messages.chunk.invalid_upload')], 422);
        }

        $totalChunks = (int) ($meta['total_chunks'] ?? 0);
        $receivedChunks = collect($meta['received_chunks'] ?? [])->unique();

        if ($totalChunks < 1 || $receivedChunks->count() !== $totalChunks) {
            return response()->json(['ok' => false, 'message' => __('messages.chunk.missing_chunks')], 422);
        }

        $assembledDirectory = 'chunks/assembled/'.now()->format('Y/m');
        Storage::makeDirectory($assembledDirectory);

        $extension = (string) ($meta['extension'] ?? '');
        $assembledName = $uploadId.($extension !== '' ? '.'.$extension : '');
        $assembledPath = $assembledDirectory.'/'.$assembledName;

        $output = fopen(Storage::path($assembledPath), 'wb');

        if (! $output) {
            return response()->json(['ok' => false, 'message' => __('messages.chunk.cannot_create_file')], 500);
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $chunkPath = $this->chunkPath($uploadId, $index);

                if (! Storage::exists($chunkPath)) {
                    fclose($output);
                    return response()->json(['ok' => false, 'message' => __('messages.chunk.chunk_missing')], 422);
                }

                $input = fopen(Storage::path($chunkPath), 'rb');

                if (! $input) {
                    fclose($output);
                    return response()->json(['ok' => false, 'message' => __('messages.chunk.chunk_read_failed')], 500);
                }

                stream_copy_to_stream($input, $output);
                fclose($input);
            }
        } finally {
            fclose($output);
        }

        $token = Str::random(48);

        Cache::put(
            $this->tokenCacheKey($token),
            [
                'owner_id' => Auth::id(),
                'path' => $assembledPath,
                'original_name' => (string) ($meta['original_name'] ?? ''),
                'mime_type' => (string) ($meta['mime_type'] ?? ''),
                'size' => (int) ($meta['size'] ?? 0),
                'extension' => $extension,
            ],
            now()->addHours(6)
        );

        Storage::deleteDirectory('chunks/uploads/'.$uploadId);

        return response()->json([
            'ok' => true,
            'uploaded_file_token' => $token,
        ]);
    }

    private function metaPath(string $uploadId): string
    {
        return 'chunks/uploads/'.$uploadId.'/meta.json';
    }

    private function chunkPath(string $uploadId, int $chunkIndex): string
    {
        return 'chunks/uploads/'.$uploadId.'/parts/'.str_pad((string) $chunkIndex, 6, '0', STR_PAD_LEFT).'.part';
    }

    private function readMeta(string $uploadId): ?array
    {
        $path = $this->metaPath($uploadId);

        if (! Storage::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function tokenCacheKey(string $token): string
    {
        return 'chunk_upload_token:'.$token;
    }
}
