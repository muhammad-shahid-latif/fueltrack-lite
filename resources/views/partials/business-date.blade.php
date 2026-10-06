{{-- Shown on entry forms: the date comes from the server, not the user's computer. --}}
<div class="business-date" title="Set automatically from the server clock">
    <i class="bi bi-calendar-check"></i>
    <span>{{ $date->format('l, d M Y') }}</span>
    <span class="text-secondary fw-normal small">set by server</span>
</div>
