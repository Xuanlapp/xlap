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

            <livewire:modals.image.review-image />
            <livewire:modals.ornament-amazon.review-image />
            <x-toast />
        </div>
        @livewireScripts
    </body>
</html>
