@php
    $seoTitle = $seoTitle ?? 'التسجيل الطبي — مصلحة الضرائب × SMART CARE';
    $seoDescription = $seoDescription ?? 'منظومة استبيان التسجيل الطبي لموظفي مصلحة الضرائب الليبية بالشراكة مع الرعاية الذكية (Smart Care).';
    $seoUrl = $seoUrl ?? url()->current();
    $seoImage = $seoImage ?? asset('images/og-registration.png');
    $seoRobots = $seoRobots ?? 'index,follow';
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoRobots }}">
<meta name="theme-color" content="#0f2744">
<meta name="application-name" content="مصلحة الضرائب · SMART CARE">
<meta name="apple-mobile-web-app-title" content="التسجيل الطبي">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="canonical" href="{{ $seoUrl }}">

{{-- Favicons --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<link rel="shortcut icon" href="{{ asset('favicon.png') }}">

{{-- Open Graph / WhatsApp / Facebook --}}
<meta property="og:locale" content="ar_LY">
<meta property="og:type" content="website">
<meta property="og:site_name" content="مصلحة الضرائب · SMART CARE">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:secure_url" content="{{ $seoImage }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="مصلحة الضرائب الليبية والرعاية الذكية — التسجيل الطبي للموظفين">

{{-- Twitter / X --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">
