<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name') }}</title>
        @vite('resources/js/app.ts')
        @inertiaHead
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
        @inertia
    </body>
</html>
