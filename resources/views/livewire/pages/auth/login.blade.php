<?php

use App\Livewire\Forms\LoginForm;
use App\Support\TurnstileVerifier;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function mount(): void
    {
        $this->form->startedAt = now()->timestamp;
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        try {
            $this->form->authenticate();
        } catch (ValidationException $exception) {
            $this->form->turnstileToken = '';
            $this->dispatch('offorest-turnstile-reset');

            throw $exception;
        }

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="relative min-h-screen overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.55),transparent_24%),radial-gradient(circle_at_80%_80%,rgba(59,130,246,0.20),transparent_26%),radial-gradient(circle_at_50%_50%,rgba(14,165,233,0.18),transparent_30%)]"></div>
    <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.20),rgba(255,255,255,0.04))]"></div>

    <div class="relative flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
        <div class="absolute inset-x-0 top-8 mx-auto h-28 w-28 rounded-full bg-white/35 blur-3xl"></div>

        <div class="w-full max-w-md rounded-[2rem] border border-white/40 bg-white/45 p-8 shadow-[0_25px_80px_rgba(15,23,42,0.18)] backdrop-blur-2xl ring-1 ring-white/50 sm:p-10">
            <div class="text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-white/75 shadow-inner shadow-white/70 ring-1 ring-white/70">
                    <img src="{{ asset('images/offorest-logo.jpg') }}" alt="{{ config('app.name', 'Offorest') }}" class="h-full w-full object-cover">
                </div>

                <h1 class="mt-6 text-2xl font-semibold tracking-[0.18em] text-slate-900">OFFOREST</h1>
                <p class="mt-2 text-sm text-slate-600">Chào mừng bạn. Vui lòng đăng nhập để tiếp tục.</p>
            </div>

            <x-auth-session-status class="mt-5" :status="session('status')" />

            <form class="mt-6 space-y-4" wire:submit="login">
                <input type="text" tabindex="-1" autocomplete="off" class="hidden" wire:model="form.website">
                <input type="hidden" wire:model="form.startedAt">

                @php
                    $authFailedMessage = trans('auth.failed');
                    $loginMessages = collect($errors->get('form.login'));
                    $credentialMessages = collect($errors->get('form.password'))
                        ->merge($loginMessages->filter(fn ($message) => $message === $authFailedMessage));
                    $turnstileMessages = collect($errors->get('form.turnstileToken'))
                        ->merge($loginMessages->filter(fn ($message) => str_contains($message, 'xac minh') || str_contains($message, 'xÃ¡c minh') || str_contains($message, 'security')));
                    $visibleLoginMessages = $loginMessages->reject(fn ($message) => $message === $authFailedMessage || $turnstileMessages->contains($message));
                @endphp

                <div>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 0 0-16 0" />
                                <circle cx="12" cy="8" r="4" />
                            </svg>
                        </span>
                        <input
                            wire:model="form.login"
                            id="login"
                            type="text"
                            name="login"
                            autocomplete="username"
                            autofocus
                            placeholder="Email / Tên đăng nhập"
                            class="w-full rounded-full border border-slate-300/80 bg-white/75 py-3.5 pl-12 pr-4 text-sm text-slate-900 shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:bg-white focus:ring-4 focus:ring-sky-400/20"
                        />
                    </div>
                    <x-input-error :messages="$visibleLoginMessages->all()" class="mt-2 px-3 text-sm" />
                </div>

                <div>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 1 0-8 0v4" />
                                <rect x="5" y="11" width="14" height="10" rx="2" />
                            </svg>
                        </span>
                        <input
                            wire:model="form.password"
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Mật khẩu"
                            class="w-full rounded-full border border-slate-300/80 bg-white/75 py-3.5 pl-12 pr-12 text-sm text-slate-900 shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:bg-white focus:ring-4 focus:ring-sky-400/20"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-4 flex items-center text-slate-400 transition hover:text-slate-600"
                            aria-label="Hiện hoặc ẩn mật khẩu"
                            onclick="const i=document.getElementById('password'); i.type = i.type === 'password' ? 'text' : 'password';"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <x-input-error :messages="$credentialMessages->all()" class="mt-2 px-3 text-sm" />
                </div>

                @if (app(TurnstileVerifier::class)->enabled())
                    <div wire:ignore class="flex justify-center">
                        <div
                            data-turnstile-login
                            class="cf-turnstile"
                            data-sitekey="{{ app(TurnstileVerifier::class)->siteKey() }}"
                            data-callback="offorestTurnstileVerified"
                            data-expired-callback="offorestTurnstileExpired"
                            data-error-callback="offorestTurnstileExpired"
                        ></div>
                    </div>
                    <x-input-error :messages="$turnstileMessages->all()" class="mt-2 px-3 text-sm" />
                @endif

                <div class="flex items-center justify-between gap-4 pt-1">
                    <label for="remember" class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input
                            wire:model="form.remember"
                            id="remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-sky-500 focus:ring-sky-400"
                            name="remember"
                        >
                        <span>Ghi nhớ tôi</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm font-medium text-sky-700 transition hover:text-sky-900" href="{{ route('password.request') }}" wire:navigate>
                            Quên mật khẩu?
                        </a>
                    @endif
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="login"
                    class="mt-2 flex w-full items-center justify-center gap-2 rounded-full bg-gradient-to-r from-sky-600 to-cyan-400 px-5 py-3.5 text-sm font-semibold tracking-wide text-white shadow-lg shadow-sky-500/30 transition duration-200 hover:scale-[1.01] hover:from-sky-500 hover:to-cyan-300 focus:outline-none focus:ring-4 focus:ring-sky-400/30 disabled:cursor-wait disabled:opacity-75"
                >
                    <span wire:loading.remove wire:target="login">ĐĂNG NHẬP</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        ĐANG ĐĂNG NHẬP...
                    </span>
                </button>

                <div class="flex items-center gap-4 py-2">
                    <div class="h-px flex-1 bg-slate-300/70"></div>
                    <span class="text-sm text-slate-500">Hoặc</span>
                    <div class="h-px flex-1 bg-slate-300/70"></div>
                </div>

                <div class="flex items-center justify-center gap-4">
                    <button type="button" class="flex h-12 w-12 items-center justify-center rounded-full bg-white/80 shadow-md ring-1 ring-white/70 transition hover:-translate-y-0.5 hover:bg-white" aria-label="Đăng nhập bằng Google">
                        <span class="text-xl font-semibold text-[#EA4335]">G</span>
                    </button>
                    <button type="button" class="flex h-12 w-12 items-center justify-center rounded-full bg-white/80 shadow-md ring-1 ring-white/70 transition hover:-translate-y-0.5 hover:bg-white" aria-label="Đăng nhập bằng Apple">
                        <span class="text-xl font-semibold text-slate-900">A</span>
                    </button>
                    <button type="button" class="flex h-12 w-12 items-center justify-center rounded-full bg-white/80 shadow-md ring-1 ring-white/70 transition hover:-translate-y-0.5 hover:bg-white" aria-label="Đăng nhập bằng Facebook">
                        <span class="text-xl font-semibold text-[#1877F2]">f</span>
                    </button>
                </div>

                <p class="pt-2 text-center text-sm text-slate-600">
                    Chưa có tài khoản? <span class="font-semibold text-slate-900">Liên hệ quản trị viên</span>
                </p>
            </form>
        </div>
    </div>
