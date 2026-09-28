@foreach (['success' => 'check-circle', 'warning' => 'exclamation-triangle', 'danger' => 'x-octagon'] as $type => $icon)
    @if (session($type))
        <div class="alert alert-{{ $type }} alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-{{ $icon }}"></i>
            <div>{{ session($type) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
