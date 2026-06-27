<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Services\Vertex\VertexImageGenerator;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TelegramAiAssistantService
{
    public function __construct(
        private readonly VertexImageGenerator $vertexImageGenerator,
    ) {}

    /**
     * Rewrite a flight recommendation with the configured Vertex text model.
     */
    public function polishFlightReply(string $baseReply): string
    {
        if (! (bool) config('services.telegram_bot.ai_enabled', true)) {
            return $baseReply;
        }

        $user = $this->vertexUser();

        if (! $user) {
            return $baseReply;
        }

        try {
            $prompt = implode("\n", [
                'Bạn là trợ lý Telegram chuyên tư vấn vé máy bay cho người Việt.',
                'Viết lại nội dung dưới đây cho tự nhiên, ngắn gọn, dễ đọc.',
                'Không được tự thêm giá, hãng bay, giờ bay, hoặc dữ liệu không có trong nội dung.',
                'Nếu nội dung nói dữ liệu demo thì phải giữ cảnh báo đó.',
                'Trả lời bằng tiếng Việt, không markdown bảng.',
                '',
                'Nội dung:',
                $baseReply,
            ]);

            $reply = trim($this->vertexImageGenerator->generateText($user, $prompt));

            return $reply !== '' ? $reply : $baseReply;
        } catch (Throwable $exception) {
            Log::warning('Telegram AI polish failed.', ['message' => $exception->getMessage()]);

            return $baseReply;
        }
    }

    /**
     * Resolve a user only because the existing Vertex text service requires one.
     */
    private function vertexUser(): ?User
    {
        $userId = config('services.telegram_bot.vertex_user_id');

        if (filled($userId)) {
            return User::query()->find((int) $userId);
        }

        return User::query()
            ->where('is_admin', true)
            ->orderBy('id')
            ->first()
            ?? User::query()->orderBy('id')->first();
    }
}
