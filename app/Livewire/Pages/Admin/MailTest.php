<?php

namespace App\Livewire\Pages\Admin;

use App\Services\Logging\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Throwable;

class MailTest extends Component
{
    public string $to = '';

    public string $subject = '';

    public string $body = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->subject = 'Offorest test mail '.now()->format('Y-m-d H:i:s');
        $this->body = 'Day la email test gui tu Offorest luc '.now()->format('Y-m-d H:i:s').'.';
    }

    /**
     * Send a plain test email through the configured mailer.
     */
    public function send(): void
    {
        $validated = $this->validate([
            'to' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->successMessage = null;
        $this->errorMessage = null;

        try {
            Mail::raw($validated['body'], function ($message) use ($validated): void {
                $message
                    ->to($validated['to'])
                    ->subject($validated['subject']);
            });

            app(ActivityLogService::class)->record(
                event: 'admin.mail_test_sent',
                description: 'Admin sent a test email.',
                properties: [
                    'to' => $validated['to'],
                    'subject' => $validated['subject'],
                    'mailer' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                ],
                actor: auth()->user(),
                actorType: 'admin',
            );

            $this->successMessage = 'Da gui mail test thanh cong. Hay check Inbox/Spam/All Mail cua email nhan.';
            $this->dispatch('toast', type: 'success', title: 'Mail sent', message: $this->successMessage);
        } catch (Throwable $exception) {
            Log::warning('Admin test email failed.', [
                'to' => $validated['to'],
                'subject' => $validated['subject'],
                'message' => $exception->getMessage(),
            ]);

            $this->errorMessage = $exception->getMessage();
            $this->dispatch('toast', type: 'error', title: 'Mail failed', message: $exception->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.pages.admin.mail-test', [
            'mailConfig' => [
                'mailer' => config('mail.default'),
                'scheme' => config('mail.mailers.smtp.scheme'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'from' => config('mail.from.address'),
                'fromName' => config('mail.from.name'),
            ],
        ])->layout('layouts.app');
    }
}
