@foreach (['success' => 'check-circle-fill', 'warning' => 'exclamation-triangle-fill', 'danger' => 'x-octagon-fill'] as $type => $icon)
    @if (session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-{{ $icon }}"></i>
            <div>{{ session($type) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach

{{-- Errors that are not tied to a visible field: a locked day, a wrong system clock or a blocked change. --}}
@foreach (['clock' => 'clock-history', 'business_date' => 'lock-fill', 'record' => 'exclamation-octagon-fill'] as $field => $icon)
    @error($field)
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-{{ $icon }}"></i>
            <div>{{ $message }}</div>
        </div>
    @enderror
@endforeach
