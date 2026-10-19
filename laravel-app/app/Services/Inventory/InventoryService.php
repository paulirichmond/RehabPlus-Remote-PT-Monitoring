<?php

namespace App\Services\Inventory;

use App\Services\Clinic\ClinicRecord;
use App\Services\Clinic\ClinicStore;

/** Inventory and supplies (Firestore `inventory`, see ClinicStore). */
class InventoryService
{
    public function __construct(private ClinicStore $store)
    {
    }

    public function overview(?ClinicRecord $editing = null): array
    {
        $items = $this->store->all(ClinicStore::INVENTORY)
            ->sortBy(fn (ClinicRecord $item) => strtolower((string) $item->item))
            ->values();

        return [
            'totalStock' => $items->sum(fn (ClinicRecord $item) => (int) $item->stock),
            'lowStock' => $items->whereIn('status', ['Low', 'Critical'])->count(),
            'restockNeeded' => $items->filter(fn (ClinicRecord $item) => (int) $item->stock <= (int) $item->reorder)->count(),
            'items' => $items,
            'inventoryItem' => $editing,
        ];
    }

    public function find(string $id): ClinicRecord
    {
        return $this->store->findOrFail(ClinicStore::INVENTORY, $id);
    }

    public function create(array $data): string
    {
        return $this->store->create(ClinicStore::INVENTORY, $this->fields($data));
    }

    public function update(ClinicRecord $item, array $data): void
    {
        $this->store->update(ClinicStore::INVENTORY, $item->id, $this->fields($data));
    }

    public function updateStatus(ClinicRecord $item, string $status): void
    {
        $this->store->update(ClinicStore::INVENTORY, $item->id, ['status' => $status]);
    }

    public function delete(ClinicRecord $item): void
    {
        $this->store->delete(ClinicStore::INVENTORY, $item->id);
    }

    private function fields(array $data): array
    {
        return [
            'item' => $data['item'],
            'category' => $data['category'],
            'stock' => (int) $data['stock'],
            'reorder' => isset($data['reorder']) ? (int) $data['reorder'] : null,
            'status' => $data['status'],
        ];
    }
}
