<?php

namespace App\Modules\Platform\Console;

use App\Modules\Platform\Notifications\OpsAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * Runs every five minutes. Looks for things that need a person — failed jobs, stuck
 * payment webhooks, the queue worker down, a backlog, low disk, a stale backup — and emails
 * one digest to OPS_ALERT_EMAIL. Each problem is repeated at most every six hours.
 */
class OpsCheck extends Command
{
    protected $signature = 'ops:check {--dry-run : Print problems instead of alerting}';

    protected $description = 'Check the system and alert the operator about problems';

    private const REPEAT_AFTER_MINUTES = 360;

    public function handle(): int
    {
        $problems = array_filter([
            'failed_jobs' => $this->failedJobs(),
            'webhooks' => $this->stuckWebhooks(),
            'horizon' => $this->horizonDown(),
            'backlog' => $this->queueBacklog(),
            'disk' => $this->lowDisk(),
            'backup' => $this->staleBackup(),
        ]);

        if ($this->option('dry-run')) {
            foreach ($problems ?: ['ok' => 'No problems found.'] as $key => $message) {
                $this->line("{$key}: {$message}");
            }

            return self::SUCCESS;
        }

        // Only alert about problems not already reported in the last few hours.
        $fresh = array_filter($problems, fn (string $message, string $key) => Cache::add("ops:alerted:{$key}", true, now()->addMinutes(self::REPEAT_AFTER_MINUTES)), ARRAY_FILTER_USE_BOTH);
        foreach (array_diff_key(['failed_jobs' => 1, 'webhooks' => 1, 'horizon' => 1, 'backlog' => 1, 'disk' => 1, 'backup' => 1], $problems) as $key => $_) {
            Cache::forget("ops:alerted:{$key}"); // resolved: alert again next time it happens
        }

        if ($fresh !== []) {
            Log::error('ops:check found problems', $fresh);
            $to = (string) config('app.ops_alert_email');
            if ($to !== '') {
                try {
                    Notification::route('mail', $to)->notifyNow(new OpsAlert(array_values($fresh)));
                } catch (Throwable $e) {
                    Log::critical('ops:check could not email the alert', ['error' => $e->getMessage()]);
                }
            }
        }

        return self::SUCCESS;
    }

    private function failedJobs(): ?string
    {
        $recent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();

        return $recent > 0 ? "{$recent} background job(s) failed in the last hour. Check Horizon or `php artisan queue:failed`." : null;
    }

    private function stuckWebhooks(): ?string
    {
        $stuck = DB::table('webhook_events')
            ->where(fn ($q) => $q->where('status', 'failed')->orWhere(fn ($w) => $w->where('status', 'pending')->where('received_at', '<', now()->subMinutes(15))))
            ->where('received_at', '>=', now()->subDay())
            ->count();

        return $stuck > 0 ? "{$stuck} Razorpay webhook(s) failed or have waited over 15 minutes — payments may not have been applied." : null;
    }

    private function horizonDown(): ?string
    {
        if (config('queue.default') !== 'redis') {
            return null;
        }
        try {
            $masters = app(MasterSupervisorRepository::class)->all();
        } catch (Throwable) {
            return 'Can’t reach Redis to check the queue worker.';
        }

        return $masters === [] ? 'The queue worker (Horizon) isn’t running: emails, invoices and video processing are paused.' : null;
    }

    private function queueBacklog(): ?string
    {
        if (config('queue.default') !== 'redis') {
            return null;
        }
        try {
            $waiting = app('queue')->connection('redis')->size('critical') + app('queue')->connection('redis')->size('default');
        } catch (Throwable) {
            return null; // reported by horizonDown
        }

        return $waiting > 500 ? "{$waiting} jobs are waiting in the queue." : null;
    }

    private function lowDisk(): ?string
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if (! $free || ! $total) {
            return null;
        }
        $pct = (int) round($free / $total * 100);

        return $pct < (int) config('app.ops_disk_min_percent', 10) ? "Only {$pct}% disk space left (".round($free / 1024 ** 3, 1).' GB).' : null;
    }

    private function staleBackup(): ?string
    {
        $dir = (string) config('app.backup_dir');
        if ($dir === '' || ! is_dir($dir)) {
            return null;
        }
        $latest = collect(glob(rtrim($dir, '/').'/db/*.dump') ?: [])->map(fn ($f) => filemtime($f))->max();
        if (! $latest) {
            return 'No database backup has been made yet.';
        }
        $hours = (int) floor((time() - $latest) / 3600);

        return $hours > 26 ? "The last database backup is {$hours} hours old." : null;
    }
}
