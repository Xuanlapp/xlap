<?php

namespace App\Services\Telegram;

use App\Models\TelegramConversation;
use App\Services\Flight\FlightIntentParser;
use App\Services\Flight\FlightRecommendationService;
use App\Services\Flight\FlightSearchService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramFlightAssistantService
{
    public function __construct(
        private readonly FlightIntentParser $intentParser,
        private readonly FlightSearchService $flightSearchService,
        private readonly FlightRecommendationService $recommendationService,
        private readonly TelegramBotService $telegramBotService,
        private readonly TelegramAiAssistantService $telegramAiAssistantService,
    ) {}

    /**
     * Handle an incoming Telegram message.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $message = trim((string) Arr::get($payload, 'message.text', ''));
        $chatId = (string) Arr::get($payload, 'message.chat.id', '');
        $telegramUserId = (string) Arr::get($payload, 'message.from.id', '');

        if ($message === '' || $chatId === '' || $telegramUserId === '') {
            return;
        }

        try {
            $this->handleMessage($message, $chatId, $telegramUserId);
        } catch (Throwable $exception) {
            Log::error('Telegram flight assistant failed.', [
                'chat_id' => $chatId,
                'telegram_user_id' => $telegramUserId,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $this->telegramBotService->sendMessage(
                $chatId,
                'Bot đang gặp lỗi khi xử lý yêu cầu này. Bạn thử gửi lại: Check vé sài gòn hà nội',
            );
        }
    }

    /**
     * Handle a validated Telegram text message.
     */
    private function handleMessage(string $message, string $chatId, string $telegramUserId): void
    {
        $conversation = TelegramConversation::query()->firstOrCreate(
            [
                'telegram_user_id' => $telegramUserId,
                'chat_id' => $chatId,
            ],
            [
                'state' => 'waiting_departure_date',
                'context' => [
                    'origin' => 'SGN',
                    'destination' => 'HAN',
                    'trip_type' => 'one_way',
                ],
            ],
        );

        $context = is_array($conversation->context) ? $conversation->context : [];
        $parsed = $this->intentParser->parse($message, $context);

        Log::info('Telegram flight assistant message parsed.', [
            'chat_id' => $chatId,
            'state' => $conversation->state,
            'parsed' => $parsed,
            'context' => $context,
        ]);

        if ($conversation->state === 'waiting_confirmation' && $parsed['is_confirmation'] === true) {
            $this->replyWithFlights($conversation, $chatId, $context);

            return;
        }

        $newContext = [
            'origin' => $parsed['origin'],
            'destination' => $parsed['destination'],
            'departure_date' => $parsed['departure_date'],
            'trip_type' => $parsed['trip_type'],
        ];

        if ($parsed['needs_departure_date'] === true) {
            $this->saveConversation($conversation, 'waiting_departure_date', $newContext, $message);
            $this->telegramBotService->sendMessage($chatId, 'Bạn muốn đi ngày nào cho chặng SGN → HAN? Ví dụ: 15/07/2026 hoặc ngày mai.');

            return;
        }

        $date = (string) $parsed['departure_date'];
        $this->saveConversation($conversation, 'waiting_confirmation', $newContext, $message);
        $this->telegramBotService->sendMessage($chatId, "Bạn muốn bay {$parsed['origin']} → {$parsed['destination']} ngày {$date} đúng không? Nếu đúng thì trả lời 'đúng'.");
    }

    /**
     * Reply with flights for the stored conversation context.
     *
     * @param  array<string, mixed>  $context
     */
    private function replyWithFlights(TelegramConversation $conversation, string $chatId, array $context): void
    {
        $origin = (string) ($context['origin'] ?? 'SGN');
        $destination = (string) ($context['destination'] ?? 'HAN');
        $departureDate = (string) ($context['departure_date'] ?? now()->toDateString());

        if ($departureDate === '') {
            $this->saveConversation($conversation, 'waiting_departure_date', $context, 'missing_departure_date');
            $this->telegramBotService->sendMessage($chatId, 'Mình chưa thấy ngày đi. Bạn gửi lại ngày đi giúp mình nhé, ví dụ 10/10/2026.');

            return;
        }

        $this->saveConversation($conversation, 'searching_flights', $context, 'confirmed');

        $flights = $this->flightSearchService->search($origin, $destination, $departureDate);
        $baseReply = $this->recommendationService->summarize($flights, $origin, $destination, $departureDate);
        $reply = $this->telegramAiAssistantService->polishFlightReply($baseReply);

        $this->telegramBotService->sendMessage($chatId, $reply);
        $this->saveConversation($conversation, 'completed', $context, 'replied');
    }

    /**
     * Persist conversation state changes.
     *
     * @param  array<string, mixed>  $context
     */
    private function saveConversation(TelegramConversation $conversation, string $state, array $context, string $lastMessage): void
    {
        $conversation->update([
            'state' => $state,
            'context' => $context,
            'last_message' => $lastMessage,
        ]);
    }
}
