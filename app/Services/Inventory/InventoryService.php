<?php

namespace App\Services\Inventory;

class InventoryService
{
    public function overview(): array
    {
        $items = session()->get('inventory_items') ?? [];

        return [
            'items' => $items,
            'totalStock' => array_sum(array_map(static fn (array $item): int => (int) ($item['stock'] ?? 0), $items)),
            'lowStock' => count(array_filter($items, static fn (array $item): bool => (int) ($item['stock'] ?? 0) <= (int) ($item['reorder'] ?? 0))),
            'restockNeeded' => 0,
        ];
    }

    public function add(array $input): void
    {
        $stock = (int) $input['stock'];

        $items = session()->get('inventory_items') ?? [];
        $items[] = [
            'item' => $input['item'],
            'category' => $input['category'],
            'stock' => $stock,
            'reorder' => $input['reorder'] !== '' ? (int) $input['reorder'] : 10,
            'status' => $input['status'] !== '' ? $input['status'] : ($stock <= 10 ? 'Low' : 'Healthy'),
        ];
        session()->set('inventory_items', $items);
    }
}