</div>

@if (app(TurnstileVerifier::class)->enabled())
    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer onload="window.offorestTurnstile?.render()"></script>
            <script>
                window.offorestTurnstile = window.offorestTurnstile || {
                    widgetId: null,

                    element() {
                        return document.querySelector('[data-turnstile-login]');
                    },

                    livewireComponent() {
                        const element = this.element();
                        const root = element?.closest('[wire\\:id]');
                        const id = root?.getAttribute('wire:id');

                        return id && window.Livewire ? window.Livewire.find(id) : null;
                    },

                    setToken(token) {
                        this.livewireComponent()?.set('form.turnstileToken', token || '');
                    },

                    render() {
                        const element = this.element();

                        if (! element || ! window.turnstile || element.dataset.rendered === '1') {
                            return;
                        }

                        this.widgetId = window.turnstile.render(element, {
                            sitekey: element.dataset.sitekey,
                            callback: window.offorestTurnstileVerified,
                            'expired-callback': window.offorestTurnstileExpired,
                            'error-callback': window.offorestTurnstileExpired,
                        });
                        element.dataset.rendered = '1';
                    },

                    reset() {
                        this.setToken('');

                        if (window.turnstile && this.widgetId !== null) {
                            window.turnstile.reset(this.widgetId);
                            return;
                        }

                        const element = this.element();

                        if (element) {
                            delete element.dataset.rendered;
                        }

                        this.render();
                    },
                };

                window.offorestTurnstileVerified = function (token) {
                    window.offorestTurnstile?.setToken(token);
                };

                window.offorestTurnstileExpired = function () {
                    window.offorestTurnstile?.setToken('');
                };

                window.addEventListener('offorest-turnstile-reset', () => {
                    setTimeout(() => window.offorestTurnstile?.reset(), 50);
                });

                document.addEventListener('livewire:navigated', () => {
                    setTimeout(() => window.offorestTurnstile?.render(), 50);
                });
            </script>
        @endpush
    @endonce
@endif
