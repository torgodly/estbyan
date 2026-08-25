@props([
    'titleId' => 'photo-requirements-title',
])

@php
    $uploads = \App\Support\RegistrationDocuments::class;
@endphp

<aside {{ $attributes->class('reg-photo-requirements') }} aria-labelledby="{{ $titleId }}">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 ring-1 ring-teal-100" aria-hidden="true">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z"/>
            </svg>
        </div>
        <div class="min-w-0">
            <h3 id="{{ $titleId }}" class="text-base font-extrabold text-navy-900">{{ $uploads::requirementsTitle() }}</h3>
            <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $uploads::requirementsIntro() }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-3">
        <section class="rounded-2xl bg-white p-4 ring-1 ring-slate-200" aria-labelledby="{{ $titleId }}-look">
            <h4 id="{{ $titleId }}-look" class="mb-3 flex items-center gap-2 text-sm font-extrabold text-navy-900 sm:text-base">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-xs font-extrabold text-white">1</span>
                شكل الصورة
            </h4>
            <ol class="grid gap-3">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[11px] font-extrabold text-teal-800">1</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">الصورة</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">شخصية <strong class="font-extrabold text-navy-900">حديثة وواضحة</strong>، ويفضل ألا يتجاوز تاريخ التقاطها 6 أشهر. الجودة عالية وغير ضبابية.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[11px] font-extrabold text-teal-800">2</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">الخلفية</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600"><strong class="font-extrabold text-navy-900">بيضاء سادة فقط</strong>، خالية من النقوش والظلال.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[11px] font-extrabold text-teal-800">3</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">الإضاءة</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">متوازنة، دون ظلال قوية أو انعكاسات على الوجه.</p>
                    </div>
                </li>
            </ol>
        </section>

        <section class="rounded-2xl bg-white p-4 ring-1 ring-slate-200" aria-labelledby="{{ $titleId }}-face">
            <h4 id="{{ $titleId }}-face" class="mb-3 flex items-center gap-2 text-sm font-extrabold text-navy-900 sm:text-base">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-xs font-extrabold text-white">2</span>
                الوجه والوضعية
            </h4>
            <ol class="grid gap-3">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[11px] font-extrabold text-teal-800">1</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">الاتجاه</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">مواجهة الكاميرا مباشرة، والرأس مستقيم، وتعبير الوجه محايد.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[11px] font-extrabold text-teal-800">2</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">الوضوح</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">يجب أن يكون <strong class="font-extrabold text-navy-900">الوجه بالكامل والعينان واضحتين</strong>، من أعلى الجبهة إلى أسفل الذقن.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[11px] font-extrabold text-teal-800">3</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">حجم الوجه في الصورة</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">يجب أن يشغل الوجه ما بين <strong class="font-extrabold text-navy-900">70% إلى 80%</strong> من المساحة الكلية.</p>
                    </div>
                </li>
            </ol>
        </section>

        <section class="rounded-2xl bg-red-50 p-4 ring-1 ring-red-100" aria-labelledby="{{ $titleId }}-forbidden">
            <h4 id="{{ $titleId }}-forbidden" class="mb-3 flex items-center gap-2 text-sm font-extrabold text-red-800 sm:text-base">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-red-600 text-xs font-extrabold text-white">3</span>
                غير مسموح
            </h4>
            <ol class="grid gap-3">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-white text-[11px] font-extrabold text-red-700 ring-1 ring-red-100">1</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">نوع الصورة</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-700">صور السيلفي، الصور الجماعية، الصور غير الرسمية، أو الصور المأخوذة من مناسبات أو رحلات.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-white text-[11px] font-extrabold text-red-700 ring-1 ring-red-100">2</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">التعديل</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-700"><strong class="font-extrabold text-navy-900">يمنع استخدام الفلاتر أو تعديلات التجميل أو معالجة الصورة بشكل مبالغ فيه.</strong></p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-white text-[11px] font-extrabold text-red-700 ring-1 ring-red-100">3</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">النظارات</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-700">النظارات الشمسية والعدسات الملونة <strong class="font-extrabold text-navy-900">غير مسموح بها</strong>. ويُسمح بالنظارات الطبية بشرط وضوح العينين وعدم وجود انعكاسات.</p>
                    </div>
                </li>
            </ol>
        </section>

        <section class="rounded-2xl bg-white p-4 ring-1 ring-slate-200" aria-labelledby="{{ $titleId }}-children">
            <h4 id="{{ $titleId }}-children" class="mb-3 flex items-center gap-2 text-sm font-extrabold text-navy-900 sm:text-base">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-navy-900 text-xs font-extrabold text-white">4</span>
                {{ $uploads::childrenRequirementsTitle() }}
            </h4>
            <ol class="grid gap-3">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[11px] font-extrabold text-navy-900">1</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">الظهور</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">يجب أن يظهر الطفل بمفرده في الصورة (دون ظهور يدي المُمسك به أو ظهر الكرسي).</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[11px] font-extrabold text-navy-900">2</span>
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-navy-900">الرضع (دون سن العامين)</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-600">تُقبل بعض المرونة البسيطة في إغلاق العينين أو فتح الفم قليلاً، مع الالتزام التام بشرط الخلفية البيضاء ووضوح الوجه.</p>
                    </div>
                </li>
            </ol>
        </section>
    </div>

    <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2.5 text-sm font-medium leading-relaxed text-amber-950 ring-1 ring-amber-100">
        <strong class="font-extrabold">ملاحظة:</strong> {{ $uploads::requirementsNote() }}
    </p>
</aside>
