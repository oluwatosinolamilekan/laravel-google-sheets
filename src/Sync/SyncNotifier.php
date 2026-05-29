<?php

namespace Olamilekan\GoogleSheets\Sync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SyncNotifier
{
    public static function send(SyncReport $report, array $channels = []): void
    {
        if (isset($channels['callback']) && is_callable($channels['callback'])) {
            $channels['callback']($report);
        }

        if (! empty($channels['slack_webhook'])) {
            Http::post($channels['slack_webhook'], [
                'text' => static::message($report),
                'report' => $report->toArray(),
            ]);
        }

        if (! empty($channels['mail_to'])) {
            Mail::raw(static::message($report), function ($message) use ($channels, $report) {
                $message->to($channels['mail_to'])
                    ->subject($channels['subject'] ?? 'Google Sheets sync ' . ($report->successful() ? 'completed' : 'needs attention'));
            });
        }
    }

    public static function message(SyncReport $report): string
    {
        $counts = $report->counts();

        return sprintf(
            'Google Sheets %s sync finished: %d created, %d updated, %d deleted, %d skipped, %d conflicts, %d failed.',
            $report->operation(),
            $counts['created'],
            $counts['updated'],
            $counts['deleted'],
            $counts['skipped'],
            $counts['conflicts'],
            $counts['failed'],
        );
    }
}
