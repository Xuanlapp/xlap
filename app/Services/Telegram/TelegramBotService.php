<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    /**
     * Send a plain text message to a Telegram chat.
     */
    public function sendMessage(string $chatId, string $text): void
    {
        if (! $this->enabled()) {
            Log::warning('Telegram bot is not configured.', ['chat_id' => $chatId]);

            return;
        }

        try {
            $response = Http::timeout((int) config('services.telegram_bot.timeout', 10))
                ->post($this->endpoint('sendMessage'), [
                    'chat_id' => $chatId,
                    'text' => mb_substr($text, 0, 3900),
                    'disable_web_page_preview' => true,
                ]);

            if ($response->failed()) {
                Log::warning('Telegram bot sendMessage failed.', [
                    'status' => $response->status(),
                    'body_preview' => mb_substr($response->body(), 0, 500),
                ]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Telegram bot connection failed.', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * Return whether the Telegram bot token is configured.
     */
    public function enabled(): bool
    {
        return filled(config('services.telegram_bot.token'));
    }

    /**
     * Build a Telegram Bot API endpoint URL.
     */
    private function endpoint(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram_bot.token').'/'.$method;
    }
}
