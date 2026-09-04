<!DOCTYPE html>
@php($initialThemeMode = auth()->check() ? (auth()->user()->theme_mode ?? 'light') : 'light')
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $initialThemeMode === 'dark' ? 'theme-dark' : 'theme-light' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Offorest') }}</title>
        <link rel="icon" type="image/jpeg" href="{{ asset('images/offorest-logo.jpg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('scripts')
    </head>
    <body class="font-sans antialiased">
        <div class="app-shell min-h-screen bg-gray-200">
            <livewire:layout.navigation />

            <div>
                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main class="app-main">
                    {{ $slot }}
                </main>
            </div>

            <div
                x-data="{ showBackToTop: window.scrollY > 300 }"
                @scroll.window="showBackToTop = window.scrollY > 300"
                class="fixed bottom-5 right-5 z-50"
            >
                <button
                    type="button"
                    x-show="showBackToTop"
                    x-cloak
                    x-transition.opacity.duration.200ms
                    x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-lg shadow-slate-400/30 transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                    aria-label="Len dau trang"
                    title="Len dau trang"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 19V5" />
                        <path d="m5 12 7-7 7 7" />
                    </svg>
                </button>
            </div>

            <livewire:modals.image.review-image />
            <livewire:modals.suncatcher.review-image />
            <livewire:modals.account-manager.data-form />
            <x-toast />
        </div>
        @livewireScripts
    </body>
</html>
