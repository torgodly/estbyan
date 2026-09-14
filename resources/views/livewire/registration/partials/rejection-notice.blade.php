<div class="reg-rejection" role="alert">
    <div class="reg-rejection-head">
        <div class="reg-rejection-icon" aria-hidden="true">
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="reg-rejection-title">تم رفض الاستبيان</h2>
                <span class="reg-rejection-badge">يحتاج تصحيحاً</span>
            </div>
            <p class="reg-rejection-lead">إدارة الموارد البشرية راجعت طلبك وطلبت تعديل بعض البيانات قبل قبوله.</p>
        </div>
    </div>

    <div class="reg-rejection-body">
        <div class="reg-rejection-reason">
            <p class="reg-rejection-reason-label">سبب الرفض</p>
            <p class="reg-rejection-reason-text">{{ $rejectionReason }}</p>
        </div>
    </div>
</div>
