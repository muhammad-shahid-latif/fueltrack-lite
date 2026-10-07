@props(['employee', 'size' => 'md'])

{{-- Photo when uploaded, otherwise initials. --}}
<span {{ $attributes->class(['staff-avatar', "is-{$size}"]) }}>
    @if ($url = $employee->documentUrl('photo'))
        <img src="{{ $url }}" alt="{{ $employee->name }}" loading="lazy">
    @else
        {{ $employee->initials() }}
    @endif
</span>
