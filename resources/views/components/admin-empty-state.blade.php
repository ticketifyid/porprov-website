@props([
    'icon' => 'ki-information-5',
    'title',
    'description' => null,
    'actionUrl' => null,
    'actionLabel' => null,
])

<div class="d-flex flex-column flex-center text-center py-15">
    <i class="ki-outline {{ $icon }} fs-3x text-muted mb-4"></i>
    <div class="fs-5 fw-bold text-gray-800 mb-2">{{ $title }}</div>
    @if ($description)
        <div class="text-muted mb-4">{{ $description }}</div>
    @endif
    @if ($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="btn btn-light-primary btn-sm">{{ $actionLabel }}</a>
    @endif
</div>
