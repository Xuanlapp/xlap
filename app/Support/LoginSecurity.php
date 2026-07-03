<?php

namespace App\Support;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginSecurity
{
    private const MAX_ACCOUNT_ATTEMPTS = 5;

    private const MAX_IP_ATTEMPTS = 10;

    private const DECAY_SECONDS = 60;

    private const SOFT_LOCK_STEPS = [
        5 => 60,
        8 => 300,
        12 => 900,
    ];

    private const MIN_FORM_SECONDS = 1;

    public function assertCanAttempt(string $login, ?string $honeypot, ?int $startedAt, ?string $turnstileToken, string $errorKey): void
    {
        $this->ensureIpIsNotFlooding($errorKey);
        $this->ensureAccountIsNotRateLimited($login, $errorKey);

        if ($this->looksAutomated($honeypot, $startedAt)) {
            RateLimiter::hit($this->ipKey(), self::DECAY_SECONDS);
            RateLimiter::hit($this->accountKey($login), self::DECAY_SECONDS);

            Log::warning('Blocked suspicious login attempt.', [
                'ip' => request()->ip(),
                'login' => Str::limit($login, 120),
                'user_agent' => Str::limit((string) request()->userAgent(), 255),
            ]);

            throw ValidationException::withMessages([
                $errorKey => 'Sai tài khoản hoặc mật khẩu.',
            ]);
        }

        if (! app(TurnstileVerifier::class)->verify($turnstileToken)) {
            RateLimiter::hit($this->ipKey(), self::DECAY_SECONDS);
            RateLimiter::hit($this->accountKey($login), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                $errorKey => 'Vui lòng tích xác minh bảo mật Cloudflare.',
            ]);
        }

        RateLimiter::hit($this->ipKey(), self::DECAY_SECONDS);
    }

    public function recordFailedAttempt(string $login): void
    {
        $key = $this->accountKey($login);
        $attempts = RateLimiter::attempts($key) + 1;

        RateLimiter::hit($key, $this->decaySecondsFor($attempts));

        Log::notice('Failed login attempt.', [
            'ip' => request()->ip(),
            'login' => Str::limit($login, 120),
            'attempts' => $attempts,
            'user_agent' => Str::limit((string) request()->userAgent(), 255),
        ]);
    }

    public function clearSuccessfulAttempt(string $login): void
    {
        RateLimiter::clear($this->accountKey($login));
    }

    public function dummyPasswordHash(): string
    {
        return '$2y$12$QyG8RjbJXuzkB5w5X.jwCeLZzfMYSY0K5k/Bxuhix8cXX0e6GGEkW';
    }

    private function ensureIpIsNotFlooding(string $errorKey): void
    {
        if (! RateLimiter::tooManyAttempts($this->ipKey(), self::MAX_IP_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        throw ValidationException::withMessages([
            $errorKey => $this->throttleMessage($this->ipKey()),
        ]);
    }

    private function ensureAccountIsNotRateLimited(string $login, string $errorKey): void
    {
        $key = $this->accountKey($login);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ACCOUNT_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        throw ValidationException::withMessages([
            $errorKey => $this->throttleMessage($key),
        ]);
    }

    private function looksAutomated(?string $honeypot, ?int $startedAt): bool
    {
        if (filled($honeypot)) {
            return true;
        }

        if (! $startedAt) {
            return true;
        }

        return now()->timestamp - $startedAt < self::MIN_FORM_SECONDS;
    }

    private function throttleMessage(string $key): string
    {
        $seconds = RateLimiter::availableIn($key);
        $minutes = max(1, (int) ceil($seconds / 60));

        return "Bạn đăng nhập sai quá nhiều lần nên IP/tài khoản tạm bị khóa. Vui lòng thử lại sau {$minutes} phút.";
    }

    private function decaySecondsFor(int $attempts): int
    {
        $decay = self::DECAY_SECONDS;

        foreach (self::SOFT_LOCK_STEPS as $threshold => $seconds) {
            if ($attempts >= $threshold) {
                $decay = $seconds;
            }
        }

        return $decay;
    }

    private function accountKey(string $login): string
    {
        return 'login:account:'.Str::transliterate(Str::lower($login)).'|'.request()->ip();
    }

    private function ipKey(): string
    {
        return 'login:ip:'.request()->ip();
    }
}
