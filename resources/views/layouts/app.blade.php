<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#f07838">
        <title>{{ isset($title) ? $title.' · ' : '' }}OUT OF BOREDOM</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#fff7f0] text-stone-900 antialiased">
            @include('layouts.navigation')
            <main class="mx-auto max-w-[1440px] px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
    </body>
</html>
