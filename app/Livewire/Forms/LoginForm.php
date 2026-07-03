<?php

namespace App\Livewire\Forms;

use App\Models\User;
use App\Support\LoginSecurity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $login = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    public string $website = '';

    public int $startedAt = 0;

    public string $turnstileToken = '';

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        app(LoginSecurity::class)->assertCanAttempt(
            $this->login,
            $this->website,
            $this->startedAt,
            $this->turnstileToken,
            'form.turnstileToken'
        );

        $security = app(LoginSecurity::class);
        $user = $this->userForLogin($this->login);
        $passwordHash = $user?->password ?? $security->dummyPasswordHash();

        if (! $user || ! Hash::check($this->password, $passwordHash)) {
            app(LoginSecurity::class)->recordFailedAttempt($this->login);

            throw ValidationException::withMessages([
                'form.password' => 'Sai tài khoản hoặc mật khẩu.',
            ]);
        }

        Auth::login($user, $this->remember);

        app(LoginSecurity::class)->clearSuccessfulAttempt($this->login);
    }

    /**
     * Resolve a user by email or username, with legacy name fallback.
     */
    private function userForLogin(string $login): ?User
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $login)->first();
        }

        return User::where('username', $login)
            ->orWhere(function ($query) use ($login): void {
                $query
                    ->whereNull('username')
                    ->where('name', $login);
            })
            ->first();
    }
}
