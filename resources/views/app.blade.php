<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Primary Meta Tags -->
    <title inertia>{{ config('seo.name') }} - {{ config('seo.title') }}</title>
    <meta name="title" content="{{ config('seo.name') }} - {{ config('seo.title') }}">
    <meta name="description" content="{{ config('seo.description') }}">
    <meta name="keywords" content="{{ config('seo.keywords') }}">
    <meta name="author" content="{{ config('seo.name') }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ config('app.url') }}">

    <!-- Open Graph (Facebook, LinkedIn) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:title" content="{{ config('seo.name') }} - {{ config('seo.title') }}">
    <meta property="og:description" content="{{ config('seo.description') }}">
    <meta property="og:image" content="{{ config('app.url') }}/og-image.jpg">
    <meta property="og:site_name" content="{{ config('seo.name') }}">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ config('app.url') }}">
    <meta name="twitter:title" content="{{ config('seo.name') }} - {{ config('seo.title') }}">
    <meta name="twitter:description" content="{{ config('seo.description') }}">
    <meta name="twitter:image" content="{{ config('app.url') }}/og-image.jpg">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

 <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => config('seo.name'),
        'url' => config('app.url'),
        'jobTitle' => config('seo.title'),
        'description' => config('seo.description'),
        'email' => config('seo.email'),
        'sameAs' => [
            config('seo.github'),
            config('seo.linkedin'),
        ],
        'knowsAbout' => ['Laravel', 'Vue.js', 'PHP', 'JavaScript', 'MySQL', 'Tailwind CSS'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) !!}
    </script>
  

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
