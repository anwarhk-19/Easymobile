<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\LogViewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    public function __construct(private LogViewer $logs) {}

    public function files(): JsonResponse
    {
        return response()->json(['files' => $this->logs->files(), 'channel' => config('logging.default'), 'max_bytes' => LogViewer::MAX_BYTES]);
    }

    public function show(Request $request, string $file): JsonResponse
    {
        $data = $request->validate([
            'level' => ['nullable', Rule::in(LogViewer::LEVELS)],
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);
        $path = $this->logs->path($file) ?? abort(404, 'Log file not found.');
        $result = $this->logs->entries($path, $data['level'] ?? null, $data['q'] ?? null);

        $perPage = $data['per_page'] ?? 50;
        $page = $data['page'] ?? 1;
        $paginator = new LengthAwarePaginator(array_slice($result['entries'], ($page - 1) * $perPage, $perPage), $result['total'], $perPage, $page);

        return response()->json($paginator->toArray() + ['counts' => $result['counts'], 'truncated' => $result['truncated'], 'file' => $file, 'size' => filesize($path)]);
    }

    public function download(string $file): StreamedResponse
    {
        $path = $this->logs->path($file) ?? abort(404, 'Log file not found.');
        AuditLogger::log('logs.downloaded', null, "Downloaded log file {$file}");

        return response()->streamDownload(function () use ($path) {
            $fh = fopen($path, 'rb');
            while (($line = fgets($fh)) !== false) {
                echo LogViewer::mask($line);
            }
            fclose($fh);
        }, $file, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Empties a log file (the file itself is kept so the logger can keep writing). */
    public function clear(string $file): JsonResponse
    {
        $path = $this->logs->path($file) ?? abort(404, 'Log file not found.');
        $size = filesize($path);
        file_put_contents($path, '');
        AuditLogger::log('logs.cleared', null, "Cleared log file {$file}", ['bytes' => $size]);

        return response()->json(['message' => "{$file} cleared."]);
    }
}
