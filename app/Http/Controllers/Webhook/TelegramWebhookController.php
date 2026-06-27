<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramFlightAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramFlightAssistantService $assistantService,
    ) {}

    /**
     * Handle Telegram webhook payloads.
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->hasValidSecret($request)) {
            return response()->json(['ok' => false], 403);
        }

        $this->assistantService->handle($request->all());

        return response()->json(['ok' => true]);
    }

    /**
     * Validate Telegram secret token when configured.
     */
    private function hasValidSecret(Request $request): bool
    {
        $secret = config('services.telegram_bot.webhook_secret');

        if (! filled($secret)) {
            return true;
        }

        return hash_equals((string) $secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''));
    }
}
