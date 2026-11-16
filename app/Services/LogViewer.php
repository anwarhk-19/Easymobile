<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Read-only access to Laravel log files in storage/logs for the admin log viewer.
 *
 * Safety:
 *  - Only *.log files directly inside storage/logs can be opened (no paths / traversal).
 *  - Only the last MAX_BYTES of a file are parsed, so huge logs can't exhaust memory.
 *  - One-time passwords that the log SMS driver writes are masked before display or download.
 */
class LogViewer
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    private const ENTRY = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+([\w-]+)\.(\w+):\s?(.*)$/';

    public function directory(): string
    {
        return storage_path('logs');
    }

    /** @return array<int, array{name:string,size:int,modified_at:string}> */
    public function files(): array
    {
        return collect(File::glob($this->directory().'/*.log'))
            ->map(fn ($p) => ['name' => basename($p), 'size' => filesize($p), 'modified_at' => Carbon::createFromTimestamp(filemtime($p))->toIso8601String()])
            ->sortByDesc('modified_at')->values()->all();
    }

    /** Resolves a user-supplied file name to a real path, or null if it isn't an existing log file. */
    public function path(string $name): ?string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.log$/', $name)) {
            return null;
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return is_file($path) && dirname(realpath($path)) === realpath($this->directory()) ? $path : null;
    }

    /**
     * Parses entries newest-first, applying optional level/search filters.
     *
     * @return array{entries: array, total:int, truncated:bool, counts: array<string,int>}
     */
    public function entries(string $path, ?string $level = null, ?string $search = null): array
    {
        $size = filesize($path);
        $truncated = $size > self::MAX_BYTES;
        $fh = fopen($path, 'rb');
        if ($truncated) {
            fseek($fh, -self::MAX_BYTES, SEEK_END);
            fgets($fh); // drop the partial first line
        }

        $entries = [];
        $current = null;
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if (preg_match(self::ENTRY, $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = ['datetime' => $m[1], 'env' => $m[2], 'level' => strtolower($m[3]), 'message' => self::mask($m[4]), 'context' => []];
            } elseif ($current) {
                $current['context'][] = self::mask($line);
            }
        }
        if ($current) {
            $entries[] = $current;
        }
        fclose($fh);

        $counts = array_fill_keys(self::LEVELS, 0);
        foreach ($entries as $e) {
            $counts[$e['level']] = ($counts[$e['level']] ?? 0) + 1;
        }

        $search = $search !== null ? mb_strtolower(trim($search)) : '';
        $filtered = array_values(array_filter($entries, function ($e) use ($level, $search) {
            if ($level && $e['level'] !== $level) {
                return false;
            }

            return $search === '' || str_contains(mb_strtolower($e['message']), $search)
                || str_contains(mb_strtolower(implode("\n", $e['context'])), $search);
        }));

        $filtered = array_reverse($filtered);
        foreach ($filtered as &$e) {
            $e['context'] = implode("\n", $e['context']);
            $e['context'] = mb_strlen($e['context']) > 20000 ? mb_substr($e['context'], 0, 20000)."\n… (truncated)" : $e['context'];
        }

        return ['entries' => $filtered, 'total' => count($filtered), 'truncated' => $truncated, 'counts' => $counts];
    }

    /** Masks OTP codes written by the log SMS driver ("123456 is your … verification code"). */
    public static function mask(string $text): string
    {
        return preg_replace('/\b\d{4,8}(?=\s+is your\b)/i', '••••••', $text);
    }
}
