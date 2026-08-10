@props([
    'referenceNumber',
    'fullName' => null,
    'nationalId' => null,
    'employeeNumber' => null,
])

<div class="w-full max-w-sm rounded-2xl border border-teal-200 bg-teal-50/50 p-5 text-center">
    <p class="text-xs font-bold tracking-wider text-teal-700">رقم المرجع</p>
    <p class="mt-2 font-mono text-xl font-extrabold tracking-wide text-navy-900" dir="ltr">{{ $referenceNumber }}</p>

    @if (filled($fullName) || filled($nationalId) || filled($employeeNumber))
        <div class="mt-4 space-y-3 border-t border-teal-200/70 pt-4 text-sm">
            @if (filled($fullName))
                <div>
                    <p class="text-xs text-slate-400">اسم الموظف</p>
                    <p class="mt-0.5 font-extrabold text-navy-900">{{ $fullName }}</p>
                </div>
            @endif
            @if (filled($nationalId))
                <div>
                    <p class="text-xs text-slate-400">الرقم الوطني</p>
                    <p class="mt-0.5 font-bold text-slate-800" dir="ltr">{{ $nationalId }}</p>
                </div>
            @endif
            @if (filled($employeeNumber))
                <div>
                    <p class="text-xs text-slate-400">الرقم الوظيفي</p>
                    <p class="mt-0.5 font-bold text-slate-800" dir="ltr">{{ $employeeNumber }}</p>
                </div>
            @endif
        </div>
    @endif
</div>
