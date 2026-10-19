<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class InventoryValidation
{
    public static function item(Request $request): array
    {
        return $request->validate([
            'item' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:100'],
            'stock' => ['required', 'integer', 'min:0'],
            'reorder' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Healthy,Low,Critical'],
        ]);
    }

    public static function status(Request $request): array
    {
        return $request->validate([
            'status' => ['required', 'in:Healthy,Low,Critical'],
        ]);
    }
}
