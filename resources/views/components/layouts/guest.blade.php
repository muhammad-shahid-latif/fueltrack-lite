@props(['title' => null, 'shell' => 'auth-shell'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="turbo-cache-control" content="no-cache">
    <title>{{ $title ?? 'Login' }} · {{ \App\Support\Brand::current()?->name ? \App\Support\Brand::current()->name.' · ' : '' }}{{ config('app.name') }}</title>
    @include('partials.fonts')
    @include('partials.brand-theme')
    @vite('resources/js/app.js')
</head>
<body>
    <main class="{{ $shell }}">
        {{ $slot }}
    </main>
</body>
</html>
