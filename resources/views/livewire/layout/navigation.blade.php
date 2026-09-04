<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/');
    }

    public function setThemeMode(string $mode): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $user->forceFill([
            'theme_mode' => $mode === 'dark' ? 'dark' : 'light',
        ])->save();
    }

    /**
     * Navigation data for the current user.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $user = auth()->user();
        $products = collect();

        if ($user) {
            if ($user->isManager()) {
                $managerProducts = \App\Models\Product::query()->where('is_active', true)->orderBy('name')->get();
                $assignedSuncatcher = $user->products()->where('slug', 'suncatcher')->where('is_active', true)->first();

                $products = $managerProducts
                    ->reject(fn ($product): bool => $product->slug === 'suncatcher')
                    ->when($assignedSuncatcher, fn ($items) => $items->push($assignedSuncatcher))
                    ->sortBy('name')
                    ->values();
            } else {
                $products = $user->products()->where('is_active', true)->orderBy('name')->get();
            }
        }
        $canAccessFinancial = (bool) ($user && app(\App\Services\Financial\FinancialAccessService::class)->visibleAccountsQuery($user)->exists());
        $canAccessAccountFinancial = (bool) ($user && ($user->is_admin || $user->role === 'admin' || $user->accountFinancialViews()->exists()));
        $canAccessWali = (bool) ($user && ((bool) $user->can_access_wali));
        $isWaliOnly = (bool) ($user && (bool) $user->can_access_wali && ! ((bool) $user->is_admin || $user->role === 'admin') && $products->isEmpty());

        return [
            'products' => $products,
            'canAccessFinancial' => $canAccessFinancial,
            'canAccessAccountFinancial' => $canAccessAccountFinancial,
            'canAccessWali' => $canAccessWali,
            'isWaliOnly' => $isWaliOnly,
            'userInitials' => $user ? mb_strtoupper(mb_substr($user->name, 0, 1)) : '?',
            'themeMode' => $user?->theme_mode ?? 'light',
        ];
    }
}; ?>

@php
    $navItemClass = 'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
    $activeClass = 'bg-blue-600 text-white shadow-lg shadow-blue-600/18';
    $inactiveClass = 'text-slate-600 hover:bg-slate-100 hover:text-slate-950';
    $iconClass = 'h-5 w-5 shrink-0';
    $isAdminUser = auth()->user()?->role === 'admin' || (bool) auth()->user()?->is_admin;
    $isWaliUser = (bool) (auth()->user()?->can_access_wali) && ! $isAdminUser && ! auth()->user()?->isManager();
    $pageProducts = $products->whereIn('slug', $isAdminUser ? ['suncatcher', 'ornament', 'ornament-etsy', 'ornament-amazon-2', 'sticker', 'glass', 'proxy', 'camp'] : ['suncatcher', 'ornament', 'ornament-etsy', 'ornament-amazon-2', 'sticker', 'glass', 'proxy', 'camp']);
    $ideaProducts = $products->whereIn('slug', ['ytrends', 'idea-etsy', 'idea-amazon']);
    $avatarPalettes = [
        'bg-gradient-to-br from-blue-500 via-indigo-500 to-violet-600',
        'bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-600',
        'bg-gradient-to-br from-rose-500 via-pink-500 to-orange-500',
        'bg-gradient-to-br from-amber-500 via-orange-500 to-red-500',
        'bg-gradient-to-br from-sky-500 via-blue-600 to-slate-800',
        'bg-gradient-to-br from-fuchsia-500 via-purple-600 to-indigo-700',
    ];
    $avatarSeed = auth()->user() ? abs(crc32(auth()->user()->email)) : 0;
    $avatarClass = $avatarPalettes[$avatarSeed % count($avatarPalettes)];
@endphp

<div class="pt-[4.75rem]" x-data="{ sidebarOpen: false, userMenuOpen: false, isDark: {{ $themeMode === 'dark' ? 'true' : 'false' }}, scrolled: window.scrollY > 70 }" @scroll.window="scrolled = window.scrollY > 70" x-init="document.documentElement.classList.toggle('theme-dark', isDark); document.documentElement.classList.toggle('theme-light', !isDark)" x-on:keydown.escape.window="sidebarOpen = false; userMenuOpen = false">
    <div class="fixed left-3 right-3 top-1 z-50 border-b border-slate-200 bg-gray-100 px-3 py-2 text-slate-950">
        <div class="relative flex h-11 items-center rounded-xl border border-slate-300 bg-white px-2 shadow-sm">
        <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none"
            x-on:click="sidebarOpen = true"
            aria-label="Open navigation"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 6h16" />
                <path d="M4 12h16" />
                <path d="M4 18h16" />
            </svg>
        </button>

        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="absolute top-1/2 z-10 flex -translate-y-1/2 items-center gap-2 whitespace-nowrap transition-all duration-500 ease-in-out focus:outline-none"
            :class="scrolled ? 'left-1/2 -translate-x-1/2' : 'left-12 translate-x-0'"
        >
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 shadow-sm">
                <x-application-logo class="h-6 w-6" />
            </span>
            <span class="text-sm font-semibold">Offorest</span>
        </a>

<button
            type="button"
            class="ml-auto flex h-9 w-9 items-center justify-center rounded-full border border-slate-200/80 bg-white/90 text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white hover:text-slate-950 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-white/20 dark:hover:bg-white/10"
            x-on:click="isDark = !isDark; document.documentElement.classList.toggle('theme-dark', isDark); document.documentElement.classList.toggle('theme-light', !isDark); $wire.setThemeMode(isDark ? 'dark' : 'light')"
            aria-label="Toggle theme"
        >
            <svg x-show="!isDark" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 3v1" />
                <path d="M12 20v1" />
                <path d="m4.22 4.22.7.7" />
                <path d="m19.08 19.08.7.7" />
                <path d="M3 12h1" />
                <path d="M20 12h1" />
                <path d="m4.22 19.78.7-.7" />
                <path d="m19.08 4.92.7-.7" />
                <circle cx="12" cy="12" r="4" />
            </svg>
            <svg x-show="isDark" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z" />
            </svg>
        </button>

        <button
            type="button"
            class="ml-2 flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-white text-sm font-semibold text-slate-950 shadow-sm ring-2 ring-blue-200 focus:outline-none dark:border dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-blue-400/30"
            x-on:click="userMenuOpen = ! userMenuOpen"
            x-bind:aria-expanded="userMenuOpen.toString()"
            aria-haspopup="true"
            aria-label="Open user menu"
        >
            @if (auth()->user()->avatar_path)
                <img src="{{ Storage::url(auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover">
            @else
                {{ $userInitials }}
            @endif
        </button>
        </div>

        <div
            x-show="userMenuOpen"
            x-cloak
            x-transition.origin.top.right
            x-on:click.outside="userMenuOpen = false"
            class="absolute right-3 top-[4.25rem] z-50 w-72 overflow-hidden rounded-3xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-400/40"
        >
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-2xl {{ $avatarClass }} text-base font-extrabold text-black shadow-sm ring-1 ring-white">
                        @if (auth()->user()->avatar_path)
                            <img src="{{ Storage::url(auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover">
                        @else
                            {{ $userInitials }}
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-extrabold text-slate-950">{{ auth()->user()->name }}</span>
                        <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">{{ auth()->user()->email }}</span>
                    </span>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Active</span>
                    @if (auth()->user()->role === 'admin' || auth()->user()->is_admin)
                        <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-[11px] font-bold text-blue-700">Admin</span>
                    @endif
                </div>
            </div>
            <div class="mt-2 space-y-1 ">
                <a href="{{ route('profile') }}" wire:navigate x-on:click="userMenuOpen = false" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950 focus:outline-none">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M20 21a8 8 0 0 0-16 0" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </span>
                    <span class="flex-1">Profile</span>
                    <svg class="h-4 w-4 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </a>
                <button type="button" wire:click="logout" class="flex w-full items-center gap-3 rounded-2xl border border-red-100 bg-white px-3 py-2.5 text-left text-sm font-bold text-red-600 shadow-sm transition hover:border-red-200 hover:bg-red-50 focus:outline-none">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="M16 17l5-5-5-5" />
                            <path d="M21 12H9" />
                        </svg>
                    </span>
                    <span class="flex-1">Logout</span>
                </button>
            </div>
        </div>
    </div>

    <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-300 bg-gray-200 p-3">
        <div class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-300 bg-white shadow-xl shadow-slate-300/70 ring-1 ring-slate-950/5">
            <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-4">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-950 shadow-sm">
                    <x-application-logo class="h-8 w-8" />
                </a>
                <div class="min-w-0">
                    <p class="truncate text-base font-extrabold tracking-tight text-slate-950">Offorest</p>
                    <p class="truncate text-xs font-medium text-slate-500">Offorest workspace</p>
                </div>
            </div>

            <div class="px-4 pt-4">
                <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-slate-400">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                    <span class="text-sm font-medium">Search</span>
                </div>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
                <div>
                    <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Page</p>
                    <div class="mt-2 space-y-1">
                        @foreach ($pageProducts as $product)
                            @php($productRouteSlug = $product->slug === 'ornament' ? 'suncatcher' : $product->slug)
                            @php($isActive = request()->routeIs('offorest.products.'.$productRouteSlug))
                            <a
                                href="{{ route('offorest.products.'.$productRouteSlug) }}"
                                wire:navigate
                                class="{{ $navItemClass }} {{ $isActive ? $activeClass : $inactiveClass }}"
                            >
                                <svg class="{{ $iconClass }} {{ $isActive ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    @if ($product->slug === 'redesign')
                                        <path d="M12 20h9" />
                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                    @elseif ($product->slug === 'ytrends')
                                        <path d="M4 19V5" />
                                        <path d="M4 19h16" />
                                        <path d="m7 14 4-4 3 3 5-6" />
                                    @elseif (in_array($product->slug, ['idea-etsy', 'idea-amazon'], true))
                                        <path d="M4 6h16" />
                                        <path d="M4 12h16" />
                                        <path d="M4 18h7" />
                                        <path d="m15 18 2 2 4-4" />
                                    @else
                                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />
                                    @endif
                                </svg>
                                <span class="truncate">{{ $product->display_name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                @if ($ideaProducts->isNotEmpty())
                    <div class="mt-6">
                        <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Idea</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($ideaProducts as $product)
                                @php($productRouteSlug = $product->slug === 'ornament' ? 'suncatcher' : $product->slug)
                                @php($isActive = request()->routeIs('offorest.products.'.$productRouteSlug))
                                <a
                                    href="{{ route('offorest.products.'.$productRouteSlug) }}"
                                    class="{{ $navItemClass }} {{ $isActive ? $activeClass : $inactiveClass }}"
                                >
                                    <svg class="{{ $iconClass }} {{ $isActive ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                        @if ($product->slug === 'ytrends')
                                            <path d="M4 19V5" />
                                            <path d="M4 19h16" />
                                            <path d="m7 14 4-4 3 3 5-6" />
                                        @else
                                            <path d="M4 6h16" />
                                            <path d="M4 12h16" />
                                            <path d="M4 18h7" />
                                            <path d="m15 18 2 2 4-4" />
                                        @endif
                                    </svg>
                                    <span class="truncate">{{ $product->display_name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! $isWaliUser)
                <div class="mt-6">
                    <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Catalog</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('offorest.listing-metadata') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.listing-metadata') ? $activeClass : $inactiveClass }}">
                            <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.listing-metadata') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path d="M4 5h16" />
                                <path d="M4 12h10" />
                                <path d="M4 19h7" />
                                <path d="m16 17 2 2 4-4" />
                            </svg>
                            <span>Listing</span>
                        </a>
                        <a href="{{ route('offorest.suncatcher.catalog') }}" class="{{ $navItemClass }} {{ request()->routeIs('offorest.suncatcher.catalog') ? $activeClass : $inactiveClass }}">
                            <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.suncatcher.catalog') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path d="M4 5h16" />
                                <path d="M4 10h16" />
                                <path d="M4 15h10" />
                                <path d="m16 17 2 2 4-4" />
                            </svg>
                            <span>Suncatcher Catalog</span>
                        </a>
                        <a href="{{ route('offorest.drive-uploads') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.drive-uploads') ? $activeClass : $inactiveClass }}">
                            <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.drive-uploads') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path d="M12 3v12" />
                                <path d="m7 8 5-5 5 5" />
                                <path d="M5 15v4h14v-4" />
                            </svg>
                            <span>Uploads</span>
                        </a>
                        <a href="{{ route('offorest.exports') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.exports') ? $activeClass : $inactiveClass }}">
                            <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.exports') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path d="M4 4h16v16H4z" />
                                <path d="M8 9h8" />
                                <path d="M8 13h8" />
                                <path d="M8 17h5" />
                            </svg>
                            <span>Export</span>
                        </a>
                    </div>
                </div>
                @endif

                @if ($canAccessFinancial && ! $isAdminUser)
                    <div class="mt-6">
                        <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Finance</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('offorest.financial-management') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.financial-management') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.financial-management') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 3v18h18"/><path d="m7 14 3-3 3 2 5-6"/></svg>
                                <span>Financial Management</span>
                            </a>
                        </div>
                    </div>
                @endif
                @if ($canAccessAccountFinancial && ! $isAdminUser)
                    <div class="mt-6">
                        <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Account</p>
                        <div class="mt-2 space-y-1"><a href="{{ route('offorest.account-manager.notes') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.account-manager.*') ? $activeClass : $inactiveClass }}"><svg class="{{ $iconClass }} {{ request()->routeIs('offorest.account-manager.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 4h12l4 4v12H4z"/><path d="M16 4v5h5"/><path d="M8 14h8"/></svg><span>Financial Management</span></a></div>
                    </div>
                @endif
                @if ($canAccessWali)
                <div class="mt-6">
                    <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Salary</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('offorest.salary.wali') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.salary.wali') ? $activeClass : $inactiveClass }}">
                            <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.salary.wali') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path d="M4 19V5" />
                                <path d="M4 19h16" />
                                <path d="M8 16V10" />
                                <path d="M12 16V7" />
                                <path d="M16 16v-4" />
                            </svg>
                            <span>Wali</span>
                        </a>
                    </div>
                </div>
                @endif

                @if (auth()->user()->role === 'admin' || auth()->user()->is_admin)
                    <div class="mt-6">
                        <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Admin</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('offorest.admin.users') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.admin.users') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.admin.users') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M19 8v6" />
                                    <path d="M22 11h-6" />
                                </svg>
                                <span>Users</span>
                            </a>
                            <a href="{{ route('offorest.account-manager.notes') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.account-manager.*') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.account-manager.*') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path d="M5 4h10l4 4v12H5z" />
                                    <path d="M15 4v5h5" />
                                    <path d="M8 13h8" />
                                    <path d="M8 17h5" />
                                </svg>
                                <span>Account Notes</span>
                            </a>
                            <a href="{{ route('offorest.admin.logs') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.admin.logs') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.admin.logs') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path d="M4 5h16" />
                                    <path d="M4 12h16" />
                                    <path d="M4 19h10" />
                                </svg>
                                <span>Logs</span>
                            </a>
                            <a href="{{ route('offorest.admin.api-credits') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.admin.api-credits') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.admin.api-credits') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path d="M4 7h16" />
                                    <path d="M4 17h16" />
                                    <path d="M7 4v6" />
                                    <path d="M17 14v6" />
                                </svg>
                                <span>API Credits</span>
                            </a>
                            <a href="{{ route('offorest.admin.financial-management') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.admin.financial-management') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.admin.financial-management') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path d="M3 3v18h18" />
                                    <path d="m7 14 3-3 3 2 5-6" />
                                </svg>
                                <span>Financial Management</span>
                            </a>                            <a href="{{ route('offorest.admin.mail-test') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.admin.mail-test') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.admin.mail-test') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path d="M4 6h16v12H4z" />
                                    <path d="m4 7 8 6 8-6" />
                                </svg>
                                <span>Mail Test</span>
                            </a>
                        </div>
                    </div>
                @endif
            </nav>

            <div class="border-t border-slate-200 p-3">
                <div class="flex w-full items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl {{ $avatarClass }} text-sm font-extrabold text-white shadow-sm">
                        @if (auth()->user()->avatar_path)
                            <img src="{{ Storage::url(auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover">
                        @else
                            {{ $userInitials }}
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-slate-950">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs font-medium text-slate-500">{{ auth()->user()->email }}</span>
                    </span>
                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                </div>
            </div>
        </div>
    </aside>

    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-50">
        <button type="button" class="absolute inset-0 bg-slate-200/60 backdrop-blur-sm focus:outline-none" x-on:click="sidebarOpen = false" aria-label="Close navigation"></button>
        <aside
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative flex h-full flex-col bg-white shadow-2xl focus:outline-none"
            style="width: 240px; max-width: 82vw;"
        >
            <div class="flex h-full min-h-0 flex-col overflow-hidden border-r border-slate-200 bg-white">
                <div class="flex h-16 items-center justify-between border-b border-slate-200 px-4">
                    <a href="{{ route('dashboard') }}" wire:navigate x-on:click="sidebarOpen = false" class="flex items-center gap-3 focus:outline-none">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100">
                            <x-application-logo class="h-6 w-6" />
                        </span>
                        <span class="text-sm font-semibold tracking-normal text-slate-950">Offorest</span>
                    </a>
                    <button type="button" x-on:click="sidebarOpen = false" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none" aria-label="Close navigation">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                    @if ($pageProducts->isNotEmpty())
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Page</p>
                        <div class="mt-3 space-y-3">
                            @foreach ($pageProducts as $product)
                                @php($productRouteSlug = $product->slug === 'ornament' ? 'suncatcher' : $product->slug)
                                <a href="{{ route('offorest.products.'.$productRouteSlug) }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">
                                    <span>{{ $product->display_name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @if ($ideaProducts->isNotEmpty())
                        <div class="mt-6 border-t border-slate-200 pt-3">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Idea</p>
                            <div class="mt-3 space-y-3">
                                @foreach ($ideaProducts as $product)
                                    @php($productRouteSlug = $product->slug === 'ornament' ? 'suncatcher' : $product->slug)
                                    <a href="{{ route('offorest.products.'.$productRouteSlug) }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">
                                        <span>{{ $product->display_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if (! $isWaliUser)
                    <div class="mt-6 border-t border-slate-200 pt-3">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Catalog</p>
                        <div class="mt-3 space-y-3">
                            <a href="{{ route('offorest.listing-metadata') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Listing</a>
                            <a href="{{ route('offorest.suncatcher.catalog') }}" x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Suncatcher Catalog</a>
                            <a href="{{ route('offorest.drive-uploads') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Uploads</a>
                            <a href="{{ route('offorest.exports') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Export</a>
                        </div>
                    </div>
                    @endif
                    @if ($canAccessFinancial && ! $isAdminUser)
                    <div class="mt-6">
                        <p class="px-3 text-[11px] font-extrabold uppercase tracking-wide text-slate-400">Finance</p>
                        <div class="mt-2 space-y-1">
                            <a href="{{ route('offorest.financial-management') }}" wire:navigate class="{{ $navItemClass }} {{ request()->routeIs('offorest.financial-management') ? $activeClass : $inactiveClass }}">
                                <svg class="{{ $iconClass }} {{ request()->routeIs('offorest.financial-management') ? 'text-white' : 'text-slate-400 group-hover:text-slate-700' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 3v18h18"/><path d="m7 14 3-3 3 2 5-6"/></svg>
                                <span>Financial Management</span>
                            </a>
                        </div>
                    </div>
                @endif
                @if ($canAccessAccountFinancial && ! $isAdminUser)
                    <div class="mt-6 border-t border-slate-200 pt-3"><p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Account</p><div class="mt-3 space-y-3"><a href="{{ route('offorest.account-manager.notes') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Financial Management</a></div></div>
                @endif
                @if ($canAccessWali)
                    <div class="mt-6 border-t border-slate-200 pt-3">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Salary</p>
                        <div class="mt-3 space-y-3">
                            <a href="{{ route('offorest.salary.wali') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Wali</a>
                        </div>
                    </div>
                    @endif
                    @if (auth()->user()->role === 'admin' || auth()->user()->is_admin)
                        <div class="mt-6 border-t border-slate-200 pt-3">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Admin</p>
                            <div class="mt-3 space-y-3">
                                <a href="{{ route('offorest.admin.users') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Users</a>
                                <a href="{{ route('offorest.account-manager.notes') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Account Notes</a>
                                <a href="{{ route('offorest.admin.logs') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Logs</a>
                                <a href="{{ route('offorest.admin.api-credits') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">API Credits</a>
                                <a href="{{ route('offorest.admin.financial-management') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Financial Management</a>
                                <a href="{{ route('offorest.admin.mail-test') }}" wire:navigate x-on:click="sidebarOpen = false" class="block rounded-md py-1 text-sm font-semibold text-slate-700 transition hover:text-slate-950">Mail Test</a>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="border-t border-slate-200 p-3">
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-50 text-sm font-semibold text-slate-950 shadow-sm">
                            @if (auth()->user()->avatar_path)
                                <img src="{{ Storage::url(auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover">
                            @else
                                {{ $userInitials }}
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-950">{{ auth()->user()->name }}</span>
                            <span class="block truncate text-xs font-medium text-slate-500">{{ auth()->user()->email }}</span>
                        </span>
                        <span class="ml-auto h-2.5 w-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

