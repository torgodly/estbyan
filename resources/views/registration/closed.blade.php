<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التسجيل مغلق — SMART CARE</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh items-center justify-center bg-slate-100 p-6 font-sans text-slate-800">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-xl">
        <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-amber-100 text-3xl">🔒</div>
        <h1 class="mb-3 text-xl font-extrabold text-slate-900">التسجيل غير متاح</h1>
        <p class="mb-4 text-base leading-relaxed text-slate-600">{{ $messageAr }}</p>
        <p class="text-sm text-slate-400" dir="ltr">{{ $messageEn }}</p>
    </div>
</body>
</html>
