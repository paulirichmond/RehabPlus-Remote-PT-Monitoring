@extends('layouts.admin')

@section('title', 'Inventory and Supplies')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="page-title mb-1">Inventory and Supplies</p>
            <p class="page-subtitle mb-0">Track equipment, supplies, stock levels, and restock needs.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @php($editing = $inventoryItem ?? null)
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header"><h5 class="mb-0">{{ $editing ? 'Edit inventory item' : 'Add inventory item' }}</h5></div>
        <div class="card-body">
            <form method="post" action="{{ $editing ? route('inventory.update', $editing->id) : route('inventory.store') }}">@csrf @if ($editing) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label small fw-semibold">Item name</label><input type="text" name="item" class="form-control" placeholder="Resistance band" value="{{ old('item', $editing?->item) }}" required></div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Category</label><input type="text" name="category" class="form-control" placeholder="Equipment" value="{{ old('category', $editing?->category) }}" required></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Stock</label><input type="number" name="stock" class="form-control" placeholder="20" value="{{ old('stock', $editing?->stock) }}" required></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Reorder point</label><input type="number" name="reorder" class="form-control" placeholder="10" value="{{ old('reorder', $editing?->reorder) }}"></div>
                    <div class="col-md-2"><label class="form-label small fw-semibold">Status</label><select name="status" class="form-select">@foreach (['Healthy', 'Low', 'Critical'] as $status)<option value="{{ $status }}" @selected(old('status', $editing?->status) === $status)>{{ $status }}</option>@endforeach</select></div>
                    <div class="col-12 text-end">@if ($editing)<a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary me-2">Cancel</a><button type="submit" class="btn btn-primary">Save changes</button>@else<button type="submit" class="btn btn-primary">Add item</button>@endif</div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        @php($stats = [
            ['label' => 'Total stock', 'value' => $totalStock, 'icon' => 'bi-box', 'tone' => 'text-primary'],
            ['label' => 'Low stock', 'value' => $lowStock.' items', 'icon' => 'bi-exclamation-triangle', 'tone' => 'text-warning'],
            ['label' => 'Restock needed', 'value' => $restockNeeded.' orders', 'icon' => 'bi-arrow-repeat', 'tone' => 'text-info'],
        ])
        @foreach ($stats as $stat)
            <div class="col-md-6 col-xl-4">
                <div class="card stat-card h-100 border-0"><div class="d-flex justify-content-between align-items-center"><div><div class="stat-label">{{ $stat['label'] }}</div><h4 class="stat-value mb-0">{{ $stat['value'] }}</h4></div><i class="bi {{ $stat['icon'] }} stat-icon {{ $stat['tone'] }}"></i></div></div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header"><h5 class="mb-0">Current inventory</h5></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead class="table-light"><tr><th>Item</th><th>Category</th><th>Stock</th><th>Reorder point</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>@forelse ($items as $item)<tr><td>{{ $item['item'] }}</td><td>{{ $item['category'] }}</td><td>{{ $item['stock'] ?? 0 }}</td><td>{{ $item['reorder'] ?? 0 }}</td><td>@switch($item['status'] ?? '') @case('Low')<span class="badge bg-warning-subtle text-warning">Low</span>@break @case('Critical')<span class="badge bg-danger-subtle text-danger">Critical</span>@break @default<span class="badge bg-success-subtle text-success">Healthy</span>@endswitch</td><td class="text-end"><form method="post" action="{{ route('inventory.status', $item->id) }}" class="d-inline me-1">@csrf @method('PATCH')<select name="status" class="form-select form-select-sm d-inline-block w-auto" title="Change status" data-autosubmit>@foreach (['Healthy', 'Low', 'Critical'] as $status)<option value="{{ $status }}" @selected(($item['status'] ?? 'Healthy') === $status)>{{ $status }}</option>@endforeach</select></form><form method="post" action="{{ route('inventory.destroy', $item->id) }}" class="d-inline" data-confirm="Remove this inventory item?">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No inventory items available.</td></tr>@endforelse</tbody></table></div></div>
    </div>
@endsection
