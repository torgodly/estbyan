@props([
    'url' => null,
    'title' => 'معاينة المستند',
    'type' => null,
    'emptyLabel' => 'لا يوجد مستند',
])

@php
    $resolvedType = $type;
    if ($url && $resolvedType === null) {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $resolvedType = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? 'image' : 'pdf';
    }
@endphp

@if ($url)
    <button
        type="button"
        @click="openPreview(@js($url), @js($resolvedType), @js($title))"
        class="group flex w-full items-stretch overflow-hidden rounded-xl border border-gray-200 bg-white text-start transition hover:border-primary-400 hover:shadow-sm dark:border-white/10 dark:bg-white/5 dark:hover:border-primary-400"
    >
        <div class="flex w-28 shrink-0 items-center justify-center bg-gray-50 dark:bg-white/5">
            @if ($resolvedType === 'image')
                <img src="{{ $url }}" alt="" class="h-24 w-full object-cover">
            @else
                <div class="flex flex-col items-center gap-1 px-2 py-4 text-primary-600 dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-8 w-8" />
                    <span class="text-[11px] font-bold">PDF</span>
                </div>
            @endif
        </div>
        <div class="flex flex-1 flex-col justify-center gap-1 px-4 py-3">
            <span class="text-sm font-bold text-gray-950 dark:text-white">{{ $title }}</span>
            <span class="text-xs font-medium text-primary-600 dark:text-primary-400">معاينة داخل الصفحة</span>
        </div>
        <div class="flex items-center px-3 text-gray-400">
            <x-filament::icon icon="heroicon-m-eye" class="h-5 w-5" />
        </div>
    </button>
@else
    <div class="flex w-full items-center gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50/80 px-4 py-5 dark:border-white/15 dark:bg-white/5">
        <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-white text-gray-400 shadow-sm dark:bg-white/10">
            <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5" />
        </div>
        <div>
            <div class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $title }}</div>
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $emptyLabel }}</div>
        </div>
    </div>
@endif
