@if ($isClosed)
    <aside class="reviewer-hours-banner" role="status">
        <p class="reviewer-hours-banner__title">
            {{ $sessionEnded ? $endedTitle : $title }}
        </p>
        <p class="reviewer-hours-banner__body">{{ $body }}</p>
        <p class="reviewer-hours-banner__org">{{ $organization }}</p>
    </aside>
@endif
