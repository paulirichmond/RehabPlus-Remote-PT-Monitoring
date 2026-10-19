<?php

namespace App\Http\Controllers;

use App\Helpers\InventoryValidation;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function index(): View
    {
        return view('admin.inventory', $this->inventory->overview());
    }

    public function create(): View
    {
        return view('admin.inventory', $this->inventory->overview());
    }

    public function show(string $inventory): RedirectResponse
    {
        return redirect()->route('inventory.edit', $inventory);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->inventory->create(InventoryValidation::item($request));

        return redirect()->route('inventory.index')->with('success', 'Inventory item added successfully.');
    }

    public function edit(string $inventory): View
    {
        return view('admin.inventory', $this->inventory->overview($this->inventory->find($inventory)));
    }

    public function update(Request $request, string $inventory): RedirectResponse
    {
        $this->inventory->update($this->inventory->find($inventory), InventoryValidation::item($request));

        return redirect()->route('inventory.index')->with('success', 'Inventory item updated successfully.');
    }

    public function updateStatus(Request $request, string $inventory): RedirectResponse
    {
        $item = $this->inventory->find($inventory);
        $this->inventory->updateStatus($item, InventoryValidation::status($request)['status']);

        return redirect()->route('inventory.index')->with('success', 'Inventory status updated successfully.');
    }

    public function destroy(string $inventory): RedirectResponse
    {
        $this->inventory->delete($this->inventory->find($inventory));

        return redirect()->route('inventory.index')->with('success', 'Inventory item deleted successfully.');
    }
}
