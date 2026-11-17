{{-- One head block for every page. Values come from layout defaults or from
     the route view that extends the layout, so even the prerendered /auth
     snapshot carries complete metadata. --}}
{{-- Reuses the existing app icon as the social image; JSON-LD stays conservative
     (WebPage/WebApplication); locale and card match the Indonesian, single-location app. --}}
@php
    $seoBase = rtrim((string) config('needs.seo.site_url'), '/');
    $seoPath = '/'.ltrim((string) $seoPath, '/');
    $seoUrl = $seoBase.$seoPath;
    $seoImage = $seoBase.'/apple-touch-icon.png';
    $seoJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $seoTitle,
        'description' => $seoDescription,
        'url' => $seoUrl,
        'inLanguage' => 'id',
        'isPartOf' => [
            '@type' => 'WebApplication',
            'name' => config('needs.seo.site_name'),
            'url' => $seoBase.'/',
            'description' => config('needs.seo.site_description'),
            'isAccessibleForFree' => true,
            'featureList' => [
                'peta interaktif',
                'pencarian kebutuhan',
                'penanda lokasi',
                'tempat dari OpenStreetMap',
            ],
        ],
    ];
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="keywords" content="{{ $seoKeywords }}">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{{ $seoUrl }}">

<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:site_name" content="{{ config('needs.seo.site_name') }}">
<meta property="og:locale" content="id_ID">
<meta property="og:image" content="{{ $seoImage }}">

<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

<script type="application/ld+json">@json($seoJsonLd)</script>
