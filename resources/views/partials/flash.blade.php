{{--
    Flash messages and page-level errors, shown as floating toasts so they stay
    visible after an in-place (Turbo) update keeps the scroll position.
--}}
@php
    $toasts = [];

    foreach (['success', 'warning', 'danger'] as $type) {
        if (session($type)) {
            $toasts[] = [$type, session($type)];
        }
    }

    // Errors that are not tied to a visible field: a locked day, a wrong system clock, a blocked change
    // or the owed-fuel popup (closed again after the page refreshes).
    foreach (['clock', 'business_date', 'record', 'give_quantity'] as $field) {
        if ($errors->has($field)) {
            $toasts[] = ['danger', $errors->first($field)];
        }
    }

    $icons = ['success' => 'check-circle-fill', 'warning' => 'exclamation-triangle-fill', 'danger' => 'x-octagon-fill'];
@endphp

<div id="toast-stack" class="toast-stack d-print-none" aria-live="polite">
    @foreach ($toasts as [$type, $message])
        <div class="app-toast app-toast-{{ $type }}" data-type="{{ $type }}" role="{{ $type === 'danger' ? 'alert' : 'status' }}">
            <i class="bi bi-{{ $icons[$type] }}"></i>
            <div class="flex-grow-1">{{ $message }}</div>
            <button type="button" class="btn-close btn-close-sm" data-toast-close aria-label="Close"></button>
        </div>
    @endforeach
</div>
