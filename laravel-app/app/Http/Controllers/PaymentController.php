<?php

namespace App\Http\Controllers;

use App\Helpers\PaymentValidation;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function index(): View
    {
        return view('admin.billing', $this->payments->overview());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->payments->create(PaymentValidation::payment($request));

        return redirect()->route('billing')->with('success', 'Payment record added successfully.');
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $this->payments->updateStatus($id, PaymentValidation::status($request)['status']);

        return redirect()->route('billing')->with('success', 'Payment status updated successfully.');
    }

    public function delete(string $id): RedirectResponse
    {
        $this->payments->delete($id);

        return redirect()->route('billing')->with('success', 'Payment record deleted successfully.');
    }
}
