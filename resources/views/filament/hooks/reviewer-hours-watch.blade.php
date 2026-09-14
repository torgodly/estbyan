<div
    hidden
    data-reviewer-session-watch
    data-close-in="{{ $millisecondsUntilClose }}"
    x-data="{ closeIn: {{ (int) $millisecondsUntilClose }} }"
    x-init="setTimeout(() => window.location.reload(), closeIn)"
></div>
