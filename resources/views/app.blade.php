<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts (Inter + JetBrains Mono se importan en resources/css/app.css) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

        <!-- Tema: dark por defecto; solo se quita `dark` si el usuario eligió light. Debe ir antes de vite para evitar el flash. -->
        <script>
            try {
                if (localStorage.getItem('mynab-theme') === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', 'resources/css/app.css'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900 dark:bg-base dark:text-text">
        @inertia
    </body>
</html>