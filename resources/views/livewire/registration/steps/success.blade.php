<section class="reg-card flex flex-col items-center py-10 text-center lg:py-16">
    <div class="reg-success-icon">
        <svg class="size-14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
        </svg>
    </div>
    <h2 class="text-2xl font-extrabold text-navy-900 sm:text-3xl">تم إرسال التسجيل بنجاح</h2>
    <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-500">شكراً لك. تم استلام طلبك وسيتم مراجعته من قبل فريق الرعاية الذكية. احتفظ برقم المرجع للمتابعة.</p>
    <div class="mt-8 w-full max-w-sm rounded-2xl border border-teal-200 bg-teal-50/50 p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-teal-700">رقم المرجع</p>
        <p class="mt-2 font-mono text-xl font-extrabold tracking-wide text-navy-900" dir="ltr">{{ $referenceNumber }}</p>
    </div>
</section>
