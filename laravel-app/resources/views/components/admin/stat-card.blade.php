@props(['label', 'value', 'icon', 'tone'])

<div class="col-md-4">
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="text-secondary mb-2">{{ $label }}</div>
                <div class="display-5 fw-bold text-{{ $tone }}">{{ $value }}</div>
            </div>
            <i class="bi {{ $icon }} fs-1 text-{{ $tone }}"></i>
        </div>
    </div>
</div>