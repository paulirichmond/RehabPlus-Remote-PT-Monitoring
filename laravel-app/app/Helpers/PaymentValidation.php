<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class PaymentValidation
{
    public static function payment(Request $request): array
    {
        return $request->validate([
            'patient' => ['required', 'string', 'max:150'],
            'session' => ['required', 'string', 'max:150'],
            'fee' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:Paid,Pending,Partial'],
            'date' => ['required', 'date'],
        ]);
    }

    public static function status(Request $request): array
    {
        return $request->validate([
            'status' => ['required', 'in:Paid,Pending,Partial'],
        ]);
    }
}
