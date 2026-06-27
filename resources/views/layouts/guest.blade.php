<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <body class="font-sans text-gray-900 antialiased bg-slate-950">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.45),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(59,130,246,0.25),_transparent_28%),linear-gradient(135deg,_#f8fafc_0%,_#dbeafe_34%,_#c7d2fe_68%,_#e0f2fe_100%)]">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
        @livewireScripts
    </body>
</html>
