<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // seconds

    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public ?string $recipientName = null
    ) {}

    public function handle(): void
    {
        Mail::raw($this->body, function ($message) {
            $message->to($this->to, $this->recipientName ?? null)
                ->subject($this->subject)
                ->from(
                    config('mail.from.address', 'novatrust08@gmail.com'),
                    config('mail.from.name', 'NovaTrust')
                );
        });

        Log::info("[Queue][Email] sent to {$this->to} : {$this->subject}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[Queue][Email] FAILED to {$this->to} : {$this->subject} — {$e->getMessage()}");
    }
}
