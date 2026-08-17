<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @include('partials.seo', [
        'seoTitle' => 'التسجيل مغلق مؤقتاً — مصلحة الضرائب × SMART CARE',
        'seoDescription' => $messageAr,
        'seoUrl' => route('registration.form'),
        'seoRobots' => 'noindex,follow',
    ])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans">
    <div class="reg-page">
        <section class="reg-login-shell">
            @include('livewire.registration.partials.login-brand')

            <div class="reg-closed-card">
                <div class="reg-closed-badge">
                    <span class="reg-closed-badge-dot" aria-hidden="true"></span>
                    مغلق مؤقتاً
                </div>

                <div class="reg-closed-icon" aria-hidden="true">
                    <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                </div>

                <h1 class="reg-closed-title">التسجيل غير متاح حالياً</h1>
                <p class="reg-closed-subtitle">منظومة الاستبيان الطبي لموظفي مصلحة الضرائب</p>

                <div class="reg-closed-message">
                    <p class="reg-closed-message-ar">{{ $messageAr }}</p>
                    @if (filled($messageEn))
                        <p class="reg-closed-message-en" dir="ltr" lang="en">{{ $messageEn }}</p>
                    @endif
                </div>

                <div class="reg-closed-hint">
                    <svg class="size-4 shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                    </svg>
                    <span>يمكنك المحاولة لاحقاً أو التواصل معنا عبر القنوات أدناه.</span>
                </div>
            </div>

            @include('livewire.registration.partials.login-contact')
        </section>
    </div>
</body>
</html>
