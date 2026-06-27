<?php

namespace App\Services\Telegram;

use App\Models\TelegramConversation;
use App\Services\Flight\FlightIntentParser;
use App\Services\Flight\FlightRecommendationService;
use App\Services\Flight\FlightSearchService;
use Illuminate\Support\Arr;

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
        $flights = $this->flightSearchService->search($origin, $destination, $departureDate);
        $baseReply = $this->recommendationService->summarize($flights, $origin, $destination, $departureDate);
        $reply = $this->telegramAiAssistantService->polishFlightReply($baseReply);

        $this->saveConversation($conversation, 'completed', $context, 'confirmed');
        $this->telegramBotService->sendMessage($chatId, $reply);
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
