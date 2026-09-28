@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Login' }} · {{ config('app.name') }}</title>
    @vite('resources/js/app.js')
</head>
<body>
    <main class="auth-shell d-flex align-items-center justify-content-center p-3">
        {{ $slot }}
    </main>
</body>
</html>
