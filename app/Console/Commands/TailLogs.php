<?php

namespace App\Console\Commands;

use App\Services\LogViewer;
use Illuminate\Console\Command;

/**
 * Live log viewer for SSH (works even when the website/API is down).
 *
 *   php artisan bmh:logs                 last 50 entries
 *   php artisan bmh:logs -f              follow live (Ctrl+C to stop)
 *   php artisan bmh:logs -f --level=error
 *   php artisan bmh:logs --lines=200 --grep=SQLSTATE --trace
 */
class TailLogs extends Command
{
    protected $signature = 'bmh:logs
        {--f|follow : Keep watching and print new entries as they are written}
        {--lines=50 : How many recent entries to show first}
        {--level= : Only show this level and more severe (debug, info, notice, warning, error, critical, alert, emergency)}
        {--grep= : Only show entries containing this text}
        {--trace : Show full stack traces instead of the first lines}
        {--file= : Log file in storage/logs (default: the most recently updated)}';

    protected $description = 'Show and live-follow application logs in the terminal (OTP codes masked)';

    private const COLORS = ['emergency' => 'red', 'alert' => 'red', 'critical' => 'red', 'error' => 'red', 'warning' => 'yellow', 'notice' => 'cyan', 'info' => 'green', 'debug' => 'gray'];

    private const ENTRY = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+([\w-]+)\.(\w+):\s?(.*)$/';

    private ?array $current = null;

    public function handle(LogViewer $viewer): int
    {
        $level = $this->option('level') ? strtolower($this->option('level')) : null;
        if ($level && ! in_array($level, LogViewer::LEVELS, true)) {
            $this->error('Unknown level. Use one of: '.implode(', ', LogViewer::LEVELS));

            return self::INVALID;
        }

        $name = $this->option('file') ?: ($viewer->files()[0]['name'] ?? 'laravel.log');
        $path = $viewer->path($name);
        if (! $path) {
            if (! $this->option('follow')) {
                $this->warn("No log file storage/logs/{$name} yet — nothing has been logged.");

                return self::SUCCESS;
            }
            $path = storage_path('logs/'.$name);
            touch($path);
        }

        $this->line("<fg=gray>── storage/logs/{$name}".($level ? " · {$level}+" : '').($this->option('grep') ? ' · matching "'.$this->option('grep').'"' : '').' ──</>');

        // Recent history.
        $recent = $viewer->entries($path, null, $this->option('grep'))['entries'];
        $recent = array_values(array_filter($recent, fn ($e) => $this->passesLevel($e['level'], $level)));
        foreach (array_reverse(array_slice($recent, 0, max(0, (int) $this->option('lines')))) as $e) {
            $this->printEntry($e['datetime'], $e['level'], $e['message'], $e['context'] === '' ? [] : explode("\n", $e['context']));
        }

        if (! $this->option('follow')) {
            return self::SUCCESS;
        }

        $this->line('<fg=gray>── following new entries, press Ctrl+C to stop ──</>');
        $pos = filesize($path);
        while (true) {
            clearstatcache(true, $path);
            $size = @filesize($path);
            if ($size === false) {
                sleep(1);

                continue;
            }
            if ($size < $pos) {
                $this->line('<fg=gray>── log file was cleared/rotated ──</>');
                $pos = 0;
            }
            if ($size > $pos) {
                $fh = fopen($path, 'rb');
                fseek($fh, $pos);
                while (($line = fgets($fh)) !== false) {
                    $this->consume(rtrim($line, "\r\n"), $level);
                }
                $pos = ftell($fh);
                fclose($fh);
                $this->flushCurrent($level);
            }
            usleep(500_000);
        }
    }

    private function consume(string $line, ?string $level): void
    {
        if (preg_match(self::ENTRY, $line, $m)) {
            $this->flushCurrent($level);
            $this->current = [$m[1], strtolower($m[3]), $m[4], []];
        } elseif ($this->current) {
            $this->current[3][] = $line;
        }
    }

    private function flushCurrent(?string $level): void
    {
        if (! $this->current) {
            return;
        }
        [$dt, $lvl, $msg, $ctx] = $this->current;
        $this->current = null;
        $grep = $this->option('grep');
        if (! $this->passesLevel($lvl, $level) || ($grep && stripos($msg."\n".implode("\n", $ctx), $grep) === false)) {
            return;
        }
        $this->printEntry($dt, $lvl, LogViewer::mask($msg), array_map([LogViewer::class, 'mask'], $ctx));
    }

    private function printEntry(string $dt, string $lvl, string $msg, array $ctx): void
    {
        $color = self::COLORS[$lvl] ?? 'white';
        $this->line(sprintf('<fg=gray>%s</> <fg=%s;options=bold>%-9s</> %s', substr($dt, 0, 19), $color, strtoupper($lvl), $this->escape($msg)));
        if ($ctx) {
            $show = $this->option('trace') ? $ctx : array_slice($ctx, 0, 4);
            foreach ($show as $c) {
                $this->line('<fg=gray>    '.$this->escape($c).'</>');
            }
            if (! $this->option('trace') && count($ctx) > 4) {
                $this->line('<fg=gray>    … '.(count($ctx) - 4).' more lines (use --trace)</>');
            }
        }
    }

    private function passesLevel(string $lvl, ?string $min): bool
    {
        if (! $min) {
            return true;
        }
        $rank = array_flip(LogViewer::LEVELS); // emergency=0 … debug=7

        return ($rank[$lvl] ?? 7) <= $rank[$min];
    }

    private function escape(string $s): string
    {
        return str_replace(['<', '>'], ['\\<', '\\>'], $s);
    }
}
