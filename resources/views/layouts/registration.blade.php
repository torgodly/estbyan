<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.seo', [
        'seoTitle' => $title ?? 'التسجيل الطبي — مصلحة الضرائب × SMART CARE',
        'seoDescription' => 'منظومة استبيان التسجيل الطبي لموظفي مصلحة الضرائب الليبية بالشراكة مع الرعاية الذكية (Smart Care). أدخل بياناتك وبيانات عائلتك بسهولة.',
        'seoUrl' => route('registration.form'),
    ])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="font-sans">
    {{ $slot }}
    @livewireScripts
</body>
</html>
